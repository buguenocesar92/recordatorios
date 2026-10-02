<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;

/**
 * `AppServiceProvider::boot()` registraba su propio `Gate::before` para dejar
 * pasar a `super_admin`, ADEMÁS del que declara `config/filament-shield.php`
 * (`super_admin.intercept_gate = 'before'`). Ese Gate::before de Shield solo
 * se registra cuando `super_admin.define_via_gate` es `true`
 * (`FilamentShieldServiceProvider::boot()`): con la bandera en `false` -como
 * estaba- Shield NO interceptaba nada, y el único camino que en verdad daba
 * paso a super_admin era el manual. La configuración prometía «before» con
 * `intercept_gate` y no lo cumplía.
 *
 * Dos `Gate::before` para el mismo rol se pisan uno al otro (Laravel evalúa
 * todos los `before` registrados hasta que uno responda algo distinto de
 * null) y hacen difícil razonar cuál decide de verdad: una policy futura
 * podría saltarse por cualquiera de los dos sin que quede claro cuál lo hizo.
 * Se deja UNO solo -el de Shield, con `define_via_gate: true`- y se borra el
 * manual.
 *
 * Este test cubre que, con SOLO el `before` de Shield, `super_admin` sigue
 * pasando cualquier permiso y una policy denegatoria SÍ se aplica a un rol
 * normal.
 */
uses(RefreshDatabase::class);

it('un super_admin sigue pasando cualquier permiso solo con el Gate::before de Shield', function () {
    $jefe = User::factory()->create();
    $jefe->assignRole(Role::findOrCreate('super_admin', 'web'));

    expect(Gate::forUser($jefe)->allows('viewAny', User::class))->toBeTrue()
        ->and(Gate::forUser($jefe)->allows('un_permiso_que_no_existe'))->toBeTrue();
});

it('una policy denegatoria sí se aplica a un rol normal, sin el Gate::before manual', function () {
    // Sin el rol super_admin y sin el permiso «view_any_user», UserPolicy debe
    // negar el acceso. Si algún Gate::before manual volviera a colarse y
    // dejara pasar a todo el mundo, este test lo detecta.
    $funcionario = User::factory()->create();

    expect(Gate::forUser($funcionario)->allows('viewAny', User::class))->toBeFalse();
});

it('el Gate::before manual ya no vive en AppServiceProvider', function () {
    $provider = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));

    expect($provider)->not->toContain('Gate::before');
});

it('config/filament-shield.php define el super_admin vía Gate, para que Shield sea el único before', function () {
    $config = (string) file_get_contents(config_path('filament-shield.php'));

    expect($config)->toContain("'define_via_gate' => true");
});
