<?php

namespace App\Actions\Pages;

use App\Models\Page;
use App\Models\SectionTemplate;
use App\Support\PageTemplates;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a page's whole section list in one transaction.
 *
 * The builder edits everything client side and saves once, so a partial write
 * would leave a page half-built. Sections are matched by id where the client
 * sends one, which keeps rows stable across an edit.
 */
class SaveSections
{
    public function handle(Page $page, array $sections): Page
    {
        DB::transaction(function () use ($page, $sections) {
            $kept = [];

            foreach (array_values($sections) as $position => $input) {
                $kept[] = $this->upsert($page, $input, $position)->id;
            }

            // Anything the client no longer sends was deleted in the builder.
            $page->sections()->whereNotIn('id', $kept ?: [0])->delete();
        });

        return $page->fresh()->load('sections.template');
    }

    /** Builds the starting sections for a brand new page from its template. */
    public function fromTemplate(Page $page, string $template): Page
    {
        return $this->handle($page, PageTemplates::sectionsFor($template));
    }

    private function upsert(Page $page, array $input, int $position)
    {
        $mode = in_array($input['mode'] ?? null, ['Library', 'Blank'], true)
            ? $input['mode']
            : 'Blank';

        // A library section that names a template by slug (as the starter
        // templates do) is resolved to its id here.
        $templateId = $input['template_id'] ?? null;

        if ($mode === 'Library' && ! $templateId && filled($input['template_slug'] ?? null)) {
            $templateId = SectionTemplate::where('slug', $input['template_slug'])->value('id');
        }

        $attributes = [
            'template_id' => $mode === 'Library' ? $templateId : null,
            'name' => $input['name'] ?? 'New section',
            'mode' => $mode,
            // Only a blank section carries its own markup; a library one reads
            // the template's, so storing a copy here would go stale.
            'html' => $mode === 'Blank' ? ($input['html'] ?? null) : null,
            'css' => $mode === 'Blank' ? ($input['css'] ?? null) : null,
            'values' => $mode === 'Library' ? ($input['values'] ?? []) : null,
            'style' => $input['style'] ?? null,
            'position' => $position,
        ];

        $existing = isset($input['id'])
            ? $page->sections()->whereKey($input['id'])->first()
            : null;

        if ($existing) {
            $existing->update($attributes);

            return $existing;
        }

        return $page->sections()->create($attributes);
    }
}
