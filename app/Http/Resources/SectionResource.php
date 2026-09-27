<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mode' => $this->mode,
            'position' => $this->position,

            'template_id' => $this->template_id,
            // The shape comes from the library, so the editor reads it from
            // here rather than keeping a stale copy on the page.
            'template' => $this->whenLoaded('template', fn () => $this->template ? [
                'id' => $this->template->id,
                'name' => $this->template->name,
                'fields' => $this->template->fields ?? [],
                // The markup comes along so the builder can preview the
                // section without a second request per row.
                'html' => $this->template->html,
                'css' => $this->template->css,
            ] : null),

            'html' => $this->html,
            'css' => $this->css,
            'values' => $this->values ?? new \stdClass,
            'style' => $this->style ?? new \stdClass,
        ];
    }
}
