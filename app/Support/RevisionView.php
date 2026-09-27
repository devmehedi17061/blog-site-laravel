<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Media;
use App\Models\SectionTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Turns a stored version into fields a person can read and compare: names
 * instead of ids, text instead of HTML, and a page's sections written out.
 */
class RevisionView
{
    /** @return array<int, array{key: string, label: string, kind: string, value: string}> */
    public static function fields(Model $model, array $data): array
    {
        $out = [];

        foreach ($model->revisionFields() as $key => [$label, $kind]) {
            $out[] = [
                'key' => $key,
                'label' => $label,
                'kind' => $kind,
                'value' => self::display($kind, $data[$key] ?? null),
            ];
        }

        return $out;
    }

    /** The labels of the fields that differ between two versions. */
    public static function changed(Model $model, array $before, array $after): array
    {
        $labels = [];

        foreach ($model->revisionFields() as $key => [$label]) {
            if (($before[$key] ?? null) != ($after[$key] ?? null)) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    private static function display(string $kind, mixed $value): string
    {
        if ($value === null || $value === '' || $value === []) {
            return '';
        }

        return match ($kind) {
            'html' => self::htmlToText((string) $value),
            'date' => Carbon::parse($value)->format('d M Y, H:i'),
            'category' => Category::whereKey($value)->value('name') ?? "Deleted category #{$value}",
            'user' => User::whereKey($value)->value('name') ?? "Deleted user #{$value}",
            'media' => Media::withTrashed()->whereKey($value)->value('name') ?? "Deleted file #{$value}",
            'sections' => self::sectionsToText((array) $value),
            default => (string) $value,
        };
    }

    /** Block elements become line breaks so a comparison lines up by paragraph. */
    private static function htmlToText(string $html): string
    {
        $html = preg_replace('/<(br|hr)\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/(p|h[1-6]|li|blockquote|pre|figure|figcaption|div|tr)>/i', "\n", $html);
        $html = preg_replace('/<img[^>]*alt="([^"]*)"[^>]*>/i', '[image: $1]', $html);

        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text);

        return trim(preg_replace("/\n\s*\n+/", "\n", $text));
    }

    private static function sectionsToText(array $sections): string
    {
        $templates = SectionTemplate::whereIn('id', collect($sections)->pluck('template_id')->filter())->pluck('name', 'id');
        $blocks = [];

        foreach (array_values($sections) as $i => $section) {
            $name = $section['name'] ?? 'Section';
            $lines = [($i + 1).'. '.$name];

            if (($section['mode'] ?? '') === 'Library') {
                $lines[0] .= ' — from "'.($templates[$section['template_id'] ?? 0] ?? 'a deleted library section').'"';

                foreach (self::flatten((array) ($section['values'] ?? [])) as $path => $value) {
                    $lines[] = "   {$path}: {$value}";
                }
            } else {
                $lines[0] .= ' — own HTML';
                $text = self::htmlToText((string) ($section['html'] ?? ''));

                if ($text !== '') {
                    $lines[] = '   '.str_replace("\n", "\n   ", $text);
                }
            }

            $blocks[] = implode("\n", $lines);
        }

        return implode("\n", $blocks);
    }

    /** {"cards":[{"title":"A"}]} → ["cards.1.title" => "A"] */
    private static function flatten(array $values, string $prefix = ''): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) (is_int($key) ? $key + 1 : $key) : $prefix.'.'.(is_int($key) ? $key + 1 : $key);

            if (is_array($value)) {
                $out += self::flatten($value, $path);
            } elseif ($value !== null && $value !== '') {
                $out[$path] = is_bool($value) ? ($value ? 'yes' : 'no') : self::htmlToText((string) $value);
            }
        }

        return $out;
    }
}
