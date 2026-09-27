<?php

namespace App\Policies;

use App\Models\Category;
use App\Models\User;

/**
 * Categories are site taxonomy rather than someone's own content, so there
 * is no "own records" pass here. Authors can read them — they have to, to
 * file a post — but not change them.
 */
class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('categories.view');
    }

    public function view(User $user, Category $category): bool
    {
        return $user->hasPermission('categories.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('categories.create');
    }

    public function update(User $user, Category $category): bool
    {
        return $user->hasPermission('categories.update');
    }

    public function delete(User $user, Category $category): bool
    {
        return $user->hasPermission('categories.delete');
    }
}
