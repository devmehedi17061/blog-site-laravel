<?php

namespace App\Support;

use App\Models\IpRule;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Settings → Security, applied: the password rule, who needs two-factor,
 * the admin IP rules and what counts as a local address.
 */
class Security
{
    public function __construct(private readonly SiteSettings $settings) {}

    public function get(string $key): mixed
    {
        return $this->settings->get('security.'.$key);
    }

    // ── passwords ──────────────────────────────────────────────────────────

    /** The rule every new password must pass: admins, users, their own. */
    public function passwordRule(): Password
    {
        $rule = Password::min(max(8, (int) $this->get('password_min')));

        if ($this->get('password_mixed_case')) {
            $rule->mixedCase();
        }
        if ($this->get('password_numbers')) {
            $rule->numbers();
        }
        if ($this->get('password_symbols')) {
            $rule->symbols();
        }
        if ($this->get('password_uncompromised')) {
            // Checks the Have I Been Pwned list without sending the password:
            // only the first five characters of its SHA-1 hash leave the server.
            $rule->uncompromised();
        }

        return $rule;
    }

    /** The rule in words, for the forms that ask for a password. */
    public function passwordHint(): string
    {
        $parts = ['at least '.max(8, (int) $this->get('password_min')).' characters'];

        if ($this->get('password_mixed_case')) {
            $parts[] = 'upper and lower case';
        }
        if ($this->get('password_numbers')) {
            $parts[] = 'a number';
        }
        if ($this->get('password_symbols')) {
            $parts[] = 'a symbol';
        }

        return ucfirst(implode(', ', $parts)).'.';
    }

    public function passwordExpired(User $user): bool
    {
        $days = (int) $this->get('password_expiry_days');

        return $days > 0 && $user->password_changed_at !== null
            && $user->password_changed_at->lt(now()->subDays($days));
    }

    // ── two-factor ─────────────────────────────────────────────────────────

    /** Whether this account's role is one that must use two-factor. */
    public function requiresTwoFactor(User $user): bool
    {
        $roles = array_map('intval', (array) $this->get('two_factor_roles'));

        return $user->role_id !== null && in_array((int) $user->role_id, $roles, true);
    }

    /** Email codes only work when the site can actually send mail. */
    public function emailCodesAvailable(): bool
    {
        return $this->settings->get('mail.provider') !== 'log';
    }

    // ── IP rules ───────────────────────────────────────────────────────────

    /** @return Collection<int, IpRule> */
    public function ipRules(): Collection
    {
        return IpRule::orderBy('type')->orderBy('id')->get();
    }

    /**
     * Whether this address may reach the admin panel under the given rules:
     * a block rule always wins; if there are any allow rules, only those
     * addresses get in; with none, everyone not blocked does.
     *
     * @param  iterable<IpRule|array{value: string, type: string}>  $rules
     */
    public static function ipAllowed(string $ip, iterable $rules): bool
    {
        $allow = [];

        foreach ($rules as $rule) {
            $value = is_array($rule) ? $rule['value'] : $rule->value;
            $type = is_array($rule) ? $rule['type'] : $rule->type;

            if ($type === 'block' && IpUtils::checkIp($ip, $value)) {
                return false;
            }

            if ($type === 'allow') {
                $allow[] = $value;
            }
        }

        return $allow === [] || IpUtils::checkIp($ip, $allow);
    }

    public function adminIpAllowed(string $ip): bool
    {
        return ! $this->get('ip_rules_enabled') || self::ipAllowed($ip, $this->ipRules());
    }

    /** An address or CIDR range IpUtils can match. */
    public static function validIpOrRange(string $value): bool
    {
        [$address, $bits] = array_pad(explode('/', $value, 2), 2, null);

        if (! filter_var($address, FILTER_VALIDATE_IP)) {
            return false;
        }

        if ($bits === null) {
            return true;
        }

        $max = str_contains($address, ':') ? 128 : 32;

        return ctype_digit($bits) && (int) $bits >= 0 && (int) $bits <= $max;
    }

    // ── HTTPS ──────────────────────────────────────────────────────────────

    /**
     * Development hosts. Forcing HTTPS on them would send the browser to an
     * https:// address no local server answers — the site would vanish.
     */
    public static function isLocalHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));

        return in_array($host, ['localhost', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.test');
    }
}
