<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
            'initials' => $this->initials,
            'avatar' => $this->avatar,
            'role_id' => $this->role_id,
            'role' => $this->whenLoaded('role', fn () => $this->role ? [
                'id' => $this->role->id,
                'name' => $this->role->name,
                'slug' => $this->role->slug,
                'scope' => $this->role->scope,
            ] : null),
            'permissions' => $this->when(
                $this->relationLoaded('role') && $this->role?->relationLoaded('permissions'),
                fn () => $this->role->permissions->pluck('slug')
            ),

            // What deleting this account would leave behind.
            'posts_count' => $this->whenCounted('posts'),
            'pages_count' => $this->whenCounted('pages'),
            'media_count' => $this->whenCounted('media'),

            'last_seen_at' => $this->last_seen_at?->diffForHumans(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'joined' => $this->created_at?->format('d M Y'),

            'is_self' => $viewer?->is($this->resource) ?? false,
            'can' => [
                'update' => $viewer?->can('update', $this->resource) ?? false,
                'delete' => $viewer?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
