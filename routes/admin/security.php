<?php

use App\Http\Controllers\Admin\SecurityController;
use Illuminate\Support\Facades\Route;

Route::get('/security', [SecurityController::class, 'show'])->name('security.show');
Route::put('/security', [SecurityController::class, 'update'])->name('security.update');

Route::post('/security/ip-rules', [SecurityController::class, 'storeIpRule'])->name('security.ip-rules.store');
Route::delete('/security/ip-rules/{ipRule}', [SecurityController::class, 'destroyIpRule'])->name('security.ip-rules.destroy');

Route::delete('/security/sessions/{key}', [SecurityController::class, 'endSession'])->name('security.sessions.end');
Route::post('/security/users/{user}/sign-out', [SecurityController::class, 'signOutUser'])->name('security.users.sign-out');
Route::post('/security/users/{user}/reset-two-factor', [SecurityController::class, 'resetTwoFactor'])->name('security.users.reset-two-factor');

Route::get('/security/log', [SecurityController::class, 'history'])->name('security.log');
