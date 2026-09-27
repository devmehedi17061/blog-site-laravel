<?php

namespace App\Support;

/**
 * Two things live here:
 *
 *   librarySections() — the reusable sections shipped with the CMS. Each is
 *   a row in section_templates: HTML, CSS and a field definition tree.
 *
 *   all() — the starter page templates, which are just lists of instances
 *   pointing at those library sections with a page's starting values.
 */
class PageTemplates
{
    /** The sections seeded into the library. Keyed by slug. */
    public static function librarySections(): array
    {
        return [
            'common-hero' => [
                'name' => 'Common Hero',
                'description' => 'Heading, paragraph and a button beside an image.',
                'html' => implode("\n", [
                    '<div class="hero">',
                    '  <div class="hero-text">',
                    '    <p class="eyebrow">{{ subtitle }}</p>',
                    '    <h1>{{ title }}</h1>',
                    '    <div class="lead">{{ body }}</div>',
                    '    <a class="hero-btn" href="{{ button_url }}">{{ button_label }}</a>',
                    '  </div>',
                    '  <img class="hero-image" src="{{ image }}" alt="{{ image_alt }}">',
                    '</div>',
                ]),
                'css' => implode("\n", [
                    '.hero { display: grid; grid-template-columns: 1.1fr 1fr; align-items: center; gap: 40px; }',
                    '.eyebrow { margin: 0 0 10px; font-size: 12px; letter-spacing: .14em; text-transform: uppercase; color: #ae1800; }',
                    '.hero h1 { margin: 0 0 14px; font-size: 42px; line-height: 1.1; }',
                    '.lead { font-size: 17px; line-height: 1.7; color: #605d5d; }',
                    '.hero-btn { display: inline-block; margin-top: 22px; padding: 12px 22px; background: #ec3013;',
                    '  color: #fff; text-decoration: none; font-weight: 700; }',
                    '.hero-image { width: 100%; height: auto; display: block; }',
                    '.hero-image[src=""] { display: none; }',
                    '@media (max-width: 720px) { .hero { grid-template-columns: 1fr; } .hero h1 { font-size: 32px; } }',
                ]),
                'fields' => [
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text'],
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'body', 'label' => 'Paragraph', 'type' => 'richtext'],
                    ['key' => 'button_label', 'label' => 'Button label', 'type' => 'text'],
                    ['key' => 'button_url', 'label' => 'Button link', 'type' => 'url'],
                    ['key' => 'image', 'label' => 'Image', 'type' => 'media'],
                    ['key' => 'image_alt', 'label' => 'Image alt text', 'type' => 'text'],
                ],
            ],

            'breadcrumb' => [
                'name' => 'Breadcrumb',
                'description' => 'The trail back to the home page, with an optional page title.',
                'html' => implode("\n", [
                    '<nav class="crumbs" aria-label="Breadcrumb">',
                    '  <ol class="crumb-list">',
                    '    <li class="crumb-item"><a href="{{ home_url }}">{{ home_label }}</a></li>',
                    '    {{#trail}}',
                    '    <li class="crumb-item"><a href="{{ url }}">{{ label }}</a></li>',
                    '    {{/trail}}',
                    '    <li class="crumb-item"><span aria-current="page">{{ page_title }}</span></li>',
                    '  </ol>',
                    '</nav>',
                    '',
                    '<h1 class="crumb-title">{{ heading }}</h1>',
                    '<div class="crumb-lead">{{ lead }}</div>',
                ]),
                'css' => implode("\n", [
                    '.crumb-list { display: flex; flex-wrap: wrap; align-items: center; gap: 8px;',
                    '  margin: 0; padding: 0; list-style: none; font-size: 13px; }',
                    '.crumb-item a { color: #605d5d; text-decoration: none; }',
                    '.crumb-item a:hover { color: #ec3013; text-decoration: underline; }',
                    '.crumb-item [aria-current] { color: #1c1a19; font-weight: 700; }',
                    '',
                    '/* The separator is drawn, not typed, so it never lands in the text a',
                    '   screen reader or a copy-paste picks up. */',
                    '.crumb-item + .crumb-item::before { content: "\\203A"; margin-right: 8px; color: #9a9595; }',
                    '',
                    '.crumb-title { margin: 14px 0 0; font-size: 38px; line-height: 1.15; }',
                    '.crumb-lead { margin-top: 10px; max-width: 680px; font-size: 16px; line-height: 1.7; color: #605d5d; }',
                    '',
                    '/* Leave the heading or the intro blank and it disappears entirely,',
                    '   rather than leaving an empty box behind. */',
                    '.crumb-title:empty, .crumb-lead:empty { display: none; }',
                    '',
                    '@media (max-width: 720px) { .crumb-title { font-size: 28px; } }',
                ]),
                'fields' => [
                    ['key' => 'home_label', 'label' => 'First crumb', 'type' => 'text', 'default' => 'Home'],
                    ['key' => 'home_url', 'label' => 'First crumb link', 'type' => 'url', 'default' => '/'],
                    ['key' => 'trail', 'label' => 'Crumbs in between', 'type' => 'group', 'fields' => [
                        ['key' => 'label', 'label' => 'Label', 'type' => 'text'],
                        ['key' => 'url', 'label' => 'Link', 'type' => 'url'],
                    ]],
                    ['key' => 'heading', 'label' => 'Page title', 'type' => 'text'],
                    ['key' => 'lead', 'label' => 'Intro', 'type' => 'richtext'],
                ],
            ],

            'card-grid' => [
                'name' => 'Card Grid',
                'description' => 'A heading and a repeatable row of cards.',
                'html' => implode("\n", [
                    '<div class="cards">',
                    '  <h2>{{ heading }}</h2>',
                    '  <div class="card-grid">',
                    '    {{#cards}}',
                    '    <a class="card" href="{{ link }}">',
                    '      <img src="{{ image }}" alt="{{ title }}">',
                    '      <h3>{{ title }}</h3>',
                    '      <div class="card-body">{{ body }}</div>',
                    '    </a>',
                    '    {{/cards}}',
                    '  </div>',
                    '</div>',
                ]),
                'css' => implode("\n", [
                    '.cards h2 { margin: 0 0 26px; font-size: 30px; }',
                    '.card-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }',
                    '.card { display: block; padding: 20px; background: #fff; border: 1px solid #e5e2e2;',
                    '  color: inherit; text-decoration: none; }',
                    '.card:hover { border-color: #ec3013; }',
                    '.card img { width: 100%; height: auto; display: block; margin-bottom: 14px; }',
                    '.card img[src=""] { display: none; }',
                    '.card h3 { margin: 0 0 6px; font-size: 19px; }',
                    '.card-body { font-size: 14px; line-height: 1.6; color: #605d5d; }',
                    '@media (max-width: 720px) { .card-grid { grid-template-columns: 1fr; } }',
                ]),
                'fields' => [
                    ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['key' => 'cards', 'label' => 'Cards', 'type' => 'group', 'fields' => [
                        ['key' => 'image', 'label' => 'Image', 'type' => 'media'],
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                        ['key' => 'body', 'label' => 'Text', 'type' => 'richtext'],
                        ['key' => 'link', 'label' => 'Link', 'type' => 'url'],
                    ]],
                ],
            ],

            'contact-form' => [
                'name' => 'Contact Form',
                'description' => 'A heading, a line of text and a form from the form builder.',
                'html' => implode("\n", [
                    '<div class="form-block">',
                    '  <div class="form-block-text">',
                    '    <h2>{{ heading }}</h2>',
                    '    <div class="form-block-lead">{{ lead }}</div>',
                    '  </div>',
                    '  <div class="form-block-form">{{ form }}</div>',
                    '</div>',
                ]),
                'css' => implode("\n", [
                    '.form-block { display: grid; grid-template-columns: 1fr 1.2fr; gap: 48px; align-items: start; }',
                    '.form-block h2 { margin: 0 0 12px; font-size: 32px; line-height: 1.15; }',
                    '.form-block-lead { font-size: 16px; line-height: 1.7; color: #605d5d; }',
                    '.form-block-lead:empty { display: none; }',
                    '@media (max-width: 860px) { .form-block { grid-template-columns: 1fr; gap: 28px; } }',
                ]),
                'fields' => [
                    ['key' => 'heading', 'label' => 'Heading', 'type' => 'text', 'default' => 'Send us a message'],
                    ['key' => 'lead', 'label' => 'Intro', 'type' => 'richtext'],
                    ['key' => 'form', 'label' => 'Form', 'type' => 'form'],
                ],
            ],

            'rich-text' => [
                'name' => 'Rich Text',
                'description' => 'A heading and a block of formatted copy.',
                'html' => implode("\n", [
                    '<div class="prose">',
                    '  <h2>{{ heading }}</h2>',
                    '  <div class="prose-body">{{ body }}</div>',
                    '</div>',
                ]),
                'css' => implode("\n", [
                    '.prose { max-width: 760px; }',
                    '.prose h2 { margin: 0 0 16px; font-size: 30px; }',
                    '.prose-body { font-size: 16px; line-height: 1.75; color: #444141; }',
                    '.prose-body p { margin: 0 0 14px; }',
                    '.prose-body ul { padding-left: 1.3em; }',
                ]),
                'fields' => [
                    ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['key' => 'body', 'label' => 'Body', 'type' => 'richtext'],
                ],
            ],

            'call-to-action' => [
                'name' => 'Call To Action',
                'description' => 'A closing line with a button.',
                'html' => implode("\n", [
                    '<div class="band">',
                    '  <strong>{{ heading }}</strong>',
                    '  <a href="{{ button_url }}">{{ button_label }}</a>',
                    '</div>',
                ]),
                'css' => implode("\n", [
                    '.band { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 18px; }',
                    '.band strong { font-size: 25px; }',
                    '.band a { padding: 10px 20px; border: 2px solid currentColor; color: inherit;',
                    '  text-decoration: none; font-weight: 700; }',
                ]),
                'fields' => [
                    ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['key' => 'button_label', 'label' => 'Button label', 'type' => 'text'],
                    ['key' => 'button_url', 'label' => 'Button link', 'type' => 'url'],
                ],
            ],
        ];
    }

    /** Starter page templates: instances of the library sections above. */
    public static function all(): array
    {
        return [
            'blank' => [
                'label' => 'Blank',
                'summary' => 'A breadcrumb and one rich text section to start from.',
                'sections' => [
                    self::breadcrumb(''),
                    self::richText('New section', 'Write the first paragraph here.'),
                ],
            ],

            'landing' => [
                'label' => 'Landing page',
                'summary' => 'Hero, cards and a call to action — with no site header or footer.',
                // A campaign page: nothing on it should lead away from the
                // call to action, so it starts without the site chrome.
                'layout' => 'landing',
                // No breadcrumb: a landing page is the top of its own trail,
                // and the hero already carries the h1.
                'sections' => [self::hero(), self::cards(), self::cta()],
            ],

            'about' => [
                'label' => 'About',
                'summary' => 'Breadcrumb, your story and the people behind it.',
                'sections' => [
                    self::breadcrumb('About us', '<p>Who we are and how we got here.</p>'),
                    self::richText('Our story', '<p>Start with why the company exists and who it is for.</p>'),
                    self::cards('The team'),
                ],
            ],

            'contact' => [
                'label' => 'Contact',
                'summary' => 'Breadcrumb, a short intro and a way to reach you.',
                'sections' => [
                    self::breadcrumb('Contact us', '<p>We answer within one working day.</p>'),
                    self::contactForm(),
                    self::cta('Prefer email?', 'hello@example.com', 'mailto:hello@example.com'),
                ],
            ],
        ];
    }

    /**
     * The trail at the top of an inner page. The last crumb is always the page
     * itself, through {{ page_title }}, so it follows a rename on its own.
     */
    private static function breadcrumb(string $heading, string $lead = ''): array
    {
        return [
            'name' => 'Breadcrumb', 'mode' => 'Library', 'template_slug' => 'breadcrumb',
            'style' => [
                'background' => '#f7f5f5',
                'padding' => '26px 32px 34px',
                'max_width' => 'var(--site-width)',
            ],
            'values' => [
                'home_label' => 'Home',
                'home_url' => '/',
                'trail' => [],
                'heading' => $heading,
                'lead' => $lead,
            ],
        ];
    }

    private static function hero(): array
    {
        return [
            'name' => 'Hero', 'mode' => 'Library', 'template_slug' => 'common-hero',
            'style' => ['padding' => '64px 32px', 'max_width' => 'var(--site-width)'],
            'values' => [
                'subtitle' => 'Composable sections',
                'title' => 'Build faster on Laravel',
                'body' => '<p>Sections you build once and reuse on every page, edited by the people who write the copy.</p>',
                'button_label' => 'Book a call',
                'button_url' => '/contact',
                'image' => '',
                'image_alt' => '',
            ],
        ];
    }

    private static function cards(string $heading = 'Why teams switch'): array
    {
        return [
            'name' => 'Cards', 'mode' => 'Library', 'template_slug' => 'card-grid',
            'style' => ['padding' => '56px 32px', 'max_width' => 'var(--site-width)', 'background' => '#ffffff'],
            'values' => [
                'heading' => $heading,
                'cards' => [
                    ['image' => '', 'title' => 'Fast', 'body' => '<p>Cached queries keep p95 under 120 ms.</p>', 'link' => '#'],
                    ['image' => '', 'title' => 'Safe', 'body' => '<p>Policy gates on every route.</p>', 'link' => '#'],
                    ['image' => '', 'title' => 'Open', 'body' => '<p>REST and webhooks included.</p>', 'link' => '#'],
                ],
            ],
        ];
    }

    /**
     * The form field holds a slug rather than an id, because the seeder runs
     * before any of this and ids are not knowable here.
     */
    private static function contactForm(): array
    {
        return [
            'name' => 'Contact form', 'mode' => 'Library', 'template_slug' => 'contact-form',
            'style' => ['padding' => '56px 32px', 'max_width' => 'var(--site-width)'],
            'values' => [
                'heading' => 'Send us a message',
                'lead' => '<p>Tell us what you need and who to reply to.</p>',
                'form' => 'contact',
            ],
        ];
    }

    private static function richText(string $heading, string $body): array
    {
        return [
            'name' => $heading, 'mode' => 'Library', 'template_slug' => 'rich-text',
            'style' => ['padding' => '56px 32px', 'max_width' => 'var(--site-width)'],
            'values' => ['heading' => $heading, 'body' => $body],
        ];
    }

    private static function cta(string $heading = 'Ready to migrate?', string $label = 'Talk to us', string $url = '/contact'): array
    {
        return [
            'name' => 'Call to action', 'mode' => 'Library', 'template_slug' => 'call-to-action',
            'style' => ['padding' => '40px 32px', 'background' => '#ec3013', 'color' => '#ffffff', 'max_width' => 'var(--site-width)'],
            'values' => ['heading' => $heading, 'button_label' => $label, 'button_url' => $url],
        ];
    }

    /** @return array<int, array{value: string, label: string, summary: string, sections: int, layout: string}> */
    public static function options(): array
    {
        return collect(self::all())
            ->map(fn (array $template, string $key) => [
                'value' => $key,
                'label' => $template['label'],
                'summary' => $template['summary'],
                'sections' => count($template['sections']),
                'layout' => $template['layout'] ?? 'default',
            ])
            ->values()
            ->all();
    }

    public static function sectionsFor(string $key): array
    {
        return self::all()[$key]['sections'] ?? self::all()['blank']['sections'];
    }

    public static function layoutFor(string $key): string
    {
        return self::all()[$key]['layout'] ?? 'default';
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }
}
