<?php

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('posts.view');
    }

    public function view(User $user, Post $post): bool
    {
        return $user->hasPermission('posts.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('posts.create');
    }

    /**
     * The Author role is scoped to "Own content", so it may only touch posts
     * it wrote. Roles that also hold posts.delete are trusted site-wide.
     */
    public function update(User $user, Post $post): bool
    {
        if (! $user->hasPermission('posts.update')) {
            return false;
        }

        return $this->ownsOrIsTrusted($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        if (! $user->hasPermission('posts.delete')) {
            return false;
        }

        return $this->ownsOrIsTrusted($user, $post);
    }

    /** Whoever may send it to the Trash may bring it back out. */
    public function restore(User $user, Post $post): bool
    {
        return $this->delete($user, $post);
    }

    /** …and delete it for good from there. */
    public function forceDelete(User $user, Post $post): bool
    {
        return $this->delete($user, $post);
    }

    private function ownsOrIsTrusted(User $user, Post $post): bool
    {
        if ($user->role?->scope !== 'Own content') {
            return true;
        }

        return $post->user_id === $user->id;
    }
}
