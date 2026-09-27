<?php

use App\Http\Controllers\Admin\MediaBulkController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\MediaFolderController;
use Illuminate\Support\Facades\Route;

// Declared before the {media} routes so these names are not read as ids.
Route::post('/media/bulk', MediaBulkController::class)->name('media.bulk');

Route::get('/media/folders', [MediaFolderController::class, 'index'])->name('media.folders.index');
Route::post('/media/folders', [MediaFolderController::class, 'store'])->name('media.folders.store');
Route::put('/media/folders/{folder}', [MediaFolderController::class, 'update'])->name('media.folders.update');
Route::delete('/media/folders/{folder}', [MediaFolderController::class, 'destroy'])->name('media.folders.destroy');

// Laravel singularises "media" to {medium}, which stops implicit model
// binding matching the controller's $media argument.
Route::apiResource('media', MediaController::class)
    ->except('show')
    ->parameters(['media' => 'media']);
Route::get('/media/{media}', [MediaController::class, 'show'])->name('media.show');
