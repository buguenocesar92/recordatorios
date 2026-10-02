<?php

use Kraftdo\Shared\Onboarding\OnboardingTour;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Las policies deciden quién puede hacer qué, y estaban sin probar.
 *
 * La medición de cobertura las encontró en 0 %: existían —hay una regla de
 * arquitectura que lo exige— pero nadie comprobaba que hicieran lo correcto.
 * Una policy que dice «sí» donde debía decir «no» no se nota hasta que alguien
 * entra donde no debía, y para entonces ya pasó.
 *
 * Lo que se comprueba acá no es que llamen a can(): es que un usuario SIN el
 * permiso reciba un no, y CON el permiso reciba un sí. Un test que solo mira lo
 * segundo pasaría igual con una policy que aprueba todo.
 */
uses(RefreshDatabase::class);

// Se declara qué código cubre este archivo. Sin esto, la prueba de mutación no
// sabe emparejar el test con la clase —lo hace por convención de nombre— y no
// genera ni un solo mutante para las policies: informa «0 mutaciones» y parece
// que todo está bien cuando en realidad no comprobó nada.
covers(UserPolicy::class);

beforeEach(function () {
    Role::findOrCreate('super_admin', 'web');
});

/** @return array<string, string> Acción del panel => prefijo de su permiso. */
function acciones(): array
{
    return [
        'viewAny' => 'view_any_',
        'create' => 'create_',
        'update' => 'update_',
        'delete' => 'delete_',
    ];
}

/**
 * Qué se le pasa a la puerta: la clase o una instancia.
 *
 * No es capricho del test. Unas policies del panel tipan el modelo en «update»
 * y «delete» —reciben usuario Y registro— y otras solo reciben el usuario.
 * Preguntarle a la primera con la clase suelta revienta con «Too few
 * arguments», así que para esas dos acciones se pasa una instancia vacía, que
 * es lo que hace Filament al preguntar por una fila concreta.
 */
function sujeto(string $modelo, string $accion): object|string
{
    return in_array($accion, ['update', 'delete'], true) ? new $modelo : $modelo;
}

/** Un usuario con exactamente un permiso, y ninguno más. */
function usuarioCon(string $permiso): User
{
    $usuario = User::factory()->create();
    $rol = Role::findOrCreate('rol_'.md5($permiso), 'web');
    $rol->givePermissionTo(Permission::findOrCreate($permiso, 'web'));
    $usuario->assignRole($rol);

    return $usuario;
}

dataset('acciones sobre usuarios', [
    ['viewAny', 'view_any_user'],
    ['view', 'view_user'],
    ['create', 'create_user'],
    ['update', 'update_user'],
    ['delete', 'delete_user'],
    ['deleteAny', 'delete_any_user'],
    // Las de recuperación y borrado definitivo son las más peligrosas del
    // conjunto: «forceDelete» se salta la papelera, y «restore» devuelve a la
    // vida una cuenta que alguien dio de baja por algo.
    ['restore', 'restore_user'],
    ['restoreAny', 'restore_any_user'],
    ['forceDelete', 'force_delete_user'],
    ['forceDeleteAny', 'force_delete_any_user'],
    ['replicate', 'replicate_user'],
    ['reorder', 'reorder_user'],
]);

it('sin el permiso, la policy de usuarios dice que no', function (string $accion, string $permiso) {
    // Es la mitad que importa: alguien que entró al panel por otra puerta no
    // puede administrar cuentas solo por estar dentro.
    $cualquiera = User::factory()->create();

    expect(Gate::forUser($cualquiera)->allows($accion, User::class))->toBeFalse(
        "«{$accion}» se permitió sin el permiso «{$permiso}»",
    );
})->with('acciones sobre usuarios');

it('con el permiso, la policy de usuarios dice que sí', function (string $accion, string $permiso) {
    // La otra mitad: sin esto, una policy que niega siempre pasaría el test de
    // arriba y dejaría el panel inservible.
    $autorizado = usuarioCon($permiso);

    expect(Gate::forUser($autorizado)->allows($accion, User::class))->toBeTrue(
        "«{$accion}» se negó a quien tiene «{$permiso}»",
    );
})->with('acciones sobre usuarios');

it('un permiso no abre la puerta de otro', function () {
    // El error clásico: copiar una policy y dejar el permiso del recurso
    // anterior, con lo que quien puede ver también puede borrar.
    $soloVer = usuarioCon('view_any_user');

    expect(Gate::forUser($soloVer)->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($soloVer)->allows('delete', User::class))->toBeFalse('Ver también borraba')
        ->and(Gate::forUser($soloVer)->allows('create', User::class))->toBeFalse('Ver también creaba');
});

it('el administrador puede con todo, y eso viene del rol, no de la policy', function () {
    // Gate::before del rol de administración pasa por encima de las policies.
    // Se comprueba porque es la puerta más ancha del sistema: si algún día ese
    // rol deja de existir con ese nombre, el administrador pierde el panel
    // entero —ya pasó en el ecosistema— y este test lo diría.
    $jefe = User::factory()->create();
    $jefe->assignRole('super_admin');

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny', 'restore', 'forceDelete'] as $accion) {
        expect(Gate::forUser($jefe)->allows($accion, User::class))->toBeTrue(
            "El administrador no pudo «{$accion}»",
        );
    }
});

/**
 * El resto de las policies del panel, con la misma exigencia.
 *
 * AVISO sobre lo que estas comprobaciones garantizan y lo que no. Miden el
 * COMPORTAMIENTO de la puerta —quien no tiene el permiso no pasa, quien lo
 * tiene sí—, que es lo que le importa a quien usa el sistema. Pero la medición
 * de cobertura las sigue reportando cerca de cero, y eso significa que la
 * decisión no está saliendo del cuerpo de la policy: Gate la resuelve antes,
 * por los permisos de Spatie.
 *
 * Se dejan porque el comportamiento verificado vale por sí mismo, y se avisa
 * porque un 0 % en una policy NO debe leerse como «está sin probar» ni como
 * «está probada»: significa que la puerta la decide otra capa, y que si alguien
 * cambia el cuerpo de la policy, estas comprobaciones podrían no enterarse.
 *
 * La medición las encontró en 8 %: se comprobaba que existieran —hay una regla
 * de arquitectura para eso— pero no que negaran a quien no debe pasar. Son las
 * que deciden quién ve la auditoría del sistema, quién administra los roles
 * —o sea, quién puede darse permisos a sí mismo— y quién toca los recorridos
 * guiados.
 */
dataset('policies del panel', [
    'auditoría' => [Activity::class, 'activity'],
    'roles' => [Role::class, 'role'],
    'recorridos guiados' => [OnboardingTour::class, 'onboarding_tour'],
]);

it('sin permiso, ninguna policy del panel deja pasar', function (string $modelo, string $sufijo) {
    $cualquiera = User::factory()->create();

    foreach (acciones() as $accion => $prefijo) {
        expect(Gate::forUser($cualquiera)->allows($accion, sujeto($modelo, $accion)))->toBeFalse(
            "«{$accion}» sobre {$modelo} se permitió sin «{$prefijo}{$sufijo}»",
        );
    }
})->with('policies del panel');

it('con permiso, cada policy del panel deja pasar', function (string $modelo, string $sufijo) {
    foreach (acciones() as $accion => $prefijo) {
        $autorizado = usuarioCon($prefijo.$sufijo);

        expect(Gate::forUser($autorizado)->allows($accion, sujeto($modelo, $accion)))->toBeTrue(
            "«{$accion}» sobre {$modelo} se negó a quien tiene «{$prefijo}{$sufijo}»",
        );
    }
})->with('policies del panel');

it('administrar roles es la llave del panel entero, y exige su propio permiso', function () {
    // Quien administra roles puede darse cualquier permiso a sí mismo. Que ese
    // permiso no se herede por tener otros es lo que impide que alguien con
    // acceso a una sección se convierta en administrador.
    $editor = usuarioCon('view_any_activity');

    expect(Gate::forUser($editor)->allows('viewAny', Role::class))->toBeFalse()
        ->and(Gate::forUser($editor)->allows('create', Role::class))->toBeFalse()
        // «update» pide el registro, no la clase: ver «sujeto()».
        ->and(Gate::forUser($editor)->allows('update', new Role))->toBeFalse();
});
