<?php

namespace App\Http\Requests\Settings;

use App\Support\CustomCode;
use App\Support\SiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Settings → Front end.
 *
 * The two code boxes put raw HTML — scripts included — on every public page,
 * in every visitor's browser. Only a Super Admin may change them; anyone
 * else with settings.update can save the rest of the tab, and their copy of
 * the code boxes must come back exactly as it was sent to them.
 */
class FrontendSettingsRequest extends FormRequest
{
    public const CODE_FIELDS = ['head_code', 'body_code'];

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('settings.update');
    }

    public function rules(): array
    {
        return [
            'disable_right_click' => ['boolean'],
            'block_devtools_keys' => ['boolean'],
            'disable_selection' => ['boolean'],
            'disable_image_drag' => ['boolean'],
            'exempt_signed_in' => ['boolean'],

            'custom_css' => ['nullable', 'string', 'max:50000'],
            'head_code' => ['nullable', 'string', 'max:50000'],
            'body_code' => ['nullable', 'string', 'max:50000'],

            'external_assets' => ['nullable', 'array', 'max:30'],
            'external_assets.*.type' => ['required', 'in:css,js'],
            'external_assets.*.url' => ['required', 'string', 'max:1000', 'regex:~^(https?:)?//[^\s"\'<>]+$~i'],
            'external_assets.*.place' => ['nullable', 'in:head,body'],
            'external_assets.*.load' => ['nullable', 'in:normal,async,defer'],
            'external_assets.*.note' => ['nullable', 'string', 'max:80'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->user()->isSuperAdmin()) {
                return;
            }

            $settings = app(SiteSettings::class);

            foreach (self::CODE_FIELDS as $field) {
                if ($this->has($field) && (string) $this->input($field) !== (string) $settings->get('frontend.'.$field)) {
                    $validator->errors()->add($field, 'Only a Super Admin can change the code that runs on every page.');
                }
            }

            // An external script runs on every page just the same.
            if ($this->has('external_assets')
                && CustomCode::externals($this->input('external_assets')) !== CustomCode::externals($settings->get('frontend.external_assets'))) {
                $validator->errors()->add('external_assets', 'Only a Super Admin can change the external files.');
            }
        }];
    }

    /** What to save: the code boxes only when a Super Admin sent them. */
    public function toSave(): array
    {
        $data = $this->validated();

        if (! $this->user()->isSuperAdmin()) {
            foreach (self::CODE_FIELDS as $field) {
                unset($data[$field]);
            }
            unset($data['external_assets']);
        } elseif (array_key_exists('external_assets', $data)) {
            $data['external_assets'] = CustomCode::externals($data['external_assets']);
        }

        return $data;
    }
}
