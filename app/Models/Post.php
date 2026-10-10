<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use App\Models\Concerns\HasRedirects;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\Trashable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Post extends Model
{
    use Filterable, HasFactory, HasRedirects, HasRevisions, Trashable;

    public const STATUSES = ['Published', 'Draft', 'Scheduled'];

    public const SPACINGS = ['tight', 'normal', 'loose'];

    protected $fillable = [
        'user_id', 'category_id', 'media_id', 'title', 'slug',
        'excerpt', 'body', 'content_spacing', 'status', 'published_at',
        'meta_title', 'meta_description', 'focus_keyword', 'canonical_url',
        'robots', 'og_title', 'og_description', 'og_image', 'custom_css', 'custom_js',
    ];

    protected array $searchable = ['title', 'excerpt', 'slug'];

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

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function featuredImage(): BelongsTo
    {
        // withTrashed: a file in the Trash is still on disk and keeps showing
        // until it is deleted for good.
        return $this->belongsTo(Media::class, 'media_id')->withTrashed();
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

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if (! $status || $status === 'All') {
            return $query;
        }

        return $query->where('status', $status);
    }

    public function scopeCategory(Builder $query, int|string|null $categoryId): Builder
    {
        if (! $categoryId) {
            return $query;
        }

        return $query->where('category_id', $categoryId);
    }

    // ── revision history (HasRevisions) ───────────────────────────────────

    /** Brought back by a restore. Status, date, address and author are not. */
    private const RESTORABLE = [
        'title', 'excerpt', 'body', 'content_spacing', 'category_id', 'media_id',
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
            'category_id' => $this->category_id ? (int) $this->category_id : null,
            'media_id' => $this->media_id ? (int) $this->media_id : null,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'content_spacing' => $this->content_spacing,
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
        ];
    }

    public function applyRevision(array $data): void
    {
        $values = array_intersect_key($data, array_flip(self::RESTORABLE));

        // These have column defaults; an old version may not have held them.
        foreach (['robots', 'content_spacing'] as $key) {
            if (($values[$key] ?? null) === null) {
                unset($values[$key]);
            }
        }

        // A category or image deleted since then cannot come back with it.
        if (($values['category_id'] ?? null) && ! Category::whereKey($values['category_id'])->exists()) {
            $values['category_id'] = null;
        }
        if (($values['media_id'] ?? null) && ! Media::withTrashed()->whereKey($values['media_id'])->exists()) {
            $values['media_id'] = null;
        }

        $this->update($values);
    }

    public function revisionFields(): array
    {
        return [
            'title' => ['Title', 'text'],
            'body' => ['Content', 'html'],
            'excerpt' => ['Excerpt', 'text'],
            'status' => ['Status', 'text'],
            'published_at' => ['Publish date', 'date'],
            'slug' => ['Slug', 'text'],
            'category_id' => ['Category', 'category'],
            'user_id' => ['Author', 'user'],
            'media_id' => ['Featured image', 'media'],
            'content_spacing' => ['Paragraph spacing', 'text'],
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
        return '/blog/'.ltrim($slug, '/');
    }

    protected function wasPublic(): bool
    {
        $date = $this->getOriginal('published_at');

        return in_array($this->getOriginal('status'), ['Published', 'Scheduled'], true)
            && $date !== null
            && Carbon::parse($date)->lte(now());
    }
}
