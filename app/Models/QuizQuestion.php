<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One question of a quiz lesson. Options are [{text, correct}]; a single or
 * true/false question has exactly one correct option, a multiple-choice one
 * at least one — and is right only when exactly those are chosen.
 */
class QuizQuestion extends Model
{
    public const TYPES = ['single', 'multiple', 'true_false'];

    protected $fillable = ['lesson_id', 'question', 'type', 'options', 'explanation', 'points', 'position'];

    protected function casts(): array
    {
        return ['options' => 'array', 'points' => 'integer'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    /** @return array<int, int> indexes of the right options */
    public function correctIndexes(): array
    {
        return collect($this->options)->keys()->filter(fn ($i) => (bool) ($this->options[$i]['correct'] ?? false))->values()->all();
    }

    /** @param  array<int, int|string>  $chosen */
    public function isRight(array $chosen): bool
    {
        $chosen = collect($chosen)->map(fn ($i) => (int) $i)->unique()->sort()->values()->all();

        return $chosen === collect($this->correctIndexes())->sort()->values()->all();
    }
}
