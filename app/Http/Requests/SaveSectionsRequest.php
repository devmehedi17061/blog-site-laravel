<?php

namespace App\Http\Requests;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSectionsRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'sections' => ['present', 'array', 'max:60'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.name' => ['required', 'string', 'max:120'],
            'sections.*.mode' => ['required', Rule::in(Section::MODES)],
            'sections.*.template_id' => ['nullable', 'integer', 'exists:section_templates,id'],

            // Blank sections only.
            'sections.*.html' => ['nullable', 'string', 'max:60000'],
            'sections.*.css' => ['nullable', 'string', 'max:60000'],

            // Library sections only. The shape is the template's business, so
            // this just caps the size of what a page can store.
            'sections.*.values' => ['nullable', 'array'],

            // The section's own box settings. Free text, because the author
            // types CSS values rather than picking from a list — so these
            // rules only cap the length of what a page can store.
            'sections.*.style' => ['nullable', 'array'],

            // The full-width band.
            'sections.*.style.background' => ['nullable', 'string', 'max:255'],
            'sections.*.style.background_image' => ['nullable', 'string', 'max:2000'],
            'sections.*.style.background_size' => ['nullable', 'string', 'max:120'],
            'sections.*.style.background_position' => ['nullable', 'string', 'max:120'],
            'sections.*.style.background_repeat' => ['nullable', 'string', 'max:120'],
            'sections.*.style.color' => ['nullable', 'string', 'max:120'],
            'sections.*.style.padding' => ['nullable', 'string', 'max:120'],
            'sections.*.style.margin' => ['nullable', 'string', 'max:120'],
            'sections.*.style.border_radius' => ['nullable', 'string', 'max:120'],
            'sections.*.style.custom' => ['nullable', 'string', 'max:2000'],

            // The column of content inside it.
            'sections.*.style.max_width' => ['nullable', 'string', 'max:120'],
            'sections.*.style.align' => ['nullable', 'string', 'max:40'],
            'sections.*.style.content_custom' => ['nullable', 'string', 'max:2000'],

            'sections.*.style.hide_mobile' => ['nullable', 'boolean'],
            'sections.*.style.hide_desktop' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'sections.*.name.required' => 'Every section needs a name.',
            'sections.*.template_id.exists' => 'That library section no longer exists.',
        ];
    }
}
