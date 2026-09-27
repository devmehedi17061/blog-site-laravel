<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InboxRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            // "all" rather than an empty string, so the filter round-trips
            // through the query string unchanged.
            'form' => ['nullable', 'string', 'max:20'],
            'state' => ['nullable', Rule::in(['All', 'Unread', 'Read'])],
            'per_page' => ['nullable', 'integer', 'between:5,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function perPage(): int
    {
        return (int) ($this->input('per_page') ?: 20);
    }
}
