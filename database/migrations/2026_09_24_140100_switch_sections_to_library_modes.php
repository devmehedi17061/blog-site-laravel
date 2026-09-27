<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Layout and theme are replaced by the free-form style panel.
        Schema::table('sections', function (Blueprint $table) {
            $table->dropColumn(['layout', 'theme']);
        });

        DB::table('sections')->where('mode', 'Fields')->update(['mode' => 'Library']);
        DB::table('sections')->where('mode', 'Code')->update(['mode' => 'Blank']);
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->string('layout')->default('One column')->after('name');
            $table->string('theme')->default('Light')->after('layout');
        });

        DB::table('sections')->where('mode', 'Library')->update(['mode' => 'Fields']);
        DB::table('sections')->where('mode', 'Blank')->update(['mode' => 'Code']);
    }
};
