<?php

namespace App\View\Composers;

use App\Models\MenuItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Feeds the public header and footer their navigation, as nested trees.
 *
 * Both menus go to both partials: the header & footer builder can place the
 * Header menu in the footer or the other way round. Built once per request,
 * however many times the partials render.
 *
 * Items are filtered by visibility against whoever is signed in, so an
 * "Authenticated" item never reaches a guest's HTML at all — it is absent,
 * not hidden with CSS.
 */
class MenuComposer
{
    private ?array $menus = null;

    public function compose(View $view): void
    {
        $this->menus ??= [
            'Header' => MenuItem::tree('Header', Auth::user()),
            'Footer' => MenuItem::tree('Footer', Auth::user()),
        ];

        $view->with('menus', $this->menus);
    }
}
