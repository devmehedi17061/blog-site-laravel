<?php

namespace App\Http\Requests;

use App\Models\Post;
use App\Support\CustomCode;
use App\Support\Seo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePostRequest extends FormRequest
{
    public function rules(): array
    {
        $postId = $this->route('post')?->id;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('posts', 'slug')->ignore($postId)],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'status' => ['sometimes', 'required', Rule::in(Post::STATUSES)],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'published_at' => ['nullable', 'date'],
            'content_spacing' => ['nullable', Rule::in(Post::SPACINGS)],
            ...Seo::rules(),
            ...CustomCode::rules(),
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
    public function withValidator(Validator $validator): void
    {
        CustomCode::guard($validator, $this, $this->route('post'));
    }
}
