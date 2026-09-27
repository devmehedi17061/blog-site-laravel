<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\DesignSettingsRequest;
use App\Http\Requests\Settings\FrontendSettingsRequest;
use App\Http\Requests\Settings\MailSettingsRequest;
use App\Http\Requests\Settings\SiteSettingsRequest;
use App\Http\Requests\Settings\SpamSettingsRequest;
use App\Mail\SettingsTestMail;
use App\Models\ActivityLog;
use App\Support\Design;
use App\Support\SiteSettings;
use App\Support\SpamGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * The Settings screen. One GET for every tab, one PUT per tab, so saving
 * Email can never touch what is on the Brand tab.
 */
class SettingsController extends Controller
{
    public function __construct(private readonly SiteSettings $settings) {}

    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.view'), 403);

        return response()->json($this->payload($request));
    }

    public function updateSite(SiteSettingsRequest $request): JsonResponse
    {
        $before = (bool) $this->settings->get('site.maintenance');
        $this->settings->save('site', $request->validated());
        $after = (bool) $request->boolean('maintenance');

        ActivityLog::record(
            match (true) {
                ! $before && $after => 'Turned on maintenance mode',
                $before && ! $after => 'Turned off maintenance mode',
                default => 'Updated site settings',
            },
            $this->settings->siteName()
        );

        return response()->json(['message' => 'Site settings saved.'] + $this->payload($request));
    }

    public function updateSpam(SpamSettingsRequest $request): JsonResponse
    {
        $this->settings->save('spam', $request->validated() + $request->only(['turnstile_secret_clear', 'recaptcha_secret_clear']));

        ActivityLog::record('Updated spam protection', match ($request->input('captcha')) {
            'turnstile' => 'CAPTCHA: Cloudflare Turnstile',
            'recaptcha' => 'CAPTCHA: Google reCAPTCHA v3',
            default => 'No CAPTCHA',
        });

        return response()->json(['message' => 'Spam protection saved.'] + $this->payload($request));
    }

    /** "Check secret key": asks Cloudflare or Google whether it is real. */
    public function checkSpamKey(Request $request, SpamGuard $guard): JsonResponse
    {
        abort_unless($request->user()->hasPermission('settings.update'), 403);

        $data = $request->validate([
            'provider' => ['required', 'in:turnstile,recaptcha'],
            'secret' => ['nullable', 'string', 'max:120'],
        ]);

        $secret = ($data['secret'] ?? '') ?: (string) $this->settings->get('spam.'.$data['provider'].'_secret');

        if ($secret === '') {
            return response()->json(['ok' => false, 'message' => 'Enter the secret key first.'], 422);
        }

        $result = $guard->checkSecret($data['provider'], $secret);

        return response()->json($result, $result['ok'] === false ? 422 : 200);
    }

    public function updateDesign(DesignSettingsRequest $request): JsonResponse
    {
        $this->settings->save('design', $request->validated());

        ActivityLog::record('Updated the site design', $this->settings->siteName());

        return response()->json(['message' => 'Design saved — the site uses it now.'] + $this->payload($request));
    }

    public function updateFrontend(FrontendSettingsRequest $request): JsonResponse
    {
        $before = [
            'head' => (string) $this->settings->get('frontend.head_code'),
            'body' => (string) $this->settings->get('frontend.body_code'),
        ];

        $this->settings->save('frontend', $request->toSave());

        // Code that runs on every page is worth its own line in the log.
        $codeChanged = $request->user()->isSuperAdmin() && (
            $before['head'] !== (string) $request->input('head_code') ||
            $before['body'] !== (string) $request->input('body_code')
        );

        ActivityLog::record($codeChanged ? 'Changed site-wide code' : 'Updated front-end settings', $this->settings->siteName());

        return response()->json(['message' => 'Front-end settings saved.'] + $this->payload($request));
    }

    public function updateMail(MailSettingsRequest $request): JsonResponse
    {
        $this->settings->save('mail', $request->validated() + $request->only([
            'gmail_app_password_clear', 'zoho_password_clear', 'webmail_password_clear', 'smtp_password_clear',
        ]));

        ActivityLog::record('Updated email settings', 'Sending through '.$this->providerLabel($request->input('provider')));

        return response()->json(['message' => 'Email settings saved.'] + $this->payload($request));
    }

    /**
     * Sends a real message with what is on screen — saved or not — so a
     * wrong password is found before it silently drops form notifications.
     */
    public function testMail(MailSettingsRequest $request): JsonResponse
    {
        $request->validate(['test_to' => ['required', 'email', 'max:190']], [
            'test_to.required' => 'Enter the address to send the test to.',
        ]);

        $values = $request->validated();

        // A blank password on screen means "the saved one".
        foreach (SiteSettings::SECRETS as $key) {
            $short = substr($key, 5);
            if (blank($values[$short] ?? null)) {
                $values[$short] = $this->settings->get($key);
            }
        }

        $from = SiteSettings::senderAddress($values) ?: (string) config('mail.from.address');
        $name = ($values['from_name'] ?? '') ?: (string) config('app.name');
        $via = $this->providerLabel($values['provider']);
        $smtp = SiteSettings::smtpConfig($values);

        try {
            $mailer = $smtp ? Mail::build($smtp) : Mail::mailer('log');
            $mailer->to($request->input('test_to'))->send(new SettingsTestMail($from, $name, $via, $request->user()->name));
        } catch (Throwable $e) {
            return response()->json([
                'message' => $this->explain($e->getMessage(), $values),
                'detail' => mb_substr($e->getMessage(), 0, 400),
            ], 422);
        }

        return response()->json([
            'message' => $smtp
                ? "Sent to {$request->input('test_to')} through {$via}. Check the inbox — and the spam folder, the first time."
                : 'Email is not set up, so nothing was sent: the test was written to storage/logs/laravel.log. Choose a provider to send for real.',
        ]);
    }

    private function payload(Request $request): array
    {
        return [
            'site' => $this->settings->forScreen('site') + ['app_name' => (string) config('app.name')],
            'mail' => $this->settings->forScreen('mail'),
            'spam' => $this->settings->forScreen('spam'),
            'frontend' => $this->settings->forScreen('frontend'),
            'design' => $this->settings->forScreen('design'),
            // What the public layout prints, for the admin's section previews.
            'design_output' => ['css' => app(Design::class)->css(), 'fonts' => app(Design::class)->fontsUrl()],
            'spam_stats' => $this->spamStats(),
            'options' => [
                'timezones' => timezone_identifiers_list(),
                'languages' => collect(SiteSettings::LANGUAGES)->map(fn ($l, $code) => ['value' => $code, 'label' => $l['label']])->values(),
                // Shown with today's date, so the choice is not a puzzle.
                'date_formats' => collect(SiteSettings::DATE_FORMATS)
                    ->map(fn ($f) => ['value' => $f, 'label' => now()->translatedFormat($f)])->values(),
                'socials' => collect(SiteSettings::SOCIAL_NETWORKS)
                    ->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
                'fonts' => collect(Design::FONTS)->map(fn ($f, $key) => ['value' => $key, 'label' => $f['label'], 'group' => $f['group'], 'stack' => $f['stack'], 'google' => $f['google']])->values(),
                'design_limits' => Design::LIMITS,
                'zoho_regions' => collect(SiteSettings::ZOHO_REGIONS)
                    ->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            ],
            'can' => [
                'update' => $request->user()->hasPermission('settings.update'),
                // The head and body code boxes run on every visitor's browser.
                'scripts' => $request->user()->hasPermission('settings.update') && $request->user()->isSuperAdmin(),
            ],
        ];
    }

    /** What was stopped this month, reason by reason. */
    private function spamStats(): array
    {
        $counts = app(SpamGuard::class)->thisMonth();

        return [
            'month' => now()->format('F Y'),
            'total' => array_sum($counts),
            'reasons' => collect(SpamGuard::REASONS)
                ->map(fn ($label, $key) => ['key' => $key, 'label' => $label, 'count' => (int) ($counts[$key] ?? 0)])
                ->values(),
        ];
    }

    private function providerLabel(?string $provider): string
    {
        return match ($provider) {
            'gmail' => 'Gmail',
            'zoho' => 'Zoho Mail',
            'webmail' => 'Webmail',
            'smtp' => 'Custom SMTP',
            default => 'the log (not sending)',
        };
    }

    /**
     * SMTP errors are written for mail administrators. This turns the usual
     * ones into what to do next.
     */
    private function explain(string $error, array $values): string
    {
        $provider = $values['provider'] ?? '';
        $e = strtolower($error);

        return match (true) {
            str_contains($e, '535') || str_contains($e, 'authentication') || str_contains($e, 'username and password not accepted') => match ($provider) {
                'gmail' => 'Google refused the login. Use an App Password (Google Account → Security → 2-Step Verification → App passwords), not your normal password, and check the Gmail address.',
                'zoho' => 'Zoho refused the login. If the account has two-factor sign-in, create an application-specific password in Zoho (My Account → Security → App passwords). Also check the region and account type.',
                default => 'The server refused the username or password. Check both — the username is usually the full email address.',
            },
            str_contains($e, '553') || str_contains($e, 'sender') || str_contains($e, 'relay') => 'The server will not send from that From address. Leave "From address" blank to use the account\'s own address, or use an alias the mailbox is allowed to send as.',
            str_contains($e, 'certificate') || str_contains($e, 'ssl') || str_contains($e, 'tls') => 'The secure connection failed. Try the other security option (SSL on 465, or TLS on 587), and check the server name matches the host\'s certificate.',
            str_contains($e, 'timed out') || str_contains($e, 'could not be established') || str_contains($e, 'connection refused') || str_contains($e, 'getaddrinfo') || str_contains($e, 'name or service not known') => 'Could not reach the mail server. Check the server name and port — and some hosts block outgoing mail ports, in which case ask them to open 465 or 587.',
            default => 'The test failed: '.mb_substr($error, 0, 200),
        };
    }
}
