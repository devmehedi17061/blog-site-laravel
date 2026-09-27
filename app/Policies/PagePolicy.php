<?php

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pages.view');
    }

    public function view(User $user, Page $page): bool
    {
        return $user->hasPermission('pages.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('pages.create');
    }

    /** Pages are site structure, so "own content" roles do not get a pass here. */
    public function update(User $user, Page $page): bool
    {
        return $user->hasPermission('pages.update');
    }

    public function delete(User $user, Page $page): bool
    {
        return $user->hasPermission('pages.delete');
    }

    /** Whoever may send it to the Trash may bring it back out. */
    public function restore(User $user, Page $page): bool
    {
        return $this->delete($user, $page);
    }

    /** …and delete it for good from there. */
    public function forceDelete(User $user, Page $page): bool
    {
        return $this->delete($user, $page);
    }
}
