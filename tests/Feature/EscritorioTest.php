<?php

use App\Filament\Pages\Escritorio;
use App\Filament\Widgets\RequiereTuAtencion;
use App\Models\User;
use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * El escritorio del panel.
 *
 * Era la pantalla de fábrica, vacía: quien entraba al sistema veía un título y
 * nada más, y tenía que adivinar por dónde empezar.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mfa.enabled', false);

    Role::findOrCreate('super_admin', 'web');
    $this->jefe = User::factory()->create();
    $this->jefe->assignRole('super_admin');
    $this->actingAs($this->jefe);
});

it('reparte en seis columnas, no en dos', function () {
    // Con dos columnas fijas, toda tarjeta que valga la pena ocupa el ancho
    // completo y el escritorio se vuelve una pila de cinco pantallas.
    expect((new Escritorio)->getColumns())->toBe(['default' => 1, 'md' => 2, 'xl' => 6]);
});

it('el escritorio es lo primero que se ve, y trae contenido', function () {
    $this->get(Escritorio::getUrl())
        ->assertOk()
        ->assertSeeLivewire(RequiereTuAtencion::class);
});

it('no exige un permiso propio para entrar', function () {
    // Es la pantalla a la que llega todo el mundo al identificarse: con un
    // permiso de por medio, quien no lo tenga se queda sin ninguna.
    expect(array_keys(FilamentShield::getPages()))->not->toContain(Escritorio::class);
});

it('quien solo administra contenido también entra', function () {
    $editor = User::factory()->create();
    $rol = Role::findOrCreate('editor', 'web');
    $rol->givePermissionTo(Permission::findOrCreate('access_panel_admin', 'web'));
    $editor->assignRole($rol);

    $this->actingAs($editor)->get(Escritorio::getUrl())->assertOk();
});
