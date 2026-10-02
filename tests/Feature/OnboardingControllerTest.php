<?php

use Kraftdo\Shared\Onboarding\OnboardingProgress;
use Kraftdo\Shared\Onboarding\OnboardingStep;
use Kraftdo\Shared\Onboarding\OnboardingTour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Estas pruebas ejercitan el controlador, no el segundo factor: se les da la sesión
    // ya verificada para que el middleware `mfa` —que ahora cubre routes/api.php— no se
    // interponga. Que el segundo factor se exija de verdad lo cubre
    // ApiExigeSegundoFactorTest.
    $this->withSession(['auth.two_factor_verified' => true]);

    // Configurar roles de prueba
    $this->superAdminRole = Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    $this->testRole = Role::create(['name' => 'test_role', 'guard_name' => 'web']);

    $this->admin = User::factory()->create();
    $this->admin->assignRole($this->superAdminRole);

    $this->user = User::factory()->create();
    $this->user->assignRole($this->testRole);

    // Crear un tour de prueba
    $this->tour = OnboardingTour::create([
        'name' => 'Tour de Prueba',
        'system' => 'test-system',
        'role' => 'test_role',
        'surface' => 'app',
        'active' => true,
    ]);

    $this->step = OnboardingStep::create([
        'tour_id' => $this->tour->id,
        'order' => 1,
        'title' => 'Paso 1',
        'description' => 'Descripcion del paso 1',
        'element_selector' => '#test-btn',
        'position' => 'bottom',
    ]);
});

it('obtiene el tour aplicable para el usuario', function () {
    $this->actingAs($this->user)
        ->getJson(route('onboarding.tour', [
            'system' => 'test-system',
            'surface' => 'app',
        ]))
        ->assertOk()
        ->assertJsonPath('tour.id', $this->tour->id)
        ->assertJsonPath('completed', false)
        ->assertJsonCount(1, 'steps');
});

it('permite al admin previsualizar un tour arbitrario por tour_id', function () {
    $this->actingAs($this->admin)
        ->getJson(route('onboarding.tour', [
            'tour_id' => $this->tour->id,
        ]))
        ->assertOk()
        ->assertJsonPath('tour.id', $this->tour->id);
});

it('no permite a un usuario normal previsualizar un tour por tour_id', function () {
    // Si no es admin, ignorará el tour_id y resolverá según sus propios roles.
    // Como el $this->admin tiene el rol super_admin y no test_role, no resolvería este tour para sí mismo.
    $otroUser = User::factory()->create(); // no tiene test_role ni admin
    $this->actingAs($otroUser)
        ->getJson(route('onboarding.tour', [
            'tour_id' => $this->tour->id,
        ]))
        ->assertOk()
        ->assertJsonPath('tour', null);
});

it('retorna null si no hay tour activo coincidente', function () {
    $this->actingAs($this->user)
        ->getJson(route('onboarding.tour', [
            'system' => 'test-non-existent',
            'surface' => 'app',
        ]))
        ->assertOk()
        ->assertJsonPath('tour', null);
});

it('marca un tour como completado', function () {
    $this->actingAs($this->user)
        ->postJson(route('onboarding.complete'), [
            'tour_id' => $this->tour->id,
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(OnboardingProgress::where('user_id', $this->user->id)
        ->where('tour_id', $this->tour->id)
        ->where('completed', true)
        ->exists())->toBeTrue();
});

it('resetea el progreso del tour para el usuario autenticado', function () {
    OnboardingProgress::create([
        'user_id' => $this->user->id,
        'tour_id' => $this->tour->id,
        'completed' => true,
        'completed_at' => now(),
    ]);

    $this->actingAs($this->user)
        ->postJson(route('onboarding.reset'), [
            'tour_id' => $this->tour->id,
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(OnboardingProgress::where('user_id', $this->user->id)
        ->where('tour_id', $this->tour->id)
        ->exists())->toBeFalse();
});

it('permite al admin resetear el progreso del tour de otro usuario', function () {
    OnboardingProgress::create([
        'user_id' => $this->user->id,
        'tour_id' => $this->tour->id,
        'completed' => true,
        'completed_at' => now(),
    ]);

    $this->actingAs($this->admin)
        ->postJson(route('onboarding.reset'), [
            'tour_id' => $this->tour->id,
            'user_id' => $this->user->id,
        ])
        ->assertOk()
        ->assertJson(['ok' => true]);

    expect(OnboardingProgress::where('user_id', $this->user->id)
        ->where('tour_id', $this->tour->id)
        ->exists())->toBeFalse();
});

it('no permite a un usuario normal resetear el progreso de otro usuario', function () {
    $otroUser = User::factory()->create();

    OnboardingProgress::create([
        'user_id' => $otroUser->id,
        'tour_id' => $this->tour->id,
        'completed' => true,
        'completed_at' => now(),
    ]);

    // Intentamos resetear como $this->user (no admin). Debe ignorar el user_id recibido y resetear el propio.
    $this->actingAs($this->user)
        ->postJson(route('onboarding.reset'), [
            'tour_id' => $this->tour->id,
            'user_id' => $otroUser->id,
        ])
        ->assertOk();

    // El progreso de $otroUser debe seguir existiendo intacto
    expect(OnboardingProgress::where('user_id', $otroUser->id)
        ->where('tour_id', $this->tour->id)
        ->exists())->toBeTrue();
});
