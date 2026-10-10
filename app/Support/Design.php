<?php

namespace App\Support;

/**
 * Settings → Design: the site's colours, fonts and widths.
 *
 * The public stylesheet is built on CSS custom properties (core/tokens.css,
 * site/layout.css), so a design setting is simply a property overridden on :root.
 * css() prints those overrides after the stylesheet; every page, section,
 * header and footer that uses the properties follows. The admin panel keeps
 * its own look — only the public layout (and the builders' previews) print
 * this.
 */
class Design
{
    /**
     * Fonts offered, all from Google Fonts except the system stack. Grouped
     * for the picker; the Bengali ones cover বাংলা text properly.
     *
     * @var array<string, array{label: string, group: string, stack: string, google: ?string}>
     */
    public const FONTS = [
        'Archivo' => ['label' => 'Archivo', 'group' => 'Sans-serif', 'stack' => '"Archivo", system-ui, sans-serif', 'google' => 'Archivo:wght@400;600;700;800'],
        'Inter' => ['label' => 'Inter', 'group' => 'Sans-serif', 'stack' => '"Inter", system-ui, sans-serif', 'google' => 'Inter:wght@400;600;700;800'],
        'Roboto' => ['label' => 'Roboto', 'group' => 'Sans-serif', 'stack' => '"Roboto", system-ui, sans-serif', 'google' => 'Roboto:wght@400;500;700;900'],
        'Open Sans' => ['label' => 'Open Sans', 'group' => 'Sans-serif', 'stack' => '"Open Sans", system-ui, sans-serif', 'google' => 'Open+Sans:wght@400;600;700;800'],
        'Lato' => ['label' => 'Lato', 'group' => 'Sans-serif', 'stack' => '"Lato", system-ui, sans-serif', 'google' => 'Lato:wght@400;700;900'],
        'Poppins' => ['label' => 'Poppins', 'group' => 'Sans-serif', 'stack' => '"Poppins", system-ui, sans-serif', 'google' => 'Poppins:wght@400;600;700;800'],
        'Montserrat' => ['label' => 'Montserrat', 'group' => 'Sans-serif', 'stack' => '"Montserrat", system-ui, sans-serif', 'google' => 'Montserrat:wght@400;600;700;800'],
        'Nunito' => ['label' => 'Nunito', 'group' => 'Sans-serif', 'stack' => '"Nunito", system-ui, sans-serif', 'google' => 'Nunito:wght@400;600;700;800'],
        'DM Sans' => ['label' => 'DM Sans', 'group' => 'Sans-serif', 'stack' => '"DM Sans", system-ui, sans-serif', 'google' => 'DM+Sans:wght@400;600;700;800'],
        'Work Sans' => ['label' => 'Work Sans', 'group' => 'Sans-serif', 'stack' => '"Work Sans", system-ui, sans-serif', 'google' => 'Work+Sans:wght@400;600;700;800'],
        'Merriweather' => ['label' => 'Merriweather', 'group' => 'Serif', 'stack' => '"Merriweather", Georgia, serif', 'google' => 'Merriweather:wght@400;700;900'],
        'Lora' => ['label' => 'Lora', 'group' => 'Serif', 'stack' => '"Lora", Georgia, serif', 'google' => 'Lora:wght@400;600;700'],
        'Playfair Display' => ['label' => 'Playfair Display', 'group' => 'Serif', 'stack' => '"Playfair Display", Georgia, serif', 'google' => 'Playfair+Display:wght@400;600;700;800'],
        'Hind Siliguri' => ['label' => 'Hind Siliguri (বাংলা)', 'group' => 'Bengali', 'stack' => '"Hind Siliguri", system-ui, sans-serif', 'google' => 'Hind+Siliguri:wght@400;500;600;700'],
        'Noto Sans Bengali' => ['label' => 'Noto Sans Bengali (বাংলা)', 'group' => 'Bengali', 'stack' => '"Noto Sans Bengali", system-ui, sans-serif', 'google' => 'Noto+Sans+Bengali:wght@400;600;700;800'],
        'Noto Serif Bengali' => ['label' => 'Noto Serif Bengali (বাংলা)', 'group' => 'Bengali', 'stack' => '"Noto Serif Bengali", Georgia, serif', 'google' => 'Noto+Serif+Bengali:wght@400;600;700;800'],
        'Tiro Bangla' => ['label' => 'Tiro Bangla (বাংলা)', 'group' => 'Bengali', 'stack' => '"Tiro Bangla", Georgia, serif', 'google' => 'Tiro+Bangla'],
        'system' => ['label' => 'System font (no download)', 'group' => 'Other', 'stack' => 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif', 'google' => null],
    ];

    /** The limits each number is held to — the settings form uses them too. */
    public const LIMITS = [
        'site_width' => [760, 1600],
        'content_width' => [520, 1000],
        'text_size' => [14, 22],
        'radius' => [0, 24],
    ];

    public function __construct(private readonly SiteSettings $settings) {}

    /** @return array<string, mixed> the design settings, each checked */
    public function values(): array
    {
        $s = $this->settings;
        $int = fn (string $key, int $default) => max(self::LIMITS[$key][0], min(self::LIMITS[$key][1], (int) ($s->get('design.'.$key) ?: $default)));

        return [
            'primary' => self::color($s->get('design.primary'), '#ec3013'),
            'text' => self::color($s->get('design.text'), '#1c1a19'),
            'background' => self::color($s->get('design.background'), '#f2f1f1'),
            'body_font' => array_key_exists((string) $s->get('design.body_font'), self::FONTS) ? $s->get('design.body_font') : 'Archivo',
            'heading_font' => array_key_exists((string) $s->get('design.heading_font'), self::FONTS) ? $s->get('design.heading_font') : 'Archivo',
            'site_width' => $int('site_width', 1100),
            'content_width' => $int('content_width', 720),
            'text_size' => $int('text_size', 16),
            'radius' => max(0, min(24, (int) $s->get('design.radius'))),
        ];
    }

    /** The Google Fonts stylesheet for the fonts in use, or null for system fonts only. */
    /**
     * Bangla text, and the Taka sign (৳), come from this font whatever font
     * is chosen: the Latin fonts on offer have no Bengali letters, and the
     * browser's own fallback looks out of place beside them.
     */
    public const BANGLA_FONT = 'Hind Siliguri';

    public function fontsUrl(?array $values = null): ?string
    {
        $v = $values ?? $this->values();
        $families = collect([$v['body_font'], $v['heading_font']])
            ->unique()
            ->map(fn ($font) => self::FONTS[$font]['google'])
            ->push('Hind+Siliguri:wght@400;500;600;700')
            ->filter();

        return $families->isEmpty()
            ? null
            : 'https://fonts.googleapis.com/css2?'.$families->map(fn ($f) => 'family='.$f)->implode('&').'&display=swap';
    }

    /** The :root overrides the public layout prints after its stylesheet. */
    /** "Inter", system-ui, sans-serif → "Inter", "Hind Siliguri", system-ui, sans-serif */
    public static function withBangla(string $stack): string
    {
        $parts = array_map('trim', explode(',', $stack));
        array_splice($parts, 1, 0, ['"'.self::BANGLA_FONT.'"']);

        return implode(', ', $parts);
    }

    public function css(?array $values = null): string
    {
        $v = $values ?? $this->values();
        $p = $v['primary'];

        $vars = [
            '--color-accent' => $p,
            // The shades the site uses for tints, hovers and dark text on a
            // tint, worked out from the one colour chosen.
            '--color-accent-100' => self::mix($p, '#ffffff', 0.08),
            '--color-accent-200' => self::mix($p, '#ffffff', 0.18),
            '--color-accent-300' => self::mix($p, '#ffffff', 0.34),
            '--color-accent-400' => self::mix($p, '#ffffff', 0.56),
            '--color-accent-500' => self::mix($p, '#ffffff', 0.82),
            '--color-accent-600' => self::mix($p, '#000000', 0.9),
            '--color-accent-700' => self::mix($p, '#000000', 0.72),
            '--color-accent-800' => self::mix($p, '#000000', 0.52),
            '--color-accent-900' => self::mix($p, '#000000', 0.32),
            '--color-ink' => $v['text'],
            '--color-bg' => $v['background'],
            '--font-sans' => self::withBangla(self::FONTS[$v['body_font']]['stack']),
            '--font-heading' => self::withBangla(self::FONTS[$v['heading_font']]['stack']),
            '--site-width' => $v['site_width'].'px',
            '--content-width' => $v['content_width'].'px',
            '--site-text-size' => $v['text_size'].'px',
            '--site-radius' => $v['radius'].'px',
        ];

        return ':root{'.collect($vars)->map(fn ($value, $name) => $name.':'.$value)->implode(';').'}';
    }

    // ── colour helpers ─────────────────────────────────────────────────────

    public static function color(mixed $value, string $default): string
    {
        if (! is_string($value)) {
            return $default;
        }

        $value = strtolower(trim($value));

        // #abc → #aabbcc, so the maths below always has six digits.
        if (preg_match('/^#([0-9a-f])([0-9a-f])([0-9a-f])$/', $value, $m)) {
            return '#'.$m[1].$m[1].$m[2].$m[2].$m[3].$m[3];
        }

        return preg_match('/^#[0-9a-f]{6}$/', $value) ? $value : $default;
    }

    /** $weight of $color over $base: 1 = all $color, 0 = all $base. */
    public static function mix(string $color, string $base, float $weight): string
    {
        [$r1, $g1, $b1] = sscanf($color, '#%02x%02x%02x');
        [$r2, $g2, $b2] = sscanf($base, '#%02x%02x%02x');

        return sprintf('#%02x%02x%02x',
            (int) round($r1 * $weight + $r2 * (1 - $weight)),
            (int) round($g1 * $weight + $g2 * (1 - $weight)),
            (int) round($b1 * $weight + $b2 * (1 - $weight)),
        );
    }
}
