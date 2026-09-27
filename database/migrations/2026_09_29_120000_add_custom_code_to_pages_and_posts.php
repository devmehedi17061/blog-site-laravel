<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A page's or post's own CSS and JavaScript — the editor's Code tab. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pages', 'posts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->longText('custom_css')->nullable();
                $table->longText('custom_js')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['pages', 'posts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['custom_css', 'custom_js']);
            });
        }
    }
};
