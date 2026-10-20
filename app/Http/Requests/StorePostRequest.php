<?php

namespace App\Http\Requests;

use App\Models\Post;
use App\Support\Seo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', 'unique:posts,slug'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'status' => ['required', Rule::in(Post::STATUSES)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'published_at' => ['nullable', 'date'],
            'content_spacing' => ['nullable', Rule::in(Post::SPACINGS)],
            ...Seo::rules(),
            ...\App\Support\CustomCode::rules(),
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'user_id' => 'author',
            'published_at' => 'publish date',
            'media_id' => 'featured image',
        ] + Seo::attributes();
    }

    public function messages(): array
    {
        return Seo::messages();
    }

    /** JavaScript on a page runs in every visitor's browser: Super Admin only. */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        \App\Support\CustomCode::guard($validator, $this, null);

        // …and so does a script in the body: Super Admin only.
        \App\Support\ScriptGuard::guard($validator, $this->user(), 'body', (string) $this->input('body'), '');
    }
}
