<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;
use Muni\Shared\Privacidad\Modelos\Solicitud;

class SolicitudPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_solicitud');
    }

    public function view(AuthUser $authUser, Solicitud $solicitud): bool
    {
        return $authUser->can('view_solicitud');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_solicitud');
    }

    public function update(AuthUser $authUser, Solicitud $solicitud): bool
    {
        return $authUser->can('update_solicitud');
    }

    public function delete(AuthUser $authUser, Solicitud $solicitud): bool
    {
        return $authUser->can('delete_solicitud');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_solicitud');
    }

    public function restore(AuthUser $authUser, Solicitud $solicitud): bool
    {
        return $authUser->can('restore_solicitud');
    }

    public function forceDelete(AuthUser $authUser, Solicitud $solicitud): bool
    {
        return $authUser->can('force_delete_solicitud');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_solicitud');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_solicitud');
    }

    public function replicate(AuthUser $authUser, Solicitud $solicitud): bool
    {
        return $authUser->can('replicate_solicitud');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_solicitud');
    }
}
