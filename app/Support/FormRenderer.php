<?php

namespace App\Support;

use App\Models\Form;
use Illuminate\Support\Facades\View;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

/**
 * Renders a built form for the public site.
 *
 * The markup is a Blade view rather than a string built here, so it escapes
 * by default and can be restyled without touching PHP. This class is the part
 * that decides *what* the view needs: which error bag belongs to this form,
 * whether its old input should be replayed, and whether it has just been
 * submitted successfully.
 *
 * Instances are cached per request, because a page can hold the same form in
 * more than one section.
 */
class FormRenderer
{
    /** @var array<int, string> */
    private array $rendered = [];

    public function render(?Form $form): string
    {
        if (! $form) {
            return '';
        }

        if (isset($this->rendered[$form->id])) {
            return $this->rendered[$form->id];
        }

        $form->loadMissing('fields');

        return $this->rendered[$form->id] = View::make('frontend.partials.form', [
            'form' => $form,
            'bag' => $this->errorsFor($form),
            // Only the form that actually failed replays what was typed;
            // otherwise a second form on the page would fill itself in with
            // the first one's answers.
            'replay' => session('form_failed') === $form->id,
            'done' => session('form_done') === $form->id ? $form->thankYou() : null,
            'hasUpload' => $form->fields->contains('type', 'file'),
        ])->render();
    }

    /** Each form gets its own error bag, so two on a page stay separate. */
    public static function bag(Form $form): string
    {
        return 'form-'.$form->id;
    }

    /**
     * This form's errors, read from the session rather than from the `$errors`
     * view variable.
     *
     * That variable is shared by the `web` middleware group, so a form
     * rendered from anywhere else — a console command, a queued job, a preview
     * — would have blown up on it. Reading the session directly means the
     * markup is the same wherever it is built.
     */
    private function errorsFor(Form $form): MessageBag
    {
        $errors = session('errors');

        return $errors instanceof ViewErrorBag
            ? $errors->getBag(self::bag($form))
            : new MessageBag;
    }

    /** Looks up a form by id or slug, for a section's form field. */
    public function find(int|string|null $key): ?Form
    {
        if (blank($key)) {
            return null;
        }

        return Form::with('fields')
            ->when(is_numeric($key), fn ($query) => $query->whereKey((int) $key))
            ->when(! is_numeric($key), fn ($query) => $query->where('slug', $key))
            ->first();
    }
}
