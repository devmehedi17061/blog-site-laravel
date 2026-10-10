<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bangla / English for students.
 *
 * Each account remembers the language its owner picked, so their emails and
 * notifications arrive in it too. And the headers built in Header & footer
 * get the new {{ language }} switch, just before their search box (or at the
 * end) — a header someone already has a switch in is left alone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 8)->nullable()->after('phone');
        });

        foreach (DB::table('site_parts')->where('kind', 'header')->get(['id', 'html']) as $part) {
            $html = (string) $part->html;
            if (str_contains($html, '{{ language }}')) {
                continue;
            }

            $html = str_contains($html, '{{ search }}')
                ? str_replace('{{ search }}', '{{ language }}'."\n      ".'{{ search }}', $html)
                : preg_replace('~(</[a-z]+>\s*)$~i', "  {{ language }}\n$1", $html, 1);

            DB::table('site_parts')->where('id', $part->id)->update(['html' => $html]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('site_parts')->where('kind', 'header')->get(['id', 'html']) as $part) {
            DB::table('site_parts')->where('id', $part->id)->update([
                'html' => preg_replace('~\s*\{\{ language \}\}~', '', (string) $part->html),
            ]);
        }

        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('locale'));
    }
};
