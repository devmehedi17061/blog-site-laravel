<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One try at a quiz, graded on the server. */
class QuizAttempt extends Model
{
    protected $fillable = ['user_id', 'lesson_id', 'score', 'earned', 'possible', 'passed', 'answers'];

    protected function casts(): array
    {
        return ['passed' => 'boolean', 'answers' => 'array', 'score' => 'integer'];
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
