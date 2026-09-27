<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('section_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            // The token name used in the section's HTML: {{ key }}.
            $table->string('key');
            $table->string('label');
            $table->enum('type', ['text', 'textarea', 'richtext', 'image', 'url', 'number', 'color', 'choice'])
                ->default('text');
            $table->text('value')->nullable();
            $table->json('options')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['section_id', 'key']);
        });

        // The block palette is gone; sections are now fields + HTML + CSS.
        Schema::dropIfExists('section_blocks');

        DB::table('sections')->where('mode', 'Modules')->update(['mode' => 'Fields']);
    }

    public function down(): void
    {
        DB::table('sections')->where('mode', 'Fields')->update(['mode' => 'Modules']);

        Schema::dropIfExists('section_fields');

        Schema::create('section_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['heading', 'text', 'richtext', 'media', 'anchor', 'button', 'divider'])->default('text');
            $table->string('text')->nullable();
            $table->longText('body')->nullable();
            $table->string('href')->nullable();
            $table->string('variant')->nullable();
            $table->string('caption')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }
};
