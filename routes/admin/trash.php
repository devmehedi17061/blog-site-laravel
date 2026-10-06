<?php

use App\Http\Controllers\Admin\RevisionController;
use App\Http\Controllers\Admin\TrashController;
use Illuminate\Support\Facades\Route;

// Revision history for posts and pages.
Route::get('/posts/{post}/revisions', [RevisionController::class, 'forPost'])->name('posts.revisions');
Route::get('/pages/{page}/revisions', [RevisionController::class, 'forPage'])->name('pages.revisions');
Route::get('/revisions/{revision}', [RevisionController::class, 'show'])->name('revisions.show');
Route::post('/revisions/{revision}/restore', [RevisionController::class, 'restore'])->name('revisions.restore');

// The Trash. Each controller action checks the policy for every item.
Route::get('/trash', [TrashController::class, 'index'])->name('trash.index');
Route::prefix('/trash/{type}')->whereIn('type', ['posts', 'pages', 'media', 'courses'])->name('trash.')->group(function () {
    Route::post('/restore', [TrashController::class, 'restore'])->name('restore');
    Route::post('/delete', [TrashController::class, 'destroy'])->name('destroy');
    Route::post('/empty', [TrashController::class, 'empty'])->name('empty');
});
