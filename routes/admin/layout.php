<?php

use App\Http\Controllers\Admin\SitePartController;
use Illuminate\Support\Facades\Route;

// Builders → Header & footer. The controller checks the layout.* permissions.
Route::get('/site-parts', [SitePartController::class, 'index'])->name('site-parts.index');
Route::post('/site-parts', [SitePartController::class, 'store'])->name('site-parts.store');
Route::post('/site-parts/preview', [SitePartController::class, 'preview'])->name('site-parts.preview');
Route::put('/site-parts/{part}', [SitePartController::class, 'update'])->name('site-parts.update');
Route::post('/site-parts/{part}/activate', [SitePartController::class, 'activate'])->name('site-parts.activate');
Route::delete('/site-parts/{part}', [SitePartController::class, 'destroy'])->name('site-parts.destroy');
