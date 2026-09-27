<?php

namespace App\Policies;

use App\Models\MenuItem;
use App\Models\User;

/**
 * The menu is site structure, not someone's own content, so there is no
 * "own records" pass here — the same rule Pages and Forms follow.
 */
class MenuItemPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('menu.view');
    }

    public function view(User $user, MenuItem $item): bool
    {
        return $user->hasPermission('menu.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('menu.create');
    }

    public function update(User $user, MenuItem $item): bool
    {
        return $user->hasPermission('menu.update');
    }

    public function delete(User $user, MenuItem $item): bool
    {
        return $user->hasPermission('menu.delete');
    }
}
