<?php

use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\PanelController;
use App\Http\Controllers\Admin\SlugPreviewController;
use App\Http\Controllers\Admin\TwoFactorChallengeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
| Everything under /admin. Session auth, so no tokens and no CORS. The JSON
| endpoints the Alpine screens call live in routes/admin/.
|
| "admin.ip" applies Security → IP rules to all of it, the sign-in page
| included. The public site is never behind them.
*/

Route::prefix('admin')->name('admin.')->middleware('admin.ip')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('/login', [LoginController::class, 'show'])->name('login');
        Route::post('/login', [LoginController::class, 'store'])
            ->middleware('throttle:6,1')
            ->name('login.store');

        // Step two, for accounts with two-factor on.
        Route::get('/two-factor', [TwoFactorChallengeController::class, 'show'])->name('two-factor');
        Route::post('/two-factor', [TwoFactorChallengeController::class, 'store'])
            ->middleware('throttle:10,1')
            ->name('two-factor.store');
        Route::post('/two-factor/resend', [TwoFactorChallengeController::class, 'resend'])
            ->middleware('throttle:3,1')
            ->name('two-factor.resend');
    });

    // "active" signs out a suspended account on its next request; "staff"
    // keeps student accounts out of the panel.
    Route::middleware(['auth', 'active', 'staff'])->group(function () {
        Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
        Route::get('/', PanelController::class)->name('panel');

        // "account.secure" holds back everything but My account while an
        // account owes two-factor setup or a new password.
        Route::prefix('api')->name('api.')->middleware('account.secure')->group(function () {
            // The slug the server would make from some text, for the editors.
            Route::get('/slug', SlugPreviewController::class)->name('slug');

            require __DIR__.'/admin/account.php';
            require __DIR__.'/admin/overview.php';
            require __DIR__.'/admin/content.php';
            require __DIR__.'/admin/lms.php';
            require __DIR__.'/admin/media.php';
            require __DIR__.'/admin/pages.php';
            require __DIR__.'/admin/trash.php';
            require __DIR__.'/admin/forms.php';
            require __DIR__.'/admin/menu.php';
            require __DIR__.'/admin/layout.php';
            require __DIR__.'/admin/categories.php';
            require __DIR__.'/admin/users.php';
            require __DIR__.'/admin/roles.php';
            require __DIR__.'/admin/settings.php';
            require __DIR__.'/admin/security.php';
        });
    });
});
