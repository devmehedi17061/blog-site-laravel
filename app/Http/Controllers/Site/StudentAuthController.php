<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Lms;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * The students' door: sign in, register, forgotten password. Staff can use
 * it too (it is the same account system) and land on their own courses;
 * the panel keeps its own sign-in with two-factor at /admin/login.
 */
class StudentAuthController extends Controller
{
    public function showLogin(Request $request): View
    {
        $this->rememberRedirect($request);

        return view('frontend.student.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'student-login:'.Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);
            throw ValidationException::withMessages(['email' => "Too many tries. Wait {$minutes} minute(s) and try again."]);
        }

        $user = User::where('email', Str::lower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 15 * 60);
            throw ValidationException::withMessages(['email' => __('That email and password do not match an account.')]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages(['email' => __('This account is suspended. Please contact us.')]);
        }

        // Accounts that use two-factor must pass it, which only the panel's
        // sign-in can ask for.
        if ($user->hasTwoFactor()) {
            throw ValidationException::withMessages(['email' => __('This account uses two-factor sign-in. Sign in at :url.', ['url' => route('admin.login')])]);
        }

        RateLimiter::clear($key);
        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_seen_at' => now()])->saveQuietly();

        return redirect()->intended(route('student.dashboard'));
    }

    public function showRegister(Request $request): View|RedirectResponse
    {
        $this->rememberRedirect($request);

        if (! Lms::setting('allow_registration')) {
            return redirect()->route('student.login')->with('status', __('New accounts are not open right now.'));
        }

        return view('frontend.student.register');
    }

    public function register(Request $request): RedirectResponse
    {
        abort_unless(Lms::setting('allow_registration'), 403, __('New accounts are not open right now.'));

        // A field people cannot see; bots fill every box they find.
        if (filled($request->input('website'))) {
            return redirect()->route('home');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ], [
            'email.unique' => __('An account already uses this email. Sign in instead, or reset the password.'),
        ]);

        $user = Lms::createStudent($data['name'], $data['email'], $data['password']);
        Auth::login($user, true);
        $request->session()->regenerate();

        ActivityLog::record('Student registered', $user->name.' <'.$user->email.'>', $user);

        return redirect()->intended(route('student.dashboard'))
            ->with('status', __('Welcome, :name! Your account is ready.', ['name' => Str::before($user->name, ' ')]));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    /**
     * "?redirect=/courses/x" — where to go once signed in. Only a path on
     * this site is taken, so the link cannot be used to send someone off to
     * another site after they type their password.
     */
    private function rememberRedirect(Request $request): void
    {
        $to = (string) $request->query('redirect', '');

        if (str_starts_with($to, '/') && ! str_starts_with($to, '//') && ! str_contains($to, '\\')) {
            $request->session()->put('url.intended', url($to));
        }
    }

    // ── forgotten password ─────────────────────────────────────────────────

    public function showForgot(): View
    {
        return view('frontend.student.forgot');
    }

    public function sendReset(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        try {
            Password::sendResetLink($request->only('email'));
        } catch (Throwable $e) {
            report($e);
        }

        // The same answer whether or not the email has an account, so the
        // form cannot be used to find out who is registered.
        return back()->with('status', __('If that email has an account, a reset link is on its way. Check your inbox.'));
    }

    public function showReset(Request $request, string $token): View
    {
        return view('frontend.student.reset', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('student.login')->with('status', __('Password changed. Sign in with the new one.'))
            : back()->withErrors(['email' => __($status)]);
    }
}
