<?php

namespace App\Models;

use App\Actions\Pages\SaveSections;
use App\Models\Concerns\Filterable;
use App\Models\Concerns\HasRedirects;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Page extends Model
{
    use Filterable, HasFactory, HasRedirects, HasRevisions, Trashable;

    public const STATUSES = ['Published', 'Draft', 'Scheduled'];

    /**
     * Which parts of the site chrome a page wears. A landing page is a
     * campaign destination: nothing on it should lead a visitor away, so it
     * drops the navigation and the footer links along with them.
     */
    public const LAYOUTS = [
        'default' => ['label' => 'Standard', 'summary' => 'The site header and footer, like every other page.', 'header' => true, 'footer' => true],
        'landing' => ['label' => 'Landing page', 'summary' => 'No header and no footer — only this page\'s own sections.', 'header' => false, 'footer' => false],
        'no-header' => ['label' => 'No header', 'summary' => 'Hides the site header and navigation, keeps the footer.', 'header' => false, 'footer' => true],
        'no-footer' => ['label' => 'No footer', 'summary' => 'Keeps the site header, hides the footer.', 'header' => true, 'footer' => false],
    ];

    /**
     * First path segments the site already routes somewhere else. A page
     * given one of these would save fine and then never be reachable, so
     * they are refused up front. Mirrors the exclusion in routes/web.php.
     */
    public const RESERVED_SLUGS = ['admin', 'blog', 'category', 'storage', 'build', 'up', 'forms'];

    /** The settings key naming the page served at "/". Unset means the blog. */
    public const HOME_SETTING = 'home_page_id';

    private static ?int $homeIdCache = null;

    private static bool $homeIdLoaded = false;

    protected $fillable = [
        'user_id', 'media_id', 'title', 'slug', 'status', 'template', 'layout', 'excerpt',
        'meta_title', 'meta_description', 'focus_keyword', 'canonical_url',
        'robots', 'og_title', 'og_description', 'og_image', 'published_at', 'custom_css', 'custom_js',
    ];

    protected array $searchable = ['title', 'slug', 'excerpt'];

    protected array $sortable = ['title', 'status', 'published_at', 'created_at'];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function featuredImage(): BelongsTo
    {
        // withTrashed: a file in the Trash is still on disk and keeps showing
        // until it is deleted for good.
        return $this->belongsTo(Media::class, 'media_id')->withTrashed();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class)->orderBy('position');
    }

    /**
     * Live on the public site: published, and not dated in the future. A
     * scheduled item counts once its date arrives, even before
     * publishDue() has flipped its status.
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->whereIn('status', ['Published', 'Scheduled'])
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /**
     * Marks scheduled items whose date has come as Published. Run every
     * minute by the scheduler and whenever the admin lists open, for hosts
     * where nothing runs "schedule:run". The edit date is left alone.
     */
    public static function publishDue(): int
    {
        return static::query()->where('status', 'Scheduled')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->toBase()
            ->update(['status' => 'Published']);
    }

    // ── the homepage ───────────────────────────────────────────────────────

    /** Read once per request: every page URL on a screen asks. */
    public static function homeId(): ?int
    {
        if (! static::$homeIdLoaded) {
            $id = Setting::get(self::HOME_SETTING);
            static::$homeIdCache = $id ? (int) $id : null;
            static::$homeIdLoaded = true;
        }

        return static::$homeIdCache;
    }

    /** Pass null to show the latest blog posts at "/" instead of a page. */
    public static function setHome(?Page $page): void
    {
        Setting::put(self::HOME_SETTING, $page?->id ?? 0, 'reading');
        static::$homeIdCache = $page?->id;
        static::$homeIdLoaded = true;
    }

    public function isHome(): bool
    {
        return $this->exists && $this->id === static::homeId();
    }

    /** Live right now: published, and not dated in the future. */
    public function isLive(): bool
    {
        return in_array($this->status, ['Published', 'Scheduled'], true) && $this->published_at && $this->published_at->lte(now());
    }

    /**
     * The path this page is served at. The homepage lives at "/" — its own
     * slug only redirects there — so every link to it, from the menu to the
     * canonical tag, should say "/".
     */
    public function path(): string
    {
        return $this->isHome() ? '/' : '/'.ltrim((string) $this->slug, '/');
    }

    // ── layout ─────────────────────────────────────────────────────────────

    public function layoutOption(): array
    {
        return self::LAYOUTS[$this->layout] ?? self::LAYOUTS['default'];
    }

    public function showsHeader(): bool
    {
        return $this->layoutOption()['header'];
    }

    public function showsFooter(): bool
    {
        return $this->layoutOption()['footer'];
    }

    /** @return array<int, array{value: string, label: string, summary: string}> */
    public static function layoutOptions(): array
    {
        return collect(self::LAYOUTS)
            ->map(fn (array $layout, string $key) => ['value' => $key, 'label' => $layout['label'], 'summary' => $layout['summary']])
            ->values()
            ->all();
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (! $status || $status === 'All') {
            return $query;
        }

        return $query->where('status', $status);
    }

    // ── revision history (HasRevisions) ───────────────────────────────────

    /** Brought back by a restore. Status, date, address and author are not. */
    private const RESTORABLE = [
        'title', 'excerpt', 'layout', 'media_id',
        'meta_title', 'meta_description', 'focus_keyword', 'canonical_url',
        'robots', 'og_title', 'og_description', 'og_image', 'custom_css', 'custom_js',
    ];

    public function revisionData(): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'published_at' => $this->published_at?->format('Y-m-d H:i:s'),
            'user_id' => $this->user_id ? (int) $this->user_id : null,
            'layout' => $this->layout,
            'media_id' => $this->media_id ? (int) $this->media_id : null,
            'excerpt' => $this->excerpt,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'focus_keyword' => $this->focus_keyword,
            'canonical_url' => $this->canonical_url,
            'robots' => $this->robots,
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'custom_css' => $this->custom_css,
            'custom_js' => $this->custom_js,
            // Read fresh: the loaded relation may still hold the sections from
            // before a save.
            'sections' => $this->sections()->get()->map(fn (Section $section) => [
                'template_id' => $section->template_id ? (int) $section->template_id : null,
                'name' => $section->name,
                'mode' => $section->mode,
                'html' => $section->html,
                'css' => $section->css,
                'values' => $section->values,
                'style' => $section->style,
            ])->all(),
        ];
    }

    /**
     * The page's details, and its sections rebuilt as they were. A section
     * whose library template has since been deleted cannot be rebuilt and is
     * left out (missingSectionsIn counts them for the message).
     */
    public function applyRevision(array $data): void
    {
        $values = array_intersect_key($data, array_flip(self::RESTORABLE));

        // These have column defaults; an old version may not have held them.
        foreach (['robots', 'layout'] as $key) {
            if (($values[$key] ?? null) === null) {
                unset($values[$key]);
            }
        }

        if (($values['media_id'] ?? null) && ! Media::withTrashed()->whereKey($values['media_id'])->exists()) {
            $values['media_id'] = null;
        }

        $this->update($values);

        $templates = SectionTemplate::pluck('id')->flip();
        $sections = collect($data['sections'] ?? [])
            ->reject(fn (array $s) => ($s['mode'] ?? '') === 'Library' && ! isset($templates[$s['template_id'] ?? 0]))
            ->values()
            ->all();

        app(SaveSections::class)->handle($this, $sections);
    }

    /** How many sections of a version could not be rebuilt (see applyRevision). */
    public static function missingSectionsIn(array $data): int
    {
        $templates = SectionTemplate::pluck('id')->flip();

        return collect($data['sections'] ?? [])
            ->filter(fn (array $s) => ($s['mode'] ?? '') === 'Library' && ! isset($templates[$s['template_id'] ?? 0]))
            ->count();
    }

    public function revisionFields(): array
    {
        return [
            'title' => ['Title', 'text'],
            'sections' => ['Sections', 'sections'],
            'excerpt' => ['Excerpt', 'text'],
            'status' => ['Status', 'text'],
            'published_at' => ['Publish date', 'date'],
            'slug' => ['Slug', 'text'],
            'layout' => ['Layout', 'text'],
            'user_id' => ['Author', 'user'],
            'media_id' => ['Featured image', 'media'],
            'meta_title' => ['SEO title', 'text'],
            'meta_description' => ['Meta description', 'text'],
            'focus_keyword' => ['Focus keyword', 'text'],
            'canonical_url' => ['Canonical URL', 'text'],
            'robots' => ['Robots', 'text'],
            'og_title' => ['Social title', 'text'],
            'og_description' => ['Social description', 'text'],
            'og_image' => ['Social image', 'text'],
            'custom_css' => ['Custom CSS', 'text'],
            'custom_js' => ['Custom JavaScript', 'text'],
        ];
    }

    // ── redirects when the slug changes (HasRedirects) ─────────────────────

    public function pathForSlug(string $slug): string
    {
        return '/'.ltrim($slug, '/');
    }

    protected function wasPublic(): bool
    {
        $date = $this->getOriginal('published_at');

        return in_array($this->getOriginal('status'), ['Published', 'Scheduled'], true)
            && $date !== null
            && \Illuminate\Support\Carbon::parse($date)->lte(now());
    }

    /** The homepage lives at "/": its old slug should lead there, not via its new slug. */
    protected function redirectTarget(): string
    {
        return $this->path();
    }
}
