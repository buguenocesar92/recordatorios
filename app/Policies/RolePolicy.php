<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

/**
 * NO borrar este archivo: `bezhansalleh/filament-shield` busca la policy del
 * Resource de Roles en la ruta `app/Policies/RolePolicy.php` hardcodeada. Sin
 * ella el Resource queda sin policy y Filament autoriza TODO.
 *
 * Delega en los permisos que Shield genera para el recurso de roles, sin
 * hardcodear ningún rol.
 */
class RolePolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_role');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('view_role');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_role');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('update_role');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('delete_role');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('delete_any_role');
    }
}
