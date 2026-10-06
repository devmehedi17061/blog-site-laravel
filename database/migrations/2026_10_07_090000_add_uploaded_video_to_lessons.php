<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A video lesson can now be an uploaded file instead of a YouTube / Vimeo
 * link. The file lives on the private "lessons" disk and is streamed only
 * to students who may watch the lesson; there is no public address for it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->string('video_path')->nullable()->after('video_url');
            $table->string('video_name')->nullable()->after('video_path');
            $table->unsignedBigInteger('video_size')->nullable()->after('video_name');
        });
    }

    public function down(): void
    {
        Schema::table('lessons', function (Blueprint $table) {
            $table->dropColumn(['video_path', 'video_name', 'video_size']);
        });
    }
};
