<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Enrollment extends Model
{
    /** Pending: a paid course whose payment has not been checked yet. */
    public const STATUSES = ['Active', 'Pending', 'Cancelled'];

    protected $fillable = [
        'user_id', 'course_id', 'status', 'amount', 'payment_method',
        'transaction_id', 'note', 'enrolled_at', 'completed_at', 'last_lesson_id', 'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }

    /** Where the student was last, for "continue where you left off". */
    public function lastLesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class, 'last_lesson_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'Active');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return in_array($status, self::STATUSES, true) ? $query->where('status', $status) : $query;
    }

    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    /** Lessons finished, out of the course's lessons, as a whole percent. */
    public function progress(): int
    {
        $total = $this->course?->lessons()->count() ?? 0;

        if ($total === 0) {
            return 0;
        }

        $done = LessonCompletion::where('user_id', $this->user_id)->where('course_id', $this->course_id)->count();

        return (int) min(100, round($done / $total * 100));
    }

    /** Notes the date the last lesson was finished; clears it if one is undone. */
    public function syncCompletion(): void
    {
        $complete = $this->progress() >= 100;

        if ($complete && ! $this->completed_at) {
            $this->forceFill(['completed_at' => now()])->save();
        } elseif (! $complete && $this->completed_at) {
            $this->forceFill(['completed_at' => null])->save();
        }
    }
}
