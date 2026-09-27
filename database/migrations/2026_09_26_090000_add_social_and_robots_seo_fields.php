<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The fuller SEO panel, for posts and pages alike.
 *
 * robots replaces the noindex checkbox: index/noindex and follow/nofollow are
 * two separate decisions, and a single yes/no could only express one of them.
 * Anything that was hidden before stays hidden, as "noindex, follow".
 *
 * og_title / og_description / og_image are what a share on Facebook, X,
 * LinkedIn or WhatsApp shows, when that should differ from the search result.
 */
return new class extends Migration
{
    private const TABLES = ['posts', 'pages'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('robots', 40)->default('index, follow')->after('canonical_url');
                $table->string('og_title')->nullable()->after('robots');
                $table->text('og_description')->nullable()->after('og_title');
                $table->string('og_image', 500)->nullable()->after('og_description');
            });

            DB::table($name)->where('noindex', true)->update(['robots' => 'noindex, follow']);

            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('noindex');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->boolean('noindex')->default(false)->after('canonical_url');
            });

            DB::table($name)->where('robots', 'like', 'noindex%')->update(['noindex' => true]);

            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['robots', 'og_title', 'og_description', 'og_image']);
            });
        }
    }
};
