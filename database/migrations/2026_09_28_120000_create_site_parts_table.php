<?php

use App\Support\SitePartStarters;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Headers and footers, built like library sections: HTML, CSS, fields — and,
 * since the whole site shares them, their values too. One of each kind is
 * "in use"; the rest wait in the library.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_parts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->longText('html')->nullable();
            $table->longText('css')->nullable();
            $table->json('fields')->nullable();
            $table->json('values')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['kind', 'is_active']);
        });

        $now = now();

        foreach (SitePartStarters::all() as $part) {
            DB::table('site_parts')->insert([
                'kind' => $part['kind'],
                'name' => $part['name'],
                'slug' => $part['kind'].'-'.Str::slug($part['name']),
                'description' => $part['description'],
                'html' => $part['html'],
                'css' => $part['css'],
                'fields' => json_encode($part['fields']),
                'values' => json_encode($part['values']),
                'settings' => json_encode($part['settings']),
                'is_active' => $part['active'] ?? false,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // The zone-based builder this replaces kept its layout in settings.
        DB::table('settings')->where('key', 'like', 'layout.%')->delete();
    }

    public function down(): void
    {
        Schema::dropIfExists('site_parts');
    }
};
