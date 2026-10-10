<?php

namespace App\Http\Resources;

use App\Support\Lms;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $courses = $this->whenLoaded('courses', fn () => $this->courses, collect());

        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'type' => $this->type,
            'value' => (float) $this->value,
            'label' => $this->label(),
            'max_uses' => $this->max_uses,
            'max_uses_per_user' => $this->max_uses_per_user,
            'min_amount' => $this->min_amount === null ? '' : (float) $this->min_amount,
            'starts_at' => $this->starts_at?->format('Y-m-d\TH:i'),
            'expires_at' => $this->expires_at?->format('Y-m-d\TH:i'),
            'expires_label' => $this->expires_at?->format('d M Y'),
            'is_active' => (bool) $this->is_active,
            'state' => $this->state(),
            'course_ids' => $courses->pluck('id')->values(),
            'applies_to' => $courses->isEmpty() ? 'All courses' : $courses->pluck('title')->join(', '),
            'used' => (int) ($this->redemptions_count ?? $this->redemptions()->count()),
            'discount_given' => Lms::money((float) ($this->redemptions_sum_discount ?? 0)),
            'share_url' => url('/courses').'?coupon='.$this->code,
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
