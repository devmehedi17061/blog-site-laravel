<?php

use App\Http\Controllers\Site\BlogController;
use App\Http\Controllers\Site\CertificateController;
use App\Http\Controllers\Site\CourseController;
use App\Http\Controllers\Site\EnrollController;
use App\Http\Controllers\Site\FormSubmissionController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\LearnController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\ReviewController;
use App\Http\Controllers\Site\SitemapController;
use App\Http\Controllers\Site\StudentAuthController;
use App\Http\Controllers\Site\StudentDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public site
|--------------------------------------------------------------------------
| Server-rendered Blade so posts are indexable. The admin panel lives in
| routes/admin.php, required at the bottom of this file.
*/

// "site.open" is maintenance mode from Settings → Site. The panel is not
// behind it — that is where it gets switched off again.
Route::middleware(['site.language', 'site.open'])->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/category/{slug}', [BlogController::class, 'category'])->name('blog.category');
    Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

    // ── Courses ─────────────────────────────────────────────────────────
    // The catalogue and each course's page are public; joining, learning
    // and reviewing need a signed-in account (a student's, usually).
    Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
    Route::get('/courses/{slug}', [CourseController::class, 'show'])->name('courses.show');
    // Free-preview lessons open without an account; the controller decides.
    Route::get('/learn/{slug}/{lesson?}', [LearnController::class, 'show'])
        ->whereNumber('lesson')->name('learn.show');
    // An uploaded lesson video, streamed only to people who may watch it.
    Route::get('/learn/{slug}/{lesson}/video', [LearnController::class, 'video'])
        ->whereNumber('lesson')->name('learn.video');

    Route::middleware(['auth', 'active'])->group(function () {
        Route::post('/courses/{slug}/enroll', [EnrollController::class, 'store'])->name('courses.enroll');
        Route::get('/courses/{slug}/checkout', [EnrollController::class, 'checkout'])->name('courses.checkout');
        Route::post('/courses/{slug}/checkout', [EnrollController::class, 'pay'])
            ->middleware('throttle:10,1')->name('courses.pay');
        Route::post('/courses/{slug}/review', [ReviewController::class, 'store'])
            ->middleware('throttle:10,1')->name('courses.review');
        Route::post('/learn/{slug}/{lesson}/complete', [LearnController::class, 'complete'])
            ->whereNumber('lesson')->name('learn.complete');

        // The student panel.
        Route::get('/my-courses', [StudentDashboardController::class, 'index'])->name('student.dashboard');
        Route::get('/my-courses/all', [StudentDashboardController::class, 'courses'])->name('student.courses');
        Route::get('/my-payments', [StudentDashboardController::class, 'payments'])->name('student.payments');
        Route::get('/my-payments/{payment}/receipt', [StudentDashboardController::class, 'receipt'])->name('student.receipt');
        // A payment screenshot: its student, and staff who handle enrollments.
        Route::get('/payments/{payment}/proof', [StudentDashboardController::class, 'proof'])->name('payments.proof');
        Route::get('/my-certificates', [StudentDashboardController::class, 'certificates'])->name('student.certificates');
        Route::get('/notifications', [StudentDashboardController::class, 'notifications'])->name('student.notifications');
        Route::get('/notifications/{id}/open', [StudentDashboardController::class, 'openNotification'])->name('student.notifications.open');
        Route::post('/notifications/read-all', [StudentDashboardController::class, 'readAll'])->name('student.notifications.read');
        Route::get('/my-account', [StudentDashboardController::class, 'account'])->name('student.account');
        Route::put('/my-account', [StudentDashboardController::class, 'updateAccount'])->name('student.account.update');
        Route::get('/certificate/{enrollment}', [CertificateController::class, 'show'])->name('student.certificate');
        Route::post('/logout', [StudentAuthController::class, 'logout'])->name('student.logout');
    });

    // ── Student sign-in ─────────────────────────────────────────────────
    // The panel keeps its own door (/admin/login, with two-factor).
    Route::middleware('guest')->group(function () {
        Route::get('/login', [StudentAuthController::class, 'showLogin'])->name('student.login');
        Route::post('/login', [StudentAuthController::class, 'login'])->middleware('throttle:10,1')->name('student.login.store');
        Route::get('/register', [StudentAuthController::class, 'showRegister'])->name('student.register');
        Route::post('/register', [StudentAuthController::class, 'register'])->middleware('throttle:5,1')->name('student.register.store');
        Route::get('/forgot-password', [StudentAuthController::class, 'showForgot'])->name('password.request');
        Route::post('/forgot-password', [StudentAuthController::class, 'sendReset'])->middleware('throttle:5,1')->name('password.email');
        Route::get('/reset-password/{token}', [StudentAuthController::class, 'showReset'])->name('password.reset');
        Route::post('/reset-password', [StudentAuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
    });

    // Public form posts. Throttled per visitor, at the rate set under
    // Settings → Spam protection (the "forms" limiter, AppServiceProvider).
    Route::post('/forms/{form:slug}', [FormSubmissionController::class, 'store'])
        ->middleware('throttle:forms')
        ->name('forms.submit');
});

// What search engines read first. Outside maintenance mode on purpose:
// crawlers should still find the map while the site is closed for a while.
Route::get('/sitemap.xml', [SitemapController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');
Route::get('/feed', [SitemapController::class, 'feed'])->middleware('site.language')->name('feed');

require __DIR__.'/admin.php';

/*
| The page catch-all is last on purpose: every named route above wins, and
| anything starting with admin/ is excluded so the panel is never shadowed.
*/
Route::get('/{slug}', [PageController::class, 'show'])
    ->middleware(['site.language', 'site.open'])
    // Whole segments only — a bare prefix would swallow real slugs such as
    // "builder-guide", which starts with "build".
    ->where('slug', '^(?!(admin|blog|category|courses|learn|storage|build|up)(/|$)).*$')
    ->name('page.show');
