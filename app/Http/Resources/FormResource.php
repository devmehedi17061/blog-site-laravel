<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'status' => $this->status,
            'submit_label' => $this->submit_label,
            'success_message' => $this->success_message,
            'notify_email' => $this->notify_email,
            'store_submissions' => (bool) $this->store_submissions,

            'fields' => FormFieldResource::collection($this->whenLoaded('fields')),
            'fields_count' => $this->whenCounted('fields'),
            'submissions_count' => $this->whenCounted('submissions'),

            'updated' => $this->updated_at?->format('d M Y'),

            'can' => [
                'update' => $request->user()?->hasPermission('forms.update') ?? false,
                'delete' => $request->user()?->hasPermission('forms.delete') ?? false,
            ],
        ];
    }
}
