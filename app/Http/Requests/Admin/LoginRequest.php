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
            $this->logFailure($user, RateLimiter::tooManyAttempts($key, $max));

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

        return $user;
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
