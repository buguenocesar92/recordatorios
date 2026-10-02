<?php

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Crear el rol super_admin para que los comandos de provisión lo asignen
    Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
});

it('provisiona el ambiente y crea o promueve al super administrador', function () {
    $this->artisan('env:provision --skip-shield --admin-email=test-admin@example.com --admin-password=secretpass')
        ->assertSuccessful();

    $admin = User::where('email', 'test-admin@example.com')->first();
    expect($admin)->not->toBeNull()
        ->and($admin->hasRole('super_admin'))->toBeTrue();
});

it('falla si la clave del super admin de la provisión está vacía', function () {
    $this->artisan('env:provision --skip-shield --admin-email=no-pass@example.com')
        ->expectsQuestion('Contraseña del super admin', '')
        ->assertExitCode(Command::FAILURE);
});

it('ejecuta la limpieza de datos operativos conservando usuarios', function () {
    $userCountBefore = User::count();

    // Crear un usuario común para verificar que no se elimine
    User::factory()->create();

    $this->artisan('env:clean-data --force')
        ->assertSuccessful();

    // El número de usuarios debe mantenerse intacto (no se borran cuentas ni roles)
    expect(User::count())->toBeGreaterThan($userCountBefore);
});

it('geocodifica direcciones usando Nominatim y OpenStreetMap', function () {
    // El Geocoder no consulta sin un contacto declarado: mandar direcciones a un
    // tercero exige saber quién responde por ello (Ley 21.719, ver el docblock de
    // App\Support\Geocoder). Acá se prueba el comando, no esa regla —tiene sus
    // propios casos en GeocoderTest—, así que se declara para poder llegar a él.
    config()->set('sitio.geocodificacion.contacto', 'informatica@ejemplo.cl');

    // Crear usuarios con dirección para geocodificar
    $user = User::factory()->create([
        'direccion' => 'Av. Providencia 1234',
        'latitud' => null,
        'longitud' => null,
    ]);

    Http::fake([
        'nominatim.openstreetmap.org/*' => Http::response([
            [
                'lat' => '-33.4262',
                'lon' => '-70.6124',
            ],
        ], 200),
    ]);

    $this->artisan('geo:geocode App\\\\Models\\\\User --limit=10')
        ->assertSuccessful();

    $user->refresh();
    expect($user->latitud)->toBe('-33.4262')
        ->and($user->longitud)->toBe('-70.6124');
});

it('es idempotente: reprovisionar al único super admin no toca sus roles ni choca con el candado', function () {
    // En producción el seeder no crea cuentas de demostración: la cuenta provisionada
    // es la ÚNICA con super_admin, que es donde el candado del último admin muerde.
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('env:provision --skip-shield --admin-email=unico@example.com --admin-password=primera-clave')
        ->assertSuccessful();

    $this->artisan('env:provision --skip-shield --admin-email=unico@example.com --admin-password=otra-clave')
        ->assertSuccessful();

    $admin = User::where('email', 'unico@example.com')->first();
    expect($admin->hasRole('super_admin'))->toBeTrue()
        ->and(Hash::check('primera-clave', $admin->password))->toBeTrue();
});

it('con --reset-password cambia la contraseña del único super admin y conserva el rol', function () {
    // En producción el seeder no crea cuentas de demostración: la cuenta provisionada
    // es la ÚNICA con super_admin, que es donde el candado del último admin muerde.
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('env:provision --skip-shield --admin-email=unico@example.com --admin-password=primera-clave')
        ->assertSuccessful();

    $this->artisan('env:provision --skip-shield --admin-email=unico@example.com --admin-password=nueva-clave --reset-password')
        ->assertSuccessful();

    $admin = User::where('email', 'unico@example.com')->first();
    expect($admin->hasRole('super_admin'))->toBeTrue()
        ->and(Hash::check('nueva-clave', $admin->password))->toBeTrue();
});
