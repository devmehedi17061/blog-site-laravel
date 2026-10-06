<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create and update share one shape; a new course must have a title. */
class SaveCourseRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // The editor sends "" for an empty number box.
        foreach (['price', 'sale_price', 'category_id', 'user_id', 'media_id'] as $key) {
            if ($this->input($key) === '') {
                $this->merge([$key => null]);
            }
        }

        // "What you will learn" and "Requirements" arrive as lists; blank lines go.
        foreach (['outcomes', 'requirements'] as $key) {
            if (is_array($this->input($key))) {
                $this->merge([$key => array_values(array_filter(array_map(
                    fn ($line) => trim((string) $line),
                    $this->input($key)
                ), fn ($line) => $line !== ''))]);
            }
        }
    }

    public function rules(): array
    {
        $courseId = $this->route('course')?->id;
        $creating = $courseId === null;

        return [
            'title' => [$creating ? 'required' : 'sometimes', 'required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', Rule::unique('courses', 'slug')->ignore($courseId)],
            'subtitle' => ['nullable', 'string', 'max:300'],
            'description' => ['nullable', 'string'],
            'outcomes' => ['nullable', 'array', 'max:30'],
            'outcomes.*' => ['string', 'max:200'],
            'requirements' => ['nullable', 'array', 'max:30'],
            'requirements.*' => ['string', 'max:200'],
            'level' => ['nullable', Rule::in(Course::LEVELS)],
            'language' => ['nullable', 'string', 'max:40'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:9999999', 'lt:price'],
            'intro_video' => ['nullable', 'string', 'max:500'],
            'status' => [$creating ? 'required' : 'sometimes', Rule::in(Course::STATUSES)],
            'published_at' => ['nullable', 'date'],
            'is_featured' => ['nullable', 'boolean'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'media_id' => ['nullable', 'integer', 'exists:media,id'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:320'],
        ];
    }

    public function attributes(): array
    {
        return [
            'category_id' => 'category',
            'user_id' => 'instructor',
            'media_id' => 'thumbnail',
            'sale_price' => 'sale price',
            'intro_video' => 'intro video',
            'outcomes.*' => 'learning outcome',
            'requirements.*' => 'requirement',
        ];
    }

    public function messages(): array
    {
        return ['sale_price.lt' => 'The sale price must be lower than the regular price.'];
    }
}
