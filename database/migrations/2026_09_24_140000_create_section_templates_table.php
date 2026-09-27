<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The reusable library: one row per section you build once and drop
        // onto many pages. Pages hold the values; this holds the shape.
        Schema::create('section_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->longText('html')->nullable();
            $table->longText('css')->nullable();
            // The field definition tree, including repeatable groups.
            $table->json('fields')->nullable();
            $table->timestamps();
        });

        Schema::table('sections', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('page_id')
                ->constrained('section_templates')->nullOnDelete();
            // Field values for this page's copy of the template.
            $table->json('values')->nullable()->after('css');
            // Free-form wrapper styling, applied inline.
            $table->json('style')->nullable()->after('values');
        });

        Schema::dropIfExists('section_fields');
    }

    public function down(): void
    {
        Schema::table('sections', function (Blueprint $table) {
            $table->dropForeign(['template_id']);
            $table->dropColumn(['template_id', 'values', 'style']);
        });

        Schema::dropIfExists('section_templates');

        Schema::create('section_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->enum('type', ['text', 'textarea', 'richtext', 'image', 'url', 'number', 'color', 'choice'])->default('text');
            $table->text('value')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->unique(['section_id', 'key']);
        });
    }
};
