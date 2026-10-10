<?php

namespace App\Models;

use App\Support\Lms;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A discount code. Percent or fixed money off, for some courses or all,
 * with limits on how often and until when. Every use is a
 * CouponRedemption, so the limits count real uses — a rejected payment
 * gives its use back.
 */
class Coupon extends Model
{
    public const TYPES = ['percent', 'fixed'];

    protected $fillable = [
        'code', 'description', 'type', 'value', 'max_uses', 'max_uses_per_user',
        'min_amount', 'starts_at', 'expires_at', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_amount' => 'decimal:2',
            'max_uses' => 'integer',
            'max_uses_per_user' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** Codes are matched without caring about case or stray spaces. */
    public static function normalise(?string $code): string
    {
        return Str::upper(preg_replace('/\s+/', '', (string) $code));
    }

    public static function findByCode(?string $code): ?self
    {
        $code = self::normalise($code);

        return $code === '' ? null : static::where('code', $code)->first();
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    public function appliesTo(Course $course): bool
    {
        $ids = $this->relationLoaded('courses') ? $this->courses->pluck('id') : $this->courses()->pluck('courses.id');

        return $ids->isEmpty() || $ids->contains($course->id);
    }

    /** Money off a price, never more than the price itself. */
    public function discountFor(float $price): float
    {
        $off = $this->type === 'percent'
            ? round($price * min(100, (float) $this->value) / 100, 2)
            : (float) $this->value;

        return round(min($price, max(0, $off)), 2);
    }

    public function label(): string
    {
        return $this->type === 'percent'
            ? __(':percent% off', ['percent' => rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.')])
            : __(':amount off', ['amount' => Lms::money($this->value)]);
    }

    /** "Active", "Scheduled", "Expired", "Used up" or "Off" — for the admin list. */
    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'Off',
            $this->starts_at && $this->starts_at->isFuture() => 'Scheduled',
            $this->expires_at && $this->expires_at->isPast() => 'Expired',
            $this->max_uses !== null && $this->redemptions()->count() >= $this->max_uses => 'Used up',
            default => 'Active',
        };
    }

    /**
     * Why this coupon cannot be used by this student on this course right
     * now, or null when it can. A use waiting on this student's own pending
     * payment for the same course does not count against them: sending
     * the payment again replaces it.
     */
    public function problemFor(?User $user, Course $course): ?string
    {
        $price = $course->currentPrice();

        $pendingMine = fn ($q) => $user
            ? $q->where(fn ($q) => $q->where('user_id', '!=', $user->id)
                ->orWhere('course_id', '!=', $course->id)
                ->orWhereNull('payment_id')
                ->orWhereHas('payment', fn ($p) => $p->where('status', '!=', 'Pending')))
            : $q;

        return match (true) {
            ! $this->is_active => __('This coupon is not active.'),
            $this->starts_at && $this->starts_at->isFuture() => __('This coupon starts on :date.', ['date' => $this->starts_at->translatedFormat('d M Y')]),
            $this->expires_at && $this->expires_at->isPast() => __('This coupon has expired.'),
            ! $this->appliesTo($course) => __('This coupon is not for this course.'),
            $price <= 0 => __('This course is already free.'),
            $this->min_amount !== null && $price < (float) $this->min_amount => __('This coupon is for courses of :amount or more.', ['amount' => Lms::money($this->min_amount)]),
            $this->max_uses !== null && $this->redemptions()->tap($pendingMine)->count() >= $this->max_uses => __('This coupon has been fully used.'),
            $user && $this->max_uses_per_user > 0
                && $this->redemptions()->where('user_id', $user->id)->tap($pendingMine)->count() >= $this->max_uses_per_user => __('You have already used this coupon.'),
            default => null,
        };
    }
}
