<?php

namespace App\Policies;

use App\Models\Form;
use App\Models\User;

/**
 * Forms are site structure rather than someone's own content, so there is no
 * "own records" pass here — the same rule Pages follow.
 */
class FormPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('forms.view');
    }

    public function view(User $user, Form $form): bool
    {
        return $user->hasPermission('forms.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('forms.create');
    }

    public function update(User $user, Form $form): bool
    {
        return $user->hasPermission('forms.update');
    }

    public function delete(User $user, Form $form): bool
    {
        return $user->hasPermission('forms.delete');
    }
}
