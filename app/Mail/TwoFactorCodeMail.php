<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A sign-in (or setup) code for someone using emailed two-factor codes. */
class TwoFactorCodeMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $code,
        public readonly string $purpose,
        public readonly int $minutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->code.' is your '.config('app.name').' '.($this->purpose === 'setup' ? 'setup' : 'sign-in').' code');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.two-factor-code');
    }
}
