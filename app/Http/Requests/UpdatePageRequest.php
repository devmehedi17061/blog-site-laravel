<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesPageFields;
use App\Models\Page;
use App\Support\PageTemplates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePageRequest extends FormRequest
{
    use ValidatesPageFields;

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('pages', 'slug')->ignore($this->route('page')?->id), $this->notReserved()],
            'template' => ['nullable', Rule::in(PageTemplates::keys())],
            'status' => ['sometimes', 'required', Rule::in(Page::STATUSES)],
            ...$this->sharedPageRules(),
        ];
    }

    /**
     * The homepage has to stay live. Unpublishing it — or dating it into the
     * future — would leave "/" showing something nobody chose, so it is
     * refused until another page takes over.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $page = $this->route('page');

            if (! $page?->isHome() || $validator->errors()->isNotEmpty()) {
                return;
            }

            $status = $this->input('status', $page->status);
            $date = $this->input('published_at');
            $future = $date && Carbon::parse($date)->startOfDay()->isFuture();

            if ($status !== 'Published' || $future) {
                $validator->errors()->add(
                    'status',
                    'This page is the homepage, so it has to stay published. Choose another homepage first.'
                );
            }
        }];
    }

    /** JavaScript on a page runs in every visitor's browser: Super Admin only. */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        \App\Support\CustomCode::guard($validator, $this, $this->route('page'));
    }
}
