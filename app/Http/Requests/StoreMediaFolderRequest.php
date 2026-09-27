<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaFolderRequest extends FormRequest
{
    public function rules(): array
    {
        $folderId = $this->route('folder')?->id;

        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('media_folders', 'name')->ignore($folderId),
            ],
        ];
    }

    public function messages(): array
    {
        return ['name.unique' => 'A folder with that name already exists.'];
    }
}
