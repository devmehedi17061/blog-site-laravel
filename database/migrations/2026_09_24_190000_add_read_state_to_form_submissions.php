<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            // Null means unread. A timestamp rather than a flag, so the inbox
            // can also say when someone first looked at it.
            $table->timestamp('read_at')->nullable()->after('ip_address');

            // Every inbox view is "newest first, unread first", and the badge
            // counts unread across every form.
            $table->index(['read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropIndex(['read_at', 'created_at']);
            $table->dropColumn('read_at');
        });
    }
};
