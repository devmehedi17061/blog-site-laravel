<?php

namespace Database\Seeders;

use App\Actions\Pages\SaveSections;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        $arif = User::where('email', 'arif@example.com')->first();
        $mira = User::where('email', 'mira@example.com')->first();
        $nadia = User::where('email', 'nadia@example.com')->first();

        foreach (['Engineering', 'Company', 'Product'] as $name) {
            Category::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }

        $pages = [
            // Keeps the site header and footer: it is the front door, not a
            // campaign page, even though it starts from the landing sections.
            ['title' => 'Home', 'slug' => '/home', 'status' => 'Published', 'template' => 'landing', 'layout' => 'default', 'published_at' => now()->subDays(8)],
            ['title' => 'About us', 'slug' => '/about', 'status' => 'Published', 'template' => 'about', 'published_at' => now()->subDays(20)],
            ['title' => 'Services', 'slug' => '/services', 'status' => 'Draft', 'template' => 'landing', 'published_at' => null],
            ['title' => 'Pricing', 'slug' => '/pricing', 'status' => 'Scheduled', 'template' => 'landing', 'published_at' => now()->addDays(5)],
            ['title' => 'Contact', 'slug' => '/contact', 'status' => 'Published', 'template' => 'contact', 'published_at' => now()->subMonth()],
        ];

        $saveSections = app(SaveSections::class);

        foreach ($pages as $attributes) {
            $template = $attributes['template'];
            $page = Page::updateOrCreate(['slug' => $attributes['slug']], $attributes + ['user_id' => $arif?->id]);

            // Only fill a page that has no sections, so re-seeding never wipes
            // work someone did in the builder.
            if ($page->sections()->doesntExist()) {
                $saveSections->fromTemplate($page, $template);
            }
        }

        // "/" shows the Home page — unless someone already chose another.
        if (! Page::homeId()) {
            Page::setHome(Page::where('slug', '/home')->first());
        }

        $posts = [
            ['title' => 'Shipping the new checkout', 'category' => 'engineering', 'author' => $arif, 'status' => 'Published', 'published_at' => now()->subDays(4)],
            ['title' => 'Notes on queue workers', 'category' => 'engineering', 'author' => $mira, 'status' => 'Draft', 'published_at' => null],
            ['title' => 'Hiring a backend engineer', 'category' => 'company', 'author' => $nadia, 'status' => 'Published', 'published_at' => now()->subDays(13)],
            ['title' => 'Q3 roadmap review', 'category' => 'product', 'author' => $arif, 'status' => 'Scheduled', 'published_at' => now()->addDays(8)],
        ];

        foreach ($posts as $post) {
            $category = Category::where('slug', $post['category'])->first();

            Post::updateOrCreate(
                ['slug' => Str::slug($post['title'])],
                [
                    'title' => $post['title'],
                    'user_id' => $post['author']?->id,
                    'category_id' => $category?->id,
                    'status' => $post['status'],
                    'published_at' => $post['published_at'],
                    'excerpt' => 'Placeholder excerpt for '.$post['title'].'.',
                    'body' => '<p>Replace this body from the post editor.</p>',
                ]
            );
        }

        $media = [
            ['name' => 'hero-factory.jpg', 'mime_type' => 'image/jpeg', 'size' => 862208, 'user_id' => $nadia?->id],
            ['name' => 'pricing-table.png', 'mime_type' => 'image/png', 'size' => 122880, 'user_id' => $mira?->id],
            ['name' => 'annual-report.pdf', 'mime_type' => 'application/pdf', 'size' => 3250585, 'user_id' => $arif?->id],
            ['name' => 'team-portrait.jpg', 'mime_type' => 'image/jpeg', 'size' => 1468006, 'user_id' => $arif?->id],
        ];

        foreach ($media as $file) {
            Media::updateOrCreate(
                ['file_name' => $file['name']],
                $file + ['path' => 'media/'.$file['name'], 'disk' => 'public']
            );
        }

        if (ActivityLog::count() > 0) {
            return;
        }

        $activity = [
            ['user_id' => $arif?->id, 'action' => 'Updated section', 'description' => 'Home / Hero banner', 'at' => now()->subMinutes(2)],
            ['user_id' => $mira?->id, 'action' => 'Published post', 'description' => 'Shipping the new checkout', 'at' => now()->subMinutes(18)],
            ['user_id' => null, 'action' => 'Queue drained', 'description' => '412 jobs', 'at' => now()->subHour()],
            ['user_id' => $nadia?->id, 'action' => 'Uploaded file', 'description' => 'hero-factory.jpg', 'at' => now()->subHours(3)],
            ['user_id' => $arif?->id, 'action' => 'Changed role', 'description' => 'Tanvir Islam moved to Viewer', 'at' => now()->subDay()],
            ['user_id' => null, 'action' => 'Backup completed', 'description' => 'db-backup.sql.gz', 'at' => now()->subDay()->subHours(2)],
        ];

        foreach ($activity as $entry) {
            $at = $entry['at'];
            unset($entry['at']);

            ActivityLog::create($entry + ['ip_address' => '127.0.0.1'])
                ->forceFill(['created_at' => $at, 'updated_at' => $at])
                ->save();
        }
    }
}
