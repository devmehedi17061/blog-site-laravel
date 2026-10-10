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
 *
 * Title, body and action are English templates (":course is open") with
 * their values in $params. They are stored that way and put into words when
 * read — the panel in the student's current language, the email in the one
 * on their account — so one notice reads right in English and in Bangla.
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
        // Off for notices sent to a whole course at once: one request
        // should not send hundreds of emails.
        public bool $email = true,
        public array $params = [],
    ) {}

    public function via(object $notifiable): array
    {
        return $this->email ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        // Sent inside the student's own locale (User::preferredLocale()).
        $mail = (new MailMessage)
            ->subject(__($this->title, $this->params))
            ->greeting(__('Hello :name,', ['name' => strtok((string) $notifiable->name, ' ')]))
            ->line(__($this->body, $this->params));

        if ($this->url) {
            $mail->action(__($this->action ?? 'Open', $this->params), $this->url);
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
            'params' => $this->params,
        ];
    }
}
