<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // nullOnDelete rather than cascade: losing a parent must never
            // silently take a whole branch of categories — and every post
            // filed under them — with it. The controller decides where
            // children go, and says so before it does.
            $table->foreignId('parent_id')->nullable()->after('id')
                ->constrained('categories')->nullOnDelete();

            $table->foreignId('media_id')->nullable()->after('description')
                ->constrained('media')->nullOnDelete();

            // A category archive is a public page, so it gets the same SEO
            // fields every other public page here has.
            $table->string('meta_title')->nullable()->after('media_id');
            $table->string('meta_description', 500)->nullable()->after('meta_title');

            $table->unsignedTinyInteger('depth')->default(0)->after('meta_description');
            $table->unsignedInteger('position')->default(0)->after('depth');

            // Every read is "one branch, in order".
            $table->index(['parent_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'position']);
            $table->dropConstrainedForeignId('parent_id');
            $table->dropConstrainedForeignId('media_id');
            $table->dropColumn(['meta_title', 'meta_description', 'depth', 'position']);
        });
    }
};
