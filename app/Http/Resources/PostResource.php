<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'content_spacing' => $this->content_spacing ?? 'normal',
            'status' => $this->status,

            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),

            'user_id' => $this->user_id,
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),

            'media_id' => $this->media_id,
            'featured_image' => $this->whenLoaded('featuredImage', fn () => $this->featuredImage
                ? MediaResource::make($this->featuredImage)
                : null),

            // SEO
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'focus_keyword' => $this->focus_keyword,
            'canonical_url' => $this->canonical_url,
            'robots' => $this->robots ?: 'index, follow',
            'og_title' => $this->og_title,
            'og_description' => $this->og_description,
            'og_image' => $this->og_image,
            'custom_css' => (string) $this->custom_css,
            'custom_js' => (string) $this->custom_js,

            'published_at' => $this->published_at?->toDateString(),
            'date' => $this->published_at?->format('d M Y') ?? $this->created_at?->format('d M Y'),
            'created_at' => $this->created_at?->toDateTimeString(),
            'public_url' => route('blog.show', $this->slug),

            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                // The Code tab's JavaScript box: Super Admin only (App\Support\CustomCode).
                'script' => (bool) $request->user()?->isSuperAdmin(),
            ],
        ];
    }
}
