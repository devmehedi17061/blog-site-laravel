<?php

namespace App\Mail;

use App\Models\Form;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The note sent to whoever owns the form. Plain and readable: the point is to
 * see the answers without opening the panel.
 */
class FormSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Form $form,
        public readonly array $payload,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'New '.$this->form->name.' submission');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.form-submitted',
            with: ['answers' => $this->answers()],
        );
    }

    /**
     * Labels rather than field names, falling back to the raw key for a field
     * that has since been renamed or removed.
     */
    private function answers(): array
    {
        $labels = $this->form->fields->pluck('label', 'name');
        $answers = [];

        foreach ($this->payload as $name => $value) {
            $text = is_array($value) ? implode(', ', $value) : (string) $value;

            $answers[$labels[$name] ?? $name] = trim($text) === '' ? '—' : $text;
        }

        return $answers;
    }
}
