<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payment verification. Every payment a student reports is its own record —
 * how much, from which number, the transaction ID, an optional screenshot —
 * and an admin verifies or rejects it. A rejected payment keeps its reason,
 * and the student can send a new one; the history stays.
 *
 * Also: where each student last was in a course (for "continue where you
 * left off"), and a phone number on accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('amount', 10, 2);
            // What the course cost when the student paid, to spot short payments.
            $table->decimal('expected_amount', 10, 2)->default(0);
            $table->string('method', 60);
            $table->string('sender_number', 40)->nullable();
            $table->string('transaction_id', 120);
            // A screenshot or PDF of the payment, on the private disk.
            $table->string('proof_path')->nullable();
            $table->text('note')->nullable();

            $table->string('status', 20)->default('Pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->string('rejection_reason', 500)->nullable();

            $table->timestamps();

            $table->index(['method', 'transaction_id']);
        });

        // Payments reported before this table existed live on as records.
        $now = now();
        foreach (DB::table('enrollments')->whereNotNull('transaction_id')->where('amount', '>', 0)->get() as $e) {
            DB::table('payments')->insert([
                'user_id' => $e->user_id,
                'course_id' => $e->course_id,
                'enrollment_id' => $e->id,
                'amount' => $e->amount,
                'expected_amount' => $e->amount,
                'method' => $e->payment_method ?: 'Unknown',
                'transaction_id' => $e->transaction_id,
                'status' => match ($e->status) {
                    'Active' => 'Verified',
                    'Cancelled' => 'Rejected',
                    default => 'Pending',
                },
                'reviewed_at' => $e->status === 'Pending' ? null : $now,
                'created_at' => $e->created_at ?? $now,
                'updated_at' => $now,
            ]);
        }

        Schema::table('enrollments', function (Blueprint $table) {
            $table->foreignId('last_lesson_id')->nullable()->after('completed_at')->constrained('lessons')->nullOnDelete();
            $table->dateTime('last_activity_at')->nullable()->after('last_lesson_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 40)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('phone'));
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_lesson_id');
            $table->dropColumn('last_activity_at');
        });
        Schema::dropIfExists('payments');
    }
};
