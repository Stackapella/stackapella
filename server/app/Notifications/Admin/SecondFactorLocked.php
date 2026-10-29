<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Too many wrong second-factor codes: the account is locked for a while, and every admin hears.
 */
final class SecondFactorLocked extends Notification implements ShouldQueue
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
            ->subject(__('admin.mail.locked.subject', ['name' => $this->user->name]))
            ->view(['emails.admin.alert', 'emails.admin.alert-text'], [
                'title' => __('admin.mail.locked.title'),
                'lines' => [
                    __('admin.mail.locked.what', ['name' => $this->user->name]),
                    __('admin.mail.locked.unexpected'),
                ],
            ]);
    }
}
