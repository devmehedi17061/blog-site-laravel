<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * A page's or post's own CSS and JavaScript (the editor's Code tab), and the
 * site-wide external files (Settings → Front end).
 *
 * CSS only styles; JavaScript runs in every visitor's browser. So, as with
 * the site-wide head and body code, only a Super Admin may add or change
 * JavaScript — anyone else may still save the page, as long as the script
 * they send back is the one already there.
 */
class CustomCode
{
    public const MAX = 100000;

    /** @return array<string, array<int, string>> */
    public static function rules(): array
    {
        return [
            'custom_css' => ['nullable', 'string', 'max:'.self::MAX],
            'custom_js' => ['nullable', 'string', 'max:'.self::MAX],
        ];
    }

    /** For a form request's withValidator(): refuses script changes from anyone but a Super Admin. */
    public static function guard(Validator $validator, FormRequest $request, ?Model $existing = null): void
    {
        $validator->after(function (Validator $validator) use ($request, $existing) {
            if (! $request->has('custom_js') || $request->user()?->isSuperAdmin()) {
                return;
            }

            $sent = trim((string) $request->input('custom_js'));
            $stored = trim((string) ($existing?->custom_js ?? ''));

            if ($sent !== $stored) {
                $validator->errors()->add('custom_js', 'Only a Super Admin can add or change JavaScript.');
            }
        });
    }

    /** CSS made safe to sit inside a <style> element. */
    public static function css(?string $css): string
    {
        return str_ireplace('</style', '<\/style', trim((string) $css));
    }

    /** JavaScript made safe to sit inside a <script> element. */
    public static function js(?string $js): string
    {
        return str_ireplace(['</script', '<!--'], ['<\/script', '<\!--'], trim((string) $js));
    }

    // ── site-wide external files ───────────────────────────────────────────

    /**
     * The external files list from Settings → Front end, each checked:
     * { type: css|js, url, place: head|body, load: normal|async|defer, note }.
     *
     * @return array<int, array{type: string, url: string, place: string, load: string, note: string}>
     */
    public static function externals(mixed $list): array
    {
        $out = [];

        foreach (array_values(is_array($list) ? $list : []) as $item) {
            if (! is_array($item)) {
                continue;
            }

            $url = trim((string) ($item['url'] ?? ''));
            // Web addresses only, and protocol-relative ones (//cdn…).
            if (! preg_match('~^(https?:)?//[^\s"\'<>]+$~i', $url)) {
                continue;
            }

            $type = ($item['type'] ?? '') === 'js' ? 'js' : 'css';
            $out[] = [
                'type' => $type,
                'url' => $url,
                'place' => $type === 'js' && ($item['place'] ?? '') === 'body' ? 'body' : 'head',
                'load' => $type === 'js' && in_array($item['load'] ?? '', ['async', 'defer'], true) ? $item['load'] : 'normal',
                'note' => mb_substr(trim((string) ($item['note'] ?? '')), 0, 80),
            ];
        }

        return array_slice($out, 0, 30);
    }
}
