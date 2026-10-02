<?php

use App\Filament\Pages\Respaldos;
use App\Filament\Pages\SaludDelSistema;
use BezhanSalleh\FilamentShield\FilamentShield;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Ningún permiso de página o widget queda huérfano.
 *
 * Causa raíz copiada a varios sistemas: el seeder le daba a `administrator` solo
 * `access_panel_admin`. Shield generaba el permiso de cada página y widget, pero
 * ningún rol que no fuera super_admin lo recibía: el escritorio y el diagnóstico
 * quedaban invisibles para quien administra el sistema día a día, y nada fallaba.
 *
 * El candado: cada permiso de página o widget lo tiene algún rol que NO sea
 * super_admin (que pasa por el Gate::before y no necesita ninguno), o su clase
 * está declarada a propósito en `RolesSeeder::SOLO_SUPER_ADMIN`, con su razón.
 * Un permiso nuevo sin dueño pone este test en rojo.
 *
 * Al paquete `laravel-muni-shared` todavía no llegó `AssertNingunPermisoHuerfano`:
 * cuando llegue, este archivo se reduce a llamarlo.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    Filament\Facades\Filament::setCurrentPanel(Filament\Facades\Filament::getPanel('admin'));

    // Mismo orden que el entrypoint y `env:provision`: permisos de Shield y después roles.
    Artisan::call('shield:generate', [
        '--all' => true,
        '--option' => 'permissions',
        '--panel' => 'admin',
        '--no-interaction' => true,
    ]);
    Artisan::call('db:seed', ['--class' => RolesSeeder::class, '--force' => true]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return array<class-string, list<string>> clase => permisos que Shield exige */
function permisosPorClaseDePaginasYWidgets(): array
{
    $shield = app(FilamentShield::class);

    return collect($shield->getPages())
        ->merge($shield->getWidgets())
        ->mapWithKeys(fn ($entrada, $llave): array => [
            (is_array($entrada) ? ($entrada['class'] ?? $llave) : $llave) => array_keys($entrada['permissions']),
        ])
        ->all();
}

it('cada permiso de página o widget tiene un rol dueño distinto de super_admin, o está declarado solo-super_admin', function () {
    $conDueno = Role::query()
        ->where('name', '!=', 'super_admin')
        ->with('permissions')
        ->get()
        ->flatMap(fn (Role $rol) => $rol->permissions->pluck('name'))
        ->unique()
        ->all();

    $huerfanos = collect(permisosPorClaseDePaginasYWidgets())
        ->reject(fn (array $permisos, string $clase): bool => in_array($clase, RolesSeeder::SOLO_SUPER_ADMIN, true))
        ->flatMap(fn (array $permisos): array => $permisos)
        ->reject(fn (string $permiso): bool => in_array($permiso, $conDueno, true))
        ->values()
        ->all();

    expect($huerfanos)->toBe([]);
});

it('lo declarado solo-super_admin existe y de verdad no lo tiene ningún otro rol', function () {
    $registradas = array_keys(permisosPorClaseDePaginasYWidgets());

    foreach (RolesSeeder::SOLO_SUPER_ADMIN as $clase) {
        expect($registradas)->toContain($clase);

        foreach (permisosPorClaseDePaginasYWidgets()[$clase] as $permiso) {
            expect(Role::where('name', '!=', 'super_admin')->get()->contains(fn (Role $r) => $r->hasPermissionTo($permiso)))
                ->toBeFalse("«{$permiso}» está declarado solo-super_admin pero otro rol lo tiene");
        }
    }
});

it('el administrador ve el diagnóstico y los widgets del escritorio, pero no los respaldos', function () {
    $admin = Role::findByName('administrator', 'web');
    $permisos = permisosPorClaseDePaginasYWidgets();

    foreach ($permisos[SaludDelSistema::class] as $permiso) {
        expect($admin->hasPermissionTo($permiso))->toBeTrue();
    }
    foreach ([Respaldos::class] as $clase) {
        foreach ($permisos[$clase] as $permiso) {
            expect($admin->hasPermissionTo($permiso))->toBeFalse("«{$permiso}» no es para administrator");
        }
    }
    expect(Permission::where('name', 'like', 'widget_%')->count())->toBeGreaterThan(0);
    foreach (Permission::where('name', 'like', 'widget_%')->pluck('name') as $widget) {
        expect($admin->hasPermissionTo($widget))->toBeTrue("administrator no tiene «{$widget}»");
    }
});
