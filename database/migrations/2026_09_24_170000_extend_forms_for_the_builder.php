<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            // A form can be switched off without deleting it, which would
            // take its submissions with it.
            $table->string('status', 20)->default('Active')->after('description');
            $table->text('success_message')->nullable()->after('submit_label');

            // Some forms only need to email someone; keeping every payload
            // is then a liability rather than a feature.
            $table->boolean('store_submissions')->default(true)->after('notify_email');
        });

        Schema::table('form_fields', function (Blueprint $table) {
            // Was an enum. A whitelist in the model is easier to extend than
            // a migration every time the builder learns a new field type.
            $table->string('type', 32)->default('text')->change();

            $table->string('width', 10)->default('full')->after('placeholder');
            $table->string('help')->nullable()->after('placeholder');
        });
    }

    public function down(): void
    {
        Schema::table('forms', function (Blueprint $table) {
            $table->dropColumn(['status', 'success_message', 'store_submissions']);
        });

        Schema::table('form_fields', function (Blueprint $table) {
            $table->dropColumn(['width', 'help']);
        });
    }
};
