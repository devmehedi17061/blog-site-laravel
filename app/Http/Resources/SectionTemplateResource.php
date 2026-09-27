<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'html' => $this->html,
            'css' => $this->css,
            'fields' => $this->fields ?? [],
            'used_by' => $this->whenCounted('sections'),
            'author' => $this->whenLoaded('author', fn () => $this->author?->name),
            'updated' => $this->updated_at?->format('d M Y'),
        ];
    }
}
