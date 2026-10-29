<?php

declare(strict_types=1);

namespace App\Notifications\Admin;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Every admin hears about every new admin session (docs/trust-and-safety-plan.md, "admin sign-ins alert Hillel").
 */
final class AdminSignedIn extends Notification implements ShouldQueue
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
            ->subject(__('admin.mail.signed_in.subject', ['name' => $this->user->name]))
            ->view(['emails.admin.alert', 'emails.admin.alert-text'], [
                'title' => __('admin.mail.signed_in.title'),
                'lines' => [
                    __('admin.mail.signed_in.how', ['name' => $this->user->name, 'method' => __('admin.mail.method.'.$this->method)]),
                    __('admin.mail.browser', ['agent' => mb_strimwidth($this->userAgent, 0, 140, '...')]),
                    __('admin.mail.signed_in.unexpected'),
                ],
            ]);
    }
}
