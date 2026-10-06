<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A menu item can now be for "Guests" only — "Sign in" and "Register",
 * which should vanish once a student is signed in. The column was an enum
 * of the three old values; a plain string lets the list grow in code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('visibility', 20)->default('Public')->change();
        });
    }

    public function down(): void
    {
        DB::table('menu_items')->where('visibility', 'Guests')->update(['visibility' => 'Public']);

        Schema::table('menu_items', function (Blueprint $table) {
            $table->enum('visibility', ['Public', 'Authenticated', 'Admin only'])->default('Public')->change();
        });
    }
};
