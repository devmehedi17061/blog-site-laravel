<?php

namespace App\Support;

/**
 * The icons a menu item can wear.
 *
 * One map, read by three places: the picker in the panel, the tree beside it,
 * and the public header. Storing a *name* rather than markup means an icon can
 * be redrawn here and every menu that uses it follows — and that nothing a
 * menu stores is ever injected into a page as raw svg.
 *
 * Each entry is the inner geometry of a 24×24 line icon. The wrapper (stroke
 * width, caps, joins) is added by icon(), so every icon matches.
 */
class MenuIcons
{
    /** @return array<string, string> name => svg body */
    public static function all(): array
    {
        return [
            'home' => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9.5 21v-6h5v6"/>',
            'grid' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>',
            'layers' => '<path d="m12 3 9 5-9 5-9-5 9-5Z"/><path d="m3 13 9 5 9-5"/>',
            'file' => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z"/><path d="M14 3v5h5"/>',
            'book' => '<path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v16H6.5A2.5 2.5 0 0 0 4 20.5V4.5Z"/><path d="M4 20.5A2.5 2.5 0 0 1 6.5 18H20v4H6.5A2.5 2.5 0 0 1 4 19.5"/>',
            'edit' => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
            'tag' => '<path d="M20.6 13.4 12 22l-9-9V3h10l7.6 7.6a2 2 0 0 1 0 2.8Z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
            'star' => '<path d="m12 3 2.9 5.9 6.5.9-4.7 4.6 1.1 6.5L12 17.8 6.2 20.9l1.1-6.5L2.6 9.8l6.5-.9L12 3Z"/>',
            'heart' => '<path d="M20.3 5.7a5 5 0 0 0-7.1 0L12 6.9l-1.2-1.2a5 5 0 1 0-7.1 7.1L12 21l8.3-8.2a5 5 0 0 0 0-7.1Z"/>',
            'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
            'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 21a6.5 6.5 0 0 1 13 0"/><path d="M16 5a3.5 3.5 0 0 1 0 7"/><path d="M18 14.5A6.5 6.5 0 0 1 21.5 21"/>',
            'briefcase' => '<rect x="2.5" y="7" width="19" height="13"/><path d="M8.5 7V5a2 2 0 0 1 2-2h3a2 2 0 0 1 2 2v2"/><path d="M2.5 12.5h19"/>',
            'building' => '<rect x="4" y="3" width="16" height="18"/><path d="M8 7h2M14 7h2M8 11h2M14 11h2M8 15h2M14 15h2"/>',
            'cart' => '<circle cx="9.5" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3h3l2.4 12h11l2.1-8.5H6"/>',
            'gift' => '<rect x="3" y="9" width="18" height="12"/><path d="M3 13h18"/><path d="M12 9v12"/><path d="M12 9S9.5 3.5 7 5.5 9 9 12 9Zm0 0s2.5-5.5 5-3.5S15 9 12 9Z"/>',
            'credit-card' => '<rect x="2.5" y="5" width="19" height="14"/><path d="M2.5 10h19"/>',
            'phone' => '<path d="M6 3h4l2 5-2.5 1.5a12 12 0 0 0 5 5L16 12l5 2v4a2 2 0 0 1-2.2 2A17 17 0 0 1 4 5.2 2 2 0 0 1 6 3Z"/>',
            'mail' => '<rect x="2.5" y="5" width="19" height="14"/><path d="m2.5 6 9.5 7 9.5-7"/>',
            'map-pin' => '<path d="M12 21s7-6.2 7-11a7 7 0 1 0-14 0c0 4.8 7 11 7 11Z"/><circle cx="12" cy="10" r="2.6"/>',
            'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18"/><path d="M12 3a15 15 0 0 1 0 18 15 15 0 0 1 0-18Z"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
            'calendar' => '<rect x="3" y="5" width="18" height="16"/><path d="M3 10h18M8 3v4M16 3v4"/>',
            'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5.5l3.5 2"/>',
            'chat' => '<path d="M21 12a8 8 0 0 1-8 8H7l-4 3 1.2-4.4A8 8 0 1 1 21 12Z"/>',
            'camera' => '<path d="M3 7h3.5L8 5h8l1.5 2H21v13H3V7Z"/><circle cx="12" cy="13" r="3.6"/>',
            'play' => '<circle cx="12" cy="12" r="9"/><path d="m10 8.5 6 3.5-6 3.5v-7Z"/>',
            'download' => '<path d="M12 3v12"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4 20h16"/>',
            'link' => '<path d="M10 13.5a4 4 0 0 0 5.7 0l3-3a4 4 0 1 0-5.7-5.7L11.5 6.3"/><path d="M14 10.5a4 4 0 0 0-5.7 0l-3 3a4 4 0 1 0 5.7 5.7l1.5-1.5"/>',
            'shield' => '<path d="M12 2.5 20 6v5.5c0 5-3.4 8.7-8 10-4.6-1.3-8-5-8-10V6l8-3.5Z"/>',
            'lock' => '<rect x="4.5" y="10" width="15" height="11"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
            'settings' => '<circle cx="12" cy="12" r="3.2"/><path d="M12 2.5v3M12 18.5v3M21.5 12h-3M5.5 12h-3M18.7 5.3l-2.1 2.1M7.4 16.6l-2.1 2.1M18.7 18.7l-2.1-2.1M7.4 7.4 5.3 5.3"/>',
            'help' => '<circle cx="12" cy="12" r="9"/><path d="M9.6 9.3a2.5 2.5 0 1 1 3.4 2.3c-.7.3-1 .9-1 1.6v.4"/><path d="M12 17.2h.01"/>',
            'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6"/><path d="M12 7.5h.01"/>',
            'bolt' => '<path d="M13.5 2 4 14h7l-.5 8L20 10h-7l.5-8Z"/>',
            'sparkle' => '<path d="m12 3 1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9L12 3Z"/><path d="m18.5 15.5.8 2.2 2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8.8-2.2Z"/>',
            'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        ];
    }

    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function has(?string $name): bool
    {
        return $name !== null && array_key_exists($name, self::all());
    }

    /**
     * One icon as inline svg, or an empty string for a name that is not in
     * the map — a menu saved with an icon that has since been removed simply
     * loses the icon rather than printing anything odd.
     */
    public static function svg(?string $name, string $class = 'menu-icon', int $size = 16): string
    {
        $body = self::all()[$name] ?? null;

        if ($body === null) {
            return '';
        }

        return '<svg class="'.e($class).'" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24"'
            .' fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"'
            .' stroke-linejoin="round" aria-hidden="true" focusable="false">'.$body.'</svg>';
    }
}
