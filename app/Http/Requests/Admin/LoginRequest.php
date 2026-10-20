<?php

namespace App\Http\Requests\Admin;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Security;
use App\Support\SpamGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Checks the password — but does not sign anyone in: someone with two-
     * factor on still has a second step to pass (see LoginController).
     *
     * Failed tries are counted per email + address. After the number set
     * under Settings → Security, that pair is paused: an attacker cannot
     * keep guessing, and cannot lock the real owner out from elsewhere
     * either. Suspended accounts get the same generic shape as a wrong
     * password until the password itself is right.
     */
    public function authenticatedUser(): User
    {
        $security = app(Security::class);
        $key = $this->throttleKey();
        $max = max(1, (int) $security->get('max_attempts'));

        // Two wider limits on top of the per email + IP one: one address
        // guessing at many accounts, and many addresses (a botnet) guessing
        // at one account. Both pause for an hour.
        $ipKey = 'login-ip:'.$this->ip();
        $accountKey = 'login-account:'.sha1(Str::lower((string) $this->input('email')));
        foreach ([[$ipKey, self::IP_LIMIT], [$accountKey, self::ACCOUNT_LIMIT]] as [$wide, $limit]) {
            if (RateLimiter::tooManyAttempts($wide, $limit)) {
                $minutes = (int) ceil(RateLimiter::availableIn($wide) / 60);

                throw ValidationException::withMessages([
                    'email' => ["Too many failed sign-ins. Try again in {$minutes} minute".($minutes === 1 ? '' : 's').'.'],
                ]);
            }
        }

        if (RateLimiter::tooManyAttempts($key, $max)) {
            $minutes = (int) ceil(RateLimiter::availableIn($key) / 60);

            throw ValidationException::withMessages([
                'email' => ["Too many failed sign-ins. Try again in {$minutes} minute".($minutes === 1 ? '' : 's').'.'],
            ]);
        }

        // Checked before the password, so a bot never gets to try one.
        if ($problem = app(SpamGuard::class)->protectsLogin() ? app(SpamGuard::class)->captchaError($this) : null) {
            throw ValidationException::withMessages(['email' => [$problem]]);
        }

        $user = User::where('email', $this->input('email'))->first();

        if (! $user || ! Hash::check((string) $this->input('password'), $user->password)) {
            RateLimiter::hit($key, max(1, (int) $security->get('lockout_minutes')) * 60);
            RateLimiter::hit($ipKey, 3600);
            RateLimiter::hit($accountKey, 3600);
            $locked = RateLimiter::tooManyAttempts($key, $max);
            $this->logFailure($user, $locked);

            // The account's owner hears about it the moment it locks.
            if ($user && ($locked && RateLimiter::attempts($key) === $max || RateLimiter::attempts($accountKey) === self::ACCOUNT_LIMIT)) {
                $this->alert($user);
            }

            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['This account is suspended. Contact a super admin.'],
            ]);
        }

        RateLimiter::clear($key);
        RateLimiter::clear($accountKey);

        return $user;
    }

    /** Failed sign-ins an hour from one IP address, whatever the account. */
    public const IP_LIMIT = 20;

    /** Failed sign-ins an hour at one account, from anywhere. */
    public const ACCOUNT_LIMIT = 30;

    /** Tells the account's owner that someone keeps guessing its password. */
    private function alert(User $user): void
    {
        try {
            \Illuminate\Support\Facades\Mail::raw(
                "Hi {$user->name},\n\nSomeone has entered the wrong password for your ".config('app.name')." admin account several times "
                ."(last from IP {$this->ip()}), so signing in to it is paused for a while.\n\n"
                ."If this was you, wait and try again, or use \"Forgot your password?\" on the sign-in page.\n"
                ."If it was not you, nothing has been let in — but consider switching on two-factor sign-in under My account.\n",
                fn ($m) => $m->to($user->email)->subject('Sign-in attempts on your '.config('app.name').' account')
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function throttleKey(): string
    {
        return 'login:'.Str::transliterate(Str::lower((string) $this->input('email'))).'|'.$this->ip();
    }

    private function logFailure(?User $user, bool $nowLocked): void
    {
        ActivityLog::create([
            'user_id' => $user?->id,
            'action' => $nowLocked ? 'Locked out' : 'Failed sign-in',
            'description' => $nowLocked
                ? 'Too many wrong passwords for '.$this->input('email').' — paused'
                : 'Wrong password for '.$this->input('email'),
            'ip_address' => $this->ip(),
        ]);
    }
}
