<?php

namespace App\Http\Resources;

use App\Support\MenuIcons;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location' => $this->location,
            'parent_id' => $this->parent_id,
            'label' => $this->label,
            'icon' => MenuIcons::has($this->icon) ? $this->icon : null,
            'url' => $this->url,
            'target' => $this->target,
            'visibility' => $this->visibility,
            'depth' => (int) $this->depth,
            'position' => (int) $this->position,
        ];
    }
}
