<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    public const TYPES = ['video', 'article', 'quiz'];

    protected $fillable = [
        'course_id', 'course_section_id', 'title', 'type', 'video_url', 'video_path', 'video_name', 'video_size',
        'body', 'media_id', 'duration_minutes', 'is_preview', 'position',
        'pass_mark', 'max_attempts', 'shuffle_questions',
    ];

    protected function casts(): array
    {
        return [
            'is_preview' => 'boolean',
            'shuffle_questions' => 'boolean',
            'pass_mark' => 'integer',
            'max_attempts' => 'integer',
            'duration_minutes' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'course_section_id');
    }

    /** A worksheet, slides or source files to download with the lesson. */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id')->withTrashed();
    }

    /** A quiz lesson's questions, in order. */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class)->orderBy('position')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    public function isQuiz(): bool
    {
        return $this->type === 'quiz';
    }

    /** An uploaded video, streamed privately, rather than a link. */
    public function hasUpload(): bool
    {
        return filled($this->video_path);
    }

    /**
     * The address to put in an <iframe> for YouTube and Vimeo links, so an
     * instructor can paste the ordinary "watch" link. Null for anything
     * else, which the player shows in a <video> tag instead.
     */
    public function embedUrl(): ?string
    {
        $url = trim((string) $this->video_url);

        if (preg_match('~(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([\w-]{11})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/'.$m[1].'?rel=0&modestbranding=1&iv_load_policy=3&playsinline=1&color=white';
        }

        if (preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/'.$m[1].'?title=0&byline=0&portrait=0&dnt=1';
        }

        return null;
    }

    public function durationLabel(): string
    {
        $m = (int) $this->duration_minutes;

        return $m <= 0 ? '' : ($m < 60 ? __(':m min', ['m' => $m]) : __(':hh :mm', ['h' => intdiv($m, 60), 'm' => str_pad((string) ($m % 60), 2, '0', STR_PAD_LEFT)]));
    }
}
