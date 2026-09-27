<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Resources\RoleResource;
use App\Models\ActivityLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Slug;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Roles and what each may do. There are only ever a handful, so the list is
 * never paged — the screen holds all of them and edits one at a time.
 */
class RoleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Role::class);

        return RoleResource::collection($this->all());
    }

    public function show(Role $role): RoleResource
    {
        $this->authorize('view', $role);

        return RoleResource::make($role->load('permissions')->loadCount('users'));
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $data = $request->validated();

        $role = DB::transaction(function () use ($data) {
            $role = Role::create([
                'name' => $data['name'],
                // Set once and never changed: code and seeders find roles by
                // slug, so renaming one must not move it.
                'slug' => Slug::unique(Role::class, $data['name']),
                'scope' => $data['scope'],
                'description' => $data['description'] ?? null,
                'is_locked' => false,
            ]);

            $role->permissions()->sync($this->permissionIds($data['permissions']));

            return $role;
        });

        ActivityLog::record('Created role', $role->name, $role);

        return RoleResource::make($role->load('permissions')->loadCount('users'))
            ->response()->setStatusCode(201);
    }

    public function update(StoreRoleRequest $request, Role $role): RoleResource
    {
        $data = $request->validated();

        DB::transaction(function () use ($role, $data) {
            $role->update([
                'name' => $data['name'],
                'scope' => $data['scope'],
                'description' => $data['description'] ?? null,
            ]);

            $role->permissions()->sync($this->permissionIds($data['permissions']));
            // A sync alone does not touch the row, and "updated" should move.
            $role->touch();
        });

        ActivityLog::record('Updated role', $role->name, $role);

        return RoleResource::make($role->fresh()->load('permissions')->loadCount('users'));
    }

    /**
     * The people in a role go wherever the caller says. With nowhere given
     * they are left with no role at all — they can still sign in, but see
     * nothing past the dashboard until someone gives them one.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        $this->authorize('delete', $role);

        $data = $request->validate([
            'move_users_to' => ['nullable', 'integer', Rule::exists('roles', 'id'), Rule::notIn([$role->id])],
        ], [
            'move_users_to.not_in' => 'People cannot be moved into the role being deleted.',
        ]);

        $target = isset($data['move_users_to']) ? Role::find($data['move_users_to']) : null;

        if ($target && ! $request->user()->canGrant($target)) {
            return response()->json([
                'message' => "You cannot move people into {$target->name}: it has access you do not hold.",
            ], 422);
        }

        $name = $role->name;
        $moved = 0;

        DB::transaction(function () use ($role, $target, &$moved) {
            $moved = User::where('role_id', $role->id)->update(['role_id' => $target?->id]);
            $role->delete();
        });

        ActivityLog::record('Deleted role', $name);

        $message = "\"{$name}\" deleted.";

        if ($moved > 0) {
            $message .= $target
                ? " {$moved} user(s) moved to {$target->name}."
                : " {$moved} user(s) now have no role.";
        }

        return response()->json([
            'message' => $message,
            'data' => RoleResource::collection($this->all())->resolve(),
        ]);
    }

    private function all()
    {
        return Role::query()->with('permissions')->withCount('users')->orderBy('id')->get();
    }

    /** @param  array<int, string>  $slugs */
    private function permissionIds(array $slugs): array
    {
        return Permission::whereIn('slug', $slugs)->pluck('id')->all();
    }
}
