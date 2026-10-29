<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * A new passkey or authenticator app on an admin account that already had one.
 */
final class SecondFactorAdded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user, public string $method, public string $userAgent) {}

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
            ->subject(__('admin.mail.added.subject', ['name' => $this->user->name]))
            ->view(['emails.admin.alert', 'emails.admin.alert-text'], [
                'title' => __('admin.mail.added.title'),
                'lines' => [
                    __('admin.mail.added.what', ['name' => $this->user->name, 'method' => __('admin.mail.factor.'.$this->method)]),
                    __('admin.mail.browser', ['agent' => mb_strimwidth($this->userAgent, 0, 140, '...')]),
                    __('admin.mail.added.unexpected'),
                ],
            ]);
    }
}
