<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Field keys become {{ tokens }}, so they have to be safe identifiers and
 * unique within their level — a duplicate would silently shadow its twin.
 */
class FieldKeys
{
    /** @param array<int, array<string, mixed>> $fields */
    public static function normalise(array $fields, bool $allowGroups = true): array
    {
        $out = [];
        $taken = [];

        foreach (array_values($fields) as $index => $field) {
            $key = self::key($field['key'] ?? '', $index, $taken);
            $taken[] = $key;

            $type = $field['type'] ?? 'text';

            // Groups nest one level deep; a group inside a group becomes text.
            if ($type === 'group' && ! $allowGroups) {
                $type = 'text';
            }

            $node = [
                'key' => $key,
                'label' => $field['label'] ?? Str::headline($key),
                'type' => $type,
            ];

            // What a page starts with when the section is added to it. Only
            // kept where it means something — a group's starting value is an
            // empty list, not a string.
            if ($type !== 'group' && filled($field['default'] ?? null)) {
                $node['default'] = (string) $field['default'];
            }

            // A choice: its options, written "yes, no" — kept tidy and unique.
            if ($type === 'select') {
                $options = collect(explode(',', (string) ($field['options'] ?? '')))
                    ->map(fn ($o) => trim($o))->filter(fn ($o) => $o !== '')->unique()->take(30)->values();
                $node['options'] = $options->implode(', ') ?: 'yes, no';
                if (! $options->contains($node['default'] ?? null)) {
                    $node['default'] = $options->first() ?? 'yes';
                }
            }

            if ($type === 'group') {
                $node['fields'] = self::normalise($field['fields'] ?? [], false);

                // The items a page starts with (e.g. three sample slides), so a
                // new section shows its shape. Plain strings only.
                $items = collect(is_array($field['items'] ?? null) ? $field['items'] : [])
                    ->filter(fn ($item) => is_array($item))
                    ->map(fn ($item) => collect($item)->filter(fn ($v) => is_scalar($v))->map(fn ($v) => (string) $v)->all())
                    ->take(12)->values()->all();
                if ($items) {
                    $node['items'] = $items;
                }
            }

            $out[] = $node;
        }

        return $out;
    }

    /**
     * One key at a time, for a list built elsewhere — a form's fields, whose
     * names become the keys of every stored submission. Falls back to the
     * label when no name was typed, which is what the builder usually sends.
     *
     * @param  array<int, string>  $taken  names already used on this form
     */
    public static function single(string $raw, string $label, int $index, array $taken): string
    {
        return self::key(trim($raw) !== '' ? $raw : $label, $index, $taken);
    }

    private static function key(string $raw, int $index, array $taken): string
    {
        $key = Str::snake(preg_replace('/[^a-zA-Z0-9_ ]/', '', $raw));
        $key = ltrim($key, '0123456789_');

        if ($key === '') {
            $key = 'field_'.($index + 1);
        }

        $candidate = $key;
        $suffix = 1;

        while (in_array($candidate, $taken, true)) {
            $suffix++;
            $candidate = $key.'_'.$suffix;
        }

        return $candidate;
    }
}
