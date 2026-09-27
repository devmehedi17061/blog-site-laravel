<?php

namespace App\Support;

/**
 * The headers and footers a fresh site starts with, written exactly the way
 * someone would in Builders → Header & footer: HTML with {{ tokens }}, CSS,
 * fields, and the fields' first values. Each works at every screen width.
 *
 * Site tokens ({{ site_brand }}, {{ menu_header }}, {{ search }} …) are listed
 * in SitePartRenderer::TOKENS. In the CSS, `:root` is the header or footer
 * itself, so `:root.menu-open .nav` styles the menu the ☰ button opened.
 */
class SitePartStarters
{
    /** @return array<int, array{kind: string, name: string, description: string, html: string, css: string, fields: array, values: array, settings: array}> */
    public static function all(): array
    {
        return [...self::headers(), ...self::footers()];
    }

    /** The shared rules that fold a header's menu behind ☰ below 900px. */
    private const FOLD = <<<'CSS'
@media (max-width: 900px) {
  .wrap { flex-wrap: wrap; }
  .menu-toggle { display: grid; margin-left: auto; order: 2; }
  .nav { order: 3; display: none; width: 100%; flex-direction: column; align-items: stretch; gap: 14px; padding: 6px 0 10px; }
  :root.menu-open .nav { display: flex; }
  .nav .site-menu { flex-direction: column; align-items: stretch; gap: 0; }
  .nav .site-menu-top-link { padding: 11px 0; border-bottom: 1px solid rgba(0, 0, 0, 0.08); }
  .nav .site-menu-panel { position: static; display: block; min-width: 0; padding: 0 0 0 16px; border: 0; box-shadow: none; background: transparent; }
  .nav .sp-search { width: 100%; }
  .nav .sp-search input { flex: 1; width: auto; }
}
CSS;

    private static function headers(): array
    {
        return [
            [
                'kind' => 'header',
                'name' => 'Classic',
                'description' => 'Logo on the left; menu and search on the right. Folds behind ☰ on tablets and phones.',
                'html' => <<<'HTML'
<div class="bar">
  <div class="wrap">
    {{ site_brand }}
    <div class="nav" data-menu-panel>
      {{ menu_header }}
      {{ search }}
    </div>
    {{ menu_toggle }}
  </div>
</div>
HTML,
                'css' => <<<'CSS'
.bar { background: {{ background }}; color: {{ text_color }}; border-bottom: 1px solid rgba(0, 0, 0, 0.1); }
.wrap { display: flex; align-items: center; gap: 20px; max-width: var(--site-width); margin: 0 auto; padding: 14px 16px; }
.nav { display: flex; align-items: center; gap: 16px; margin-left: auto; }
.menu-toggle { display: none; }
CSS."\n".self::FOLD,
                'fields' => [
                    ['key' => 'background', 'label' => 'Background colour', 'type' => 'text'],
                    ['key' => 'text_color', 'label' => 'Text colour', 'type' => 'text'],
                ],
                'values' => ['background' => '#ffffff', 'text_color' => '#1c1a19'],
                'settings' => ['sticky' => true],
                'active' => true,
            ],
            [
                'kind' => 'header',
                'name' => 'Top bar and button',
                'description' => 'A slim bar with a notice and contact details, then logo, menu and a call-to-action button.',
                'html' => <<<'HTML'
<div class="topbar">
  <div class="wrap">
    <span>{{ notice }}</span>
    <span class="contacts">{{ contact_phone }} {{ contact_email }}</span>
  </div>
</div>
<div class="bar">
  <div class="wrap">
    {{ site_brand }}
    <div class="nav" data-menu-panel>
      {{ menu_header }}
      <a class="cta" href="{{ button_link }}">{{ button_label }}</a>
    </div>
    {{ menu_toggle }}
  </div>
</div>
HTML,
                'css' => <<<'CSS'
.topbar { background: #201e1d; color: #d7d3d3; font-size: 13px; }
.topbar .wrap { padding-top: 8px; padding-bottom: 8px; justify-content: space-between; }
.contacts { display: flex; gap: 18px; }
.contacts a { color: inherit; text-decoration: none; }
.bar { background: #ffffff; border-bottom: 1px solid rgba(0, 0, 0, 0.1); }
.wrap { display: flex; align-items: center; gap: 20px; max-width: var(--site-width); margin: 0 auto; padding: 14px 16px; }
.nav { display: flex; align-items: center; gap: 16px; margin-left: auto; }
.cta { display: inline-flex; align-items: center; height: 40px; padding: 0 18px; background: {{ button_colour }}; color: #fff; font-weight: 700; font-size: 14px; text-decoration: none; }
.menu-toggle { display: none; }
@media (max-width: 640px) {
  .topbar .wrap { flex-direction: column; gap: 4px; text-align: center; }
  .contacts { justify-content: center; flex-wrap: wrap; }
}
CSS."\n".self::FOLD."\n@media (max-width: 900px) { .cta { justify-content: center; } }",
                'fields' => [
                    ['key' => 'notice', 'label' => 'Notice in the top bar', 'type' => 'text'],
                    ['key' => 'button_label', 'label' => 'Button label', 'type' => 'text'],
                    ['key' => 'button_link', 'label' => 'Button link', 'type' => 'url'],
                    ['key' => 'button_colour', 'label' => 'Button colour', 'type' => 'text'],
                ],
                'values' => ['notice' => 'Free consultation for new clients this month.', 'button_label' => 'Get in touch', 'button_link' => '/contact', 'button_colour' => '#ec3013'],
                'settings' => ['sticky' => true],
            ],
            [
                'kind' => 'header',
                'name' => 'Centred logo',
                'description' => 'The logo in the middle with social links beside it, and the menu centred underneath.',
                'html' => <<<'HTML'
<div class="top">
  <div class="wrap">
    <div class="side">{{ social }}</div>
    <div class="middle">{{ site_brand }}</div>
    <div class="side end">{{ menu_toggle }}</div>
  </div>
</div>
<nav class="nav" data-menu-panel>
  <div class="wrap">{{ menu_header }}</div>
</nav>
HTML,
                'css' => <<<'CSS'
.top { background: #ffffff; }
.wrap { display: flex; align-items: center; max-width: var(--site-width); margin: 0 auto; padding: 16px; }
.side { flex: 1; display: flex; }
.side.end { justify-content: flex-end; }
.middle { flex: none; }
.nav { background: #ffffff; border-top: 1px solid rgba(0, 0, 0, 0.08); border-bottom: 1px solid rgba(0, 0, 0, 0.1); }
.nav .wrap { justify-content: center; padding-top: 4px; padding-bottom: 4px; }
.menu-toggle { display: none; }
@media (max-width: 900px) {
  .top .side:first-child { display: none; }
  .top .wrap { justify-content: space-between; }
  .middle { flex: 1; }
  .menu-toggle { display: grid; }
  .nav { display: none; }
  :root.menu-open .nav { display: block; }
  .nav .wrap { display: block; }
  .nav .site-menu { flex-direction: column; align-items: stretch; }
  .nav .site-menu-top-link { padding: 11px 0; border-bottom: 1px solid rgba(0, 0, 0, 0.08); }
  .nav .site-menu-panel { position: static; display: block; min-width: 0; padding: 0 0 0 16px; border: 0; box-shadow: none; }
}
CSS,
                'fields' => [],
                'values' => [],
                'settings' => ['sticky' => false],
            ],
        ];
    }

    private static function footers(): array
    {
        return [
            [
                'kind' => 'footer',
                'name' => 'Four columns',
                'description' => 'About, the footer menu, quick links and contact details; copyright underneath. Two columns on a tablet, one on a phone.',
                'html' => <<<'HTML'
<div class="main">
  <div class="wrap grid">
    <div class="col">
      {{ site_brand }}
      <p>{{ about }}</p>
      {{ social }}
    </div>
    <div class="col">
      <h3>{{ menu_heading }}</h3>
      {{ menu_footer }}
    </div>
    <div class="col">
      <h3>{{ links_heading }}</h3>
      <ul class="links">
        {{#links}}<li><a href="{{ url }}">{{ label }}</a></li>{{/links}}
      </ul>
    </div>
    <div class="col contact">
      <h3>{{ contact_heading }}</h3>
      {{ contact_phone }}
      {{ contact_email }}
      {{ contact_address }}
    </div>
  </div>
</div>
<div class="bottom">
  <div class="wrap">© {{ year }} {{ site_name }}. {{ bottom_note }}</div>
</div>
HTML,
                'css' => <<<'CSS'
.main { background: #2d2b2b; color: #b3afaf; padding: 56px 0; }
.wrap { max-width: var(--site-width); margin: 0 auto; padding: 0 16px; }
.grid { display: grid; grid-template-columns: 1.6fr 1fr 1fr 1fr; gap: 36px; }
.col { display: flex; flex-direction: column; align-items: flex-start; gap: 12px; font-size: 14px; line-height: 1.6; }
.col p { margin: 0; }
h3 { margin: 0; color: #fff; font-size: 13px; letter-spacing: 0.08em; text-transform: uppercase; }
a { color: #d7d3d3; text-decoration: none; }
a:hover { color: #fff; }
.sp-brand { color: #fff; }
.links, .sp-list { display: flex; flex-direction: column; gap: 8px; margin: 0; padding: 0; list-style: none; }
.bottom { background: #201e1d; color: #9b9797; font-size: 13px; padding: 18px 0; }
/* No contact details saved yet: leave the column out rather than show a lone heading. */
.contact:not(:has(.sp-contact)) { display: none; }
@media (max-width: 900px) { .grid { grid-template-columns: 1fr 1fr; } }
@media (max-width: 600px) {
  .grid { grid-template-columns: 1fr; gap: 28px; }
  .bottom { text-align: center; }
}
CSS,
                'fields' => [
                    ['key' => 'about', 'label' => 'About the site', 'type' => 'text'],
                    ['key' => 'menu_heading', 'label' => 'Menu heading', 'type' => 'text'],
                    ['key' => 'links_heading', 'label' => 'Links heading', 'type' => 'text'],
                    ['key' => 'links', 'label' => 'Quick links', 'type' => 'group', 'fields' => [
                        ['key' => 'label', 'label' => 'Label', 'type' => 'text'],
                        ['key' => 'url', 'label' => 'Link', 'type' => 'url'],
                    ]],
                    ['key' => 'contact_heading', 'label' => 'Contact heading', 'type' => 'text'],
                    ['key' => 'bottom_note', 'label' => 'Line after the copyright', 'type' => 'text'],
                ],
                'values' => [
                    'about' => 'A short sentence about who you are and what you do.',
                    'menu_heading' => 'Explore',
                    'links_heading' => 'Quick links',
                    'links' => [['label' => 'About us', 'url' => '/about'], ['label' => 'Blog', 'url' => '/blog'], ['label' => 'Contact', 'url' => '/contact']],
                    'contact_heading' => 'Get in touch',
                    'bottom_note' => 'All rights reserved.',
                ],
                'settings' => [],
                'active' => true,
            ],
            [
                'kind' => 'footer',
                'name' => 'Simple bar',
                'description' => 'One slim line: copyright on the left, the footer menu on the right. Stacks and centres on a phone.',
                'html' => <<<'HTML'
<div class="bar">
  <div class="wrap">
    <span>© {{ year }} {{ site_name }}</span>
    {{ menu_footer }}
  </div>
</div>
HTML,
                'css' => <<<'CSS'
.bar { background: #201e1d; color: #9b9797; font-size: 13px; }
.wrap { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 10px 24px; max-width: var(--site-width); margin: 0 auto; padding: 22px 16px; }
.sp-list { display: flex; flex-wrap: wrap; gap: 6px 20px; margin: 0; padding: 0; list-style: none; }
a { color: #d7d3d3; text-decoration: none; }
a:hover { color: #fff; }
@media (max-width: 600px) { .wrap { flex-direction: column; text-align: center; } .sp-list { justify-content: center; } }
CSS,
                'fields' => [],
                'values' => [],
                'settings' => [],
            ],
            [
                'kind' => 'footer',
                'name' => 'Centred',
                'description' => 'Logo, a line of text, the footer menu and social links, all centred.',
                'html' => <<<'HTML'
<div class="box">
  <div class="wrap">
    {{ site_brand }}
    <p>{{ tagline }}</p>
    {{ menu_footer }}
    {{ social }}
    <small>© {{ year }} {{ site_name }}</small>
  </div>
</div>
HTML,
                'css' => <<<'CSS'
.box { background: {{ background }}; color: #b3afaf; padding: 48px 0 32px; }
.wrap { display: flex; flex-direction: column; align-items: center; gap: 16px; max-width: 760px; margin: 0 auto; padding: 0 16px; text-align: center; }
.wrap p { margin: 0; }
.sp-brand { color: #fff; }
.sp-list { display: flex; flex-wrap: wrap; justify-content: center; gap: 6px 22px; margin: 0; padding: 0; list-style: none; }
a { color: #d7d3d3; text-decoration: none; }
a:hover { color: #fff; }
small { font-size: 12px; color: #7d7979; }
CSS,
                'fields' => [
                    ['key' => 'tagline', 'label' => 'Line under the logo', 'type' => 'text'],
                    ['key' => 'background', 'label' => 'Background colour', 'type' => 'text'],
                ],
                'values' => ['tagline' => 'Made with care, published often.', 'background' => '#2d2b2b'],
                'settings' => [],
            ],
        ];
    }
}
