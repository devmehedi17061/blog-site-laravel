<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Settings → Spam protection, applied.
 *
 * Two kinds of answer:
 *
 *   silent — honeypot, time trap, blocked words and addresses, too many
 *   links. The bot is told "thanks" exactly as a person would be, so it has
 *   nothing to learn from; the submission is simply not kept or mailed.
 *
 *   spoken — a failed CAPTCHA. That can be a real person on a bad day, so
 *   they are told and can try again.
 */
class SpamGuard
{
    /** The hidden field carrying when the form was drawn. */
    public const STAMP_FIELD = '_ts';

    public const REASONS = [
        'honeypot' => 'Filled the hidden field',
        'too_fast' => 'Sent faster than a person can type',
        'blocked_word' => 'Contained a blocked word',
        'blocked_email' => 'Used a blocked email address',
        'too_many_links' => 'Too many links',
        'captcha' => 'Failed the CAPTCHA',
    ];

    public function __construct(private readonly SiteSettings $settings) {}

    private function get(string $key): mixed
    {
        return $this->settings->get('spam.'.$key);
    }

    // ── the hidden stamp ───────────────────────────────────────────────────

    /** Encrypted, so a bot cannot just send an old time to look patient. */
    public function stamp(): string
    {
        return Crypt::encryptString((string) now()->timestamp);
    }

    // ── silent checks ──────────────────────────────────────────────────────

    /**
     * Why this submission is spam, or null when it looks like a person.
     *
     * @param  array<string, mixed>  $answers  the form's own answers
     */
    public function silentReason(Request $request, array $answers): ?string
    {
        if (filled($request->input('website'))) {
            return 'honeypot';
        }

        if ($this->get('time_trap') && $this->tooFast($request->input(self::STAMP_FIELD))) {
            return 'too_fast';
        }

        $text = mb_strtolower(implode("\n", array_map(
            fn ($v) => is_array($v) ? implode(' ', $v) : (string) $v,
            $answers
        )));

        if ($this->hasBlockedWord($text)) {
            return 'blocked_word';
        }

        if ($this->hasBlockedEmail($answers)) {
            return 'blocked_email';
        }

        if ($this->get('limit_links') && $this->linkCount($text) > (int) $this->get('max_links')) {
            return 'too_many_links';
        }

        return null;
    }

    private function tooFast(mixed $stamp): bool
    {
        // No stamp, or one that was not ours: not a form a person loaded.
        try {
            $drawn = (int) Crypt::decryptString((string) $stamp);
        } catch (Throwable) {
            return true;
        }

        return now()->timestamp - $drawn < max(1, (int) $this->get('min_seconds'));
    }

    /** @return array<int, string> one entry per non-empty line, lower-cased */
    public static function lines(?string $list): array
    {
        return collect(preg_split('/\R/', (string) $list))
            ->map(fn ($l) => mb_strtolower(trim($l)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Whole words, so "sex" does not catch "Essex" or "assess". */
    private function hasBlockedWord(string $text): bool
    {
        foreach (self::lines($this->get('blocked_words')) as $word) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/u', $text)) {
                return true;
            }
        }

        return false;
    }

    /** "spam@x.com" blocks one address; "@x.com" the whole domain. */
    private function hasBlockedEmail(array $answers): bool
    {
        $blocked = self::lines($this->get('blocked_emails'));

        if (! $blocked) {
            return false;
        }

        foreach ($answers as $value) {
            foreach ((array) $value as $item) {
                $email = mb_strtolower(trim((string) $item));

                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    continue;
                }

                foreach ($blocked as $rule) {
                    if ($email === $rule || (str_starts_with($rule, '@') && str_ends_with($email, $rule))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function linkCount(string $text): int
    {
        return preg_match_all('#(https?://|www\.)\S+|\[url[=\]]#i', $text);
    }

    // ── CAPTCHA ────────────────────────────────────────────────────────────

    public function captchaProvider(): ?string
    {
        $provider = $this->get('captcha');

        // A provider without both keys is not switched on — a half-set
        // CAPTCHA would lock every visitor out of every form.
        return match ($provider) {
            'turnstile' => filled($this->get('turnstile_site_key')) && filled($this->get('turnstile_secret')) ? 'turnstile' : null,
            'recaptcha' => filled($this->get('recaptcha_site_key')) && filled($this->get('recaptcha_secret')) ? 'recaptcha' : null,
            default => null,
        };
    }

    public function siteKey(): string
    {
        return (string) match ($this->captchaProvider()) {
            'turnstile' => $this->get('turnstile_site_key'),
            'recaptcha' => $this->get('recaptcha_site_key'),
            default => '',
        };
    }

    public function protectsLogin(): bool
    {
        return (bool) $this->get('protect_login') && $this->captchaProvider() !== null;
    }

    /**
     * Null when the visitor passed (or no CAPTCHA is on), otherwise the
     * sentence to show them.
     */
    public function captchaError(Request $request): ?string
    {
        $provider = $this->captchaProvider();

        if (! $provider) {
            return null;
        }

        $token = (string) $request->input($provider === 'turnstile' ? 'cf-turnstile-response' : 'g-recaptcha-response');

        if ($token === '') {
            return 'Please complete the "not a robot" check and send it again.';
        }

        $result = $this->verify($provider, $token, $request->ip());

        return $result['passed'] ? null : $result['message'];
    }

    /** @return array{passed: bool, message: string} */
    private function verify(string $provider, string $token, ?string $ip): array
    {
        $secret = $provider === 'turnstile' ? $this->get('turnstile_secret') : $this->get('recaptcha_secret');
        $url = $provider === 'turnstile'
            ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            : 'https://www.google.com/recaptcha/api/siteverify';

        try {
            $body = Http::asForm()->timeout(8)->post($url, [
                'secret' => $secret,
                'response' => $token,
                'remoteip' => $ip,
            ])->json();
        } catch (Throwable $e) {
            // Fail closed: an unverified post is not let through. Logged so
            // an outage on the provider's side is visible.
            Log::warning('CAPTCHA verification unreachable', ['provider' => $provider, 'error' => $e->getMessage()]);

            return ['passed' => false, 'message' => 'The "not a robot" check could not be reached. Please try again in a moment.'];
        }

        $passed = (bool) ($body['success'] ?? false);

        if ($passed && $provider === 'recaptcha') {
            $passed = (float) ($body['score'] ?? 0) >= ((int) $this->get('recaptcha_min_score')) / 100;
        }

        return ['passed' => $passed, 'message' => 'We could not confirm you are a person. Please try again.'];
    }

    /**
     * Is this secret the right one? Asked with a dummy token.
     *
     * Cloudflare checks the secret first: "invalid-input-secret" means it is
     * wrong, anything else means it is right. Google checks the token first
     * and so cannot confirm a secret without a real visitor's token — for
     * reCAPTCHA the answer is only "not rejected" (ok: null), never "valid".
     *
     * @return array{ok: bool|null, message: string}
     */
    public function checkSecret(string $provider, string $secret): array
    {
        $url = $provider === 'turnstile'
            ? 'https://challenges.cloudflare.com/turnstile/v0/siteverify'
            : 'https://www.google.com/recaptcha/api/siteverify';

        try {
            $body = Http::asForm()->timeout(8)->post($url, ['secret' => $secret, 'response' => 'key-check'])->json();
        } catch (Throwable) {
            return ['ok' => false, 'message' => 'Could not reach '.($provider === 'turnstile' ? 'Cloudflare' : 'Google').' to check the key.'];
        }

        $codes = $body['error-codes'] ?? [];

        if (in_array('invalid-input-secret', $codes, true) || in_array('missing-input-secret', $codes, true)) {
            return ['ok' => false, 'message' => 'That secret key was rejected. Copy it again from the '.($provider === 'turnstile' ? 'Cloudflare' : 'reCAPTCHA').' dashboard.'];
        }

        if ($provider === 'recaptcha') {
            return ['ok' => null, 'message' => 'Google did not reject it, but only confirms a secret with a real visitor\'s token. Send a form on the site to be sure.'];
        }

        return ['ok' => true, 'message' => 'The secret key is valid.'];
    }

    // ── the tally ──────────────────────────────────────────────────────────

    /** Counted per month and reason, for the Spam protection tab. */
    public function record(string $reason): void
    {
        try {
            $key = 'spam.blocked.'.now()->format('Y-m');
            $counts = Cache::get($key, []);
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
            Cache::put($key, $counts, now()->addMonths(2));
        } catch (Throwable) {
            // Counting must never be why a form fails.
        }

        Log::info('Spam submission dropped', ['reason' => $reason]);
    }

    /** @return array<string, int> */
    public function thisMonth(): array
    {
        return Cache::get('spam.blocked.'.now()->format('Y-m'), []);
    }
}
