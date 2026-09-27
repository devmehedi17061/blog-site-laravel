<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What Settings → Security needs to know about each account.
 *
 * two_factor_method is "app" (an authenticator app, TOTP) or "email" (a code
 * sent at sign-in); the secret and recovery codes are stored encrypted, and
 * two_factor_confirmed_at is only set once a first code has been typed back,
 * so half-finished setups never lock anyone out.
 *
 * password_changed_at drives password expiry. Existing accounts start from
 * now, so switching expiry on never expires everyone at once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('two_factor_method', 10)->nullable()->after('password');
            $table->text('two_factor_secret')->nullable()->after('two_factor_method');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
            $table->timestamp('password_changed_at')->nullable()->after('two_factor_confirmed_at');
        });

        DB::table('users')->update(['password_changed_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'two_factor_method', 'two_factor_secret', 'two_factor_recovery_codes',
                'two_factor_confirmed_at', 'password_changed_at',
            ]);
        });
    }
};
