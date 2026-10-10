<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One use of a coupon: who, on which course, how much it took off. */
class CouponRedemption extends Model
{
    protected $fillable = ['coupon_id', 'user_id', 'course_id', 'payment_id', 'discount'];

    protected function casts(): array
    {
        return ['discount' => 'decimal:2'];
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
