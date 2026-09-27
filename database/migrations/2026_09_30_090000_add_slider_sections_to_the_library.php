<?php

use App\Support\FieldKeys;
use App\Support\SliderSections;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the Hero Slider, Testimonial Slider and Call to Action Banner to the
 * section library. Only missing ones are added: a section someone has made
 * with the same slug is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (SliderSections::sections() as $slug => $section) {
            if (DB::table('section_templates')->where('slug', $slug)->exists()) {
                continue;
            }

            // A name must be unique too; step aside rather than fail.
            $name = $section['name'];
            if (DB::table('section_templates')->where('name', $name)->exists()) {
                $name .= ' (starter)';
            }

            DB::table('section_templates')->insert([
                'slug' => $slug,
                'name' => $name,
                'description' => $section['description'],
                'html' => $section['html'],
                'css' => $section['css'],
                'fields' => json_encode(FieldKeys::normalise($section['fields'])),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('section_templates')->whereIn('slug', array_keys(SliderSections::sections()))->delete();
    }
};
