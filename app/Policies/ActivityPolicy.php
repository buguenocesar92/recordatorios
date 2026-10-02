<?php

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * El historial de auditoría es de solo lectura: se puede mirar con el permiso
 * que Shield genera para el recurso, pero nadie lo crea, edita ni borra desde
 * el panel. Un registro de auditoría que se puede editar no prueba nada.
 */
class ActivityPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_activity');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('view_activity');
    }

    public function create(AuthUser $authUser): bool
    {
        return false;
    }

    public function update(AuthUser $authUser): bool
    {
        return false;
    }

    public function delete(AuthUser $authUser): bool
    {
        return false;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return false;
    }
}
