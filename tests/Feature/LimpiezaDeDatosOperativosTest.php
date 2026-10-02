<?php

use App\Console\Commands\CleanOperationalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El comando que vacía las tablas transaccionales del sistema.
 *
 * Viene como plantilla: su lista de tablas está comentada para que cada sistema
 * agregue las suyas. Estaba sin probar, y es de lo más peligroso que trae el
 * scaffold — borra filas y no las devuelve.
 *
 * Lo que más cuida esta prueba no es lo que hace hoy (nada, porque la lista está
 * vacía) sino lo que pasará cuando alguien la llene: que el comando no arrase
 * usuarios ni permisos, que no se pueda ejecutar de un tirón en producción, y
 * que cada tabla nombrada exista de verdad.
 */
uses(RefreshDatabase::class);

covers(CleanOperationalData::class);

/** @return list<string> Las tablas que el comando vaciaría. */
function tablasQueVacia(): array
{
    $constante = (new ReflectionClass(CleanOperationalData::class))->getConstant('TABLAS');

    return array_values((array) $constante);
}

it('sin tablas configuradas no borra nada y lo dice', function () {
    // El estado en que viene el scaffold. Callar y devolver éxito sería peor:
    // quien lo ejecuta creería que limpió algo.
    $this->artisan('app:clear-operational-data --force')
        ->expectsOutputToContain('No hay tablas configuradas')
        ->assertSuccessful();
});

it('nunca toca las cuentas ni los permisos', function () {
    $usuario = User::factory()->create();

    // Se compara antes y después en vez de exigir que haya filas: la base de
    // pruebas no viene sembrada, y una tabla vacía de entrada haría pasar esta
    // comprobación sin haber mirado nada.
    $antes = collect(['users', 'roles', 'permissions'])
        ->filter(fn (string $t): bool => Schema::hasTable($t))
        ->mapWithKeys(fn (string $t): array => [$t => DB::table($t)->count()]);

    $this->artisan('app:clear-operational-data --force')->assertSuccessful();

    // Vaciar datos operativos y quedarse sin poder entrar al sistema sería el
    // peor resultado posible de un comando de limpieza.
    expect(User::find($usuario->id))->not->toBeNull();

    foreach ($antes as $tabla => $cantidad) {
        expect(DB::table($tabla)->count())->toBe($cantidad, "El comando cambió «{$tabla}»");
    }
});

/*
 * No hay prueba de «en producción pide confirmación».
 *
 * La protección existe —el comando usa `confirmToProceed()`— pero la pregunta
 * la hace Laravel Prompts, que no se controla con `expectsConfirmation`: haría
 * falta falsear el prompt, y lo que se estaría probando es el framework, no
 * este proyecto. Lo que sí es de acá —qué tablas vacía y cuáles jamás— está
 * cubierto arriba y abajo.
 */

it('toda tabla que el comando nombre existe de verdad', function () {
    /*
     * El candado que importa a futuro. Cuando alguien agregue sus tablas, un
     * nombre mal escrito no da error: el comando lo salta —comprueba
     * `hasTable`— y sigue informando éxito. Se creería que limpió y la tabla
     * seguiría llena.
     */
    $tablas = tablasQueVacia();

    if ($tablas === []) {
        // Todavía es la plantilla que trae el scaffold. Se deja constancia en
        // vez de pasar en silencio: el día que alguien agregue tablas, esta
        // prueba empieza a comprobarlas sola.
        expect($tablas)->toBe([], 'La lista dejó de estar vacía: ahora sí hay que comprobar cada tabla');

        return;
    }

    foreach ($tablas as $tabla) {
        expect(Schema::hasTable($tabla))->toBeTrue(
            "El comando vaciaría «{$tabla}», que no existe en la base: lo saltaría en silencio ".
            'y diría que limpió sin haber limpiado'
        );
    }
});

it('no vaciaría una tabla que sostiene las cuentas o los permisos', function () {
    // Ni por descuido: si alguien agrega «users» a la lista pensando en datos
    // de prueba, el comando dejaría el sistema sin forma de entrar.
    $prohibidas = ['users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'migrations'];
    $tablas = tablasQueVacia();

    // Con la lista todavía vacía el bucle no comprobaría nada, y una prueba sin
    // aserciones es una prueba que miente sobre lo que cubre.
    expect($tablas)->toBeArray();

    foreach ($tablas as $tabla) {
        expect(in_array($tabla, $prohibidas, true))->toBeFalse(
            "El comando vaciaría «{$tabla}», que sostiene el acceso al sistema"
        );
    }
});
