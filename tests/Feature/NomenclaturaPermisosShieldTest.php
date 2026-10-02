<?php

use BezhanSalleh\FilamentShield\FilamentShield;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Candado de coherencia de permisos del panel bajo Octane.
 *
 * Shield se registra como binding `scoped` y Octane descarta los scoped al terminar
 * cada petición. Este test fuerza la recreación de la instancia (lo que hace Octane)
 * y comprueba que el permiso que exige cada página/widget del panel sigue existiendo
 * en la BD. Si alguien introdujera un builder de nomenclatura mal ubicado (en boot()
 * en vez de register()), CLI y web calcularían permisos distintos y esto lo delataría.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    // El deploy crea los permisos por recurso con shield:generate. Se replica aquí
    // para validar contra el mismo estado que producción.
    Artisan::call('shield:generate', [
        '--all' => true,
        '--option' => 'permissions',
        '--panel' => 'admin',
        '--no-interaction' => true,
    ]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/** @return list<string> */
function clavesDePaginasYWidgets(): array
{
    $shield = app(FilamentShield::class);

    return collect($shield->getPages())
        ->merge($shield->getWidgets())
        ->flatMap(fn (array $e): array => array_keys($e['permissions']))
        ->values()
        ->all();
}

it('las claves de permisos se mantienen al recrearse la instancia (lo que hace Octane)', function () {
    $antes = clavesDePaginasYWidgets();

    app()->forgetScopedInstances();

    expect(clavesDePaginasYWidgets())->toBe($antes);
});

it('cada permiso que exigen páginas y widgets existe en la BD', function () {
    app()->forgetScopedInstances();

    $faltantes = array_values(array_diff(
        clavesDePaginasYWidgets(),
        Permission::pluck('name')->all()
    ));

    expect($faltantes)->toBe([]);
});
