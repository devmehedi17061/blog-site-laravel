<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The permission matrix, read from the permissions table rather than kept
 * in the front end: a module × ability grid, grouped the way the sidebar is.
 * Each cell says whether the person asking may grant it.
 */
class RoleOptionsController extends Controller
{
    private const ABILITIES = ['view', 'create', 'update', 'delete'];

    /** Where the sidebar calls a module something other than its slug. */
    private const LABELS = [
        'media' => 'Media library',
        'menu' => 'Menu control',
        'sections' => 'Section library',
        'forms' => 'Forms & inbox',
        'users' => 'User management',
        'roles' => 'Roles & permissions',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Role::class);

        $actor = $request->user();

        $groups = Permission::orderBy('id')->get()
            ->groupBy('group')
            ->map(fn ($permissions, $group) => [
                'label' => $group,
                'modules' => $permissions
                    ->groupBy(fn (Permission $p) => Str::before($p->slug, '.'))
                    ->map(fn ($cells, $module) => [
                        'key' => $module,
                        'label' => self::LABELS[$module] ?? Str::headline($module),
                        'cells' => $cells->mapWithKeys(fn (Permission $p) => [
                            Str::after($p->slug, '.') => [
                                'slug' => $p->slug,
                                'name' => $p->name,
                                'grantable' => $actor->canGrantPermissions([$p->slug]),
                            ],
                        ]),
                    ])
                    ->values(),
            ])
            ->values();

        return response()->json([
            'abilities' => self::ABILITIES,
            'groups' => $groups,
            'scopes' => collect(Role::SCOPES)
                ->map(fn ($description, $name) => ['name' => $name, 'description' => $description])
                ->values(),
        ]);
    }
}
