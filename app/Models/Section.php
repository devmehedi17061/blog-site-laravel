<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One section placed on one page.
 *
 * Library: points at a section template and holds only this page's values.
 * Blank:   carries its own HTML and CSS and has no template behind it.
 */
class Section extends Model
{
    use HasFactory;

    public const MODES = ['Library', 'Blank'];

    protected $fillable = ['page_id', 'template_id', 'name', 'mode', 'html', 'css', 'values', 'style', 'position'];

    protected function casts(): array
    {
        return [
            'values' => 'array',
            'style' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(SectionTemplate::class, 'template_id');
    }

    public function isLibrary(): bool
    {
        return $this->mode === 'Library' && $this->template !== null;
    }

    /** The markup to render: the template's for a library section, its own otherwise. */
    public function sourceHtml(): string
    {
        return (string) ($this->isLibrary() ? $this->template->html : $this->html);
    }

    public function sourceCss(): string
    {
        return (string) ($this->isLibrary() ? $this->template->css : $this->css);
    }

    /** @return array<int, array<string, mixed>> the field definition tree */
    public function fieldDefinitions(): array
    {
        return $this->isLibrary() ? ($this->template->fields ?? []) : [];
    }
}
