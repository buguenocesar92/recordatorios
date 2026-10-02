<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class UserPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_user');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('view_user');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_user');
    }

    /**
     * Además del permiso, dos candados contra la escalada de privilegios: un rol
     * delegado de gestión de cuentas no toca la cuenta de un super_admin, ni la
     * suya propia -si pudiera editarse a sí mismo se repartiría los roles que
     * quisiera-. Para un super_admin no hay escalada posible: ya lo tiene todo.
     */
    public function update(AuthUser $authUser, ?User $user = null): bool
    {
        if (! $authUser->can('update_user')) {
            return false;
        }

        if ($authUser instanceof User && $authUser->hasRole('super_admin')) {
            return true;
        }

        if ($user === null) {
            return true;
        }

        return ! $user->hasRole('super_admin') && $user->getKey() !== $authUser->getKey();
    }

    /** Nadie se borra a sí mismo, y un rol delegado tampoco borra a un super_admin. */
    public function delete(AuthUser $authUser, ?User $user = null): bool
    {
        if (! $authUser->can('delete_user')) {
            return false;
        }

        if ($user === null) {
            return true;
        }

        if ($user->getKey() === $authUser->getKey()) {
            return false;
        }

        if ($user->hasRole('super_admin')) {
            return $authUser instanceof User && $authUser->hasRole('super_admin');
        }

        return true;
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_user');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('restore_user');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_user');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_user');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_user');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('replicate_user');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_user');
    }
}
