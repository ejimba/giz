<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo('view any roles');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('view roles');
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo('create roles');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('update roles');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('delete roles');
    }

    public function restore(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('restore roles');
    }

    public function forceDelete(User $user, Role $role): bool
    {
        return $user->hasPermissionTo('force delete roles');
    }
}
