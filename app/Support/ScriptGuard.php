<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Validation\Validator;

/**
 * Who may put code that runs in a visitor's browser into raw HTML: a post's
 * body, a blank section, a library section's template or a rich-text value,
 * the header and footer.
 *
 * A <script>, an on…= event handler, a javascript: link, an <iframe> /
 * <object> / <embed> / <base>, or Alpine code (x-data, x-init, @click …)
 * runs as whoever looks at the page — a Super Admin included — so only a
 * Super Admin may add one. Everyone else can still edit and save HTML that
 * already holds them: only what is *new* compared with the saved version is
 * refused. This is the rule CustomCode applies to the Code tab's JavaScript.
 */
class ScriptGuard
{
    private const PATTERNS = [
        // A script element, whole.
        '#<script\b[^>]*>.*?</script\s*>#is',
        '#<script\b[^>]*>#i',
        // Elements that load or redirect to other code.
        '#<(iframe|frame|object|embed|applet|base|form)\b[^>]*>#i',
        '#<meta\b[^>]*http-equiv[^>]*>#i',
        // on…= event handlers.
        '#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i',
        // javascript: / vbscript: / data:text/html addresses.
        '#(javascript|vbscript)\s*:[^"\'>\s]*#i',
        '#data\s*:\s*text/html[^"\'>\s]*#i',
        // Alpine (and Vue-style) code in attributes.
        '#\s(x-[a-z][\w:.-]*|@[a-z][\w:.-]*|:[a-z][\w.-]*)\s*=\s*("[^"]*"|\'[^\']*\')#i',
        // srcdoc runs a whole document.
        '#\ssrcdoc\s*=\s*("[^"]*"|\'[^\']*\')#i',
    ];

    /**
     * The pieces of $html that would run code, each once per occurrence,
     * normalised so whitespace changes alone are not "new".
     *
     * @return array<string, int> fragment => how many times
     */
    public static function fragments(string $html): array
    {
        $found = [];
        foreach (self::PATTERNS as $pattern) {
            if (preg_match_all($pattern, $html, $m)) {
                foreach ($m[0] as $piece) {
                    $key = strtolower(preg_replace('/\s+/', ' ', trim($piece)));
                    $found[$key] = ($found[$key] ?? 0) + 1;
                }
            }
        }

        return $found;
    }

    /** What $sent holds that $stored did not. @return array<int, string> */
    public static function added(string $sent, string $stored): array
    {
        $before = self::fragments($stored);
        $new = [];
        foreach (self::fragments($sent) as $piece => $count) {
            if ($count > ($before[$piece] ?? 0)) {
                $new[] = $piece;
            }
        }

        return $new;
    }

    /** Every string anywhere in a value (section values are nested arrays). */
    public static function flatten(mixed $value): string
    {
        if (is_array($value)) {
            return implode("\n", array_map([self::class, 'flatten'], $value));
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * For a request's validator: refuses newly added code from anyone but a
     * Super Admin. $sent and $stored are whole HTML strings (join several).
     */
    public static function guard(Validator $validator, ?User $user, string $field, string $sent, string $stored): void
    {
        $validator->after(function (Validator $validator) use ($user, $field, $sent, $stored) {
            if ($user?->isSuperAdmin()) {
                return;
            }

            $added = self::added($sent, $stored);
            if ($added) {
                $validator->errors()->add($field, 'Only a Super Admin can add scripts, event handlers, embeds or Alpine code (found: '
                    .\Illuminate\Support\Str::limit($added[0], 80).'). Remove it, or ask a Super Admin to add it.');
            }
        });
    }
}
