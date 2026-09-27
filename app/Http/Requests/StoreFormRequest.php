<?php

namespace App\Http\Requests;

use App\Models\Form;
use App\Models\FormField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Creating and updating a form, fields and all — the builder saves the whole
 * thing in one request, so they are validated together.
 */
class StoreFormRequest extends FormRequest
{
    /**
     * Checked before validation, so someone who may not touch forms is
     * refused outright rather than told which of their fields were invalid.
     */
    public function authorize(): bool
    {
        $permission = $this->isMethod('POST') ? 'forms.create' : 'forms.update';

        return (bool) $this->user()?->hasPermission($permission);
    }

    public function rules(): array
    {
        $id = $this->route('form')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('forms', 'slug')->ignore($id)],
            'description' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', Rule::in(Form::STATUSES)],
            'submit_label' => ['nullable', 'string', 'max:60'],
            'success_message' => ['nullable', 'string', 'max:500'],
            'notify_email' => ['nullable', 'email:rfc', 'max:255'],
            'store_submissions' => ['nullable', 'boolean'],

            'fields' => ['nullable', 'array', 'max:60'],
            'fields.*.id' => ['nullable', 'integer'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.name' => ['nullable', 'string', 'max:60'],
            'fields.*.type' => ['required', Rule::in(FormField::TYPES)],
            'fields.*.placeholder' => ['nullable', 'string', 'max:160'],
            'fields.*.help' => ['nullable', 'string', 'max:255'],
            'fields.*.width' => ['nullable', Rule::in(FormField::WIDTHS)],
            'fields.*.rule' => ['nullable', 'string', 'max:255'],
            'fields.*.required' => ['nullable', 'boolean'],
            'fields.*.options' => ['nullable', 'array', 'max:60'],
            'fields.*.options.*' => ['nullable', 'string', 'max:160'],
        ];
    }

    public function messages(): array
    {
        return [
            'fields.*.label.required' => 'Every field needs a label.',
            'slug.unique' => 'Another form already uses that address.',
        ];
    }
}
