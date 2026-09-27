<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'template' => $this->template,
            'layout' => $this->layout ?? 'default',
            'is_home' => $this->isHome(),
            'is_live' => $this->isLive(),
            'excerpt' => $this->excerpt,

            'user_id' => $this->user_id,
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),

            'media_id' => $this->media_id,
            'featured_image' => $this->whenLoaded('featuredImage', fn () => $this->featuredImage
                ? MediaResource::make($this->featuredImage)
                : null),

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

            'sections_count' => $this->whenCounted('sections'),
            'sections' => SectionResource::collection($this->whenLoaded('sections')),

            'published_at' => $this->published_at?->toDateString(),
            'updated' => $this->updated_at?->format('d M Y'),
            'path' => $this->path(),
            'public_url' => url($this->path()),
            // Works whatever the status, for someone signed in to the panel.
            'preview_url' => url('/'.ltrim((string) $this->slug, '/')).'?preview=1',

            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
                // The Code tab's JavaScript box: Super Admin only (App\Support\CustomCode).
                'script' => (bool) $request->user()?->isSuperAdmin(),
            ],
        ];
    }
}
