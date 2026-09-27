<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An image's size, and the smaller WebP copies made from it, so a phone is
 * sent a 480-pixel picture instead of the 4000-pixel original.
 *
 * variants: {"480": "media/variants/abc-480.webp", "960": …, "1600": …}
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->unsignedInteger('width')->nullable()->after('size');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->json('variants')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropColumn(['width', 'height', 'variants']);
        });
    }
};
