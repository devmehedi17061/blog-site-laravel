<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SecuritySettingsRequest;
use App\Models\ActivityLog;
use App\Models\IpRule;
use App\Models\Role;
use App\Models\User;
use App\Support\Security;
use App\Support\Sessions;
use App\Support\SiteSettings;
use App\Support\TwoFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The Security screen: sign-in rules, two-factor by role, everyone's
 * sessions, admin IP rules, HTTPS and headers, and the security log.
 */
class SecurityController extends Controller
{
    /** What the security log shows, by filter. */
    private const LOG_TYPES = [
        'sign-ins' => ['Signed in', 'Signed out'],
        'failures' => ['Failed sign-in', 'Locked out', 'Failed two-factor code'],
        'two-factor' => ['Turned on two-factor', 'Turned off two-factor', 'New recovery codes', 'Reset two-factor'],
        'changes' => [
            'Updated security settings', 'Added IP rule', 'Removed IP rule', 'Ended a session',
            'Signed out other devices', 'Signed out everywhere', 'Changed own password', 'Suspended user',
        ],
    ];

    public function __construct(
        private readonly SiteSettings $settings,
        private readonly Security $security,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $this->can($request, 'view');

        return response()->json($this->payload($request));
    }

    public function update(SecuritySettingsRequest $request): JsonResponse
    {
        $this->settings->save('security', $request->validated());
        $this->log($request, 'Updated security settings', 'Security settings saved');

        return response()->json(['message' => 'Security settings saved.'] + $this->payload($request));
    }

    // ── IP rules ───────────────────────────────────────────────────────────

    public function storeIpRule(Request $request): JsonResponse
    {
        $this->can($request, 'update');

        $data = $request->validate([
            'value' => ['required', 'string', 'max:64', function ($attribute, $value, $fail) {
                if (! Security::validIpOrRange(trim($value))) {
                    $fail('Use an address like 203.0.113.7, or a range like 203.0.113.0/24.');
                }
            }],
            'type' => ['required', Rule::in(['allow', 'block'])],
            'note' => ['nullable', 'string', 'max:120'],
        ]);
        $data['value'] = trim($data['value']);

        $rules = $this->security->ipRules()->map(fn (IpRule $r) => ['value' => $r->value, 'type' => $r->type])->push($data);

        if ($this->security->get('ip_rules_enabled') && ! Security::ipAllowed((string) $request->ip(), $rules)) {
            return response()->json([
                'message' => "That rule would lock you out: your address ({$request->ip()}) would no longer be allowed.",
                'errors' => ['value' => ['This would lock you out.']],
            ], 422);
        }

        $rule = IpRule::create($data);
        $this->log($request, 'Added IP rule', ucfirst($rule->type).' '.$rule->value);

        return response()->json(['message' => 'Rule added.'] + $this->payload($request), 201);
    }

    public function destroyIpRule(Request $request, IpRule $ipRule): JsonResponse
    {
        $this->can($request, 'update');

        $rules = $this->security->ipRules()->reject(fn (IpRule $r) => $r->is($ipRule))
            ->map(fn (IpRule $r) => ['value' => $r->value, 'type' => $r->type]);

        // Removing the last allow rule that covers you, while other allow
        // rules remain, would shut you out too.
        if ($this->security->get('ip_rules_enabled') && ! Security::ipAllowed((string) $request->ip(), $rules)) {
            return response()->json(['message' => "Removing that rule would lock you out: your address ({$request->ip()}) would no longer be allowed."], 422);
        }

        $ipRule->delete();
        $this->log($request, 'Removed IP rule', ucfirst($ipRule->type).' '.$ipRule->value);

        return response()->json(['message' => 'Rule removed.'] + $this->payload($request));
    }

    // ── people and sessions ────────────────────────────────────────────────

    public function endSession(Request $request, string $key): JsonResponse
    {
        $this->can($request, 'update');

        abort_if(Sessions::end($key) === 0, 404);
        $this->log($request, 'Ended a session', 'A signed-in session was ended');

        return response()->json(['message' => 'That session is signed out.'] + $this->payload($request));
    }

    public function signOutUser(Request $request, User $user): JsonResponse
    {
        $this->canManage($request, $user);

        $ended = Sessions::endAllFor($user, $request->user()->is($user) ? $request->session()->getId() : null);
        $this->log($request, 'Signed out everywhere', "{$user->name}: {$ended} session(s)");

        return response()->json(['message' => "{$user->name} is signed out everywhere."] + $this->payload($request));
    }

    /** For a lost phone: they sign in with their password and set it up again. */
    public function resetTwoFactor(Request $request, User $user): JsonResponse
    {
        $this->canManage($request, $user);

        TwoFactor::disable($user);
        Sessions::endAllFor($user);
        $this->log($request, 'Reset two-factor', "For {$user->name}");

        $required = $this->security->requiresTwoFactor($user);

        return response()->json([
            'message' => "Two-factor reset for {$user->name}.".($required ? ' Their role requires it, so they will set it up again after signing in.' : ''),
        ] + $this->payload($request));
    }

    // ── log ────────────────────────────────────────────────────────────────

    /** The security log: sign-ins, failures, two-factor and security changes. */
    public function history(Request $request): JsonResponse
    {
        $this->can($request, 'view');

        $data = $request->validate([
            'type' => ['nullable', Rule::in(['all', ...array_keys(self::LOG_TYPES)])],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $type = $data['type'] ?? 'all';
        $actions = $type === 'all' ? array_merge(...array_values(self::LOG_TYPES)) : self::LOG_TYPES[$type];

        $page = ActivityLog::query()
            ->with('user:id,name,email')
            ->whereIn('action', $actions)
            ->when($data['search'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('description', 'like', "%{$term}%")
                ->orWhere('ip_address', 'like', "%{$term}%")))
            ->latest('id')
            ->paginate(25);

        return response()->json([
            'data' => $page->getCollection()->map(fn (ActivityLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'description' => $l->description,
                'user' => $l->user?->name,
                'ip' => $l->ip_address,
                'when' => $l->created_at?->diffForHumans(),
                'at' => $l->created_at?->format('j M Y, H:i'),
                'tone' => in_array($l->action, self::LOG_TYPES['failures'], true) ? 'bad' : 'normal',
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    // ───────────────────────────────────────────────────────────────────────

    private function payload(Request $request): array
    {
        $users = User::with('role:id,name')->orderBy('name')->get();

        return [
            'settings' => $this->settings->forScreen('security'),
            'can' => ['update' => $request->user()->hasPermission('security.update')],
            'you' => [
                'ip' => $request->ip(),
                'secure' => $request->isSecure(),
                'local' => Security::isLocalHost($request->getHost()),
                'host' => $request->getHost(),
            ],
            'email_codes_available' => $this->security->emailCodesAvailable(),
            'sessions_available' => Sessions::available(),
            'roles' => Role::withCount('users')->orderBy('id')->get()->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'users' => $role->users_count,
                'with_two_factor' => $users->where('role_id', $role->id)->filter->hasTwoFactor()->count(),
            ]),
            'people' => $users->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role?->name,
                'role_id' => $u->role_id,
                'status' => $u->status,
                'two_factor' => $u->hasTwoFactor() ? $u->two_factor_method : null,
                'required' => $this->security->requiresTwoFactor($u),
                'password_changed' => $u->password_changed_at?->diffForHumans(),
                'password_expired' => $this->security->passwordExpired($u),
                'can_manage' => $request->user()->hasPermission('security.update') && $request->user()->canManage($u),
            ]),
            'ip_rules' => $this->security->ipRules()->map(fn (IpRule $r) => [
                'id' => $r->id, 'value' => $r->value, 'type' => $r->type, 'note' => $r->note,
                'matches_you' => Security::ipAllowed((string) $request->ip(), [['value' => $r->value, 'type' => 'block']]) === false,
            ]),
            'sessions' => Sessions::list(null, $request->session()->getId())->values(),
        ];
    }

    private function log(Request $request, string $action, string $description): void
    {
        ActivityLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => $request->ip(),
        ]);
    }

    private function can(Request $request, string $ability): void
    {
        abort_unless($request->user()->hasPermission('security.'.$ability), 403);
    }

    /** Security changes to an account follow the same "no one above you" rule as User management. */
    private function canManage(Request $request, User $user): void
    {
        $this->can($request, 'update');
        abort_unless($request->user()->canManage($user), 403);
    }
}
