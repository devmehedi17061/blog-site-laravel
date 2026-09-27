<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            // Which menu this belongs to. The header is the one that already
            // existed, so everything already in the table stays where it is.
            $table->string('location', 20)->default('Header')->after('id');

            // A name from App\Support\MenuIcons, not a path or a class — the
            // svg is drawn from one map the panel and the site both read.
            $table->string('icon', 40)->nullable()->after('label');

            $table->string('target', 10)->default('_self')->after('url');

            // Every read is "one location, in order".
            $table->index(['location', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropIndex(['location', 'position']);
            $table->dropColumn(['location', 'icon', 'target']);
        });
    }
};
