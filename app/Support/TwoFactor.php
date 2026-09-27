<?php

namespace App\Support;

use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Two-factor sign-in: authenticator-app codes, emailed codes and the
 * one-time recovery codes that get someone in when their phone is gone.
 */
class TwoFactor
{
    public const METHODS = ['app', 'email'];

    private const EMAIL_CODE_MINUTES = 10;

    private const MAX_TRIES = 5;

    // ── recovery codes ─────────────────────────────────────────────────────

    /** Eight codes like "k3f9-2mqx", shown once, each usable once. */
    public static function newRecoveryCodes(): array
    {
        return collect(range(1, 8))
            ->map(fn () => Str::lower(Str::random(4)).'-'.Str::lower(Str::random(4)))
            ->all();
    }

    /** Uses up a recovery code if it is one of this user's. */
    public static function useRecoveryCode(User $user, string $typed): bool
    {
        $typed = Str::lower(trim($typed));
        $codes = $user->two_factor_recovery_codes ?? [];

        foreach ($codes as $i => $code) {
            if (hash_equals($code, $typed)) {
                unset($codes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    // ── emailed codes ──────────────────────────────────────────────────────

    /**
     * Sends a fresh 6-digit code. Only its hash is kept, for ten minutes,
     * with a count of wrong tries so it cannot be guessed.
     */
    public static function sendEmailCode(User $user, string $purpose = 'sign-in'): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::emailKey($user), [
            'hash' => Hash::make($code),
            'tries' => 0,
        ], now()->addMinutes(self::EMAIL_CODE_MINUTES));

        Mail::to($user->email)->send(new TwoFactorCodeMail($user, $code, $purpose, self::EMAIL_CODE_MINUTES));
    }

    public static function verifyEmailCode(User $user, string $typed): bool
    {
        $key = self::emailKey($user);
        $entry = Cache::get($key);

        if (! $entry) {
            return false;
        }

        if (Hash::check(preg_replace('/\s+/', '', $typed), $entry['hash'])) {
            Cache::forget($key);

            return true;
        }

        // Too many wrong guesses and the code is gone: a new one must be sent.
        $entry['tries']++;
        $entry['tries'] >= self::MAX_TRIES
            ? Cache::forget($key)
            : Cache::put($key, $entry, now()->addMinutes(self::EMAIL_CODE_MINUTES));

        return false;
    }

    private static function emailKey(User $user): string
    {
        return '2fa-email:'.$user->id;
    }

    // ── the code the person typed, whatever their method ───────────────────

    public static function verify(User $user, string $code): bool
    {
        return match ($user->two_factor_method) {
            'app' => $user->two_factor_secret && Totp::verify($user->two_factor_secret, $code, 'user-'.$user->id),
            'email' => self::verifyEmailCode($user, $code),
            default => false,
        };
    }

    // ── switching it on and off ────────────────────────────────────────────

    /** @return array<int, string> the new recovery codes, to show once */
    public static function enable(User $user, string $method, ?string $secret = null): array
    {
        $codes = self::newRecoveryCodes();

        $user->forceFill([
            'two_factor_method' => $method,
            'two_factor_secret' => $method === 'app' ? $secret : null,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $codes;
    }

    public static function disable(User $user): void
    {
        $user->forceFill([
            'two_factor_method' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();
    }
}
