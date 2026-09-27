<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Security;
use App\Support\Sessions;
use App\Support\Totp;
use App\Support\TwoFactor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * My account: anyone signed in, whatever their role. Their name and email,
 * their password, their two-factor sign-in and where they are signed in.
 *
 * Changing the email, the password or two-factor asks for the current
 * password — a panel left open on someone's desk should not be enough.
 */
class AccountController extends Controller
{
    public function __construct(private readonly Security $security) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->payload($request));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'current_password' => [Rule::requiredIf($request->input('email') !== $user->email), 'nullable', 'current_password'],
        ], [
            'current_password.required' => 'Enter your current password to change your email.',
            'current_password.current_password' => 'That is not your current password.',
        ]);

        $user->update(['name' => $data['name'], 'email' => $data['email']]);
        $this->log($request, 'Updated own profile', $user->name);

        return response()->json(['message' => 'Profile saved.'] + $this->payload($request));
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', 'different:current_password', $this->security->passwordRule(), 'max:190'],
        ], [
            'current_password.current_password' => 'That is not your current password.',
            'password.different' => 'Choose a password you are not using now.',
        ]);

        $user->update(['password' => $request->input('password')]);

        // Everywhere else this account was signed in ends; this browser stays.
        $ended = Sessions::endAllFor($user, $request->session()->getId());
        $this->log($request, 'Changed own password', $ended > 0 ? "{$ended} other session(s) signed out" : $user->name);

        return response()->json(['message' => 'Password changed. Any other devices were signed out.'] + $this->payload($request));
    }

    // ── two-factor ─────────────────────────────────────────────────────────

    /**
     * Starts app setup: a new secret, kept in this session only until a first
     * code proves the phone has it. Nothing changes on the account yet.
     */
    public function startApp(Request $request): JsonResponse
    {
        $secret = Totp::newSecret();
        $request->session()->put('two_factor.setup', ['method' => 'app', 'secret' => $secret]);

        return response()->json([
            'secret' => trim(chunk_split($secret, 4, ' ')),
            'uri' => Totp::uri($secret, $request->user()->email, (string) config('app.name')),
        ]);
    }

    /** Starts email setup: a code to the account's own address. */
    public function startEmail(Request $request): JsonResponse
    {
        if (! $this->security->emailCodesAvailable()) {
            return response()->json(['message' => 'The site cannot send email yet — set up Settings → Email first, or use an authenticator app.'], 422);
        }

        $request->session()->put('two_factor.setup', ['method' => 'email']);

        try {
            TwoFactor::sendEmailCode($request->user(), 'setup');
        } catch (Throwable $e) {
            report($e);

            return response()->json(['message' => 'The code email could not be sent. Check Settings → Email, or use an authenticator app.'], 422);
        }

        return response()->json(['message' => 'A code is on its way to '.$request->user()->email.'.']);
    }

    /** The first code proves setup worked: two-factor goes on here. */
    public function confirm(Request $request): JsonResponse
    {
        $user = $request->user();
        $setup = $request->session()->get('two_factor.setup');
        $request->validate(['code' => ['required', 'string', 'max:10']]);

        if (! $setup) {
            return response()->json(['message' => 'Start the setup again.'], 422);
        }

        $ok = $setup['method'] === 'app'
            ? Totp::verify($setup['secret'], (string) $request->input('code'), 'setup-'.$user->id)
            : TwoFactor::verifyEmailCode($user, (string) $request->input('code'));

        if (! $ok) {
            throw ValidationException::withMessages(['code' => 'That code is not right. Check it and try again.']);
        }

        $codes = TwoFactor::enable($user, $setup['method'], $setup['secret'] ?? null);
        $request->session()->forget('two_factor.setup');

        $this->log($request, 'Turned on two-factor', $setup['method'] === 'app' ? 'Authenticator app' : 'Email codes');

        return response()->json([
            'message' => 'Two-factor sign-in is on.',
            'recovery_codes' => $codes,
        ] + $this->payload($request));
    }

    public function regenerateRecoveryCodes(Request $request): JsonResponse
    {
        $request->validate(['current_password' => ['required', 'current_password']], [
            'current_password.current_password' => 'That is not your current password.',
        ]);

        $user = $request->user();
        abort_unless($user->hasTwoFactor(), 422, 'Two-factor is not on.');

        $codes = TwoFactor::newRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();
        $this->log($request, 'New recovery codes', $user->name);

        return response()->json(['message' => 'New recovery codes made. The old ones no longer work.', 'recovery_codes' => $codes] + $this->payload($request));
    }

    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate(['current_password' => ['required', 'current_password']], [
            'current_password.current_password' => 'That is not your current password.',
        ]);

        if ($this->security->requiresTwoFactor($user)) {
            return response()->json(['message' => 'Your role requires two-factor sign-in, so it cannot be turned off. You can switch to the other method instead.'], 422);
        }

        TwoFactor::disable($user);
        $this->log($request, 'Turned off two-factor', $user->name);

        return response()->json(['message' => 'Two-factor sign-in is off.'] + $this->payload($request));
    }

    // ── sessions ───────────────────────────────────────────────────────────

    public function endSession(Request $request, string $key): JsonResponse
    {
        $ended = Sessions::end($key, $request->user()->id);
        abort_if($ended === 0, 404);

        $this->log($request, 'Ended a session', 'Own session signed out');

        return response()->json(['message' => 'That session is signed out.'] + $this->payload($request));
    }

    public function endOtherSessions(Request $request): JsonResponse
    {
        $ended = Sessions::endAllFor($request->user(), $request->session()->getId());
        $this->log($request, 'Signed out other devices', "{$ended} session(s)");

        return response()->json(['message' => $ended > 0 ? "Signed out of {$ended} other session(s)." : 'No other sessions were open.'] + $this->payload($request));
    }

    // ───────────────────────────────────────────────────────────────────────

    private function payload(Request $request): array
    {
        /** @var User $user */
        $user = $request->user()->fresh();

        return [
            'account' => [
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role?->name,
                'password_changed' => $user->password_changed_at?->diffForHumans(),
                'password_expired' => $this->security->passwordExpired($user),
                'password_hint' => $this->security->passwordHint(),
            ],
            'two_factor' => [
                'enabled' => $user->hasTwoFactor(),
                'method' => $user->hasTwoFactor() ? $user->two_factor_method : null,
                'since' => $user->two_factor_confirmed_at?->format('j M Y'),
                'recovery_left' => $user->hasTwoFactor() ? count($user->two_factor_recovery_codes ?? []) : 0,
                'required' => $this->security->requiresTwoFactor($user),
                'email_available' => $this->security->emailCodesAvailable(),
            ],
            'sessions' => Sessions::list($user->id, $request->session()->getId())->values(),
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
}
