<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Mail\FormSubmitted;
use App\Models\Form;
use App\Support\FormRenderer;
use App\Support\FormRules;
use App\Support\SpamGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

/**
 * Takes a submission from the public site.
 *
 * A plain form post, not a JSON call: the form works with JavaScript off, and
 * the answer comes back as a redirect to the page the visitor was already on.
 * Which form the flash belongs to is carried by id, because a page may hold
 * more than one.
 */
class FormSubmissionController extends Controller
{
    public function __construct(private readonly SpamGuard $guard) {}

    public function store(Request $request, Form $form): RedirectResponse
    {
        abort_unless($form->isAccepting(), 404);

        // Honeypot, time trap, blocked words and addresses, too many links.
        // Checked before anything is validated or uploaded, and answered
        // with the same "thanks" a person gets, so a bot has nothing to
        // learn from the difference. Nothing is kept and nothing is mailed.
        if ($reason = $this->guard->silentReason($request, (array) $request->input('fields', []))) {
            $this->guard->record($reason);

            return back()->with('form_done', $form->id);
        }

        // A failed CAPTCHA might be a person, so it is said out loud.
        if ($problem = $this->guard->captchaError($request)) {
            $this->guard->record('captcha');

            return back()
                ->withErrors(['captcha' => $problem], FormRenderer::bag($form))
                ->withInput()
                ->with('form_failed', $form->id);
        }

        $form->loadMissing('fields');

        $validator = Validator::make(
            $request->all(),
            FormRules::for($form),
            [],
            FormRules::attributes($form)
        );

        if ($validator->fails()) {
            return back()
                ->withErrors($validator, FormRenderer::bag($form))
                ->withInput()
                ->with('form_failed', $form->id);
        }

        $payload = $this->payload($form, $request);

        if ($form->store_submissions) {
            $form->submissions()->create([
                'payload' => $payload,
                'ip_address' => $request->ip(),
            ]);
        }

        $this->notify($form, $payload);

        return back()->with('form_done', $form->id);
    }

    /**
     * The answers, keyed by field name. An uploaded file is stored first and
     * only its path is kept, so the payload stays a small JSON document.
     */
    private function payload(Form $form, Request $request): array
    {
        $payload = [];

        foreach ($form->fields as $field) {
            $value = $request->input('fields.'.$field->name);

            if ($field->type === 'file') {
                $file = $request->file('fields.'.$field->name);

                $payload[$field->name] = $file
                    ? $file->store('form-uploads/'.$form->slug, 'public')
                    : null;

                continue;
            }

            if ($field->isToggle()) {
                $payload[$field->name] = filled($value) ? 'Yes' : 'No';

                continue;
            }

            $payload[$field->name] = is_array($value)
                ? array_values(array_map('strval', $value))
                : (is_null($value) ? null : (string) $value);
        }

        return $payload;
    }

    /**
     * Sent after the visitor already has their "thank you" page: talking to
     * a mail server can take seconds, and they should not sit watching a
     * spinner for an email they will never see. On PHP-FPM the response is
     * flushed first and this runs afterwards in the same process — no queue
     * worker to install or keep running.
     *
     * A failed send must never cost the visitor their submission — it is
     * already saved by this point, so the error is logged and swallowed.
     */
    private function notify(Form $form, array $payload): void
    {
        if (blank($form->notify_email)) {
            return;
        }

        app()->terminating(function () use ($form, $payload) {
            try {
                Mail::to($form->notify_email)->send(new FormSubmitted($form, $payload));
            } catch (\Throwable $e) {
                Log::warning('Form notification failed', [
                    'form' => $form->slug,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
