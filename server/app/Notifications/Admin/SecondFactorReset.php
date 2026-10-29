<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An admin's second factor was removed from the console (app:admin:reset-second-factor).
 */
final class SecondFactorReset extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public User $user) {}

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
            ->subject(__('admin.mail.reset.subject', ['name' => $this->user->name]))
            ->view(['emails.admin.alert', 'emails.admin.alert-text'], [
                'title' => __('admin.mail.reset.title'),
                'lines' => [
                    __('admin.mail.reset.what', ['name' => $this->user->name]),
                    __('admin.mail.reset.unexpected'),
                ],
            ]);
    }
}
