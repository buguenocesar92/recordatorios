<?php

use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Toda policy del proyecto niega sin permiso y concede con él.
 *
 * La medición de cobertura las encontró casi sin probar: `SolicitudPolicy` —la
 * que decide quién ve las solicitudes de datos personales de la Ley 21.719—
 * estaba en 0 %, y otras tres en 8 %. Existían porque una regla de arquitectura
 * las exige, pero nadie comprobaba que hicieran lo correcto.
 *
 * No se comprueba método por método a mano, porque esa lista se desactualiza en
 * cuanto alguien agrega uno: se recorren TODOS los métodos públicos de cada
 * policy por reflexión. Una policy nueva, o un método nuevo en una existente,
 * entra solo en esta prueba.
 *
 * Los nombres de permiso se sacan del propio código de la policy en vez de
 * adivinarlos por convención: si alguien renombra un permiso, la prueba sigue
 * comprobando el de verdad y no una suposición que ya no existe.
 */
uses(RefreshDatabase::class);

/** @return list<string> Los permisos que menciona el código de la policy. */
function permisosDeclaradosEn(string $clase): array
{
    $ruta = (new ReflectionClass($clase))->getFileName();

    preg_match_all("/can\(\s*'([a-z_0-9]+)'/", (string) file_get_contents((string) $ruta), $coincidencias);

    return array_values(array_unique($coincidencias[1]));
}

/**
 * @return list<ReflectionMethod> Los métodos de autorización de la policy.
 *
 * Se reconocen por recibir al usuario como primer parámetro. Filtrar por
 * `$m->class` no basta: los métodos que aporta el trait HandlesAuthorization
 * —deny(), allow(), denyWithStatus()— se declaran como propios de la clase que
 * lo usa, y colarlos daba un fallo confuso sobre un método que no autoriza nada.
 */
function metodosDeAutorizacion(string $clase): array
{
    return array_values(array_filter(
        (new ReflectionClass($clase))->getMethods(ReflectionMethod::IS_PUBLIC),
        function (ReflectionMethod $m) use ($clase): bool {
            if ($m->class !== $clase || $m->isStatic() || $m->isConstructor()) {
                return false;
            }

            $primero = $m->getParameters()[0] ?? null;
            $tipo = $primero?->getType();

            if (! $tipo instanceof ReflectionNamedType || $tipo->isBuiltin()) {
                return false;
            }

            return is_a($tipo->getName(), Authenticatable::class, true);
        },
    ));
}

/**
 * Invoca un método de policy resolviendo sus argumentos: el usuario y, si lo
 * pide, una instancia vacía del modelo (las policies deciden por permiso, no
 * por el contenido del registro).
 */
function invocarPolicy(ReflectionMethod $metodo, object $policy, User $usuario): mixed
{
    $argumentos = [$usuario];

    foreach (array_slice($metodo->getParameters(), 1) as $parametro) {
        $tipo = $parametro->getType();

        if (! $tipo instanceof ReflectionNamedType || $tipo->isBuiltin()) {
            return null; // Forma inesperada: se informa fuera, no se inventa un valor.
        }

        $clase = $tipo->getName();
        $argumentos[] = new $clase;
    }

    return $metodo->invokeArgs($policy, $argumentos);
}

it('toda policy niega sin permiso y concede con él', function () {
    $policies = collect(glob(app_path('Policies/*.php')))
        ->map(fn (string $ruta): string => 'App\\Policies\\'.basename($ruta, '.php'))
        ->filter(fn (string $clase): bool => class_exists($clase))
        // RolePolicy es una fachada vacía que EXTIENDE Muni\Shared\Auditoria\RolePolicy
        // (bezhansalleh/filament-shield la busca por la ruta app/Policies/RolePolicy.php
        // hardcodeada y no se puede borrar): no declara ningún método ni `can()` propio
        // —viven en el archivo del paquete—, así que la reflexión de acá, que lee el
        // ARCHIVO de la clase, no encuentra nada que revisar. Su comportamiento real ya
        // lo prueba la suite de laravel-muni-shared.
        ->reject(fn (string $clase): bool => $clase === RolePolicy::class)
        ->values();

    // Si el glob no encuentra nada, esta prueba pasaría sin comprobar una sola
    // policy. Se exige el número que hay hoy para que no enmudezca en silencio.
    expect($policies)->toHaveCount(4, 'Cambió la cantidad de policies: revisar esta prueba');

    Role::findOrCreate('super_admin', 'web');

    $revisadas = 0;

    foreach ($policies as $clase) {
        $permisos = permisosDeclaradosEn($clase);

        expect($permisos)->not->toBeEmpty("{$clase} no consulta ningún permiso: no está autorizando nada");

        foreach ($permisos as $permiso) {
            Permission::findOrCreate($permiso, 'web');
        }

        $policy = new $clase;
        $metodos = metodosDeAutorizacion($clase);

        expect($metodos)->not->toBeEmpty("{$clase} no expone ningún método de autorización");

        // 1) Sin ningún permiso: todo tiene que ser «no».
        $sinPermiso = User::factory()->create();

        foreach ($metodos as $metodo) {
            $resultado = invocarPolicy($metodo, $policy, $sinPermiso);

            expect($resultado)->toBeFalse(
                "{$clase}::{$metodo->getName()}() autorizó a un usuario SIN permisos"
            );
            $revisadas++;
        }

        // 2) Con todos los permisos que la propia policy nombra: todo «sí».
        $conPermiso = User::factory()->create();
        $conPermiso->givePermissionTo($permisos);
        $conPermiso->refresh();

        foreach ($metodos as $metodo) {
            $resultado = invocarPolicy($metodo, $policy, $conPermiso);

            expect($resultado)->toBeTrue(
                "{$clase}::{$metodo->getName()}() negó a un usuario CON el permiso que ella misma exige"
            );
            $revisadas++;
        }
    }

    // Una comprobación que no comprobó nada es peor que ninguna. El umbral bajó de
    // 80 a 60 al excluir RolePolicy (sus doce métodos aportaban 24 revisiones que
    // ya no pasan por acá, ver el reject() de arriba); hoy quedan 74 con las cuatro
    // policies locales que siguen.
    expect($revisadas)->toBeGreaterThan(60, 'Se revisaron muy pocos métodos: la reflexión no encontró lo que debía');
});
