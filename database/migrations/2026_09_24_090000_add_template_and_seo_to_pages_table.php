<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->string('template')->default('blank')->after('status');
            $table->foreignId('media_id')->nullable()->after('user_id')
                ->constrained('media')->nullOnDelete();
            $table->string('focus_keyword')->nullable()->after('meta_description');
            $table->string('canonical_url')->nullable()->after('focus_keyword');
            $table->boolean('noindex')->default(false)->after('canonical_url');
        });
    }

    public function down(): void
    {
        Schema::table('pages', function (Blueprint $table) {
            $table->dropForeign(['media_id']);
            $table->dropColumn(['template', 'media_id', 'focus_keyword', 'canonical_url', 'noindex']);
        });
    }
};
