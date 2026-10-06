<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Something a student should hear about — a payment verified or rejected,
 * a course opened for them. Kept in their panel's Notifications, and sent
 * by email too (Settings → Email decides whether mail really leaves).
 *
 * Send through App\Support\Lms::notify(), which never lets a mail problem
 * undo the action that caused the notice.
 */
class StudentNotice extends Notification
{
    use Queueable;

    /** @param  'success'|'warn'|'info'  $tone */
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public string $tone = 'info',
        public ?string $action = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->title)
            ->greeting('Hello '.strtok((string) $notifiable->name, ' ').',')
            ->line($this->body);

        if ($this->url) {
            $mail->action($this->action ?? 'Open', $this->url);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'tone' => $this->tone,
            'action' => $this->action,
        ];
    }
}
