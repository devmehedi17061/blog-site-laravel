<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The LMS's second round:
 *
 *   coupons ── coupon_course        which courses a coupon is for (none = all)
 *          └─ coupon_redemptions    each use, so limits can be counted
 *   lessons ── quiz_questions       a lesson of type "quiz"
 *          └─ quiz_attempts         every try, graded on the server
 *   discussions                     questions and replies under a lesson
 *   course_announcements            news an instructor sends to a course
 *   wishlists                       courses a student saved for later
 *
 * and a "coupons" permission module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('description')->nullable();
            // percent: value is 1–100; fixed: value is money off.
            $table->string('type', 10)->default('percent');
            $table->decimal('value', 10, 2);
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_user')->default(1);
            $table->decimal('min_amount', 10, 2)->nullable();
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('coupon_course', function (Blueprint $table) {
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->primary(['coupon_id', 'course_id']);
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('discount', 10, 2);
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('coupon_id')->nullable()->after('enrollment_id')->constrained()->nullOnDelete();
            $table->string('coupon_code', 40)->nullable()->after('coupon_id');
            $table->decimal('discount', 10, 2)->default(0)->after('expected_amount');
        });

        // Quizzes: a lesson of type "quiz" holds questions.
        Schema::table('lessons', function (Blueprint $table) {
            $table->unsignedTinyInteger('pass_mark')->default(70)->after('is_preview');
            $table->unsignedSmallInteger('max_attempts')->default(0)->after('pass_mark');
            $table->boolean('shuffle_questions')->default(false)->after('max_attempts');
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            // single: one right answer; multiple: several; true_false: two options.
            $table->string('type', 20)->default('single');
            // [{"text": "…", "correct": true}, …]
            $table->json('options');
            $table->text('explanation')->nullable();
            $table->unsignedSmallInteger('points')->default(1);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('quiz_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('score');
            $table->unsignedSmallInteger('earned');
            $table->unsignedSmallInteger('possible');
            $table->boolean('passed');
            // question id => the option indexes chosen
            $table->json('answers');
            $table->timestamps();

            $table->index(['user_id', 'lesson_id']);
        });

        Schema::create('discussions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // A reply points at the question it answers.
            $table->foreignId('parent_id')->nullable()->constrained('discussions')->cascadeOnDelete();
            $table->text('body');
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();

            $table->index(['lesson_id', 'parent_id']);
        });

        Schema::create('course_announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'course_id']);
        });

        // "coupons" permissions, for every role that can approve enrollments
        // (they already decide who pays what).
        $now = now();
        $ids = [];
        foreach (['view', 'create', 'update', 'delete'] as $ability) {
            DB::table('permissions')->updateOrInsert(
                ['slug' => "coupons.{$ability}"],
                ['name' => ucfirst($ability).' Coupons', 'group' => 'LMS', 'created_at' => $now, 'updated_at' => $now]
            );
            $ids[] = DB::table('permissions')->where('slug', "coupons.{$ability}")->value('id');
        }

        $enrollUpdate = DB::table('permissions')->where('slug', 'enrollments.update')->value('id');
        $roles = DB::table('permission_role')->where('permission_id', $enrollUpdate)->pluck('role_id')
            ->merge(DB::table('roles')->where('slug', 'super-admin')->pluck('id'))->unique();

        foreach ($roles as $roleId) {
            foreach ($ids as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['permission_id' => $permissionId, 'role_id' => $roleId]);
            }
        }
    }

    public function down(): void
    {
        $ids = DB::table('permissions')->where('slug', 'like', 'coupons.%')->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();

        Schema::dropIfExists('wishlists');
        Schema::dropIfExists('course_announcements');
        Schema::dropIfExists('discussions');
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::table('lessons', fn (Blueprint $t) => $t->dropColumn(['pass_mark', 'max_attempts', 'shuffle_questions']));
        Schema::table('payments', function (Blueprint $t) {
            $t->dropConstrainedForeignId('coupon_id');
            $t->dropColumn(['coupon_code', 'discount']);
        });
        Schema::dropIfExists('coupon_redemptions');
        Schema::dropIfExists('coupon_course');
        Schema::dropIfExists('coupons');
    }
};
