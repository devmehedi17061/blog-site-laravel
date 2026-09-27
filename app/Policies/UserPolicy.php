<?php

namespace App\Policies;

use App\Models\User;

/**
 * Accounts. Holding users.update is not enough on its own: nobody may edit
 * or delete an account with more access than theirs, and nobody may delete
 * the account they are signed in with.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('users.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->hasPermission('users.update') && $user->canManage($model);
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermission('users.delete')
            && ! $user->is($model)
            && $user->canManage($model);
    }
}
