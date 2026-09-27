<?php

namespace App\Http\Requests\Settings;

use App\Support\SiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/** Settings → Spam protection. */
class SpamSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('settings.update');
    }

    public function rules(): array
    {
        $key = ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-.]+$/'];

        return [
            'captcha' => ['required', Rule::in(['none', 'turnstile', 'recaptcha'])],
            'turnstile_site_key' => ['nullable', 'required_if:captcha,turnstile', ...array_slice($key, 1)],
            'turnstile_secret' => $key,
            'recaptcha_site_key' => ['nullable', 'required_if:captcha,recaptcha', ...array_slice($key, 1)],
            'recaptcha_secret' => $key,
            'recaptcha_min_score' => ['required', 'integer', 'between:10,90'],
            'protect_login' => ['boolean'],

            'time_trap' => ['boolean'],
            'min_seconds' => ['required', 'integer', 'between:1,30'],
            'limit_links' => ['boolean'],
            'max_links' => ['required', 'integer', 'between:0,20'],
            'blocked_words' => ['nullable', 'string', 'max:5000'],
            'blocked_emails' => ['nullable', 'string', 'max:5000'],
            'rate_limit' => ['required', 'integer', 'between:1,120'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $captcha = $this->input('captcha');
            $settings = app(SiteSettings::class);

            // A CAPTCHA with no secret would lock every visitor out of every
            // form, so the secret is needed the first time.
            foreach (['turnstile' => 'turnstile_secret', 'recaptcha' => 'recaptcha_secret'] as $provider => $field) {
                if ($captcha === $provider && blank($this->input($field)) && blank($settings->get('spam.'.$field))) {
                    $validator->errors()->add($field, 'Enter the secret key — none is saved yet.');
                }
            }

            foreach (preg_split('/\R/', (string) $this->input('blocked_emails')) as $line) {
                $line = trim($line);
                $ok = $line === ''
                    || filter_var($line, FILTER_VALIDATE_EMAIL)
                    || preg_match('/^@[a-z0-9.-]+\.[a-z]{2,}$/i', $line);

                if (! $ok) {
                    $validator->errors()->add('blocked_emails', "\"{$line}\" is neither an address nor a domain like @example.com.");
                    break;
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'turnstile_site_key.required_if' => 'Enter the site key from the Cloudflare dashboard.',
            'recaptcha_site_key.required_if' => 'Enter the site key from the reCAPTCHA admin console.',
            '*.regex' => 'Keys are letters, digits and - _ . only — check nothing extra was copied.',
        ];
    }
}
