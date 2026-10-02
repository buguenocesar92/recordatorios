<?php

use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

/**
 * Quién puede repartir el rol que lo puede todo.
 *
 * La pantalla de usuarios ofrecía la lista completa de roles a cualquiera con
 * permiso para editar cuentas: un encargado al que se le delegó el alta de
 * personal se marcaba «super_admin» a sí mismo y pasaba a ver —y a borrar— todo
 * el sistema. No quedaba rastro de ello en ninguna parte, porque el registro de
 * actividad solo anotaba los ingresos.
 *
 * Lo mismo con la contraseña: el formulario aceptaba «1» aunque la política del
 * sistema exija doce caracteres con letras y números. La política regía en el
 * restablecimiento por correo y no acá, que es justo por donde se crean todas
 * las cuentas del sistema.
 */
uses(RefreshDatabase::class);

/** El nombre del rol que lo puede todo cambia entre sistemas del ecosistema. */
function rolQuePuedeTodo(): string
{
    return (string) config('filament-shield.super_admin.name', 'super_admin');
}

beforeEach(function () {
    // El segundo factor no es lo que se prueba acá y su middleware no interviene
    // en las pruebas de componente Livewire.
    config()->set('mfa.enabled', false);

    Role::findOrCreate(rolQuePuedeTodo(), 'web');

    $permisos = ['view_any_user', 'view_user', 'create_user', 'update_user'];

    foreach ($permisos as $permiso) {
        Permission::findOrCreate($permiso, 'web');
    }

    // Un encargado de personal: puede dar de alta y editar cuentas, y nada más.
    // Es exactamente el perfil al que se delega esto en una municipalidad.
    $encargado = Role::findOrCreate('encargado_de_personal', 'web');
    $encargado->givePermissionTo($permisos);

    $this->delegado = User::factory()->create();
    $this->delegado->assignRole($encargado);

    $this->jefatura = User::factory()->create();
    $this->jefatura->assignRole(rolQuePuedeTodo());

    $this->cuenta = User::factory()->create();
    $this->cuenta->assignRole($encargado);
});

it('un administrador delegado no puede darle super_admin a otra cuenta', function () {
    // «roles.0» y no «roles»: Filament valida los desplegables de selección
    // múltiple elemento por elemento, así que el error cuelga de cada valor
    // enviado. Con la clave equivocada el test falla aunque el candado funcione.
    $this->actingAs($this->delegado);

    livewire(EditUser::class, ['record' => $this->cuenta->getKey()])
        ->fillForm(['roles' => [Role::findByName(rolQuePuedeTodo(), 'web')->getKey()]])
        ->call('save')
        ->assertHasFormErrors(['roles.0']);

    expect($this->cuenta->fresh()?->hasRole(rolQuePuedeTodo()))
        ->toBeFalse('el delegado repartió el rol que lo puede todo');
});

it('el rechazo se explica en vez de decir que el campo no es válido', function () {
    // El mensaje de fábrica —«el campo roles seleccionado no es válido»— hace
    // pensar que el formulario está roto. Aparece también al editar una cuenta
    // que YA tiene el rol, y ahí es donde más falta hace la explicación.
    $this->actingAs($this->delegado);

    $componente = livewire(EditUser::class, ['record' => $this->cuenta->getKey()])
        ->fillForm(['roles' => [Role::findByName(rolQuePuedeTodo(), 'web')->getKey()]])
        ->call('save');

    expect($componente->errors()->first('data.roles.0'))
        ->toContain('solo lo puede asignar quien ya lo tiene');
});

it('un administrador delegado ni siquiera puede abrir su propia cuenta', function () {
    // El camino corto de la escalada era editarse a sí mismo y marcar la casilla.
    // Desde que «UserPolicy» mira al usuario objetivo, el formulario ya no llega
    // a abrirse: el delegado no pasa la autorización sobre su propia ficha. Sus
    // datos personales los cambia por la página de perfil del panel
    // («->profile()» en AdminPanelProvider), que no pasa por esta policy.
    $this->actingAs($this->delegado);

    expect(Gate::forUser($this->delegado)->allows('update', $this->delegado))
        ->toBeFalse('el delegado puede editar su propia cuenta desde la pantalla de usuarios');

    livewire(EditUser::class, ['record' => $this->delegado->getKey()])
        ->assertForbidden();

    expect($this->delegado->fresh()?->hasRole(rolQuePuedeTodo()))
        ->toBeFalse('el delegado se ascendió a sí mismo');
});

it('un administrador delegado tampoco lo cuela al crear una cuenta nueva', function () {
    // Si solo se tapa la edición, queda abierto crear una cuenta con el rol y
    // entrar con ella.
    $this->actingAs($this->delegado);

    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Cuenta de prueba',
            'email' => 'cuenta-nueva@ejemplo.test',
            'password' => 'una-contrasena-larga-42',
            'roles' => [Role::findByName(rolQuePuedeTodo(), 'web')->getKey()],
        ])
        ->call('create')
        ->assertHasFormErrors(['roles.0']);

    expect(User::query()->where('email', 'cuenta-nueva@ejemplo.test')->exists())
        ->toBeFalse('se creó la cuenta con el rol que lo puede todo');
});

it('quien ya tiene el rol sí lo puede repartir', function () {
    // Si el candado fuera indiscriminado, nadie podría nombrar a un segundo
    // administrador y el arreglo sería peor que el problema.
    $this->actingAs($this->jefatura);

    livewire(EditUser::class, ['record' => $this->cuenta->getKey()])
        ->fillForm(['roles' => [Role::findByName(rolQuePuedeTodo(), 'web')->getKey()]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->cuenta->fresh()?->hasRole(rolQuePuedeTodo()))->toBeTrue();
});

it('un administrador delegado sigue pudiendo repartir los demás roles', function () {
    $otro = Role::findOrCreate('inspector', 'web');

    $this->actingAs($this->delegado);

    livewire(EditUser::class, ['record' => $this->cuenta->getKey()])
        ->fillForm(['roles' => [$otro->getKey()]])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->cuenta->fresh()?->hasRole('inspector'))->toBeTrue();
});

it('la contraseña que fija un administrador cumple la política del sistema', function () {
    // «Corta12345» son diez caracteres con letras y números: pasa el mínimo de
    // fábrica de Laravel —ocho— y la política del sistema —doce— la rechaza. Si
    // este test se escribiera con una contraseña de tres letras, pasaría con
    // cualquier regla y no comprobaría nada.
    $this->actingAs($this->jefatura);

    livewire(EditUser::class, ['record' => $this->cuenta->getKey()])
        ->fillForm(['password' => 'Corta12345'])
        ->call('save')
        ->assertHasFormErrors(['password']);
});

it('la contraseña larga y con números sí se acepta', function () {
    $this->actingAs($this->jefatura);

    livewire(EditUser::class, ['record' => $this->cuenta->getKey()])
        ->fillForm(['password' => 'caballo-correcto-42'])
        ->call('save')
        ->assertHasNoFormErrors();
});

it('queda constancia de quién cambió los roles de quién', function () {
    // Sin esto, tras un incidente no se puede saber quién repartió qué: el
    // registro de actividad solo anotaba los ingresos.
    $this->actingAs($this->jefatura);

    livewire(EditUser::class, ['record' => $this->cuenta->getKey()])
        ->fillForm(['roles' => [Role::findByName(rolQuePuedeTodo(), 'web')->getKey()]])
        ->call('save')
        ->assertHasNoFormErrors();

    $registro = Activity::query()->where('description', 'cambio de roles')->latest('id')->first();

    expect($registro)->not->toBeNull('el cambio de roles no dejó rastro');
    expect($registro?->causer_id)->toBe($this->jefatura->getKey());
    expect($registro?->subject_id)->toBe($this->cuenta->getKey());
    expect($registro?->properties->get('despues'))->toContain(rolQuePuedeTodo());
    expect($registro?->properties->get('antes'))->toContain('encargado_de_personal');
});

it('el historial guarda el cambio de nombre y de correo, nunca la contraseña', function () {
    // La contraseña se guarda cifrada, pero su resumen tampoco tiene por qué
    // quedar copiado en una tabla que lee más gente que la de usuarios.
    $this->cuenta->update([
        'name' => 'Nombre corregido',
        'password' => 'otra-contrasena-larga-42',
    ]);

    $registro = Activity::query()
        ->where('subject_type', User::class)
        ->where('description', '!=', 'cambio de roles')
        ->latest('id')
        ->first();

    expect($registro)->not->toBeNull('el cambio de datos de la cuenta no dejó rastro');
    expect(json_encode($registro?->properties))->not->toContain('password');
});
