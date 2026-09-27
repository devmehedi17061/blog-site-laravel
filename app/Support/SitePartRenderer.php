<?php

namespace App\Support;

use App\Models\SitePart;
use Illuminate\Support\Facades\View;

/**
 * Renders a header or footer built in Builders → Header & footer.
 *
 * The template goes through the section engine (SectionRenderer), so fields,
 * {{#groups}} and CSS scoping behave exactly as in the Section library. On
 * top of the fields it gets the site tokens below — ready-made pieces of the
 * site that would be tedious or impossible to write by hand.
 */
class SitePartRenderer
{
    /**
     * Every site token, grouped for the builder's Insert list. A field with
     * the same key wins, as in the section engine.
     */
    public const TOKENS = [
        'site_brand' => ['group' => 'Site', 'label' => 'Logo + name', 'hint' => 'The logo, with the site name beside it as set under Settings → Site. Links home.'],
        'site_logo' => ['group' => 'Site', 'label' => 'Logo', 'hint' => 'Only the logo image (or the initials until there is one). Links home.'],
        'site_name' => ['group' => 'Site', 'label' => 'Site name', 'hint' => 'Plain text.'],
        'site_tagline' => ['group' => 'Site', 'label' => 'Tagline', 'hint' => 'Plain text, from Settings → Site.'],
        'home_url' => ['group' => 'Site', 'label' => 'Home link', 'hint' => 'For an href.'],
        'menu_header' => ['group' => 'Menus', 'label' => 'Header menu', 'hint' => 'The Header menu from Menu control, with drop-downs.'],
        'menu_footer' => ['group' => 'Menus', 'label' => 'Footer menu', 'hint' => 'The Footer menu as a plain list (class sp-list).'],
        'menu_toggle' => ['group' => 'Menus', 'label' => '☰ Menu button', 'hint' => 'Adds the class menu-open to this header or footer when pressed. Style the open menu with :root.menu-open … in the CSS.'],
        'search' => ['group' => 'Menus', 'label' => 'Search box', 'hint' => 'A search field and button that search the blog.'],
        'social' => ['group' => 'Contact', 'label' => 'Social icons', 'hint' => 'The social links from Settings → Site.'],
        'contact_phone' => ['group' => 'Contact', 'label' => 'Phone', 'hint' => 'A tap-to-call link, from Settings → Site.'],
        'contact_email' => ['group' => 'Contact', 'label' => 'Email', 'hint' => 'A mailto link, from Settings → Site.'],
        'contact_address' => ['group' => 'Contact', 'label' => 'Address', 'hint' => 'From Settings → Site.'],
        'year' => ['group' => 'Other', 'label' => 'This year', 'hint' => 'For the copyright line.'],
    ];

    public function __construct(private readonly SectionRenderer $sections, private readonly Site $site) {}

    /**
     * @param  array  $menus  ['Header' => tree, 'Footer' => tree] (MenuComposer)
     * @return array{html: string, css: string}
     */
    public function render(SitePart $part, array $menus): array
    {
        $html = (string) $part->html;
        $values = (array) ($part->values ?? []);

        return [
            'html' => $this->sections->renderTemplate($html, $values, (array) ($part->fields ?? []), $this->tokens($html, $menus, $part)),
            // Nothing in the CSS may close the <style> element it is printed in.
            'css' => str_ireplace('</style', '', $this->sections->templateCss((string) $part->css, '#site-'.$part->kind, $values)),
        ];
    }

    /** Only the tokens the template mentions are built — a menu is a query and a view. */
    private function tokens(string $html, array $menus, SitePart $part): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/', $html, $matches);
        $used = array_intersect(array_unique($matches[1]), array_keys(self::TOKENS));

        $out = [];
        foreach ($used as $token) {
            $out[$token] = match ($token) {
                'site_name' => e($this->site->name()),
                'site_tagline' => e((string) $this->site->get('tagline')),
                'home_url' => e(route('home', [], false)),
                'year' => date('Y'),
                default => trim(View::make('frontend.partials.site-parts.token', [
                    'token' => $token,
                    'menus' => $menus,
                    'caret' => $part->caret(),
                ])->render()),
            };
        }

        return $out;
    }
}
