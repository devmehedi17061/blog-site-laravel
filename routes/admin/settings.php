<?php

use App\Http\Controllers\Admin\CacheController;
use App\Http\Controllers\Admin\RedirectController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');

// The header's "Clear cache" button.
Route::post('/system/clear-cache', [CacheController::class, 'clear'])
    ->middleware('throttle:6,1')
    ->name('system.clear-cache');

Route::put('/settings/mail', [SettingsController::class, 'updateMail'])->name('settings.mail.update');
// Throttled: each try opens a real connection to someone's mail server.
Route::post('/settings/mail/test', [SettingsController::class, 'testMail'])
    ->middleware('throttle:10,1')
    ->name('settings.mail.test');

Route::put('/settings/site', [SettingsController::class, 'updateSite'])->name('settings.site.update');

Route::put('/settings/spam', [SettingsController::class, 'updateSpam'])->name('settings.spam.update');
Route::post('/settings/spam/check', [SettingsController::class, 'checkSpamKey'])
    ->middleware('throttle:10,1')
    ->name('settings.spam.check');

Route::put('/settings/frontend', [SettingsController::class, 'updateFrontend'])->name('settings.frontend.update');

Route::put('/settings/lms', [SettingsController::class, 'updateLms'])->name('settings.lms.update');

Route::put('/settings/design', [SettingsController::class, 'updateDesign'])->name('settings.design.update');

// Settings → Redirects.
Route::get('/redirects', [RedirectController::class, 'index'])->name('redirects.index');
Route::post('/redirects', [RedirectController::class, 'store'])->name('redirects.store');
Route::delete('/redirects/{redirect}', [RedirectController::class, 'destroy'])->name('redirects.destroy');
