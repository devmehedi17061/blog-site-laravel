<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,

            'media_id' => $this->media_id,
            'image' => $this->whenLoaded('image', fn () => $this->image ? [
                'id' => $this->image->id,
                'url' => $this->image->url,
            ] : null),

            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,

            'depth' => (int) $this->depth,
            'position' => (int) $this->position,

            // Posts filed directly here, and posts anywhere in the branch —
            // the second is what the public archive actually shows.
            'posts_count' => $this->whenCounted('posts'),
            'branch_posts_count' => $this->when(isset($this->branch_posts_count), fn () => (int) $this->branch_posts_count),

            'public_url' => '/category/'.$this->slug,

            'can' => [
                'update' => $request->user()?->hasPermission('categories.update') ?? false,
                'delete' => $request->user()?->hasPermission('categories.delete') ?? false,
            ],
        ];
    }
}
