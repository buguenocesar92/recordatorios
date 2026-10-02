<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/**
 * El muestrario de componentes tiene que EXISTIR donde corre la batería de
 * accesibilidad.
 *
 * Solo se abría en «local». El E2E de CI levanta la aplicación con
 * APP_ENV=testing, así que «/admin/componentes» devolvía un 403 y la prueba
 * de contraste en oscuro medía la página de error de Laravel —que no lleva la
 * clase «dark»— en vez del muestrario. En claro pasaba de casualidad, por la
 * misma razón: el 403 tampoco está en oscuro. El único lugar donde el
 * muestrario se revisaba de verdad era el portátil de quien lo abría a mano.
 */
beforeEach(function () {
    $this->withSession(['auth.two_factor_verified' => true]);
});

it('abre el muestrario en el entorno de pruebas, que es donde corre la batería de accesibilidad', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));

    expect(app()->environment())->toBe('testing');

    $this->actingAs($superAdmin)
        ->get('/admin/componentes')
        ->assertOk()
        ->assertSee('x-muni::alert');
});

it('no existe fuera de desarrollo y pruebas', function () {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole(Role::create(['name' => 'super_admin', 'guard_name' => 'web']));

    // Se cambia el entorno DESPUÉS de arrancar: lo que se prueba es la
    // decisión de la página, no cómo Laravel carga el .env.
    app()->detectEnvironment(fn (): string => 'production');

    $this->actingAs($superAdmin)
        ->get('/admin/componentes')
        ->assertForbidden();
});
