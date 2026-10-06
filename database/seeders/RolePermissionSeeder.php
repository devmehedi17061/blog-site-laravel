<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    /**
     * Permission groups mirror the sidebar sections of the admin panel.
     */
    private array $groups = [
        'Content' => ['pages', 'posts', 'categories', 'media', 'menu'],
        'Builders' => ['sections', 'forms', 'layout'],
        'LMS' => ['courses', 'enrollments'],
        'Access' => ['users', 'roles', 'security'],
        'System' => ['settings'],
    ];

    private array $abilities = ['view', 'create', 'update', 'delete'];

    public function run(): void
    {
        $permissions = [];

        foreach ($this->groups as $group => $modules) {
            foreach ($modules as $module) {
                foreach ($this->abilities as $ability) {
                    $slug = "{$module}.{$ability}";

                    $permissions[$slug] = Permission::updateOrCreate(
                        ['slug' => $slug],
                        [
                            'name' => Str::headline($ability).' '.Str::headline($module),
                            'group' => $group,
                        ]
                    );
                }
            }
        }

        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'scope' => 'Global', 'is_locked' => true,
                'description' => 'Full access to every module and setting.', 'grant' => 'all'],
            ['name' => 'Editor', 'slug' => 'editor', 'scope' => 'Content', 'is_locked' => false,
                'description' => 'Manages all content and builders, no access control.',
                'grant' => ['pages', 'posts', 'categories', 'media', 'menu', 'sections', 'forms', 'layout', 'courses', 'enrollments']],
            ['name' => 'Instructor', 'slug' => 'instructor', 'scope' => 'Own content', 'is_locked' => false,
                'description' => 'Builds and edits their own courses and sees who is enrolled on them.',
                'grant' => ['courses.view', 'courses.create', 'courses.update', 'enrollments.view',
                    'media.view', 'media.create', 'categories.view']],
            ['name' => 'Author', 'slug' => 'author', 'scope' => 'Own content', 'is_locked' => false,
                'description' => 'Writes and edits their own posts and uploads media.',
                'grant' => ['posts.view', 'posts.create', 'posts.update', 'categories.view',
                    'media.view', 'media.create', 'pages.view']],
            ['name' => 'Viewer', 'slug' => 'viewer', 'scope' => 'Read only', 'is_locked' => false,
                'description' => 'Read only access to content listings.',
                'grant' => ['pages.view', 'posts.view', 'categories.view', 'media.view', 'users.view']],
            // Learners sign in on the public site and never see the panel.
            ['name' => 'Student', 'slug' => 'student', 'scope' => 'Read only', 'is_locked' => true,
                'description' => 'Learners. Sign in on the public site; no admin panel access.',
                'grant' => []],
        ];

        foreach ($roles as $definition) {
            $grant = $definition['grant'];
            unset($definition['grant']);

            $role = Role::updateOrCreate(['slug' => $definition['slug']], $definition);

            $slugs = match (true) {
                $grant === 'all' => array_keys($permissions),
                // A bare module name grants every ability on that module.
                default => collect($grant)->flatMap(fn (string $item) => str_contains($item, '.')
                    ? [$item]
                    : array_map(fn ($ability) => "{$item}.{$ability}", $this->abilities)
                )->all(),
            };

            $role->permissions()->sync(
                collect($slugs)->map(fn ($slug) => $permissions[$slug]->id)->all()
            );
        }
    }
}
