<?php

namespace Database\Seeders;

use App\Models\SectionTemplate;
use App\Models\User;
use App\Support\PageTemplates;
use Illuminate\Database\Seeder;

/**
 * Seeds the shared section library. Runs before ContentSeeder, because the
 * starter pages attach their sections to these templates by slug.
 */
class SectionLibrarySeeder extends Seeder
{
    public function run(): void
    {
        $owner = User::where('email', 'arif@example.com')->value('id');

        // The slider sections (App\Support\SliderSections) join the starters.
        foreach (PageTemplates::librarySections() + \App\Support\SliderSections::sections() as $slug => $template) {
            $existing = SectionTemplate::where('slug', $slug)->first();

            // Markup someone has since edited in the builder is left alone;
            // only a missing template is created.
            if ($existing) {
                continue;
            }

            SectionTemplate::create([
                'user_id' => $owner,
                'slug' => $slug,
                'name' => $template['name'],
                'description' => $template['description'],
                'html' => $template['html'],
                'css' => $template['css'],
                'fields' => $template['fields'],
            ]);
        }
    }
}
