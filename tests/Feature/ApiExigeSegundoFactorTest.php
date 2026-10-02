<?php

use Kraftdo\Shared\Onboarding\OnboardingTour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role;

/**
 * Las rutas de `routes/api.php` también tienen que pasar por el segundo factor.
 *
 * Llevaban «auth» a secas: con la contraseña bastaba. Lo verificado en local fue que
 * una sesión abierta por POST /login, que nunca pasó por /verificar-mfa, obtenía 200 en
 * GET /api/onboarding/tour.
 *
 * Hoy lo que se alcanza así es poco (leer tours, marcar progreso). Lo que importa es
 * que este archivo es el molde: cada sistema derivado de este scaffold cuelga sus
 * endpoints acá y copia el grupo tal cual. En licencias eso serían las citas.
 *
 * El segundo test es el candado: comprueba el archivo de rutas entero, no un endpoint,
 * para que una ruta nueva no entre sin el segundo factor.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('mfa.enabled', true);

    Role::findOrCreate('operador', 'web');
    $this->usuario = User::factory()->create();
    $this->usuario->assignRole('operador');

    OnboardingTour::create([
        'name' => 'Tour del panel',
        'system' => 'admin',
        'role' => 'operador',
        'surface' => 'panel',
        'active' => true,
    ]);
});

it('no entrega el tour a una sesión que no pasó el segundo factor', function () {
    $this->actingAs($this->usuario)
        ->get(route('onboarding.tour', ['system' => 'admin', 'surface' => 'panel']))
        ->assertRedirect(route('mfa.verify'));
});

it('sí lo entrega cuando la sesión ya verificó el segundo factor', function () {
    $this->actingAs($this->usuario)
        ->withSession(['auth.two_factor_verified' => true])
        ->getJson(route('onboarding.tour', ['system' => 'admin', 'surface' => 'panel']))
        ->assertOk()
        ->assertJsonPath('tour.system', 'admin');
});

it('ninguna ruta del grupo api queda sin el segundo factor', function () {
    $sinMfa = collect(Route::getRoutes())
        ->filter(fn ($ruta): bool => str_starts_with($ruta->uri(), 'api/'))
        ->reject(fn ($ruta): bool => in_array('mfa', $ruta->gatherMiddleware(), true))
        ->map(fn ($ruta): string => $ruta->uri())
        ->values()
        ->all();

    expect($sinMfa)->toBe([], 'hay rutas de api/ sin el segundo factor: '.implode(', ', $sinMfa));
});
