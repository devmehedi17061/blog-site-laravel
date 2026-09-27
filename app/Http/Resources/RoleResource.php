<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'scope' => $this->scope,
            'description' => $this->description,
            'is_locked' => (bool) $this->is_locked,
            'permissions' => $this->whenLoaded('permissions', fn () => $this->permissions->pluck('slug')->values()),
            'users_count' => $this->whenCounted('users'),
            'updated' => $this->updated_at?->format('d M Y'),

            'is_own' => $viewer?->role_id === $this->id,
            'can' => [
                'update' => $viewer?->can('update', $this->resource) ?? false,
                'delete' => $viewer?->can('delete', $this->resource) ?? false,
            ],
        ];
    }
}
