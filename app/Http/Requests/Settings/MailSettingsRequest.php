<?php

namespace App\Http\Requests\Settings;

use App\Support\SiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The Email tab. Only the chosen provider's fields are required — the others
 * are kept as they were, so switching back and forth loses nothing.
 */
class MailSettingsRequest extends FormRequest
{
    /** Each provider's password field. */
    private const SECRET_FOR = [
        'gmail' => 'gmail_app_password',
        'zoho' => 'zoho_password',
        'webmail' => 'webmail_password',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('settings.update');
    }

    public function rules(): array
    {
        $host = ['regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i', 'max:190'];

        return [
            'provider' => ['required', Rule::in(SiteSettings::MAIL_PROVIDERS)],
            'from_address' => ['nullable', 'email', 'max:190'],
            'from_name' => ['nullable', 'string', 'max:100'],
            'reply_to' => ['nullable', 'email', 'max:190'],

            'gmail_address' => ['nullable', 'required_if:provider,gmail', 'email', 'max:190'],
            'gmail_app_password' => ['nullable', 'string', 'max:64'],

            'zoho_address' => ['nullable', 'required_if:provider,zoho', 'email', 'max:190'],
            'zoho_password' => ['nullable', 'string', 'max:190'],
            'zoho_region' => ['required', Rule::in(array_keys(SiteSettings::ZOHO_REGIONS))],
            'zoho_account' => ['required', Rule::in(['personal', 'organization'])],
            'zoho_security' => ['required', Rule::in(['ssl', 'tls'])],

            'webmail_host' => ['nullable', 'required_if:provider,webmail', ...$host],
            'webmail_port' => ['required', Rule::in([465, 587])],
            'webmail_username' => ['nullable', 'required_if:provider,webmail', 'string', 'max:190'],
            'webmail_password' => ['nullable', 'string', 'max:190'],

            'smtp_host' => ['nullable', 'required_if:provider,smtp', ...$host],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_security' => ['required', Rule::in(['tls', 'ssl', 'none'])],
            'smtp_username' => ['nullable', 'string', 'max:190'],
            'smtp_password' => ['nullable', 'string', 'max:190'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $provider = $this->input('provider');
            $settings = app(SiteSettings::class);

            // A password is needed once. After that a blank field keeps it.
            $field = self::SECRET_FOR[$provider] ?? null;

            if ($field && blank($this->input($field)) && blank($settings->get('mail.'.$field))) {
                $validator->errors()->add($field, 'Enter the password — none is saved yet.');
            }

            // Google only accepts an App Password here, never the account's
            // normal one: 16 letters, shown in four groups of four.
            if ($provider === 'gmail' && filled($this->input('gmail_app_password'))) {
                $clean = str_replace(' ', '', (string) $this->input('gmail_app_password'));

                if (! preg_match('/^[a-z]{16}$/i', $clean)) {
                    $validator->errors()->add(
                        'gmail_app_password',
                        'That is not an App Password. Google gives you 16 letters (like "abcd efgh ijkl mnop") under Google Account → Security → App passwords. Your normal Gmail password will not work.'
                    );
                }
            }
        }];
    }

    public function messages(): array
    {
        return [
            'gmail_address.required_if' => 'Enter the Gmail address that sends the mail.',
            'zoho_address.required_if' => 'Enter the Zoho address that sends the mail.',
            'webmail_host.required_if' => 'Enter the mail server, e.g. mail.yourdomain.com.',
            'webmail_username.required_if' => 'Enter the mailbox login — usually the full email address.',
            'smtp_host.required_if' => 'Enter the SMTP server.',
            'webmail_host.regex' => 'That does not look like a server name. Use something like mail.yourdomain.com — no https:// and no port.',
            'smtp_host.regex' => 'That does not look like a server name. Use something like smtp.example.com — no https:// and no port.',
        ];
    }
}
