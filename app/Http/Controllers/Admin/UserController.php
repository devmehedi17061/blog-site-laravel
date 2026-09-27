<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UserIndexRequest;
use App\Http\Resources\UserResource;
use App\Models\ActivityLog;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\SectionTemplate;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const COUNTS = ['posts', 'pages', 'media'];

    public function index(UserIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()
            ->with('role.permissions')
            ->withCount(self::COUNTS)
            ->search($request->input('search'))
            ->status($request->input('status'))
            ->role($request->input('role'))
            ->sortBy($request->input('sort'), $request->input('direction'))
            ->paginate($request->perPage())
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function show(User $user): UserResource
    {
        $this->authorize('view', $user);

        return UserResource::make($user->load('role.permissions')->loadCount(self::COUNTS));
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated() + ['email_verified_at' => now()]);

        ActivityLog::record('Created user', "{$user->name} ({$user->email})", $user);

        return UserResource::make($user->load('role.permissions')->loadCount(self::COUNTS))
            ->response()->setStatusCode(201);
    }

    public function update(StoreUserRequest $request, User $user): UserResource
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $wasActive = $user->isActive();
        $user->update($data);

        // Someone else's password changed, or their account was suspended:
        // whatever sessions they have open should not outlive the decision.
        if (! $user->is($request->user()) && (isset($data['password']) || ($wasActive && ! $user->isActive()))) {
            $this->signOutEverywhere($user);
        }

        ActivityLog::record(
            $wasActive && ! $user->isActive() ? 'Suspended user' : 'Updated user',
            "{$user->name} ({$user->email})",
            $user
        );

        return UserResource::make($user->fresh()->load('role.permissions')->loadCount(self::COUNTS));
    }

    /**
     * Their posts, pages, media and section templates go to whoever the
     * caller picks. With nobody picked they stay on the site without an
     * author, which is what the foreign keys would do anyway — but the
     * screen asks first, rather than letting it happen quietly.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        $data = $request->validate([
            'reassign_to' => ['nullable', 'integer', Rule::exists('users', 'id'), Rule::notIn([$user->id])],
        ], [
            'reassign_to.not_in' => 'Content cannot be handed to the account being deleted.',
        ]);

        $heir = isset($data['reassign_to']) ? User::find($data['reassign_to']) : null;
        $label = "{$user->name} ({$user->email})";
        $moved = 0;

        DB::transaction(function () use ($user, $heir, &$moved) {
            if ($heir) {
                foreach ([Post::class, Page::class, Media::class, SectionTemplate::class] as $model) {
                    $moved += $model::where('user_id', $user->id)->update(['user_id' => $heir->id]);
                }
            }

            $this->signOutEverywhere($user);
            $user->tokens()->delete();
            $user->delete();
        });

        ActivityLog::record('Deleted user', $label);

        $message = "{$user->name} deleted.";

        if ($heir && $moved > 0) {
            $message .= " {$moved} item(s) now belong to {$heir->name}.";
        }

        return response()->json(['message' => $message]);
    }

    /**
     * Ends every session the user has open. Only the database driver can
     * find sessions by user; the remember-me token is cycled either way, so
     * a "keep me signed in" cookie stops working too.
     */
    private function signOutEverywhere(User $user): void
    {
        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }

        $user->forceFill(['remember_token' => Str::random(60)])->saveQuietly();
    }
}
