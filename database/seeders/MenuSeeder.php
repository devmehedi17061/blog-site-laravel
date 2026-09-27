<?php

namespace Database\Seeders;

use App\Actions\Menu\SaveMenu;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * The two menus the site ships with.
 *
 * Written as flat lists with depths, the same shape the builder saves, so
 * what is seeded here is exactly what the builder would produce.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $save = app(SaveMenu::class);

        if (MenuItem::where('location', 'Header')->doesntExist()) {
            $save->handle('Header', [
                ['label' => 'Home', 'url' => '/', 'icon' => 'home', 'depth' => 0],
                ['label' => 'Services', 'url' => '/services', 'icon' => 'briefcase', 'depth' => 0],
                ['label' => 'Consulting', 'url' => '/services/consulting', 'depth' => 1],
                ['label' => 'Training', 'url' => '/services/training', 'depth' => 1],
                ['label' => 'Pricing', 'url' => '/pricing', 'icon' => 'tag', 'depth' => 0],
                ['label' => 'Blog', 'url' => '/blog', 'icon' => 'book', 'depth' => 0],
                ['label' => 'Contact', 'url' => '/contact', 'icon' => 'mail', 'depth' => 0],
                ['label' => 'Client portal', 'url' => '/portal', 'icon' => 'lock',
                    'visibility' => 'Authenticated', 'depth' => 0],
            ]);
        }

        // These three used to be hardcoded in the footer partial.
        if (MenuItem::where('location', 'Footer')->doesntExist()) {
            $save->handle('Footer', [
                ['label' => 'Home', 'url' => '/', 'depth' => 0],
                ['label' => 'Blog', 'url' => '/blog', 'depth' => 0],
                ['label' => 'Contact', 'url' => '/contact', 'depth' => 0],
                ['label' => 'Admin', 'url' => '/admin/login', 'depth' => 0],
            ]);
        }
    }
}
