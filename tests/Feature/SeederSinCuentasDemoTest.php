<?php

use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Las cuentas de demostración son una puerta trasera si sobreviven al despliegue.
 *
 * `admin@example.com` con la contraseña «password» y el rol super_admin es la primera
 * combinación que prueba cualquiera que encuentre el panel. Este scaffold se clona a
 * cada sistema municipal, así que la puerta no se abre una vez: se abre tantas veces
 * como instalaciones haya.
 *
 * Y hay un segundo filo, menos evidente: sembrar con updateOrCreate REESCRIBE la
 * contraseña de una cuenta que ya existe. Un `env:provision` de mantenimiento —que es
 * un comando pensado para correrse en el servidor— devolvía la contraseña del super
 * administrador real a «password» sin que nadie lo notara.
 *
 * Lo que se fija acá: en producción no se crean cuentas de demostración, y sembrar
 * nunca pisa la credencial de una cuenta existente.
 */
uses(RefreshDatabase::class);

it('no crea cuentas de demostración cuando el entorno es de producción', function () {
    app()->detectEnvironment(fn (): string => 'production');

    (new RolesSeeder)->run();

    expect(User::where('email', 'admin@example.com')->exists())->toBeFalse(
        'el seeder creó un super administrador de demostración en producción'
    );
    expect(User::where('email', 'user@example.com')->exists())->toBeFalse(
        'el seeder creó un usuario de demostración en producción'
    );
});

it('sí siembra roles y permisos en producción: eso no es dato de demostración', function () {
    app()->detectEnvironment(fn (): string => 'production');

    (new RolesSeeder)->run();

    expect(Role::where('name', 'super_admin')->exists())->toBeTrue();
    expect(Role::where('name', 'administrator')->exists())->toBeTrue();
    expect(Permission::where('name', 'access_panel_admin')->exists())->toBeTrue();
});

it('no reescribe la contraseña de una cuenta que ya existe', function () {
    $existente = User::create([
        'name' => 'Administrador del municipio',
        'email' => 'admin@example.com',
        'password' => Hash::make('una-clave-que-el-municipio-eligio'),
    ]);

    (new RolesSeeder)->run();

    expect(Hash::check('una-clave-que-el-municipio-eligio', $existente->fresh()->password))
        ->toBeTrue('la siembra revirtió la contraseña de una cuenta existente');
    expect(Hash::check('password', $existente->fresh()->password))
        ->toBeFalse('la siembra dejó la cuenta con la contraseña de demostración');
});

/**
 * `syncRoles()` de Spatie hace `detach()` de TODOS los roles y luego los vuelve a
 * asignar. En la primera siembra no hay filas que quitar y pasa; en la segunda
 * intenta quitarle `super_admin` a la única cuenta que lo tiene, que es
 * exactamente lo que «GuardaDeRolAdministrador» prohíbe. El entrypoint de
 * desarrollo siembra en cada `make up`, así que el contenedor quedaba en bucle de
 * reinicio desde el segundo arranque.
 */
it('se puede sembrar dos veces seguidas sin chocar con el candado del rol', function () {
    (new RolesSeeder)->run();
    (new RolesSeeder)->run();

    $admin = User::where('email', 'admin@example.com')->first();

    expect($admin)->not->toBeNull()
        ->and($admin->hasRole('super_admin'))->toBeTrue(
            'la segunda siembra dejó al administrador sin el rol'
        );
});

it('env:provision no revierte la contraseña del super administrador', function () {
    Role::findOrCreate('super_admin', 'web');

    $admin = User::create([
        'name' => 'Administrador del municipio',
        'email' => 'jefatura@municipio.cl',
        'password' => Hash::make('la-clave-real-de-jefatura'),
    ]);
    $admin->syncRoles(['super_admin']);

    $this->artisan('env:provision', [
        '--skip-shield' => true,
        '--admin-email' => 'jefatura@municipio.cl',
        '--admin-password' => 'la-que-pasa-el-script-de-despliegue',
    ])->assertSuccessful();

    expect(Hash::check('la-clave-real-de-jefatura', $admin->fresh()->password))
        ->toBeTrue('el aprovisionamiento pisó la contraseña del super administrador existente');
});

it('env:provision sí fija la contraseña cuando se pide explícitamente', function () {
    Role::findOrCreate('super_admin', 'web');

    $admin = User::create([
        'name' => 'Administrador del municipio',
        'email' => 'jefatura@municipio.cl',
        'password' => Hash::make('la-clave-vieja'),
    ]);
    $admin->syncRoles(['super_admin']);

    $this->artisan('env:provision', [
        '--skip-shield' => true,
        '--admin-email' => 'jefatura@municipio.cl',
        '--admin-password' => 'la-clave-nueva',
        '--reset-password' => true,
    ])->assertSuccessful();

    expect(Hash::check('la-clave-nueva', $admin->fresh()->password))->toBeTrue();
});
