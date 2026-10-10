<?php

namespace App\Http\Requests\Concerns;

use App\Models\Page;
use App\Support\CustomCode;
use App\Support\Seo;
use Closure;
use Illuminate\Validation\Rule;

/** The rules the create and edit requests share. */
trait ValidatesPageFields
{
    protected function sharedPageRules(): array
    {
        return [
            'layout' => ['nullable', Rule::in(array_keys(Page::LAYOUTS))],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'published_at' => ['nullable', 'date'],
            ...Seo::rules(),
            ...CustomCode::rules(),
        ];
    }

    /** A slug whose first segment another part of the site already owns. */
    protected function notReserved(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $first = strtolower(explode('/', trim((string) $value, '/ '))[0] ?? '');

            if ($first !== '' && in_array($first, Page::RESERVED_SLUGS, true)) {
                $fail("\"/{$first}\" is already used by the site itself. Pick another address.");
            }
        };
    }

    public function attributes(): array
    {
        return ['media_id' => 'featured image'] + Seo::attributes();
    }

    public function messages(): array
    {
        return Seo::messages();
    }
}
