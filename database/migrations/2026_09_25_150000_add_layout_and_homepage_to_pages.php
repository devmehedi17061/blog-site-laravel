<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two changes to how pages reach the public site.
 *
 * layout — whether the page wears the site header and footer. A landing page
 * wears neither.
 *
 * The homepage becomes a setting that names a page, rather than "whichever
 * page happens to have the slug /". The page that held "/" keeps being the
 * homepage, and gets a real address of its own, which then redirects to /.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('layout', 20)->default('default')->after('template');
        });

        $home = DB::table('pages')->where('slug', '/')->first();

        if ($home) {
            DB::table('pages')->where('id', $home->id)->update(['slug' => $this->freeSlug('/home')]);

            DB::table('settings')->updateOrInsert(
                ['key' => 'home_page_id'],
                ['value' => (string) $home->id, 'group' => 'reading', 'cast' => 'integer', 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    public function down(): void
    {
        $homeId = DB::table('settings')->where('key', 'home_page_id')->value('value');

        if ($homeId && ! DB::table('pages')->where('slug', '/')->exists()) {
            DB::table('pages')->where('id', (int) $homeId)->update(['slug' => '/']);
        }

        DB::table('settings')->where('key', 'home_page_id')->delete();

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }

    private function freeSlug(string $slug): string
    {
        $candidate = $slug;
        $n = 1;

        while (DB::table('pages')->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.(++$n);
        }

        return $candidate;
    }
};
