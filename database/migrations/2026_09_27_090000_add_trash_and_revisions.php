<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The records that go to the Trash instead of being deleted outright. */
    private const TRASHABLE = ['posts', 'pages', 'media'];

    public function up(): void
    {
        foreach (self::TRASHABLE as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
                $table->foreignId('deleted_by')->nullable()->after('deleted_at')
                    ->constrained('users')->nullOnDelete();
            });
        }

        // One saved version of a post or page. `data` is the whole record as
        // it stood after that save — sections included for a page — so any
        // version can be shown or brought back on its own.
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->morphs('revisionable');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label')->nullable();
            $table->longText('data');
            $table->char('hash', 40);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');

        foreach (self::TRASHABLE as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('deleted_by');
                $table->dropSoftDeletes();
            });
        }
    }
};
