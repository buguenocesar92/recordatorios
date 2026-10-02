<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * El candado contra la escalada a super_admin, fuera del formulario.
 *
 * «UserResource» ya impide que un delegado reparta o quite el rol que lo
 * puede todo, pero esa barrera vive en «disableOptionWhen» de UN <select>:
 * protege ese formulario y ningún otro camino de escritura. Una acción
 * masiva, un importador, otro Resource, o simplemente
 * «$cuenta->roles()->sync([...])» a mano —el mismo método que usa Filament
 * por debajo— no pasan por ahí.
 *
 * Estas pruebas atacan el modelo directamente, sin tocar ningún formulario:
 * es la prueba de que el candado vive en una capa que ningún camino nuevo
 * puede saltarse por accidente.
 */
uses(RefreshDatabase::class);

function rolAdministrador(): string
{
    return (string) config('filament-shield.super_admin.name', 'super_admin');
}

beforeEach(function () {
    Role::findOrCreate(rolAdministrador(), 'web');
    Role::findOrCreate('inspector', 'web');

    $permisos = ['view_any_user', 'view_user', 'create_user', 'update_user'];
    foreach ($permisos as $permiso) {
        Permission::findOrCreate($permiso, 'web');
    }

    $encargado = Role::findOrCreate('encargado_de_personal', 'web');
    $encargado->givePermissionTo($permisos);

    $this->delegado = User::factory()->create();
    $this->delegado->assignRole($encargado);

    $this->jefatura = User::factory()->create();
    $this->jefatura->assignRole(rolAdministrador());

    $this->cuenta = User::factory()->create();
    $this->cuenta->assignRole($encargado);
});

it('el candado de escalada también corta fuera del formulario de usuarios', function () {
    // Ni «assignRole()» ni «sync()» directo sobre la relación —el mismo
    // camino que usa Filament por debajo del <select>— pueden colar el rol
    // que lo puede todo si quien actúa no lo tiene ya.
    $this->actingAs($this->delegado);

    $rolId = Role::findByName(rolAdministrador(), 'web')->getKey();

    expect(fn () => $this->cuenta->roles()->sync([$rolId]))
        ->toThrow(ValidationException::class);

    expect($this->cuenta->fresh()?->hasRole(rolAdministrador()))
        ->toBeFalse('el candado no cortó fuera del formulario de usuarios');
});

it('el candado también corta assignRole() llamado a mano', function () {
    $this->actingAs($this->delegado);

    expect(fn () => $this->cuenta->assignRole(rolAdministrador()))
        ->toThrow(ValidationException::class);

    expect($this->cuenta->fresh()?->hasRole(rolAdministrador()))->toBeFalse();
});

it('quien ya tiene el rol lo puede repartir fuera del formulario', function () {
    // Si el candado fuera indiscriminado, nombrar a un segundo administrador
    // por cualquier camino que no sea ESE formulario dejaría de funcionar.
    $this->actingAs($this->jefatura);

    $this->cuenta->assignRole(rolAdministrador());

    expect($this->cuenta->fresh()?->hasRole(rolAdministrador()))->toBeTrue();
});

it('un comando de consola puede asignar el primer super_admin al desplegar', function () {
    // Sin sesión —un seeder, un comando artisan— es justo el camino con el
    // que un proyecto nuevo nace con su primera cuenta de administración.
    // Bloquearlo ahí impediría desplegar cualquier sistema nacido de este
    // scaffold.
    $this->cuenta->assignRole(rolAdministrador());

    expect($this->cuenta->fresh()?->hasRole(rolAdministrador()))->toBeTrue();
});

it('no se puede quitar el rol a la única cuenta que lo tiene', function () {
    // Un delegado con «update_user» abre la cuenta del único super_admin,
    // cambia sus roles a otra cosa y guarda: el panel se queda sin nadie que
    // pueda administrarlo todo, y sin nadie ya con el rol para devolvérselo.
    expect(fn () => $this->jefatura->syncRoles(['inspector']))
        ->toThrow(ValidationException::class);

    expect($this->jefatura->fresh()?->hasRole(rolAdministrador()))
        ->toBeTrue('se pudo quitar el rol a la única cuenta que lo tenía');
});

it('sí se puede quitar el rol si queda otra cuenta que lo tiene', function () {
    $segunda = User::factory()->create();
    $segunda->assignRole(rolAdministrador());

    $this->jefatura->syncRoles(['inspector']);

    expect($this->jefatura->fresh()?->hasRole(rolAdministrador()))->toBeFalse();
    expect($segunda->fresh()?->hasRole(rolAdministrador()))->toBeTrue();
});
