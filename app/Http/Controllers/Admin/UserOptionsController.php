<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the user list and its dialogs need to fill their select boxes. Each
 * role says whether the person asking may assign it, so the form can grey
 * out the ones the server would refuse.
 */
class UserOptionsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $actor = $request->user();

        $roles = Role::query()
            ->with('permissions:id,slug')
            ->withCount('users')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'slug' => $role->slug,
                'scope' => $role->scope,
                'users_count' => $role->users_count,
                'assignable' => $actor->canGrant($role),
            ]);

        return response()->json([
            'statuses' => User::STATUSES,
            'roles' => $roles,
            // Everyone who could inherit a deleted account's content.
            'users' => User::orderBy('name')->get(['id', 'name', 'email', 'status']),
        ]);
    }
}
