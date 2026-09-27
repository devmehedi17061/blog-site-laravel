<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormField extends Model
{
    use HasFactory;

    /**
     * What the builder offers. Kept here rather than in the column, so adding
     * a type is a one-line change instead of a migration.
     */
    public const TYPES = [
        'text', 'email', 'tel', 'url', 'number', 'date',
        'textarea', 'select', 'radio', 'checkbox', 'file',
    ];

    /** Types whose answer is chosen from a list the author writes. */
    public const CHOICE_TYPES = ['select', 'radio', 'checkbox'];

    public const WIDTHS = ['full', 'half'];

    protected $fillable = [
        'form_id', 'type', 'label', 'name', 'placeholder', 'help',
        'width', 'rule', 'required', 'options', 'position',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'options' => 'array',
        ];
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    public function isChoice(): bool
    {
        return in_array($this->type, self::CHOICE_TYPES, true);
    }

    /**
     * A checkbox with options is a set of boxes and answers with a list; one
     * on its own is a single yes/no, like an agreement to terms.
     */
    public function isMultiple(): bool
    {
        return $this->type === 'checkbox' && $this->choices() !== [];
    }

    public function isToggle(): bool
    {
        return $this->type === 'checkbox' && $this->choices() === [];
    }

    /** @return array<int, string> */
    public function choices(): array
    {
        return array_values(array_filter(
            array_map('strval', $this->options ?? []),
            fn (string $option) => trim($option) !== ''
        ));
    }
}
