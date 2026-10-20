<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** A link to choose a new admin password, from "Forgot your password?". */
class PasswordResetMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly string $url,
        public readonly int $minutes,
        public readonly ?string $ip,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Reset your '.config('app.name').' admin password');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.password-reset');
    }
}
