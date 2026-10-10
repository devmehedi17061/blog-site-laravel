<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPageFields;
use App\Models\Page;
use App\Support\CustomCode;
use App\Support\PageTemplates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePageRequest extends FormRequest
{
    use ValidatesPageFields;

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', 'unique:pages,slug', $this->notReserved()],
            'template' => ['nullable', Rule::in(PageTemplates::keys())],
            'status' => ['required', Rule::in(Page::STATUSES)],
            ...$this->sharedPageRules(),
        ];
    }

    /** JavaScript on a page runs in every visitor's browser: Super Admin only. */
    public function withValidator(Validator $validator): void
    {
        CustomCode::guard($validator, $this, null);
    }
}
