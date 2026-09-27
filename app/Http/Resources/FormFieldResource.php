<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FormFieldResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'label' => $this->label,
            'name' => $this->name,
            'placeholder' => $this->placeholder,
            'help' => $this->help,
            'width' => $this->width,
            'rule' => $this->rule,
            'required' => (bool) $this->required,
            // Always an array, so the builder can bind to it without a guard.
            'options' => $this->options ?? [],
            'position' => $this->position,
        ];
    }
}
