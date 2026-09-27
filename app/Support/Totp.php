<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Time-based one-time passwords (RFC 6238) — the 6-digit codes Google
 * Authenticator, Microsoft Authenticator, Authy and the rest show.
 *
 * SHA-1, 6 digits, 30-second steps: the defaults every authenticator app
 * assumes when the QR code does not say otherwise.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const STEP = 30;

    private const DIGITS = 6;

    /** 160 random bits, base32 — what the apps expect to be given. */
    public static function newSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    public static function code(string $secret, ?int $timestamp = null): string
    {
        return self::codeAt($secret, intdiv($timestamp ?? time(), self::STEP));
    }

    /**
     * Checks a typed code, allowing one step either side for a phone clock
     * that is a little out. A code is accepted once only: the step it
     * matched is remembered per user, so a code seen over someone's shoulder
     * cannot be replayed within its 30 seconds.
     */
    public static function verify(string $secret, string $code, ?string $replayKey = null, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\s+/', '', $code);

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', (string) $code)) {
            return false;
        }

        $now = intdiv($timestamp ?? time(), self::STEP);

        foreach ([0, -1, 1] as $offset) {
            $step = $now + $offset;

            if (! hash_equals(self::codeAt($secret, $step), $code)) {
                continue;
            }

            if ($replayKey !== null) {
                $last = (int) Cache::get('totp-used:'.$replayKey, 0);

                if ($step <= $last) {
                    return false;
                }

                Cache::put('totp-used:'.$replayKey, $step, now()->addMinutes(5));
            }

            return true;
        }

        return false;
    }

    /** The otpauth:// address the QR code carries. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        $label = rawurlencode($issuer).':'.rawurlencode($account);

        return 'otpauth://totp/'.$label.'?'.http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::STEP,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    private static function codeAt(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;

        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private static function base32Decode(string $text): string
    {
        $text = strtoupper(preg_replace('/[\s=]/', '', $text));
        $bits = '';

        foreach (str_split($text) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index === false) {
                continue;
            }
            $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
