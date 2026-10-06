<?php

namespace App\Policies;

use App\Models\Course;
use App\Models\User;

/**
 * The same shape as posts: an "Own content" role (Instructor) may change
 * only the courses it teaches; roles without that scope are trusted
 * site-wide.
 */
class CoursePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('courses.view');
    }

    public function view(User $user, Course $course): bool
    {
        return $user->hasPermission('courses.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('courses.create');
    }

    public function update(User $user, Course $course): bool
    {
        return $user->hasPermission('courses.update') && $this->ownsOrIsTrusted($user, $course);
    }

    public function delete(User $user, Course $course): bool
    {
        return $user->hasPermission('courses.delete') && $this->ownsOrIsTrusted($user, $course);
    }

    public function restore(User $user, Course $course): bool
    {
        return $this->delete($user, $course);
    }

    public function forceDelete(User $user, Course $course): bool
    {
        return $this->delete($user, $course);
    }

    private function ownsOrIsTrusted(User $user, Course $course): bool
    {
        return $user->role?->scope !== 'Own content' || $course->user_id === $user->id;
    }
}
