<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An email to the admins about how Stackapella is running: a readiness check that changed, an
 * alert over its threshold, or the daily digest. Each line is plain text, already in words.
 */
final class OpsAlert extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $lines
     */
    public function __construct(public string $subject, public string $title, public array $lines) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->subject)
            ->view(['emails.admin.alert', 'emails.admin.alert-text'], ['title' => $this->title, 'lines' => $this->lines]);
    }
}
