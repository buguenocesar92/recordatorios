<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * UserPolicy::update()/delete() no comprobaban NADA sobre el usuario objetivo:
 * un rol delegado con `update_user` podía editarse a sí mismo (repartirse los
 * roles que quisiera) o tocar la cuenta de un super_admin.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->superAdmin = Role::findOrCreate('super_admin');

    $gestor = Role::findOrCreate('gestor_cuentas');
    $gestor->givePermissionTo(
        Permission::findOrCreate('view_any_user'),
        Permission::findOrCreate('view_user'),
        Permission::findOrCreate('create_user'),
        Permission::findOrCreate('update_user'),
        Permission::findOrCreate('delete_user'),
    );

    $this->gestor = User::factory()->create();
    $this->gestor->assignRole($gestor);
});

it('no deja que quien administra cuentas se edite a sí mismo', function () {
    expect(Gate::forUser($this->gestor)->allows('update', $this->gestor))->toBeFalse();
});

it('no deja tocar la cuenta de un super_admin desde un rol delegado', function () {
    $jefe = User::factory()->create();
    $jefe->assignRole($this->superAdmin);

    expect(Gate::forUser($this->gestor)->allows('update', $jefe))->toBeFalse()
        ->and(Gate::forUser($this->gestor)->allows('delete', $jefe))->toBeFalse();
});

it('un gestor no puede borrarse a sí mismo', function () {
    expect(Gate::forUser($this->gestor)->allows('delete', $this->gestor))->toBeFalse();
});

it('un gestor sí puede editar y borrar una cuenta normal', function () {
    $victima = User::factory()->create();

    expect(Gate::forUser($this->gestor)->allows('update', $victima))->toBeTrue()
        ->and(Gate::forUser($this->gestor)->allows('delete', $victima))->toBeTrue();
});

it('un super_admin sí puede tocar cualquier cuenta', function () {
    $jefe = User::factory()->create();
    $jefe->assignRole($this->superAdmin);
    $victima = User::factory()->create();

    expect(Gate::forUser($jefe)->allows('update', $victima))->toBeTrue();
});
