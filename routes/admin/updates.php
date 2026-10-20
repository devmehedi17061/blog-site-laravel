<?php

use App\Http\Controllers\Admin\UpdateController;
use Illuminate\Support\Facades\Route;

// Settings → Updates: the core version, from GitHub Releases.
Route::get('/updates', [UpdateController::class, 'index'])->name('updates.index');
Route::post('/updates/check', [UpdateController::class, 'check'])->middleware('throttle:10,1')->name('updates.check');
Route::post('/updates/install', [UpdateController::class, 'install'])->middleware('throttle:3,10')->name('updates.install');
Route::post('/updates/rollback', [UpdateController::class, 'rollback'])->middleware('throttle:3,10')->name('updates.rollback');
Route::put('/updates/settings', [UpdateController::class, 'settings'])->name('updates.settings');
