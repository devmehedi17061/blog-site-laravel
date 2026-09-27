<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * published_at is a date people type, and MySQL's TIMESTAMP stops at
 * 2038-01-19: scheduling anything later failed with a server error instead
 * of saving. DATETIME runs to the year 9999 and keeps the same values.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['pages', 'posts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dateTime('published_at')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['pages', 'posts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->timestamp('published_at')->nullable()->change();
            });
        }
    }
};
