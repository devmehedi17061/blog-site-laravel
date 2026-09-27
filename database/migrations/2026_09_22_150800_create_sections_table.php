<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('layout')->default('One column');
            $table->string('theme')->default('Light');
            $table->string('mode')->default('Modules');
            $table->longText('html')->nullable();
            $table->longText('css')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

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

    public function down(): void
    {
        Schema::dropIfExists('section_blocks');
        Schema::dropIfExists('sections');
    }
};
