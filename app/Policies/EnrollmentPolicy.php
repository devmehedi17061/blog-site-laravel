<?php

namespace App\Policies;

use App\Models\Enrollment;
use App\Models\User;

/**
 * An "Own content" role (Instructor) only ever sees the students on its own
 * courses — the list is filtered in the controller, and these checks hold
 * the same line for single records.
 */
class EnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('enrollments.view');
    }

    public function view(User $user, Enrollment $enrollment): bool
    {
        return $user->hasPermission('enrollments.view') && $this->reaches($user, $enrollment);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('enrollments.create');
    }

    public function update(User $user, Enrollment $enrollment): bool
    {
        return $user->hasPermission('enrollments.update') && $this->reaches($user, $enrollment);
    }

    public function delete(User $user, Enrollment $enrollment): bool
    {
        return $user->hasPermission('enrollments.delete') && $this->reaches($user, $enrollment);
    }

    private function reaches(User $user, Enrollment $enrollment): bool
    {
        return $user->role?->scope !== 'Own content' || $enrollment->course?->user_id === $user->id;
    }
}
