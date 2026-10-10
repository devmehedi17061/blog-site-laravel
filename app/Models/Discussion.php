<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Lesson Q&A. A question has no parent; its replies point at it. A
 * question counts as answered once staff who can edit the course reply,
 * or the student marks it resolved.
 */
class Discussion extends Model
{
    protected $fillable = ['course_id', 'lesson_id', 'user_id', 'parent_id', 'body', 'is_resolved'];

    protected function casts(): array
    {
        return ['is_resolved' => 'boolean'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->oldest();
    }

    public function scopeQuestions(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /** Written by someone who teaches or runs the course. */
    public function byStaff(): bool
    {
        return (bool) $this->author?->isStaff();
    }
}
