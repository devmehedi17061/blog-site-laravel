<?php

namespace App\Http\Requests\Settings;

use App\Support\SiteSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Settings → Site: brand, contact & social, SEO defaults, locale & maintenance. */
class SiteSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('settings.update');
    }

    /** Images come from the media library, as a site path or a full URL. */
    private function image(): array
    {
        return ['nullable', 'string', 'max:500', 'regex:#^(/|https?://)#i'];
    }

    public function rules(): array
    {
        $social = [];

        foreach (array_keys(SiteSettings::SOCIAL_NETWORKS) as $network) {
            $social['social_'.$network] = ['nullable', 'url:http,https', 'max:300'];
        }

        return [
            'name' => ['nullable', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'logo' => $this->image(),
            'logo_dark' => $this->image(),
            'show_name' => ['boolean'],
            'favicon' => $this->image(),

            'contact_email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[\d\s()+\-.]+$/'],
            'address' => ['nullable', 'string', 'max:300'],
            ...$social,

            // Must say where the page's own title goes, or every tab in the
            // browser would read the same.
            'title_format' => ['required', 'string', 'max:80', 'regex:/\{title\}/'],
            'default_description' => ['nullable', 'string', 'max:300'],
            'share_image' => $this->image(),
            // Only the code, not the whole <meta> tag Google shows.
            'google_verification' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'bing_verification' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'noindex' => ['boolean'],
            'robots_txt' => ['nullable', 'string', 'max:5000'],

            'language' => ['required', Rule::in(array_keys(SiteSettings::LANGUAGES))],
            'timezone' => ['required', Rule::in(timezone_identifiers_list())],
            'date_format' => ['required', Rule::in(SiteSettings::DATE_FORMATS)],
            'maintenance' => ['boolean'],
            'maintenance_message' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'title_format.regex' => 'Keep {title} in the format — it is where each page\'s own title goes.',
            'phone.regex' => 'Digits, spaces and + ( ) - only.',
            'google_verification.regex' => 'Paste only the code from content="…", not the whole tag.',
            'bing_verification.regex' => 'Paste only the code from content="…", not the whole tag.',
            '*.url' => 'Use the full address, starting with https://',
            'logo.regex' => 'Choose the image from the media library.',
            'logo_dark.regex' => 'Choose the image from the media library.',
            'favicon.regex' => 'Choose the image from the media library.',
            'share_image.regex' => 'Choose the image from the media library.',
        ];
    }

    /**
     * People paste the whole <meta … content="CODE"> Google gives them. Take
     * the code out of it rather than making them do it by hand.
     */
    protected function prepareForValidation(): void
    {
        foreach (['google_verification', 'bing_verification'] as $key) {
            $value = trim((string) $this->input($key));

            if (preg_match('/content=["\']([^"\']+)["\']/i', $value, $m)) {
                $this->merge([$key => $m[1]]);
            }
        }
    }
}
