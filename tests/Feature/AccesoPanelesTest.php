<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Configurar roles y permisos básicos
    $this->superAdminRole = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->adminRole = Role::create(['name' => 'administrator', 'guard_name' => 'web']);
    $this->userRole = Role::create(['name' => 'user', 'guard_name' => 'web']);

    $this->accessPermission = Permission::create(['name' => 'access_panel_admin', 'guard_name' => 'web']);
    $this->adminRole->givePermissionTo($this->accessPermission);

    // Evitar requerir MFA en este test
    $this->withSession(['auth.two_factor_verified' => true]);
});

it('redirecciona a los invitados al login', function () {
    $response = $this->get('/admin');
    $response->assertStatus(302);
    $response->assertRedirect('/admin/login');
});

it('deniega el acceso al panel admin a usuarios comunes', function () {
    $user = User::factory()->create();
    $user->assignRole($this->userRole);

    $response = $this->actingAs($user)->get('/admin');
    expect($response->status())->toBeIn([403, 302]);
});

it('permite el acceso al panel admin a administradores con permiso', function () {
    $user = User::factory()->create();
    $user->assignRole($this->adminRole);

    $response = $this->actingAs($user)->get('/admin');
    $response->assertStatus(200);
});

it('permite el acceso total al panel admin a super administradores', function () {
    $user = User::factory()->create();
    $user->assignRole($this->superAdminRole);

    $response = $this->actingAs($user)->get('/admin');
    $response->assertStatus(200);
});
