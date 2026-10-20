<?php

use App\Http\Controllers\Admin\FormController;
use App\Http\Controllers\Admin\FormOptionsController;
use App\Http\Controllers\Admin\FormSubmissionController;
use App\Http\Controllers\Admin\InboxController;
use Illuminate\Support\Facades\Route;

// Declared before the resource routes so "options" is not read as a {form}.
Route::get('/forms/options', FormOptionsController::class)->name('forms.options');

Route::get('/forms/{form}/submissions', [FormSubmissionController::class, 'index'])
    ->name('forms.submissions.index');
Route::get('/forms/{form}/submissions/export', [FormSubmissionController::class, 'export'])
    ->name('forms.submissions.export');
Route::delete('/forms/{form}/submissions/{submission}', [FormSubmissionController::class, 'destroy'])
    ->name('forms.submissions.destroy');

Route::apiResource('forms', FormController::class);

// The inbox: everything every form has been sent, in one list.
Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
Route::post('/inbox/bulk', [InboxController::class, 'bulk'])->name('inbox.bulk');
Route::post('/inbox/read-all', [InboxController::class, 'readAll'])->name('inbox.read-all');
Route::get('/inbox/{submission}', [InboxController::class, 'show'])->name('inbox.show');
Route::get('/inbox/{submission}/file/{field}', [InboxController::class, 'file'])->name('inbox.file');
Route::put('/inbox/{submission}', [InboxController::class, 'update'])->name('inbox.update');
Route::delete('/inbox/{submission}', [InboxController::class, 'destroy'])->name('inbox.destroy');
