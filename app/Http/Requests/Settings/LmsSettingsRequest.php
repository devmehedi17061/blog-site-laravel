<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

/** Settings → LMS: prices, sign-up, payments, certificates. */
class LmsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('settings.update');
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'max:8'],
            'allow_registration' => ['required', 'boolean'],
            'auto_approve' => ['required', 'boolean'],
            'payment_methods' => ['nullable', 'string', 'max:1000'],
            'payment_instructions' => ['nullable', 'string', 'max:3000'],
            'max_video_mb' => ['required', 'integer', 'min:10', 'max:20480'],
            'sequential' => ['required', 'boolean'],
            'certificates' => ['required', 'boolean'],
            'reviews' => ['required', 'boolean'],
            'certificate_text' => ['nullable', 'string', 'max:200'],
            'certificate_signer' => ['nullable', 'string', 'max:120'],
        ];
    }
}
