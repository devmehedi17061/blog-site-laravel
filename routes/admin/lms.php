<?php

use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\CourseOptionsController;
use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\DiscussionController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\LessonVideoController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\StudentController;
use Illuminate\Support\Facades\Route;

// Declared before the resource routes so "options" is not read as a {course}.
Route::get('/courses/options', CourseOptionsController::class)->name('courses.options');
Route::get('/courses/{course}/curriculum', [CurriculumController::class, 'show'])->name('courses.curriculum');
Route::put('/courses/{course}/curriculum', [CurriculumController::class, 'update'])->name('courses.curriculum.update');
// Uploaded lesson videos arrive in chunks, so the limit is generous.
Route::post('/courses/{course}/lesson-video', [LessonVideoController::class, 'store'])
    ->middleware('throttle:600,1')->name('courses.lesson-video');
Route::get('/courses/{course}/announcements', [AnnouncementController::class, 'index'])->name('courses.announcements');
Route::post('/courses/{course}/announcements', [AnnouncementController::class, 'store'])
    ->middleware('throttle:10,1')->name('courses.announcements.store');
Route::delete('/courses/{course}/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('courses.announcements.destroy');
Route::post('/courses/{course}/duplicate', [CourseController::class, 'duplicate'])->name('courses.duplicate');
Route::apiResource('courses', CourseController::class);

// Lesson Q&A across courses.
Route::get('/discussions', [DiscussionController::class, 'index'])->name('discussions.index');
Route::post('/discussions/{discussion}/reply', [DiscussionController::class, 'reply'])->name('discussions.reply');
Route::post('/discussions/{discussion}/resolve', [DiscussionController::class, 'resolve'])->name('discussions.resolve');
Route::delete('/discussions/{discussion}', [DiscussionController::class, 'destroy'])->name('discussions.destroy');

Route::get('/enrollments/options', [EnrollmentController::class, 'options'])->name('enrollments.options');
Route::apiResource('enrollments', EnrollmentController::class)->except('show');

Route::get('/coupons/options', [CouponController::class, 'options'])->name('coupons.options');
Route::apiResource('coupons', CouponController::class);

// Payment verification.
Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
Route::get('/payments/export', [PaymentController::class, 'export'])->name('payments.export');
Route::post('/payments/bulk-verify', [PaymentController::class, 'bulkVerify'])->name('payments.bulk-verify');
Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');

Route::get('/students', [StudentController::class, 'index'])->name('students.index');
Route::get('/students/export', [StudentController::class, 'export'])->name('students.export');

// LMS → Reports.
Route::get('/reports', ReportController::class)->name('reports');
