<?php

namespace App\Http\Requests\Settings;

use App\Support\Design;
use Illuminate\Foundation\Http\FormRequest;

/** Settings → Design: colours, fonts and widths. */
class DesignSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('settings.update');
    }

    public function rules(): array
    {
        $hex = 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/';
        $fonts = 'in:'.implode(',', array_keys(Design::FONTS));
        $range = fn (string $key) => ['required', 'integer', 'min:'.Design::LIMITS[$key][0], 'max:'.Design::LIMITS[$key][1]];

        return [
            'primary' => ['required', $hex],
            'text' => ['required', $hex],
            'background' => ['required', $hex],
            'body_font' => ['required', $fonts],
            'heading_font' => ['required', $fonts],
            'site_width' => $range('site_width'),
            'content_width' => $range('content_width'),
            'text_size' => $range('text_size'),
            'radius' => $range('radius'),
        ];
    }

    public function messages(): array
    {
        return [
            'primary.regex' => 'Use a colour like #ec3013.',
            'text.regex' => 'Use a colour like #1c1a19.',
            'background.regex' => 'Use a colour like #f2f1f1.',
        ];
    }
}
