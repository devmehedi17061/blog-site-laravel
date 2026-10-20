<?php

use App\Http\Controllers\Site\BlogController;
use App\Http\Controllers\Site\FormSubmissionController;
use App\Http\Controllers\Site\HomeController;
use App\Http\Controllers\Site\PageController;
use App\Http\Controllers\Site\PublicStorageController;
use App\Http\Controllers\Site\SitemapController;
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

// Uploaded images when public/storage (the storage:link symlink) is missing —
// hosts that forbid symlinks. With the link in place the web server serves
// the file directly and this route is never reached.
Route::get('/storage/{path}', PublicStorageController::class)
    ->where('path', '.*')
    ->name('storage.fallback');

require __DIR__.'/admin.php';

/*
| The page catch-all is last on purpose: every named route above wins, and
| anything starting with admin/ is excluded so the panel is never shadowed.
*/
Route::get('/{slug}', [PageController::class, 'show'])
    ->middleware(['site.language', 'site.open'])
    // Whole segments only — a bare prefix would swallow real slugs such as
    // "builder-guide", which starts with "build".
    ->where('slug', '^(?!(admin|blog|category|storage|build|up)(/|$)).*$')
    ->name('page.show');
