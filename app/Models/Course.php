<?php

namespace App\Models;

use App\Models\Concerns\Filterable;
use App\Models\Concerns\Trashable;
use App\Support\LessonVideos;
use App\Support\Lms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Course extends Model
{
    use Filterable, Trashable;

    public const STATUSES = ['Published', 'Draft'];

    public const LEVELS = ['Beginner', 'Intermediate', 'Advanced', 'All levels'];

    protected $fillable = [
        'user_id', 'category_id', 'media_id', 'title', 'slug', 'subtitle',
        'description', 'outcomes', 'requirements', 'level', 'language',
        'price', 'sale_price', 'intro_video', 'status', 'published_at',
        'is_featured', 'meta_title', 'meta_description',
    ];

    protected array $searchable = ['title', 'subtitle', 'slug'];

    protected array $sortable = ['title', 'status', 'price', 'published_at', 'created_at'];

    protected function casts(): array
    {
        return [
            'outcomes' => 'array',
            'requirements' => 'array',
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Gone for good (emptied from the Trash): so are its uploaded videos.
        static::forceDeleted(fn (Course $course) => LessonVideos::deleteCourse($course->id));
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function thumbnail(): BelongsTo
    {
        // withTrashed: a file in the Trash is still on disk.
        return $this->belongsTo(Media::class, 'media_id')->withTrashed();
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('position')->orderBy('id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function announcements(): HasMany
    {
        return $this->hasMany(CourseAnnouncement::class)->latest();
    }

    public function discussions(): HasMany
    {
        return $this->hasMany(Discussion::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CourseReview::class);
    }

    /** Live on the public site. */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'Published')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return in_array($status, self::STATUSES, true) ? $query->where('status', $status) : $query;
    }

    public function scopeCategory(Builder $query, int|string|null $categoryId): Builder
    {
        return $categoryId ? $query->where('category_id', $categoryId) : $query;
    }

    public function scopeLevel(Builder $query, ?string $level): Builder
    {
        return in_array($level, self::LEVELS, true) ? $query->where('level', $level) : $query;
    }

    public function isPublished(): bool
    {
        return $this->status === 'Published' && $this->published_at !== null && $this->published_at->lte(now());
    }

    /** What a student pays today: the sale price when there is one. */
    public function currentPrice(): float
    {
        $sale = $this->sale_price !== null ? (float) $this->sale_price : null;

        return $sale !== null && $sale < (float) $this->price ? $sale : (float) $this->price;
    }

    public function isFree(): bool
    {
        return $this->currentPrice() <= 0;
    }

    public function onSale(): bool
    {
        return $this->sale_price !== null && (float) $this->sale_price < (float) $this->price;
    }

    public function priceLabel(): string
    {
        return $this->isFree() ? __('Free') : Lms::money($this->currentPrice());
    }

    /** The first lesson in curriculum order, where "Start" leads. */
    public function firstLesson(): ?Lesson
    {
        return $this->orderedLessons()->first();
    }

    /** Every lesson, in the order the curriculum shows them. */
    public function orderedLessons(): Collection
    {
        $sections = $this->relationLoaded('sections') ? $this->sections : $this->sections()->with('lessons')->get();

        return $sections->flatMap(fn (CourseSection $section) => $section->lessons)->values();
    }

    /**
     * Lessons a student may not open yet because an earlier one is not
     * complete (Settings → LMS → "Lessons in order"). Everything up to and
     * including the first unfinished lesson is open, as is anything already
     * finished and every free preview.
     *
     * @param  array<int, int>  $done  ids of the lessons this student finished
     * @return array<int, int> ids that are locked
     */
    public function lockedLessonIds(array $done): array
    {
        if (! Lms::setting('sequential')) {
            return [];
        }

        $locked = [];
        $blocked = false;

        foreach ($this->orderedLessons() as $lesson) {
            $finished = in_array($lesson->id, $done, true);

            if ($blocked && ! $finished && ! $lesson->is_preview) {
                $locked[] = $lesson->id;
            }

            // The first unfinished lesson is open; everything after it waits.
            if (! $finished) {
                $blocked = true;
            }
        }

        return $locked;
    }

    public function totalMinutes(): int
    {
        return (int) $this->lessons()->sum('duration_minutes');
    }

    public function averageRating(): ?float
    {
        $avg = $this->reviews()->where('is_approved', true)->avg('rating');

        return $avg === null ? null : round((float) $avg, 1);
    }

    public function url(): string
    {
        return route('courses.show', $this->slug);
    }
}
