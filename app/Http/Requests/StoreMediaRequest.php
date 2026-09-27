<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    /**
     * SVG is deliberately absent. It is XML that can carry <script>, and these
     * files are served from the same origin as the admin panel, so a single
     * crafted upload from a lower-trust author could read a session. Add it
     * only behind a sanitiser.
     */
    public const EXTENSIONS = 'jpg,jpeg,png,gif,webp,avif,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip';

    public const MAX_KB = 10240;

    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'mimes:'.self::EXTENSIONS, 'max:'.self::MAX_KB],
            'folder_id' => ['nullable', 'integer', 'exists:media_folders,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'files.*.max' => 'Each file must be 10 MB or smaller.',
            'files.*.mimes' => 'That file type is not allowed. Images, PDFs, Office documents, CSV, text and zip only.',
            'files.max' => 'Up to 20 files at a time.',
        ];
    }
}
