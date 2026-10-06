<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The learning side of the site.
 *
 *   courses ─┬─ course_sections ── lessons
 *            ├─ enrollments        (a student on a course, paid or free)
 *            ├─ lesson_completions (which lessons a student has finished)
 *            └─ course_reviews     (one rating per student per course)
 *
 * Courses share the blog's categories, so one tree files both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            // The instructor. Kept nullable so removing an account never
            // takes its courses (and every student's progress) with it.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->string('subtitle', 300)->nullable();
            $table->longText('description')->nullable();
            // "What you will learn" and "Requirements": short lines, in order.
            $table->json('outcomes')->nullable();
            $table->json('requirements')->nullable();

            $table->string('level', 30)->default('Beginner');
            $table->string('language', 40)->default('English');
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->string('intro_video', 500)->nullable();

            $table->string('status', 20)->default('Draft')->index();
            $table->dateTime('published_at')->nullable();
            $table->boolean('is_featured')->default(false);

            $table->string('meta_title')->nullable();
            $table->string('meta_description', 320)->nullable();

            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('course_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_section_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            // video: an embedded YouTube / Vimeo / file URL; article: the body only.
            $table->string('type', 20)->default('video');
            $table->string('video_url', 500)->nullable();
            $table->longText('body')->nullable();
            // A downloadable file from the media library (slides, worksheet).
            $table->foreignId('media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedInteger('duration_minutes')->default(0);
            // Free preview: watchable before enrolling.
            $table->boolean('is_preview')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            // Pending: a paid course waiting for the payment to be checked.
            $table->string('status', 20)->default('Active')->index();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('payment_method', 60)->nullable();
            $table->string('transaction_id', 120)->nullable();
            $table->text('note')->nullable();
            $table->dateTime('enrolled_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });

        Schema::create('lesson_completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            // Copied from the lesson so progress is one count per course.
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'lesson_id']);
            $table->index(['user_id', 'course_id']);
        });

        Schema::create('course_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->boolean('is_approved')->default(true);
            $table->timestamps();

            $table->unique(['user_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_reviews');
        Schema::dropIfExists('lesson_completions');
        Schema::dropIfExists('enrollments');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('course_sections');
        Schema::dropIfExists('courses');
    }
};
