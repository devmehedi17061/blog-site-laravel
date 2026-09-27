<?php

use App\Http\Controllers\Admin\AccountController;
use Illuminate\Support\Facades\Route;

// My account: every signed-in person, whatever their role. These stay open
// while an account owes two-factor setup or a new password — this is where
// those get done (see EnsureAccountSecure).
Route::prefix('account')->name('account.')->group(function () {
    Route::get('/', [AccountController::class, 'show'])->name('show');
    Route::put('/profile', [AccountController::class, 'updateProfile'])->name('profile');
    Route::put('/password', [AccountController::class, 'updatePassword'])
        ->middleware('throttle:6,1')->name('password');

    Route::post('/two-factor/app', [AccountController::class, 'startApp'])->name('two-factor.app');
    Route::post('/two-factor/email', [AccountController::class, 'startEmail'])
        ->middleware('throttle:3,1')->name('two-factor.email');
    Route::post('/two-factor/confirm', [AccountController::class, 'confirm'])
        ->middleware('throttle:10,1')->name('two-factor.confirm');
    Route::post('/two-factor/recovery-codes', [AccountController::class, 'regenerateRecoveryCodes'])
        ->middleware('throttle:6,1')->name('two-factor.recovery');
    Route::delete('/two-factor', [AccountController::class, 'disable'])
        ->middleware('throttle:6,1')->name('two-factor.disable');

    Route::delete('/sessions/{key}', [AccountController::class, 'endSession'])->name('sessions.end');
    Route::post('/sessions/sign-out-others', [AccountController::class, 'endOtherSessions'])->name('sessions.others');
});
