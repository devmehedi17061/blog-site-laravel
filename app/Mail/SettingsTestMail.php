<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** "Send test email" on the Email tab: proof the connection works. */
class SettingsTestMail extends Mailable
{
    use Queueable;

    public function __construct(
        public readonly string $fromAddress,
        public readonly string $fromName,
        public readonly string $via,
        public readonly string $sentBy,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address($this->fromAddress, $this->fromName),
            subject: 'Test email from '.$this->fromName,
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.settings-test');
    }
}
