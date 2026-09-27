<?php

namespace App\Http\Requests;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Checked before validation, so someone who may not touch categories is
     * refused outright rather than told which of their fields were invalid.
     */
    public function authorize(): bool
    {
        $permission = $this->isMethod('POST') ? 'categories.create' : 'categories.update';

        return (bool) $this->user()?->hasPermission($permission);
    }

    public function rules(): array
    {
        $id = $this->route('category')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('categories', 'slug')->ignore($id)],
            'description' => ['nullable', 'string', 'max:1000'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'meta_title' => ['nullable', 'string', 'max:120'],
            'meta_description' => ['nullable', 'string', 'max:300'],

            // Whether this parent is legal — not its own descendant, not too
            // deep — is decided in the controller, which can see the tree.
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'slug.unique' => 'Another category already uses that address.',
            'parent_id.exists' => 'That parent category no longer exists.',
        ];
    }
}
