<?php

use Kraftdo\Shared\Onboarding\OnboardingProgress;
use Kraftdo\Shared\Onboarding\OnboardingStep;
use Kraftdo\Shared\Onboarding\OnboardingTour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Un modelo sin policy no está «sin restricciones»: está ABIERTO.
 *
 * Filament resuelve la autorización buscando la policy del modelo, y cuando no la
 * encuentra devuelve `true`. Es lo que ya advierte el CLAUDE.md de este repo para los
 * Resources, y vale igual para los RelationManagers: los pasos del tour y el progreso
 * de los usuarios colgaban de `OnboardingTourResource` sin policy propia, así que
 * crear, editar y borrar pasos —y ver el nombre y correo de cada usuario en el
 * progreso— no consultaba ningún permiso de Shield.
 *
 * Los permisos no se inventan nuevos: Shield no genera permisos para un RelationManager,
 * y un paso no es una entidad aparte del tour. Quien puede editar el tour puede editar
 * sus pasos; quien puede verlo, ve el progreso. La policy hace explícita esa relación.
 *
 * Caso aparte, `Activity`: la policy existía en app/Policies pero vive en el namespace
 * de Spatie, así que el descubrimiento automático de Laravel no la encontraba nunca
 * (verificado con Gate::getPolicyFor). Se registra a mano.
 */
uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['view_any_onboarding_tour', 'update_onboarding_tour', 'view_any_activity'] as $permiso) {
        Permission::findOrCreate($permiso, 'web');
    }

    Role::findOrCreate('operador', 'web');

    $this->sinPermisos = User::factory()->create();
    $this->sinPermisos->assignRole('operador');

    $this->editor = User::factory()->create();
    $this->editor->givePermissionTo('view_any_onboarding_tour', 'update_onboarding_tour');

    $this->tour = OnboardingTour::create([
        'name' => 'Tour del panel',
        'system' => 'admin',
        'role' => 'operador',
        'surface' => 'panel',
        'active' => true,
    ]);
});

it('resuelve una policy para cada modelo que el panel expone', function () {
    foreach ([OnboardingStep::class, OnboardingProgress::class, Activity::class] as $modelo) {
        expect(Gate::getPolicyFor($modelo))->not->toBeNull(
            "{$modelo} no tiene policy: Filament autoriza TODO sobre él"
        );
    }
});

it('no deja tocar los pasos del tour a quien no puede editarlo', function () {
    $paso = OnboardingStep::create([
        'tour_id' => $this->tour->id,
        'order' => 1,
        'title' => 'Paso',
        'description' => 'Descripción',
        'position' => 'bottom',
    ]);

    $puerta = Gate::forUser($this->sinPermisos);

    expect($puerta->allows('viewAny', OnboardingStep::class))->toBeFalse();
    expect($puerta->allows('create', OnboardingStep::class))->toBeFalse();
    expect($puerta->allows('update', $paso))->toBeFalse();
    expect($puerta->allows('delete', $paso))->toBeFalse();
});

it('sí deja tocarlos a quien puede editar el tour', function () {
    $paso = OnboardingStep::create([
        'tour_id' => $this->tour->id,
        'order' => 1,
        'title' => 'Paso',
        'description' => 'Descripción',
        'position' => 'bottom',
    ]);

    $puerta = Gate::forUser($this->editor);

    expect($puerta->allows('viewAny', OnboardingStep::class))->toBeTrue();
    expect($puerta->allows('create', OnboardingStep::class))->toBeTrue();
    expect($puerta->allows('update', $paso))->toBeTrue();
    expect($puerta->allows('delete', $paso))->toBeTrue();
});

it('no expone el progreso —nombre y correo de cada usuario— sin permiso', function () {
    $progreso = OnboardingProgress::create([
        'user_id' => $this->sinPermisos->id,
        'tour_id' => $this->tour->id,
        'completed' => true,
        'completed_at' => now(),
    ]);

    expect(Gate::forUser($this->sinPermisos)->allows('viewAny', OnboardingProgress::class))->toBeFalse();
    expect(Gate::forUser($this->sinPermisos)->allows('delete', $progreso))->toBeFalse();
    expect(Gate::forUser($this->editor)->allows('viewAny', OnboardingProgress::class))->toBeTrue();
});

it('la auditoría solo se ve con su permiso', function () {
    expect(Gate::forUser($this->sinPermisos)->allows('viewAny', Activity::class))->toBeFalse();

    $this->sinPermisos->givePermissionTo('view_any_activity');

    expect(Gate::forUser($this->sinPermisos->fresh())->allows('viewAny', Activity::class))->toBeTrue();
});
