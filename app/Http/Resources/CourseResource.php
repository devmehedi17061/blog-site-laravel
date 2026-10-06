<?php

namespace App\Http\Resources;

use App\Support\Lms;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'outcomes' => $this->outcomes ?? [],
            'requirements' => $this->requirements ?? [],
            'level' => $this->level,
            'language' => $this->language,
            'price' => $this->price === null ? '' : (float) $this->price,
            'sale_price' => $this->sale_price === null ? '' : (float) $this->sale_price,
            'price_label' => $this->isFree() ? 'Free' : Lms::money($this->currentPrice()),
            'intro_video' => $this->intro_video,
            'status' => $this->status,
            'is_featured' => (bool) $this->is_featured,
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,

            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'user_id' => $this->user_id,
            'instructor' => $this->whenLoaded('instructor', fn () => $this->instructor?->name),
            'media_id' => $this->media_id,
            'thumbnail' => $this->whenLoaded('thumbnail', fn () => $this->thumbnail ? MediaResource::make($this->thumbnail) : null),

            'lessons_count' => $this->whenCounted('lessons'),
            'students_count' => $this->whenCounted('enrollments'),

            'published_at' => $this->published_at?->toDateString(),
            'date' => $this->published_at?->format('d M Y') ?? $this->created_at?->format('d M Y'),
            'public_url' => route('courses.show', $this->slug),

            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
