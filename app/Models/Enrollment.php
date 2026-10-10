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

    /**
     * The certificate number: C-00012-261006-7KQ2PA. The last part is
     * signed with the app key, so numbers cannot be guessed to look up who
     * finished what on the public verification page.
     */
    public function certificateSerial(): ?string
    {
        if (! $this->completed_at) {
            return null;
        }

        $base = 'C-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT).'-'.$this->completed_at->format('ymd');
        $sign = strtoupper(substr(base_convert(substr(hash_hmac('sha256', $base.'|'.$this->user_id, (string) config('app.key')), 0, 12), 16, 36), 0, 6));

        return $base.'-'.$sign;
    }

    /** The enrollment a certificate number belongs to, if it is genuine. */
    public static function fromCertificateSerial(?string $serial): ?self
    {
        $serial = strtoupper(trim((string) $serial));

        if (! preg_match('/^C-(\d{5,})-\d{6}-[A-Z0-9]{4,6}$/', $serial, $m)) {
            return null;
        }

        $enrollment = static::with(['student:id,name', 'course' => fn ($q) => $q->with('instructor:id,name')])
            ->where('status', 'Active')
            ->find((int) $m[1]);

        return $enrollment && hash_equals((string) $enrollment->certificateSerial(), $serial) ? $enrollment : null;
    }

    /** Notes the date the last lesson was finished. */
    public function syncCompletion(): void
    {
        // Set once, never taken back: a certificate a student earned stays
        // theirs when a lesson is added later or one is un-ticked.
        if (! $this->completed_at && $this->progress() >= 100) {
            $this->forceFill(['completed_at' => now()])->save();
        }
    }
}
