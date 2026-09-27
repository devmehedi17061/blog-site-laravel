<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Media;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Support\Site;
use App\Support\SiteSettings;
use App\Support\Trash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * One screen that says how the whole site is doing: what is live, what came
 * in, what needs doing, and what changed. Every block is only built for an
 * account that may open the screen it links to.
 */
class DashboardController extends Controller
{
    /** Drafts untouched this long are called stale. */
    private const STALE_DRAFT_DAYS = 14;

    private const TREND_DAYS = 30;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $can = fn (string $permission) => $user->hasPermission($permission);

        return response()->json([
            'greeting' => $this->greeting($user),
            'site' => $this->site($can),
            'stats' => $this->stats($can),
            'trend' => $this->trend($can),
            'attention' => $this->attention($user, $can),
            'content' => $this->content($can),
            'seo' => $can('posts.view') ? $this->seo($can) : [],
            'upcoming' => $this->upcoming($can),
            'recent' => $this->recent($can),
            'messages' => $can('forms.view') ? $this->messages() : [],
            'activity' => $this->activity(),
            'system' => $can('settings.view') ? $this->system() : [],
            'counts' => $this->counts($can),
        ]);
    }

    // ── the top of the screen ──────────────────────────────────────────────

    private function greeting(User $user): array
    {
        $hour = (int) now()->format('G');

        return [
            'hello' => $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening'),
            'name' => strtok((string) $user->name, ' ') ?: $user->name,
            'today' => now()->format('l, j F Y'),
        ];
    }

    private function site(callable $can): array
    {
        $site = app(Site::class);
        $settings = app(SiteSettings::class);
        $home = Page::homeId() ? Page::whereKey(Page::homeId())->value('title') : null;
        $language = SiteSettings::LANGUAGES[$site->get('language')]['label'] ?? 'English';

        return [
            'name' => $site->name(),
            'url' => url('/'),
            'live' => ! $site->inMaintenance(),
            'indexed' => ! $site->hiddenFromSearch(),
            'home' => $home ?? 'Latest posts',
            'language' => $language,
            'mail' => $can('settings.view') ? $this->mailLabel((string) $settings->get('mail.provider')) : null,
        ];
    }

    private function mailLabel(string $provider): string
    {
        return match ($provider) {
            'gmail' => 'Gmail',
            'zoho' => 'Zoho Mail',
            'webmail' => 'Webmail',
            'smtp' => 'SMTP',
            default => 'Log only',
        };
    }

    /** The key figures, each leading to the screen behind it. */
    private function stats(callable $can): array
    {
        $tiles = [];
        $monthStart = now()->startOfMonth();

        if ($can('posts.view')) {
            $live = Post::published()->count();
            $tiles[] = [
                'key' => 'posts', 'label' => 'Live posts', 'value' => $live, 'tone' => 'accent',
                'sub' => sprintf('%+d this month · %d draft%s', Post::published()->where('published_at', '>=', $monthStart)->count(),
                    $drafts = Post::where('status', 'Draft')->count(), $drafts === 1 ? '' : 's'),
            ];
        }

        if ($can('pages.view')) {
            $tiles[] = [
                'key' => 'pages', 'label' => 'Live pages', 'value' => $live = Page::published()->count(), 'tone' => 'dark',
                'sub' => (Page::count() - $live).' not published',
            ];
        }

        if ($can('forms.view')) {
            $now = FormSubmission::where('created_at', '>=', now()->subDays(self::TREND_DAYS))->count();
            $before = FormSubmission::whereBetween('created_at', [now()->subDays(self::TREND_DAYS * 2), now()->subDays(self::TREND_DAYS)])->count();
            $tiles[] = [
                'key' => 'inbox', 'label' => 'Messages · 30 days', 'value' => $now, 'tone' => 'tint',
                'sub' => FormSubmission::unread()->count().' unread'.($before ? ' · '.$this->change($now, $before).' vs last 30' : ''),
            ];
        }

        if ($can('media.view')) {
            $tiles[] = [
                'key' => 'media', 'label' => 'Media files', 'value' => Media::count(), 'tone' => 'plain',
                'sub' => $this->humanBytes((int) Media::sum('size')).' on disk',
            ];
        }

        if ($can('users.view')) {
            $tiles[] = [
                'key' => 'users', 'label' => 'Active users', 'value' => User::where('status', 'Active')->count(), 'tone' => 'plain',
                'sub' => User::where('last_seen_at', '>=', now()->subMinutes(15))->count().' online now',
            ];
        }

        if ($can('posts.delete') || $can('pages.delete') || $can('media.delete')) {
            $tiles[] = [
                'key' => 'trash', 'label' => 'In the trash', 'value' => $this->trashCount(), 'tone' => 'plain',
                'sub' => 'Emptied after '.Trash::DAYS.' days',
            ];
        }

        return $tiles;
    }

    private function change(int $now, int $before): string
    {
        $pct = (int) round(($now - $before) / max(1, $before) * 100);

        return ($pct >= 0 ? '+' : '').$pct.'%';
    }

    /** Posts published and messages received per day, for the last 30 days. */
    private function trend(callable $can): array
    {
        $from = now()->subDays(self::TREND_DAYS - 1)->startOfDay();
        $perDay = fn ($dates) => collect($dates)->countBy(fn ($d) => Carbon::parse($d)->toDateString());

        $posts = $can('posts.view')
            ? $perDay(Post::published()->where('published_at', '>=', $from)->pluck('published_at'))
            : collect();
        $messages = $can('forms.view')
            ? $perDay(FormSubmission::where('created_at', '>=', $from)->pluck('created_at'))
            : collect();
        $uploads = $can('media.view')
            ? $perDay(Media::where('created_at', '>=', $from)->pluck('created_at'))
            : collect();

        $days = [];
        for ($day = $from->copy(); $day->lte(now()); $day->addDay()) {
            $key = $day->toDateString();
            $days[] = [
                'date' => $key,
                'label' => $day->format('j M'),
                'weekday' => $day->format('D'),
                'posts' => $posts[$key] ?? 0,
                'messages' => $messages[$key] ?? 0,
                'uploads' => $uploads[$key] ?? 0,
            ];
        }

        return [
            'days' => $days,
            'series' => array_values(array_filter([
                $can('posts.view') ? ['key' => 'posts', 'label' => 'Posts published'] : null,
                $can('forms.view') ? ['key' => 'messages', 'label' => 'Form messages'] : null,
                $can('media.view') ? ['key' => 'uploads', 'label' => 'Files uploaded'] : null,
            ])),
        ];
    }

    // ── what needs doing ───────────────────────────────────────────────────

    /**
     * A to-do list worked out from the data. "warn" should be dealt with,
     * "info" is worth a look. Each names the screen that fixes it.
     */
    private function attention(User $user, callable $can): array
    {
        $items = [];
        $add = function (string $tone, string $title, string $detail, ?string $screen = null, ?string $param = null) use (&$items) {
            $items[] = compact('tone', 'title', 'detail', 'screen', 'param');
        };
        $site = app(Site::class);
        $settings = app(SiteSettings::class);

        if ($can('settings.view')) {
            if ($site->inMaintenance()) {
                $add('warn', 'The site is in maintenance mode', 'Visitors see the maintenance page, not your content.', 'settings');
            }
            if ($site->hiddenFromSearch()) {
                $add('warn', 'Search engines are told to stay away', '"Hide from search engines" is on under Settings → Site.', 'settings');
            }
            if ($settings->get('mail.provider') === 'log') {
                $add('warn', 'Emails are not being sent', 'Mail is set to "Log only": form notifications and sign-in codes only reach the log file.', 'settings');
            }
            if (blank($site->get('tagline')) || blank($site->get('default_description'))) {
                $add('info', 'The site has no tagline or description', 'Both are used by search engines and when links are shared.', 'settings');
            }
            if (config('app.debug') && ! app()->environment('local')) {
                $add('warn', 'Debug mode is on', 'APP_DEBUG=true shows error details to visitors. Turn it off in .env.');
            }
            if (app()->environment('production') && ! $settings->get('security.force_https')) {
                $add('info', 'HTTPS is not enforced', 'Turn on "Force HTTPS" under Security once the certificate works.', 'security');
            }
            if ($failed = DB::table('failed_jobs')->count()) {
                $add('warn', "{$failed} background job(s) failed", 'Check storage/logs and run "php artisan queue:retry all".');
            }
        }

        if (! $user->two_factor_confirmed_at) {
            $add('info', 'Your account has no two-factor sign-in', 'A code from your phone or email keeps the panel safe even if the password leaks.', 'account');
        }

        if ($can('forms.view') && ($unread = FormSubmission::unread()->count())) {
            $add('warn', "{$unread} unread form message".($unread === 1 ? '' : 's'), 'People are waiting for an answer.', 'inbox');
        }

        if ($can('posts.view')) {
            $live = Post::published();
            if ($n = (clone $live)->where(fn ($q) => $q->whereNull('meta_description')->orWhere('meta_description', ''))
                ->where(fn ($q) => $q->whereNull('excerpt')->orWhere('excerpt', ''))->count()) {
                $add('info', "{$n} live post(s) have no description", 'Without a meta description or excerpt, Google picks its own text.', 'posts');
            }
            if ($n = (clone $live)->whereNull('media_id')->count()) {
                $add('info', "{$n} live post(s) have no featured image", 'They show without a picture on the blog and when shared.', 'posts');
            }
            if ($n = Post::where('status', 'Draft')->where('updated_at', '<', now()->subDays(self::STALE_DRAFT_DAYS))->count()) {
                $add('info', "{$n} draft(s) untouched for ".self::STALE_DRAFT_DAYS.'+ days', 'Finish them, or move them to the trash.', 'posts');
            }
        }

        if ($can('media.view') && ($n = Media::where('mime_type', 'like', 'image/%')
            ->where(fn ($q) => $q->whereNull('alt_text')->orWhere('alt_text', ''))->count())) {
            $add('info', "{$n} image(s) have no alt text", 'Screen readers and image search rely on it.', 'media');
        }

        if ($can('posts.delete') || $can('pages.delete') || $can('media.delete')) {
            $soon = 0;
            foreach (Trash::TYPES as $type) {
                $soon += $type['model']::onlyTrashed()->where('deleted_at', '<', now()->subDays(Trash::DAYS - 3))->count();
            }
            if ($soon) {
                $add('warn', "{$soon} trashed item(s) are deleted for good within 3 days", 'Restore anything you still need.', 'trash');
            }
        }

        // Most urgent first.
        usort($items, fn ($a, $b) => ($a['tone'] === 'warn' ? 0 : 1) <=> ($b['tone'] === 'warn' ? 0 : 1));

        return $items;
    }

    // ── content ────────────────────────────────────────────────────────────

    private function content(callable $can): array
    {
        $byStatus = function (string $model) {
            $counts = $model::query()->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

            return collect($model::STATUSES)->map(fn ($s) => ['status' => $s, 'count' => (int) ($counts[$s] ?? 0)])->all();
        };

        return [
            'posts' => $can('posts.view') ? $byStatus(Post::class) : null,
            'pages' => $can('pages.view') ? $byStatus(Page::class) : null,
            'categories' => $can('posts.view') && $can('categories.view')
                ? Category::withCount('posts')->orderByDesc('posts_count')->limit(6)->get()
                    ->map(fn (Category $c) => ['name' => $c->name, 'count' => $c->posts_count])->all()
                : [],
            'uncategorised' => $can('posts.view') ? Post::whereNull('category_id')->count() : 0,
        ];
    }

    /** How much of the live content carries each SEO basic. */
    private function seo(callable $can): array
    {
        $live = Post::published();
        $total = (clone $live)->count();
        $filled = fn (string $column) => (clone $live)->whereNotNull($column)->where($column, '!=', '')->count();

        $rows = [
            ['label' => 'Meta description', 'have' => $filled('meta_description'), 'total' => $total],
            ['label' => 'Focus keyword', 'have' => $filled('focus_keyword'), 'total' => $total],
            ['label' => 'Excerpt', 'have' => $filled('excerpt'), 'total' => $total],
            ['label' => 'Featured image', 'have' => (clone $live)->whereNotNull('media_id')->count(), 'total' => $total],
        ];

        if ($can('media.view')) {
            $images = Media::where('mime_type', 'like', 'image/%');
            $rows[] = ['label' => 'Images with alt text', 'have' => (clone $images)->whereNotNull('alt_text')->where('alt_text', '!=', '')->count(), 'total' => (clone $images)->count()];
        }

        return $rows;
    }

    /** Posts and pages dated in the future: they go live on their own. */
    private function upcoming(callable $can): array
    {
        $rows = collect();
        $future = fn ($q) => $q->whereIn('status', ['Scheduled', 'Published'])->where('published_at', '>', now());

        if ($can('posts.view')) {
            $rows = $rows->concat(Post::where($future)->whereNotNull('published_at')->orderBy('published_at')->limit(5)->get()
                ->map(fn (Post $p) => ['type' => 'posts', 'id' => $p->id, 'title' => $p->title, 'at' => $p->published_at]));
        }
        if ($can('pages.view')) {
            $rows = $rows->concat(Page::where($future)->whereNotNull('published_at')->orderBy('published_at')->limit(5)->get()
                ->map(fn (Page $p) => ['type' => 'pages', 'id' => $p->id, 'title' => $p->title, 'at' => $p->published_at]));
        }

        return $rows->sortBy(fn ($r) => $r['at']->getTimestamp())->take(5)->map(fn ($r) => [
            ...$r,
            'when' => $r['at']->format('D j M, H:i'),
            'in' => $r['at']->diffForHumans(),
            'at' => $r['at']->toIso8601String(),
        ])->values()->all();
    }

    /** The posts and pages edited most recently. */
    private function recent(callable $can): array
    {
        $rows = collect();

        if ($can('posts.view')) {
            $rows = $rows->concat(Post::with('author:id,name')->latest('updated_at')->limit(6)->get()
                ->map(fn (Post $p) => ['type' => 'posts', 'kind' => 'Post', 'id' => $p->id, 'title' => $p->title, 'status' => $p->status, 'author' => $p->author?->name, 'updated' => $p->updated_at]));
        }
        if ($can('pages.view')) {
            $rows = $rows->concat(Page::with('author:id,name')->latest('updated_at')->limit(6)->get()
                ->map(fn (Page $p) => ['type' => 'pages', 'kind' => 'Page', 'id' => $p->id, 'title' => $p->title, 'status' => $p->status, 'author' => $p->author?->name, 'updated' => $p->updated_at]));
        }

        // By timestamp: sorting the Carbon objects themselves is unreliable.
        return $rows->sortByDesc(fn ($r) => $r['updated']?->getTimestamp() ?? 0)->take(6)->map(fn ($r) => [
            ...$r,
            'updated' => $r['updated']?->diffForHumans(),
        ])->values()->all();
    }

    private function messages(): array
    {
        return FormSubmission::with('form:id,name')->latest()->limit(5)->get()
            ->map(fn (FormSubmission $s) => [
                'id' => $s->id,
                'form' => $s->form?->name ?? 'Deleted form',
                'summary' => $s->summary(),
                'ago' => $s->created_at?->diffForHumans(),
                'unread' => $s->isUnread(),
            ])->all();
    }

    private function activity(): array
    {
        return ActivityLog::with('user')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'when' => $log->created_at?->diffForHumans(null, true).' ago',
                'actor' => $log->user?->name ?? 'System',
                'initials' => $log->user?->initials ?? 'SY',
                'action' => $log->action,
                'target' => $log->description,
            ])
            ->all();
    }

    /** The server, with a verdict on each line where one makes sense. */
    private function system(): array
    {
        $free = @disk_free_space(storage_path());
        // file_exists, not is_link: on Windows storage:link makes a junction,
        // which is_link and is_dir both report as false.
        $linked = file_exists(public_path('storage'));

        return [
            ['k' => 'Laravel', 'v' => app()->version(), 'ok' => true],
            ['k' => 'PHP', 'v' => PHP_VERSION, 'ok' => version_compare(PHP_VERSION, '8.2', '>=')],
            ['k' => 'Environment', 'v' => config('app.env').(config('app.debug') ? ' · debug on' : ''), 'ok' => ! config('app.debug') || app()->environment('local')],
            ['k' => 'Database', 'v' => config('database.default').' · '.config('database.connections.'.config('database.default').'.database'), 'ok' => true],
            ['k' => 'Cache', 'v' => config('cache.default'), 'ok' => true],
            ['k' => 'Queue', 'v' => config('queue.default').' · '.DB::table('jobs')->count().' waiting', 'ok' => true],
            ['k' => 'Failed jobs', 'v' => (string) ($failed = DB::table('failed_jobs')->count()), 'ok' => $failed === 0],
            ['k' => 'Storage link', 'v' => $linked ? 'public/storage is linked' : 'missing — run php artisan storage:link', 'ok' => $linked],
            ['k' => 'Media on disk', 'v' => $this->humanBytes((int) Media::withTrashed()->sum('size')), 'ok' => true],
            ['k' => 'Free disk space', 'v' => $free !== false ? $this->humanBytes((int) $free) : 'unknown', 'ok' => $free === false || $free > 1024 ** 3],
        ];
    }

    private function counts(callable $can): array
    {
        return array_filter([
            'pages' => $can('pages.view') ? Page::count() : null,
            'posts' => $can('posts.view') ? Post::count() : null,
            'users' => $can('users.view') ? User::count() : null,
            'media' => $can('media.view') ? Media::count() : null,
            'roles' => $can('roles.view') ? DB::table('roles')->count() : null,
            'forms' => $can('forms.view') ? Form::count() : null,
            // Unread, not total: a badge is a to-do list.
            'inbox' => $can('forms.view') ? FormSubmission::unread()->count() : null,
            'trash' => $can('posts.delete') || $can('pages.delete') || $can('media.delete') ? $this->trashCount() : null,
        ], fn ($v) => $v !== null);
    }

    private function trashCount(): int
    {
        return collect(Trash::TYPES)->sum(fn ($type) => $type['model']::onlyTrashed()->count());
    }

    private function humanBytes(int $bytes): string
    {
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024) {
                return round($bytes, $unit === 'B' ? 0 : 1).' '.$unit;
            }
            $bytes /= 1024;
        }

        return round($bytes, 1).' TB';
    }
}
