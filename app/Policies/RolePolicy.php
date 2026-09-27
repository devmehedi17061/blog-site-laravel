<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Roles. A locked role (Super Admin) is never edited or deleted. Nobody
 * edits the role they are in — that is how someone would grant themselves
 * more, or strip the permission they need to undo the mistake — and nobody
 * touches a role with more access than their own.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.update') && $this->mayChange($user, $role);
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.delete') && $this->mayChange($user, $role);
    }

    private function mayChange(User $user, Role $role): bool
    {
        return ! $role->is_locked
            && $role->id !== $user->role_id
            && $user->canGrant($role);
    }
}
