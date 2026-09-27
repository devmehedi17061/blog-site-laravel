<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            // Folders are a database grouping, not a path on disk — renaming
            // one must never change a URL that is already published.
            $table->foreignId('folder_id')->nullable()->after('user_id')
                ->constrained('media_folders')->nullOnDelete();
            $table->text('caption')->nullable()->after('alt_text');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->dropForeign(['folder_id']);
            $table->dropColumn(['folder_id', 'caption']);
        });
    }
};
