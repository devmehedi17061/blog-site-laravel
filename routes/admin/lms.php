<?php

use App\Http\Controllers\Admin\CourseController;
use App\Http\Controllers\Admin\CourseOptionsController;
use App\Http\Controllers\Admin\CurriculumController;
use App\Http\Controllers\Admin\EnrollmentController;
use App\Http\Controllers\Admin\LessonVideoController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\StudentController;
use Illuminate\Support\Facades\Route;

// Declared before the resource routes so "options" is not read as a {course}.
Route::get('/courses/options', CourseOptionsController::class)->name('courses.options');
Route::get('/courses/{course}/curriculum', [CurriculumController::class, 'show'])->name('courses.curriculum');
Route::put('/courses/{course}/curriculum', [CurriculumController::class, 'update'])->name('courses.curriculum.update');
// Uploaded lesson videos arrive in chunks, so the limit is generous.
Route::post('/courses/{course}/lesson-video', [LessonVideoController::class, 'store'])
    ->middleware('throttle:600,1')->name('courses.lesson-video');
Route::apiResource('courses', CourseController::class);

Route::get('/enrollments/options', [EnrollmentController::class, 'options'])->name('enrollments.options');
Route::apiResource('enrollments', EnrollmentController::class)->except('show');

// Payment verification.
Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
Route::post('/payments/{payment}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
Route::post('/payments/{payment}/reject', [PaymentController::class, 'reject'])->name('payments.reject');

Route::get('/students', [StudentController::class, 'index'])->name('students.index');
