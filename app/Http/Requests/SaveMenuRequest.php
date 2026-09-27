<?php

namespace App\Http\Requests;

use App\Models\MenuItem;
use App\Support\MenuIcons;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One location's whole menu, saved in one request.
 *
 * The list is flat, each row carrying its own depth — nesting is rebuilt by
 * SaveMenu, which is also where depth is clamped. These rules only decide
 * what a row may contain.
 */
class SaveMenuRequest extends FormRequest
{
    /**
     * Checked before validation, so someone who may not touch the menu is
     * refused outright rather than told which of their rows were invalid.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('menu.update');
    }

    public function rules(): array
    {
        return [
            'location' => ['required', Rule::in(MenuItem::LOCATIONS)],

            'items' => ['present', 'array', 'max:120'],
            'items.*.id' => ['nullable', 'integer'],
            'items.*.label' => ['required', 'string', 'max:80'],
            'items.*.url' => ['nullable', 'string', 'max:2000'],
            'items.*.icon' => ['nullable', 'string', Rule::in(MenuIcons::names())],
            'items.*.target' => ['nullable', Rule::in(MenuItem::TARGETS)],
            'items.*.visibility' => ['nullable', Rule::in(MenuItem::VISIBILITIES)],
            'items.*.depth' => ['nullable', 'integer', 'between:0,'.MenuItem::MAX_DEPTH],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.label.required' => 'Every menu item needs a label.',
            'items.*.icon.in' => 'That icon is not one the menu can draw.',
        ];
    }
}
