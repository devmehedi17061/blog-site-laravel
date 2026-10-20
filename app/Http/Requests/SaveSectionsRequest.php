<?php

namespace App\Http\Requests;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSectionsRequest extends FormRequest
{
    /**
     * A blank section's HTML, and a rich-text value, reach the page as they
     * are: code in them runs for every visitor. New code is Super Admin only
     * (ScriptGuard); what the page already holds may be saved back as it is.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $page = $this->route('page');
        $sent = collect((array) $this->input('sections', []))
            ->map(fn ($s) => ($s['html'] ?? '')."\n".\App\Support\ScriptGuard::flatten($s['values'] ?? []))->implode("\n");
        $stored = $page instanceof \App\Models\Page
            ? $page->sections()->get()->map(fn ($s) => $s->html."\n".\App\Support\ScriptGuard::flatten($s->values ?? []))->implode("\n")
            : '';

        \App\Support\ScriptGuard::guard($validator, $this->user(), 'sections', $sent, $stored);
    }

    public function rules(): array
    {
        return [
            'sections' => ['present', 'array', 'max:60'],
            'sections.*.id' => ['nullable', 'integer'],
            'sections.*.name' => ['required', 'string', 'max:120'],
            'sections.*.mode' => ['required', Rule::in(Section::MODES)],
            'sections.*.template_id' => ['nullable', 'integer', 'exists:section_templates,id'],

            // Blank sections only.
            'sections.*.html' => ['nullable', 'string', 'max:200000'],
            'sections.*.css' => ['nullable', 'string', 'max:200000'],

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
