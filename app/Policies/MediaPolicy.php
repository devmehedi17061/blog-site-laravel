<?php

namespace App\Policies;

use App\Models\Media;
use App\Models\User;

class MediaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('media.view');
    }

    public function view(User $user, Media $media): bool
    {
        return $user->hasPermission('media.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('media.create');
    }

    /** Authors are scoped to their own content, here and in posts. */
    public function update(User $user, Media $media): bool
    {
        return $user->hasPermission('media.update') && $this->ownsOrIsTrusted($user, $media);
    }

    public function delete(User $user, Media $media): bool
    {
        return $user->hasPermission('media.delete') && $this->ownsOrIsTrusted($user, $media);
    }

    /** Whoever may send it to the Trash may bring it back out. */
    public function restore(User $user, Media $media): bool
    {
        return $this->delete($user, $media);
    }

    /** …and delete it for good from there. */
    public function forceDelete(User $user, Media $media): bool
    {
        return $this->delete($user, $media);
    }

    private function ownsOrIsTrusted(User $user, Media $media): bool
    {
        if ($user->role?->scope !== 'Own content') {
            return true;
        }

        return $media->user_id === $user->id;
    }
}
