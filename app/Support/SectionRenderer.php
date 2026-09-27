<?php

namespace App\Support;

use App\Models\Page;
use App\Models\Section;
use Illuminate\Support\Collection;

/**
 * Turns a section into the HTML, CSS and inline style a page prints.
 *
 *   {{ key }}                 a field's value, escaped by its type
 *   {{#group}} … {{/group}}   the block repeated once per item in that group,
 *                             with the item's own fields in scope
 *
 * Two sets of tokens come from outside the fields:
 *
 *   {{ page_title }}, {{ page_url }}   the page being rendered, so a section
 *                                      like a breadcrumb follows the page it
 *                                      sits on instead of being retyped
 *   {{ _number }}, {{ _index }}        which item of a group this is, 1- and
 *                                      0-based, available inside the block
 *
 * A field of the same name always wins, so adding one of these can never
 * change what an existing section renders.
 *
 * The CSS is rewritten so every rule is prefixed with the section's id, which
 * is what keeps one section's styles away from the rest of the page.
 */
class SectionRenderer
{
    private const TOKEN = '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/';

    /** Non-greedy so two groups in a row do not swallow each other. */
    private const GROUP = '/\{\{#\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}(.*?)\{\{\/\s*\1\s*\}\}/s';

    /** A `form` field resolves through this, which owns the form's markup. */
    public function __construct(private readonly FormRenderer $forms) {}

    public function html(Section $section, ?Page $page = null): string
    {
        $template = $section->sourceHtml();

        if (! $section->isLibrary()) {
            return $template;
        }

        return $this->renderBlock(
            $template,
            $section->values ?? [],
            $this->typeMap($section->fieldDefinitions()),
            '',
            $this->pageTokens($page)
        );
    }

    /**
     * What the page contributes. Escaped here, because it is merged in after
     * the field values have been escaped by type.
     *
     * @return array<string, string>
     */
    private function pageTokens(?Page $page): array
    {
        if (! $page) {
            return [];
        }

        return [
            'page_title' => e((string) $page->title),
            'page_url' => e('/'.ltrim((string) $page->slug, '/')),
        ];
    }

    public function css(Section $section): string
    {
        $css = trim($section->sourceCss());

        if ($css === '') {
            return '';
        }

        if ($section->isLibrary()) {
            // Tokens work in CSS too, so a colour field can drive a rule.
            $css = $this->fill($css, $this->cssValues($section));
        }

        return $this->scope($css, '#section-'.$section->id);
    }

    /**
     * The outer wrapper's inline style: the band that runs the full width of
     * the page, so a background colour or image reaches both edges.
     *
     * Every entry is free text the author typed, so it is only stripped of the
     * characters that could break out of the attribute or inject another rule.
     */
    public function inlineStyle(Section $section): string
    {
        $style = $section->style ?? [];

        $out = $this->declarationsFor($style, [
            'background' => 'background',
            'color' => 'color',
        ]);

        // Written as longhand, not as the `background` shorthand, so a colour
        // set above still shows through a transparent image or behind one that
        // does not cover.
        if (filled($style['background_image'] ?? null)) {
            $out[] = 'background-image:url("'.$this->safeCssUrl((string) $style['background_image']).'")';
            $out[] = 'background-size:'.$this->safeCssValue((string) ($style['background_size'] ?? '') ?: 'cover');
            $out[] = 'background-position:'.$this->safeCssValue((string) ($style['background_position'] ?? '') ?: 'center');
            $out[] = 'background-repeat:'.$this->safeCssValue((string) ($style['background_repeat'] ?? '') ?: 'no-repeat');
        }

        $out = array_merge($out, $this->declarationsFor($style, [
            'padding' => 'padding',
            'margin' => 'margin',
            'border_radius' => 'border-radius',
        ]));

        if (filled($style['custom'] ?? null)) {
            $out[] = $this->freeDeclarations((string) $style['custom']);
        }

        return implode(';', array_filter($out));
    }

    /**
     * The inner wrapper's inline style: the column the words sit in.
     *
     * This is what makes the common layout work — a background that runs edge
     * to edge with the content held at a readable width in the middle. The max
     * width belongs here rather than on the band, or it would crop the
     * background with it.
     */
    public function contentStyle(Section $section): string
    {
        $style = $section->style ?? [];
        $out = [];

        if (filled($style['max_width'] ?? null)) {
            $out[] = 'max-width:'.$this->safeCssValue((string) $style['max_width']);
            $out[] = 'margin-left:auto';
            $out[] = 'margin-right:auto';
        }

        if (filled($style['align'] ?? null)) {
            $out[] = 'text-align:'.$this->safeCssValue((string) $style['align']);
        }

        if (filled($style['content_custom'] ?? null)) {
            $out[] = $this->freeDeclarations((string) $style['content_custom']);
        }

        return implode(';', array_filter($out));
    }

    /** @param array<string, string> $map style key => css property */
    private function declarationsFor(array $style, array $map): array
    {
        return Collection::make($map)
            ->map(fn (string $property, string $key) => [$property, $style[$key] ?? null])
            ->filter(fn (array $pair) => filled($pair[1]))
            ->map(fn (array $pair) => $pair[0].':'.$this->safeCssValue((string) $pair[1]))
            ->values()
            ->all();
    }

    /**
     * A block of declarations the author wrote themselves — `display:flex;
     * gap:24px`, and anything else CSS accepts. Written over several lines in
     * the editor, so the whitespace is collapsed for the attribute.
     */
    private function freeDeclarations(string $value): string
    {
        $collapsed = preg_replace('/\s+/', ' ', $this->safeCssValue($value));

        return trim((string) $collapsed, '; ');
    }

    /** Breakpoint visibility cannot be inline, so it becomes a scoped rule. */
    public function visibilityCss(Section $section): string
    {
        $style = $section->style ?? [];
        $id = '#section-'.$section->id;
        $rules = '';

        if (! empty($style['hide_mobile'])) {
            $rules .= '@media (max-width:640px){'.$id.'{display:none !important}}';
        }

        if (! empty($style['hide_desktop'])) {
            $rules .= '@media (min-width:641px){'.$id.'{display:none !important}}';
        }

        return $rules;
    }

    // ── any template: the header & footer builder uses these too ──────────

    /**
     * Renders a template with the section engine: its fields' values, its
     * repeated groups, and $globals — ready-made HTML the caller vouches for
     * (the site's logo, menu, search box …). A field of the same name wins.
     */
    public function renderTemplate(string $html, array $values, array $fields, array $globals = []): string
    {
        return $this->renderBlock($html, $values, $this->typeMap($fields), '', $globals);
    }

    /** A template's CSS, its field tokens filled in and every rule put under $prefix. */
    public function templateCss(string $css, string $prefix, array $values = []): string
    {
        $css = trim($css);

        if ($css === '') {
            return '';
        }

        $safe = Collection::make($values)
            ->reject(fn ($value) => is_array($value))
            ->map(fn ($value) => $this->safeCssValue((string) $value))
            ->all();

        return $this->scope($this->fill($css, $safe), $prefix);
    }

    // ── template rendering ─────────────────────────────────────────────────

    /**
     * Renders one block: groups first, so a token inside a group is resolved
     * against that item rather than the block it sits in.
     *
     * $globals carries the page tokens down every level, and gains the item
     * counters inside a group. Local values are merged over it, so a field
     * always beats a token of the same name.
     */
    private function renderBlock(
        string $template,
        array $scope,
        array $types,
        string $prefix = '',
        array $globals = []
    ): string {
        $withGroups = preg_replace_callback(
            self::GROUP,
            function (array $match) use ($scope, $types, $prefix, $globals) {
                $key = $match[1];
                $inner = $match[2];
                $items = $scope[$key] ?? [];

                if (! is_array($items)) {
                    return '';
                }

                $childPrefix = $prefix === '' ? $key : $prefix.'.'.$key;

                return Collection::make(array_values($items))
                    ->map(fn ($item, $index) => $this->renderBlock(
                        $inner,
                        is_array($item) ? $item : [],
                        $types,
                        $childPrefix,
                        // Merged, not `+`: an inner group's counter has to
                        // replace the outer one, and `+` keeps the left side.
                        array_merge($globals, [
                            '_index' => (string) $index,
                            '_number' => (string) ($index + 1),
                        ])
                    ))
                    ->implode('');
            },
            $template
        ) ?? '';

        return $this->fill($withGroups, array_merge($globals, $this->values($scope, $types, $prefix)));
    }

    private function fill(string $source, array $values): string
    {
        return preg_replace_callback(
            self::TOKEN,
            fn (array $match) => $values[$match[1]] ?? '',
            $source
        ) ?? '';
    }

    /** Escapes each value in scope according to the field type behind it. */
    private function values(array $scope, array $types, string $prefix = ''): array
    {
        $out = [];

        foreach ($scope as $key => $value) {
            if (is_array($value)) {
                continue; // groups are handled before tokens
            }

            $path = $prefix === '' ? $key : $prefix.'.'.$key;
            $type = $types[$path] ?? $types[$key] ?? 'text';

            $out[$key] = match ($type) {
                // Authored as HTML on purpose.
                'richtext' => (string) $value,
                // The value is which form to show; the markup comes from the
                // form builder, already escaped by its own view.
                'form' => $this->forms->render($this->forms->find($value)),
                // Everything else is going into a text node or an attribute.
                default => e((string) $value),
            };
        }

        return $out;
    }

    /**
     * Values for use inside CSS. HTML escaping would corrupt them here
     * (&amp; is not a colour), so anything that could break out of a
     * declaration is stripped instead. Only top-level fields are offered —
     * a repeated group has no single value a stylesheet could use.
     */
    public function cssValues(Section $section): array
    {
        return Collection::make($section->values ?? [])
            ->reject(fn ($value) => is_array($value))
            ->map(fn ($value) => $this->safeCssValue((string) $value))
            ->all();
    }

    private function safeCssValue(string $value): string
    {
        return trim(preg_replace('/[{}<>]/', '', $value));
    }

    /**
     * A url going inside `url("…")`. The quote and the closing bracket would
     * end the function early, which is how a stray value turns into a second
     * declaration, so both go — along with the whitespace a url never has.
     */
    private function safeCssUrl(string $value): string
    {
        return trim(preg_replace('/["\'()\\\\{}<>\s]/', '', $value));
    }

    /** @return array<string, string> field path => type */
    private function typeMap(array $definitions, string $prefix = ''): array
    {
        $map = [];

        foreach ($definitions as $field) {
            $key = $field['key'] ?? null;

            if (! $key) {
                continue;
            }

            $path = $prefix === '' ? $key : $prefix.'.'.$key;

            if (($field['type'] ?? 'text') === 'group') {
                $map = array_merge($map, $this->typeMap($field['fields'] ?? [], $path));

                continue;
            }

            $map[$path] = $field['type'] ?? 'text';
        }

        return $map;
    }

    // ── css scoping ────────────────────────────────────────────────────────

    private function scope(string $css, string $prefix): string
    {
        // Comments go first: one sitting before a rule would otherwise be
        // read as part of its selector — and a comma in it would split it.
        $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);

        $out = '';
        $offset = 0;
        $length = strlen($css);

        while ($offset < $length) {
            $braceAt = strpos($css, '{', $offset);

            if ($braceAt === false) {
                break;
            }

            $selector = trim(substr($css, $offset, $braceAt - $offset));
            $block = $this->readBlock($css, $braceAt);

            if ($block === null) {
                break;
            }

            [$body, $after] = $block;

            if (str_starts_with($selector, '@')) {
                // At-rules that wrap other rules need their contents scoped;
                // @font-face and @keyframes do not.
                $out .= $this->wrapsRules($selector)
                    ? $selector.'{'.$this->scope($body, $prefix).'}'
                    : $selector.'{'.$body.'}';
            } else {
                $out .= $this->prefixSelector($selector, $prefix).'{'.$body.'}';
            }

            $offset = $after;
        }

        return $out;
    }

    /** @return array{0: string, 1: int}|null the block body and the offset after it */
    private function readBlock(string $css, int $openAt): ?array
    {
        $depth = 0;
        $length = strlen($css);

        for ($i = $openAt; $i < $length; $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return [substr($css, $openAt + 1, $i - $openAt - 1), $i + 1];
                }
            }
        }

        return null;
    }

    private function wrapsRules(string $atRule): bool
    {
        return (bool) preg_match('/^@(media|supports|layer|container|scope)\b/i', $atRule);
    }

    private function prefixSelector(string $selector, string $prefix): string
    {
        return Collection::make(explode(',', $selector))
            ->map(fn (string $part) => trim($part))
            ->filter()
            ->map(function (string $part) use ($prefix) {
                // `:root`, `html` and `body` mean "this section" here.
                if (preg_match('/^(:root|html|body)$/i', $part)) {
                    return $prefix;
                }

                // …and `:root.menu-open .nav` means "this section while it
                // carries that class" — how a header styles its open menu.
                if (preg_match('/^(:root|html|body)(?=[.:\[#])/i', $part)) {
                    return preg_replace('/^(:root|html|body)/i', $prefix, $part);
                }

                return $prefix.' '.$part;
            })
            ->implode(',');
    }
}
