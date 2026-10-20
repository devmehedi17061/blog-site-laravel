<?php

namespace App\Support;

use App\Models\Page;

/**
 * Structured data (schema.org JSON-LD) a page earns from its content, for
 * search engines, beside the WebPage block the page view always prints:
 *
 *   FAQPage         from any section holding a `faqs` group of question /
 *                   answer items — the FAQ sections — so the questions can
 *                   show as rich results
 *   BreadcrumbList  from the page's address: Home › Services › SAP Solutions,
 *                   each step named after the page at that address
 */
class PageSchema
{
    /** @return array<int, array<string, mixed>> */
    public static function for(Page $page): array
    {
        return array_values(array_filter([self::faq($page), self::breadcrumbs($page)]));
    }

    private static function faq(Page $page): ?array
    {
        $questions = [];
        foreach ($page->sections as $section) {
            foreach ((array) (($section->values ?? [])['faqs'] ?? []) as $item) {
                $q = trim(strip_tags((string) ($item['question'] ?? '')));
                $a = trim(strip_tags((string) ($item['answer'] ?? '')));
                if ($q !== '' && $a !== '') {
                    $questions[$q] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
                }
            }
        }

        return $questions ? ['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_values($questions)] : null;
    }

    private static function breadcrumbs(Page $page): ?array
    {
        $path = trim((string) $page->slug, '/');
        if ($page->isHome() || $path === '') {
            return null;
        }

        $crumbs = [['name' => 'Home', 'url' => url('/')]];
        $parts = explode('/', $path);
        $titles = Page::query()->published()
            ->whereIn('slug', array_map(fn ($i) => '/'.implode('/', array_slice($parts, 0, $i + 1)), array_keys($parts)))
            ->pluck('title', 'slug');
        foreach ($parts as $i => $part) {
            $slug = '/'.implode('/', array_slice($parts, 0, $i + 1));
            $crumbs[] = ['name' => $titles[$slug] ?? ucwords(str_replace('-', ' ', $part)), 'url' => url($slug)];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn ($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c['name'], 'item' => $c['url']], $crumbs, array_keys($crumbs)),
        ];
    }
}
