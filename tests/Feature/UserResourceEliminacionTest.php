<?php

use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use function Pest\Livewire\livewire;

/**
 * El candado contra la escalada de super_admin (ver
 * «GuardaDeRolAdministrador» y «CandadoDeRolAdministradorTest») vive en el
 * borrado de una FILA del pivote «model_has_roles» —«$usuario->roles()»,
 * «sync()», «assignRole()»—. Eliminar el USUARIO por completo no pasa por
 * ahí: no hay clave foránea de «model_has_roles.model_id» hacia «users.id»
 * —es una columna morph, no se puede— y nada desataba los roles antes de
 * borrar la cuenta. «DeleteBulkAction» de fábrica borra el registro sin
 * tocar el pivote: el candado nunca se entera, y la única cuenta con el rol
 * que lo puede todo se podía borrar igual, dejando además la fila del
 * pivote huérfana.
 */
uses(RefreshDatabase::class);

function rolAdministrador2(): string
{
    return (string) config('filament-shield.super_admin.name', 'super_admin');
}

beforeEach(function () {
    Role::findOrCreate(rolAdministrador2(), 'web');

    foreach (['view_any_user', 'delete_user', 'delete_any_user'] as $permiso) {
        Permission::findOrCreate($permiso, 'web');
    }

    $delegado = Role::findOrCreate('encargado_de_personal', 'web');
    $delegado->givePermissionTo(['view_any_user', 'delete_user', 'delete_any_user']);

    $this->delegado = User::factory()->create();
    $this->delegado->assignRole($delegado);
});

it('no se puede eliminar a la única cuenta con el rol que lo puede todo', function () {
    // Asignado ANTES de «actingAs»: el propio candado de asignación
    // (CandadoDeRolAdministradorTest) exige que quien reparte el rol ya lo
    // tenga, y el delegado de esta prueba justamente no lo tiene.
    $unico = User::factory()->create();
    $unico->assignRole(rolAdministrador2());

    $this->actingAs($this->delegado);

    livewire(ListUsers::class)
        ->callTableBulkAction('delete', [$unico])
        ->assertNotified('No se pudo eliminar');

    expect(User::query()->whereKey($unico->getKey())->exists())->toBeTrue(
        'se eliminó a la única cuenta con el rol que lo puede todo',
    );
});

it('sí se puede eliminar una cuenta con el rol si queda otra que lo tiene', function () {
    $primero = User::factory()->create();
    $primero->assignRole(rolAdministrador2());

    $segundo = User::factory()->create();
    $segundo->assignRole(rolAdministrador2());

    $this->actingAs($this->delegado);

    livewire(ListUsers::class)
        ->callTableBulkAction('delete', [$segundo]);

    expect(User::query()->whereKey($segundo->getKey())->exists())->toBeFalse();
    expect(User::query()->whereKey($primero->getKey())->exists())->toBeTrue();
});

it('una cuenta sin el rol que lo puede todo se elimina normalmente', function () {
    $cualquiera = User::factory()->create();

    $this->actingAs($this->delegado);

    livewire(ListUsers::class)
        ->callTableBulkAction('delete', [$cualquiera]);

    expect(User::query()->whereKey($cualquiera->getKey())->exists())->toBeFalse();
});
