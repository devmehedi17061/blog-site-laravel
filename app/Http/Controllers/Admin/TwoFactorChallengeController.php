<?php

namespace App\Http\Controllers\Admin;

use App\Actions\SignIn;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\TwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Step two of signing in, for someone with two-factor on: a code from their
 * authenticator app or their inbox — or one of their recovery codes.
 */
class TwoFactorChallengeController extends Controller
{
    private const MAX_TRIES = 5;

    public function show(Request $request): View|RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('admin.login')->withErrors(['email' => 'Sign in again — the code step timed out.']);
        }

        return view('backend.two-factor', ['method' => $user->two_factor_method, 'email' => $this->maskEmail($user->email)]);
    }

    public function store(Request $request, SignIn $signIn): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('admin.login')->withErrors(['email' => 'Sign in again — the code step timed out.']);
        }

        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
        ]);

        $recovery = trim((string) ($data['recovery_code'] ?? ''));
        $passed = $recovery !== ''
            ? TwoFactor::useRecoveryCode($user, $recovery)
            : TwoFactor::verify($user, (string) ($data['code'] ?? ''));

        if (! $passed) {
            // A handful of guesses, then back to the password: six digits
            // cannot be guessed five at a time.
            $pending = $request->session()->get('login.pending');
            $pending['tries'] = ($pending['tries'] ?? 0) + 1;

            ActivityLog::create([
                'user_id' => $user->id,
                'action' => 'Failed two-factor code',
                'description' => 'Wrong '.($recovery !== '' ? 'recovery code' : 'code').' for '.$user->email,
                'ip_address' => $request->ip(),
            ]);

            if ($pending['tries'] >= self::MAX_TRIES) {
                $request->session()->forget('login.pending');

                return redirect()->route('admin.login')->withErrors(['email' => 'Too many wrong codes. Sign in again.']);
            }

            $request->session()->put('login.pending', $pending);

            return back()->withErrors([$recovery !== '' ? 'recovery_code' : 'code' => $recovery !== ''
                ? 'That recovery code is not valid, or has been used.'
                : 'That code is not right. Check it and try again.']);
        }

        $pending = $request->session()->get('login.pending');
        $signIn->complete($request, $user, (bool) ($pending['remember'] ?? false), $recovery !== '' ? 'a recovery code' : 'two-factor');

        if ($recovery !== '') {
            $left = count($user->fresh()->two_factor_recovery_codes ?? []);
            session()->flash('recovery_notice', $left);
        }

        return redirect()->intended(route('admin.panel'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user || $user->two_factor_method !== 'email') {
            return redirect()->route('admin.login');
        }

        try {
            TwoFactor::sendEmailCode($user);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['code' => 'The email could not be sent. Use a recovery code instead.']);
        }

        return back()->with('status', 'A new code is on its way.');
    }

    private function pendingUser(Request $request): ?User
    {
        $pending = $request->session()->get('login.pending');

        if (! $pending || ($pending['expires'] ?? 0) < now()->timestamp) {
            $request->session()->forget('login.pending');

            return null;
        }

        $user = User::find($pending['user_id']);

        return $user && $user->isActive() && $user->hasTwoFactor() ? $user : null;
    }

    /** "ar***@example.com" — enough to recognise, not enough to harvest. */
    private function maskEmail(string $email): string
    {
        [$name, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return mb_substr($name, 0, 2).str_repeat('*', max(1, mb_strlen($name) - 2)).'@'.$domain;
    }
}
