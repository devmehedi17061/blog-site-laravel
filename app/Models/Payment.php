<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One payment a student reported for a course: verified or rejected by an
 * admin. An enrollment can have several (a rejected one, then a new one).
 */
class Payment extends Model
{
    public const STATUSES = ['Pending', 'Verified', 'Rejected'];

    protected $fillable = [
        'user_id', 'course_id', 'enrollment_id', 'amount', 'expected_amount', 'method',
        'sender_number', 'transaction_id', 'proof_path', 'note', 'status',
        'reviewed_by', 'reviewed_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'expected_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
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

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        return in_array($status, self::STATUSES, true) ? $query->where('status', $status) : $query;
    }

    public function isPending(): bool
    {
        return $this->status === 'Pending';
    }

    public function isVerified(): bool
    {
        return $this->status === 'Verified';
    }

    /** Paid less than the course cost at the time. */
    public function isShort(): bool
    {
        return (float) $this->amount + 0.009 < (float) $this->expected_amount;
    }

    /**
     * Other payments that used the same transaction ID with the same method
     * and were not rejected — the commonest way one payment is claimed twice.
     */
    public function duplicates(): Collection
    {
        return static::query()
            ->whereKeyNot($this->id)
            ->where('method', $this->method)
            ->where('transaction_id', $this->transaction_id)
            ->where('status', '!=', 'Rejected')
            ->with(['student:id,name', 'course:id,title'])
            ->get();
    }

    /** For receipts: INV-00012. */
    public function invoiceNumber(): string
    {
        return 'INV-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }
}
