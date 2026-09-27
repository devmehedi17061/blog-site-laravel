<?php

namespace App\Http\Requests\Settings;

use App\Support\Security;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/** Settings → Security: the switches and numbers (IP rules have their own routes). */
class SecuritySettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('security.update');
    }

    public function rules(): array
    {
        return [
            'max_attempts' => ['required', 'integer', 'between:3,20'],
            'lockout_minutes' => ['required', 'integer', 'between:1,1440'],

            'password_min' => ['required', 'integer', 'between:8,64'],
            'password_mixed_case' => ['boolean'],
            'password_numbers' => ['boolean'],
            'password_symbols' => ['boolean'],
            'password_uncompromised' => ['boolean'],
            'password_expiry_days' => ['required', 'integer', 'between:0,730'],

            'two_factor_roles' => ['present', 'array'],
            'two_factor_roles.*' => ['integer', 'distinct', 'exists:roles,id'],

            'idle_minutes' => ['required', 'integer', 'between:5,1440'],
            'single_session' => ['boolean'],

            'ip_rules_enabled' => ['boolean'],

            'force_https' => ['boolean'],
            'hsts' => ['boolean'],
            'headers' => ['boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            // The one change that could shut the person making it out.
            if ($this->boolean('ip_rules_enabled')
                && ! Security::ipAllowed((string) $this->ip(), app(Security::class)->ipRules())) {
                $validator->errors()->add('ip_rules_enabled', "Turning this on would lock you out: your address ({$this->ip()}) is not allowed by the rules below. Add it first.");
            }

            // HSTS tells browsers to insist on HTTPS for a year. Only once
            // this very request came in over HTTPS is that known to work.
            if ($this->boolean('hsts') && ! $this->isSecure() && ! Security::isLocalHost($this->getHost())) {
                $validator->errors()->add('hsts', 'Open the admin over https:// first, so HSTS is only switched on once HTTPS is known to work.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'max_attempts.between' => 'Between 3 and 20 tries.',
            'password_min.between' => 'Between 8 and 64 characters.',
            'password_expiry_days.between' => '0 (never) up to 730 days.',
            'idle_minutes.between' => 'Between 5 minutes and 24 hours (1440).',
        ];
    }
}
