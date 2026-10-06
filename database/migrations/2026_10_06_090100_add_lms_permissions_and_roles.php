<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Two permission modules for the learning side, "courses" and
 * "enrollments", and two roles:
 *
 *   Instructor — builds their own courses and sees who is enrolled on them.
 *   Student    — no panel access at all; signs in on the public site.
 *
 * Roles are not reseeded (that would undo changes made under Roles &
 * permissions). Every role that may already edit pages gets the new modules
 * too: whoever runs the site's content is the natural owner of its courses.
 */
return new class extends Migration
{
    private const ABILITIES = ['view', 'create', 'update', 'delete'];

    private const MODULES = ['courses', 'enrollments'];

    public function up(): void
    {
        $now = now();
        $ids = [];

        foreach (self::MODULES as $module) {
            foreach (self::ABILITIES as $ability) {
                DB::table('permissions')->updateOrInsert(
                    ['slug' => "{$module}.{$ability}"],
                    ['name' => ucfirst($ability).' '.ucfirst($module), 'group' => 'LMS', 'created_at' => $now, 'updated_at' => $now]
                );
                $ids["{$module}.{$ability}"] = DB::table('permissions')->where('slug', "{$module}.{$ability}")->value('id');
            }
        }

        $grant = function (int $roleId, array $slugs) use ($ids) {
            foreach ($slugs as $slug) {
                DB::table('permission_role')->updateOrInsert(['permission_id' => $ids[$slug], 'role_id' => $roleId]);
            }
        };

        $pagesUpdate = DB::table('permissions')->where('slug', 'pages.update')->value('id');
        $roleIds = DB::table('permission_role')->where('permission_id', $pagesUpdate)->pluck('role_id');
        $roleIds = $roleIds->merge(DB::table('roles')->where('slug', 'super-admin')->pluck('id'))->unique();

        foreach ($roleIds as $roleId) {
            $grant($roleId, array_keys($ids));
        }

        // Instructor: their own courses ("Own content" scope), and the
        // media and categories a course editor needs to pick from.
        DB::table('roles')->updateOrInsert(['slug' => 'instructor'], [
            'name' => 'Instructor', 'scope' => 'Own content', 'is_locked' => false,
            'description' => 'Builds and edits their own courses and sees who is enrolled on them.',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $instructor = DB::table('roles')->where('slug', 'instructor')->value('id');
        $grant($instructor, ['courses.view', 'courses.create', 'courses.update', 'enrollments.view']);

        foreach (['media.view', 'media.create', 'categories.view'] as $slug) {
            if ($id = DB::table('permissions')->where('slug', $slug)->value('id')) {
                DB::table('permission_role')->updateOrInsert(['permission_id' => $id, 'role_id' => $instructor]);
            }
        }

        // Student: locked, so nobody hands it panel permissions by mistake.
        DB::table('roles')->updateOrInsert(['slug' => 'student'], [
            'name' => 'Student', 'scope' => 'Read only', 'is_locked' => true,
            'description' => 'Learners. Sign in on the public site; no admin panel access.',
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('slug', 'like', 'courses.%')
            ->orWhere('slug', 'like', 'enrollments.%')->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        $roles = DB::table('roles')->whereIn('slug', ['instructor', 'student'])->pluck('id');
        DB::table('users')->whereIn('role_id', $roles)->update(['role_id' => null]);
        DB::table('permission_role')->whereIn('role_id', $roles)->delete();
        DB::table('roles')->whereIn('id', $roles)->delete();
    }
};
