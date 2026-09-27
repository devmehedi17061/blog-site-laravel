<?php

namespace App\Support;

/**
 * Three ready-made library sections: a hero slider, a testimonial slider and
 * a call-to-action banner. Plain HTML, CSS and fields like any section — the
 * sliding is resources/js/site.js, switched on by data-slider attributes and
 * driven by the Choice fields (autoplay, effect, cards per view …).
 *
 * In the CSS, `:root` is the section's full-width band, so a gradient
 * reaches both edges; `.wrap` holds the content to the site width.
 */
class SliderSections
{
    private const ARROW_PREV = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>';

    private const ARROW_NEXT = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>';

    /** The slider options every slider section offers, as Choice fields. */
    private static function sliderFields(array $defaults = []): array
    {
        $d = $defaults + ['autoplay' => 'yes', 'interval' => '6', 'effect' => 'slide', 'loop' => 'yes', 'pause_on_hover' => 'yes', 'show_arrows' => 'yes', 'show_dots' => 'yes'];

        return [
            ['key' => 'autoplay', 'label' => 'Autoplay', 'type' => 'select', 'options' => 'yes, no', 'default' => $d['autoplay']],
            ['key' => 'interval', 'label' => 'Seconds per slide', 'type' => 'select', 'options' => '3, 4, 5, 6, 8, 10, 12', 'default' => $d['interval']],
            ['key' => 'effect', 'label' => 'Effect', 'type' => 'select', 'options' => 'slide, fade', 'default' => $d['effect']],
            ['key' => 'loop', 'label' => 'Loop (after the last, the first)', 'type' => 'select', 'options' => 'yes, no', 'default' => $d['loop']],
            ['key' => 'pause_on_hover', 'label' => 'Pause while the mouse is over it', 'type' => 'select', 'options' => 'yes, no', 'default' => $d['pause_on_hover']],
            ['key' => 'show_arrows', 'label' => 'Show arrows', 'type' => 'select', 'options' => 'yes, no', 'default' => $d['show_arrows']],
            ['key' => 'show_dots', 'label' => 'Show dots', 'type' => 'select', 'options' => 'yes, no', 'default' => $d['show_dots']],
        ];
    }

    /** @return array<string, array{name: string, description: string, html: string, css: string, fields: array}> */
    public static function sections(): array
    {
        return [
            'hero-slider' => self::hero(),
            'testimonial-slider' => self::testimonials(),
            'call-to-action-banner' => self::cta(),
        ];
    }

    private static function hero(): array
    {
        $prev = self::ARROW_PREV;
        $next = self::ARROW_NEXT;

        return [
            'name' => 'Hero Slider',
            'description' => 'Slides with words on the left and an image on the right, over a gradient. Autoplay, fade or slide, arrows and dots.',
            'html' => <<<HTML
<div class="hs" data-slider data-autoplay="{{ autoplay }}" data-interval="{{ interval }}" data-effect="{{ effect }}"
     data-loop="{{ loop }}" data-pause="{{ pause_on_hover }}" aria-label="Highlights">
  <div class="wrap">
    <div class="hs-track" data-slider-track>
      {{#slides}}
      <div class="hs-slide" data-slide>
        <div class="hs-text">
          <p class="hs-eyebrow">{{ eyebrow }}</p>
          <h1 class="hs-title">{{ title }}</h1>
          <p class="hs-lead">{{ text }}</p>
          <div class="hs-actions">
            <a class="hs-btn" href="{{ button_url }}">{{ button_label }}</a>
            <a class="hs-btn hs-btn-ghost" href="{{ button2_url }}">{{ button2_label }}</a>
          </div>
        </div>
        <div class="hs-media">
          <img src="{{ image }}" alt="{{ image_alt }}">
        </div>
      </div>
      {{/slides}}
    </div>
    <div class="hs-controls" data-arrows="{{ show_arrows }}" data-dots="{{ show_dots }}">
      <div class="hs-dots" data-slider-dots></div>
      <div class="hs-arrows">
        <button type="button" class="hs-arrow" data-slider-prev aria-label="Previous slide">{$prev}</button>
        <button type="button" class="hs-arrow" data-slider-next aria-label="Next slide">{$next}</button>
      </div>
    </div>
  </div>
</div>
HTML,
            'css' => <<<'CSS'
/* :root is the full-width band, so the gradient reaches both edges. */
:root { background: linear-gradient(120deg, {{ bg_from }}, {{ bg_to }}); color: {{ text_color }}; overflow: hidden; }
.wrap { max-width: var(--site-width, 1100px); margin: 0 auto; padding: 72px 20px 40px; }

.hs-slide { display: grid; grid-template-columns: 1.05fr 1fr; align-items: center; gap: 48px; }
.hs-eyebrow { display: inline-block; margin: 0 0 16px; padding: 6px 12px; font-size: 12px; font-weight: 700;
  letter-spacing: .12em; text-transform: uppercase; background: rgba(255, 255, 255, .12); border-radius: 999px; }
.hs-title { margin: 0 0 18px; font-size: clamp(32px, 4.4vw, 54px); line-height: 1.08; color: inherit; }
.hs-lead { margin: 0; max-width: 520px; font-size: 18px; line-height: 1.65; opacity: .85; }
.hs-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 30px; }
.hs-btn { display: inline-flex; align-items: center; height: 50px; padding: 0 26px; font-weight: 700; font-size: 15px;
  text-decoration: none; color: #0f172a; background: {{ accent }}; border: 2px solid {{ accent }}; border-radius: 999px;
  transition: transform .15s ease, box-shadow .15s ease; }
.hs-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(0, 0, 0, .2); }
.hs-btn-ghost { color: inherit; background: transparent; border-color: rgba(255, 255, 255, .5); }
.hs-btn:empty, .hs-eyebrow:empty, .hs-lead:empty { display: none; }
.hs-media img { display: block; width: 100%; height: auto; max-height: 440px; object-fit: contain;
  filter: drop-shadow(0 30px 40px rgba(0, 0, 0, .25)); }
.hs-media img[src=""] { display: none; }

.hs-controls { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 36px; }
.hs-dots { display: flex; gap: 8px; }
.hs-dots .sl-dot { width: 10px; height: 10px; padding: 0; border: 0; border-radius: 999px; cursor: pointer;
  background: rgba(255, 255, 255, .35); transition: width .25s ease, background .25s ease; }
.hs-dots .sl-dot.is-active { width: 32px; background: {{ accent }}; }
.hs-arrows { display: flex; gap: 10px; margin-left: auto; }
.hs-arrow { display: grid; place-items: center; width: 44px; height: 44px; cursor: pointer; color: inherit;
  background: rgba(255, 255, 255, .08); border: 1px solid rgba(255, 255, 255, .35); border-radius: 999px; transition: background .15s ease; }
.hs-arrow:hover:not(:disabled) { background: rgba(255, 255, 255, .2); }
.hs-arrow:disabled { opacity: .35; cursor: default; }
[data-arrows="no"] .hs-arrows, [data-dots="no"] .hs-dots { display: none; }

@media (max-width: 860px) {
  .wrap { padding-top: 48px; }
  .hs-slide { grid-template-columns: 1fr; gap: 28px; text-align: center; }
  .hs-lead { margin: 0 auto; }
  .hs-actions { justify-content: center; }
  .hs-media img { max-height: 300px; }
}
CSS,
            'fields' => [
                ['key' => 'slides', 'label' => 'Slides', 'type' => 'group', 'fields' => [
                    ['key' => 'eyebrow', 'label' => 'Small line above', 'type' => 'text'],
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'text', 'label' => 'Text', 'type' => 'text'],
                    ['key' => 'button_label', 'label' => 'Button', 'type' => 'text'],
                    ['key' => 'button_url', 'label' => 'Button link', 'type' => 'url'],
                    ['key' => 'button2_label', 'label' => 'Second button', 'type' => 'text'],
                    ['key' => 'button2_url', 'label' => 'Second button link', 'type' => 'url'],
                    ['key' => 'image', 'label' => 'Image', 'type' => 'media'],
                    ['key' => 'image_alt', 'label' => 'Image description', 'type' => 'text'],
                ], 'items' => [
                    ['eyebrow' => 'Digital growth partner', 'title' => 'Grow your business with a website that works', 'text' => 'Design, marketing and support under one roof — so you can focus on your customers.', 'button_label' => 'Get started', 'button_url' => '/contact', 'button2_label' => 'Our services', 'button2_url' => '/about', 'image' => '/images/sections/hero-dashboard.svg', 'image_alt' => 'A dashboard showing growth'],
                    ['eyebrow' => 'Marketing that pays off', 'title' => 'Turn visitors into loyal customers', 'text' => 'Campaigns you can measure, with reports you can read in a minute.', 'button_label' => 'See results', 'button_url' => '/blog', 'button2_label' => 'Talk to us', 'button2_url' => '/contact', 'image' => '/images/sections/hero-growth.svg', 'image_alt' => 'A rising growth chart'],
                    ['eyebrow' => 'Always here to help', 'title' => 'Support that answers — day and night', 'text' => 'A friendly team and a call centre that keeps every enquiry moving.', 'button_label' => 'Contact us', 'button_url' => '/contact', 'button2_label' => '', 'button2_url' => '', 'image' => '/images/sections/hero-support.svg', 'image_alt' => 'A support chat on a phone'],
                ]],
                ['key' => 'bg_from', 'label' => 'Gradient start colour', 'type' => 'text', 'default' => '#1e1b4b'],
                ['key' => 'bg_to', 'label' => 'Gradient end colour', 'type' => 'text', 'default' => '#0e7490'],
                ['key' => 'text_color', 'label' => 'Text colour', 'type' => 'text', 'default' => '#ffffff'],
                ['key' => 'accent', 'label' => 'Button and dot colour', 'type' => 'text', 'default' => '#22d3ee'],
                ...self::sliderFields(['effect' => 'fade', 'interval' => '6']),
            ],
        ];
    }

    private static function testimonials(): array
    {
        $prev = self::ARROW_PREV;
        $next = self::ARROW_NEXT;

        return [
            'name' => 'Testimonial Slider',
            'description' => 'What clients say, in a card with dots and arrows. One to three quotes side by side, autoplay, loop and more.',
            'html' => <<<HTML
<div class="wrap">
  <h2 class="ts-heading">{{ heading }} <span>{{ heading_highlight }}</span></h2>
  <span class="ts-rule" aria-hidden="true"></span>

  <div class="ts-card" data-slider data-autoplay="{{ autoplay }}" data-interval="{{ interval }}" data-effect="{{ effect }}"
       data-per-view="{{ per_view }}" data-loop="{{ loop }}" data-pause="{{ pause_on_hover }}" aria-label="Testimonials">
    <div class="ts-track" data-slider-track>
      {{#testimonials}}
      <figure class="ts-slide" data-slide>
        <blockquote class="ts-quote">“{{ quote }}”</blockquote>
        <figcaption class="ts-person">
          <img class="ts-photo" src="{{ photo }}" alt="">
          <span>
            <strong class="ts-name">{{ name }}</strong>
            <span class="ts-role">{{ role }}</span>
          </span>
        </figcaption>
      </figure>
      {{/testimonials}}
    </div>
    <div class="ts-controls" data-arrows="{{ show_arrows }}" data-dots="{{ show_dots }}">
      <div class="ts-dots" data-slider-dots></div>
      <div class="ts-arrows">
        <button type="button" class="ts-arrow" data-slider-prev aria-label="Previous testimonial">{$prev}</button>
        <button type="button" class="ts-arrow" data-slider-next aria-label="Next testimonial">{$next}</button>
      </div>
    </div>
  </div>
</div>
HTML,
            'css' => <<<'CSS'
:root { background: {{ background }}; }
.wrap { max-width: var(--site-width, 1100px); margin: 0 auto; padding: 64px 20px; }

.ts-heading { margin: 0; font-size: clamp(26px, 3vw, 36px); line-height: 1.2; color: {{ heading_color }}; }
.ts-heading span { color: {{ accent }}; }
.ts-heading span:empty { display: none; }
.ts-rule { display: block; width: 64px; height: 4px; margin: 14px 0 30px; background: {{ accent }}; border-radius: 4px; }

.ts-card { padding: 36px 40px 28px; background: #ffffff; border: 1px solid #e5e9f0; border-radius: 18px;
  box-shadow: 0 10px 30px rgba(15, 23, 42, .05); overflow: hidden; }
/* Space between quotes shown side by side; the edges line up with the card. */
.ts-slide { margin: 0; padding: 0 16px; }
.ts-card .sl-viewport { margin: 0 -16px; }
.ts-quote { margin: 0; font-size: 17px; line-height: 1.75; color: #475569; }
.ts-person { display: flex; align-items: center; gap: 14px; margin-top: 24px; }
.ts-photo { flex: none; width: 58px; height: 58px; object-fit: cover; border-radius: 50%;
  border: 2px solid {{ accent }}; padding: 2px; background: #fff; }
.ts-photo[src=""] { display: none; }
.ts-name { display: block; font-size: 16px; color: {{ heading_color }}; }
.ts-role { display: block; margin-top: 2px; font-size: 13px; color: #64748b; }

.ts-controls { display: flex; align-items: center; justify-content: space-between; gap: 16px; margin-top: 34px; }
.ts-dots { display: flex; gap: 8px; }
.ts-dots .sl-dot { width: 10px; height: 10px; padding: 0; border: 0; border-radius: 999px; cursor: pointer;
  background: #cbd5e1; transition: width .25s ease, background .25s ease; }
.ts-dots .sl-dot.is-active { width: 32px; background: {{ accent }}; }
.ts-arrows { display: flex; gap: 10px; margin-left: auto; }
.ts-arrow { display: grid; place-items: center; width: 38px; height: 38px; cursor: pointer; color: #334155;
  background: #fff; border: 1px solid #cbd5e1; border-radius: 999px; transition: border-color .15s ease, color .15s ease; }
.ts-arrow:hover:not(:disabled) { color: {{ accent }}; border-color: {{ accent }}; }
.ts-arrow:disabled { opacity: .4; cursor: default; }
[data-arrows="no"] .ts-arrows, [data-dots="no"] .ts-dots { display: none; }

@media (max-width: 640px) {
  .ts-card { padding: 26px 22px 22px; }
  .ts-slide { padding: 0 12px; }
  .ts-card .sl-viewport { margin: 0 -12px; }
}
CSS,
            'fields' => [
                ['key' => 'heading', 'label' => 'Heading', 'type' => 'text', 'default' => 'Testimonials From Our'],
                ['key' => 'heading_highlight', 'label' => 'Highlighted word(s)', 'type' => 'text', 'default' => 'Clients'],
                ['key' => 'testimonials', 'label' => 'Testimonials', 'type' => 'group', 'fields' => [
                    ['key' => 'quote', 'label' => 'What they said', 'type' => 'text'],
                    ['key' => 'name', 'label' => 'Name', 'type' => 'text'],
                    ['key' => 'role', 'label' => 'Role and company', 'type' => 'text'],
                    ['key' => 'photo', 'label' => 'Photo', 'type' => 'media'],
                ], 'items' => [
                    ['quote' => 'Their 360-degree digital marketing services have transformed our practice. From website design to online marketing and call centre services, they have streamlined how we reach and serve patients.', 'name' => 'Dr. Mahmud Alam', 'role' => 'Consultant, City Hospital', 'photo' => '/images/sections/avatar-1.svg'],
                    ['quote' => 'The new website paid for itself within three months. Enquiries doubled, and the team is always one message away.', 'name' => 'Nusrat Sultana', 'role' => 'Founder, Bloom Studio', 'photo' => '/images/sections/avatar-2.svg'],
                    ['quote' => 'Clear reports, honest advice and campaigns that actually bring customers. Exactly what a growing business needs.', 'name' => 'Rakib Khan', 'role' => 'Director, North Trade Ltd', 'photo' => '/images/sections/avatar-3.svg'],
                    ['quote' => 'Friendly, fast and professional from the first call. I recommend them to every business owner I meet.', 'name' => 'Tahmina Hossain', 'role' => 'Owner, Green Leaf Café', 'photo' => '/images/sections/avatar-4.svg'],
                ]],
                ['key' => 'heading_color', 'label' => 'Heading colour', 'type' => 'text', 'default' => '#1e1b4b'],
                ['key' => 'accent', 'label' => 'Accent colour', 'type' => 'text', 'default' => '#0ea5e9'],
                ['key' => 'background', 'label' => 'Background colour', 'type' => 'text', 'default' => '#f8fafc'],
                ['key' => 'per_view', 'label' => 'Quotes side by side (wide screens)', 'type' => 'select', 'options' => '1, 2, 3', 'default' => '1'],
                ...self::sliderFields(['effect' => 'slide', 'interval' => '6']),
            ],
        ];
    }

    private static function cta(): array
    {
        return [
            'name' => 'Call to Action Banner',
            'description' => 'A bold gradient banner with a heading, a line of text and one or two buttons. Side by side or centred.',
            'html' => <<<'HTML'
<div class="wrap">
  <div class="cta" data-layout="{{ layout }}">
    <div class="cta-text">
      <h2 class="cta-title">{{ title }}</h2>
      <p class="cta-lead">{{ text }}</p>
    </div>
    <div class="cta-actions">
      <a class="cta-btn" href="{{ button_url }}">{{ button_label }}</a>
      <a class="cta-btn cta-btn-ghost" href="{{ button2_url }}">{{ button2_label }}</a>
    </div>
  </div>
</div>
HTML,
            'css' => <<<'CSS'
.wrap { max-width: var(--site-width, 1100px); margin: 0 auto; padding: 56px 20px; }

.cta { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 28px;
  padding: 48px 52px; overflow: hidden; color: {{ text_color }};
  background: linear-gradient(120deg, {{ bg_from }}, {{ bg_to }}); border-radius: 22px; }
/* Two soft circles for depth; purely decoration. */
.cta::before, .cta::after { content: ""; position: absolute; border-radius: 50%; background: rgba(255, 255, 255, .1); pointer-events: none; }
.cta::before { width: 260px; height: 260px; right: -60px; top: -120px; }
.cta::after { width: 160px; height: 160px; left: 38%; bottom: -100px; }

.cta-text { position: relative; max-width: 620px; }
.cta-title { margin: 0 0 10px; font-size: clamp(26px, 3vw, 38px); line-height: 1.15; color: inherit; }
.cta-lead { margin: 0; font-size: 17px; line-height: 1.6; opacity: .88; }
.cta-lead:empty { display: none; }
.cta-actions { position: relative; display: flex; flex-wrap: wrap; gap: 12px; flex: none; }
.cta-btn { display: inline-flex; align-items: center; height: 50px; padding: 0 26px; font-weight: 700; font-size: 15px;
  text-decoration: none; color: {{ bg_from }}; background: #ffffff; border: 2px solid #ffffff; border-radius: 999px;
  transition: transform .15s ease, box-shadow .15s ease; }
.cta-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px rgba(0, 0, 0, .2); }
.cta-btn-ghost { color: inherit; background: transparent; border-color: rgba(255, 255, 255, .6); }
.cta-btn:empty { display: none; }

.cta[data-layout="centered"] { flex-direction: column; text-align: center; }
.cta[data-layout="centered"] .cta-text { margin: 0 auto; }
.cta[data-layout="centered"] .cta-actions { justify-content: center; }

@media (max-width: 760px) {
  .cta { flex-direction: column; align-items: flex-start; padding: 36px 26px; }
  .cta[data-layout="centered"] { align-items: center; }
}
CSS,
            'fields' => [
                ['key' => 'title', 'label' => 'Heading', 'type' => 'text', 'default' => 'Ready to grow your business?'],
                ['key' => 'text', 'label' => 'Text', 'type' => 'text', 'default' => 'Book a free 20-minute call. We will look at your site and give you three ideas you can use right away.'],
                ['key' => 'button_label', 'label' => 'Button', 'type' => 'text', 'default' => 'Book a free call'],
                ['key' => 'button_url', 'label' => 'Button link', 'type' => 'url', 'default' => '/contact'],
                ['key' => 'button2_label', 'label' => 'Second button', 'type' => 'text', 'default' => 'See our work'],
                ['key' => 'button2_url', 'label' => 'Second button link', 'type' => 'url', 'default' => '/blog'],
                ['key' => 'layout', 'label' => 'Layout', 'type' => 'select', 'options' => 'split, centered', 'default' => 'split'],
                ['key' => 'bg_from', 'label' => 'Gradient start colour', 'type' => 'text', 'default' => '#4f46e5'],
                ['key' => 'bg_to', 'label' => 'Gradient end colour', 'type' => 'text', 'default' => '#0ea5e9'],
                ['key' => 'text_color', 'label' => 'Text colour', 'type' => 'text', 'default' => '#ffffff'],
            ],
        ];
    }
}
