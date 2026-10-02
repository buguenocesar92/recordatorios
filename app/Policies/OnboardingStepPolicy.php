<?php

declare(strict_types=1);

namespace App\Policies;

use Kraftdo\Shared\Onboarding\OnboardingStep;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * Un paso no es una entidad aparte del tour: es su contenido.
 *
 * Shield no genera permisos para un RelationManager, y sin policy Filament autoriza
 * TODO —crear, editar y borrar pasos sin consultar nada—. En vez de inventar permisos
 * nuevos que nadie tildaría en la pantalla de Roles, se apoya en los del tour padre:
 * quien puede verlo, ve sus pasos; quien puede editarlo, los edita.
 */
class OnboardingStepPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_onboarding_tour');
    }

    public function view(AuthUser $authUser, OnboardingStep $step): bool
    {
        return $authUser->can('view_any_onboarding_tour');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('update_onboarding_tour');
    }

    public function update(AuthUser $authUser, OnboardingStep $step): bool
    {
        return $authUser->can('update_onboarding_tour');
    }

    public function delete(AuthUser $authUser, OnboardingStep $step): bool
    {
        return $authUser->can('update_onboarding_tour');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('update_onboarding_tour');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('update_onboarding_tour');
    }
}
