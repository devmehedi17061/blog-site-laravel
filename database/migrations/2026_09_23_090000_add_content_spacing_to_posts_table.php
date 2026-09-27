<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            // Paragraph spacing for the whole post, chosen in the editor and
            // applied to the public page so both read the same.
            $table->enum('content_spacing', ['tight', 'normal', 'loose'])
                ->default('normal')
                ->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('content_spacing');
        });
    }
};
