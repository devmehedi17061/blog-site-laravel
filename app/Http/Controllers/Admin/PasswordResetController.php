<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Security;
use App\Support\Sessions;
use App\Support\SpamGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * "Forgot your password?" for the admin panel.
 *
 * The answer to a request is always the same, whether or not the email
 * belongs to an account, so the form cannot be used to find out who has
 * one. A link works once, for an hour (config/auth.php), and is only sent to
 * an active account. Choosing a new password follows the password rules,
 * signs every other session of the account out and clears its lockout; it
 * does not skip two-factor, which still asks for its code at sign-in.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('backend.forgot-password');
    }

    public function send(Request $request, SpamGuard $guard): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email:rfc', 'max:255']]);

        if ($guard->protectsLogin() && ($error = $guard->captchaError($request))) {
            return back()->withInput()->withErrors(['email' => $error]);
        }

        $email = Str::lower($data['email']);
        // One email per address per hour at most, however often it is asked.
        $key = 'password-reset:'.sha1($email);

        if (! RateLimiter::tooManyAttempts($key, 1)) {
            RateLimiter::hit($key, 3600);
            $user = User::where('email', $email)->first();

            if ($user && $user->isActive()) {
                try {
                    $token = Password::broker()->createToken($user);
                    $url = route('admin.password.reset', ['token' => $token, 'email' => $user->email]);
                    Mail::to($user->email)->send(new PasswordResetMail($user, $url, (int) config('auth.passwords.users.expire', 60), $request->ip()));
                    ActivityLog::create(['user_id' => $user->id, 'action' => 'Password reset requested', 'description' => 'A password reset link was emailed to '.$user->email, 'ip_address' => $request->ip()]);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return back()->with('status', 'If that address belongs to an account, a link to choose a new password is on its way. It works for an hour.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('backend.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function update(Request $request, Security $security): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'confirmed', $security->passwordRule()],
        ]);

        $status = Password::broker()->reset(
            ['email' => Str::lower($data['email']), 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password) use ($request) {
                if (! $user->isActive()) {
                    return;
                }
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                Sessions::endAllFor($user);
                RateLimiter::clear('login:'.Str::lower($user->email).'|'.$request->ip());
                ActivityLog::create(['user_id' => $user->id, 'action' => 'Password reset', 'description' => $user->name.' chose a new password with an emailed link', 'ip_address' => $request->ip()]);
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'This link is not valid any more. Ask for a new one.']);
        }

        return redirect()->route('admin.login')->with('status', 'Your password is changed. Sign in with the new one.');
    }
}
