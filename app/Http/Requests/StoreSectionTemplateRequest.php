<?php

namespace App\Http\Requests;

use App\Models\SectionTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionTemplateRequest extends FormRequest
{
    /**
     * Checked here rather than in the controller so a user who may not touch
     * the library is refused outright, instead of being told which of their
     * fields were invalid first.
     */
    public function authorize(): bool
    {
        $permission = $this->isMethod('POST') ? 'pages.create' : 'pages.update';

        return (bool) $this->user()?->hasPermission($permission);
    }

    /** New code in a library section's markup is Super Admin only (ScriptGuard). */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $template = $this->route('template');
        \App\Support\ScriptGuard::guard($validator, $this->user(), 'html', (string) $this->input('html'),
            $template instanceof \App\Models\SectionTemplate ? (string) $template->html : '');
    }

    public function rules(): array
    {
        $id = $this->route('template')?->id;

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('section_templates', 'name')->ignore($id)],
            'description' => ['nullable', 'string', 'max:255'],
            'html' => ['nullable', 'string', 'max:200000'],
            'css' => ['nullable', 'string', 'max:200000'],

            'fields' => ['nullable', 'array', 'max:40'],
            'fields.*.key' => ['required', 'string', 'max:60'],
            'fields.*.label' => ['nullable', 'string', 'max:120'],
            'fields.*.type' => ['required', Rule::in(SectionTemplate::FIELD_TYPES)],
            'fields.*.default' => ['nullable', 'string', 'max:2000'],
            'fields.*.options' => ['nullable', 'string', 'max:500'],
            'fields.*.items' => ['nullable', 'array', 'max:12'],

            // One level of nesting: a group holds plain fields, not more groups.
            'fields.*.fields' => ['nullable', 'array', 'max:20'],
            'fields.*.fields.*.key' => ['required', 'string', 'max:60'],
            'fields.*.fields.*.label' => ['nullable', 'string', 'max:120'],
            'fields.*.fields.*.type' => ['required', Rule::in(['text', 'richtext', 'media', 'url'])],
        ];
    }

    public function messages(): array
    {
        return [
            'name.unique' => 'A section with that name is already in the library.',
            'fields.*.key.required' => 'Every field needs a key to use as its token.',
            'fields.*.fields.*.type.in' => 'A group can hold text, rich text, media and link fields.',
        ];
    }
}
