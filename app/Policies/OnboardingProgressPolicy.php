<?php

declare(strict_types=1);

namespace App\Policies;

use Kraftdo\Shared\Onboarding\OnboardingProgress;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * El progreso lista NOMBRE y CORREO de cada usuario que abrió el tour.
 *
 * Sin policy, Filament dejaba ver y borrar esa tabla a cualquiera que llegara a la
 * página de edición del tour. Es dato personal de funcionarios, así que se gobierna
 * con los mismos permisos que el tour al que pertenece.
 */
class OnboardingProgressPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_onboarding_tour');
    }

    public function view(AuthUser $authUser, OnboardingProgress $progress): bool
    {
        return $authUser->can('view_any_onboarding_tour');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('update_onboarding_tour');
    }

    public function update(AuthUser $authUser, OnboardingProgress $progress): bool
    {
        return $authUser->can('update_onboarding_tour');
    }

    public function delete(AuthUser $authUser, OnboardingProgress $progress): bool
    {
        return $authUser->can('update_onboarding_tour');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('update_onboarding_tour');
    }
}
