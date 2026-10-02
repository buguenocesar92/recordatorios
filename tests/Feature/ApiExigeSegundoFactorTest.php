<?php

use App\Models\User;
use App\Support\Rutas\MiddlewareDeApi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/**
 * Las rutas de `routes/api.php` también tienen que pasar por el segundo factor.
 *
 * Con «auth» a secas bastaba la contraseña: una sesión abierta que nunca pasó por
 * /verificar-mfa obtenía 200. El MVP hoy no expone API, así que estas pruebas
 * cuelgan una ruta sonda con la MISMA pila (`MiddlewareDeApi::AUTENTICADA`) que
 * usa el grupo de `routes/api.php`: lo que se prueba es la pila con la que nacerá
 * la primera ruta real, no un endpoint en particular.
 *
 * Los dos últimos casos son los candados: revisan el archivo de rutas entero para
 * que una ruta nueva no entre sin el segundo factor.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mfa.enabled', true);

    Route::middleware(MiddlewareDeApi::AUTENTICADA)
        ->get('api/sonda-mfa', fn () => response()->json(['ok' => true]))
        ->name('sonda.api');
    Route::getRoutes()->refreshNameLookups();

    Role::findOrCreate('operador', 'web');
    $this->usuario = User::factory()->create();
    $this->usuario->assignRole('operador');
});

it('no responde a una sesión que no pasó el segundo factor', function () {
    $this->actingAs($this->usuario)
        ->get(route('sonda.api'))
        ->assertRedirect(route('mfa.verify'));
});

it('sí responde cuando la sesión ya verificó el segundo factor', function () {
    $this->actingAs($this->usuario)
        ->withSession(['auth.two_factor_verified' => true])
        ->getJson(route('sonda.api'))
        ->assertOk()
        ->assertJsonPath('ok', true);
});

it('sin sesión no responde', function () {
    $this->getJson(route('sonda.api'))->assertUnauthorized();
});

it('la pila de la api incluye contraseña, segundo factor y sin caché', function () {
    expect(MiddlewareDeApi::AUTENTICADA)->toContain('web', 'auth', 'mfa', 'sin-cache');
});

it('ninguna ruta del grupo api queda sin el segundo factor', function () {
    $sinMfa = collect(Route::getRoutes())
        ->filter(fn ($ruta): bool => str_starts_with($ruta->uri(), 'api/'))
        ->reject(fn ($ruta): bool => in_array('mfa', $ruta->gatherMiddleware(), true)
            && in_array('sin-cache', $ruta->gatherMiddleware(), true))
        ->map(fn ($ruta): string => $ruta->uri())
        ->values()
        ->all();

    expect($sinMfa)->toBe([], 'hay rutas de api/ sin el segundo factor: '.implode(', ', $sinMfa));
});

it('ninguna ruta autenticada fuera del panel queda sin el segundo factor', function () {
    // El panel de Filament aplica el segundo factor desde su propia pila; la
    // pantalla de verificación y la salida son las únicas exentas, o no habría
    // forma de verificar ni de irse.
    $exentas = ['verificar-mfa'];

    $sinMfa = collect(Route::getRoutes())
        ->filter(fn ($ruta): bool => in_array('auth', $ruta->gatherMiddleware(), true))
        ->reject(fn ($ruta): bool => str_starts_with((string) $ruta->getName(), 'filament.'))
        ->reject(fn ($ruta): bool => in_array($ruta->uri(), $exentas, true))
        ->reject(fn ($ruta): bool => in_array('mfa', $ruta->gatherMiddleware(), true))
        ->map(fn ($ruta): string => $ruta->uri())
        ->values()
        ->all();

    expect($sinMfa)->toBe([], 'rutas autenticadas sin segundo factor: '.implode(', ', $sinMfa));
});
