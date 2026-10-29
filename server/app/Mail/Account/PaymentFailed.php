<?php

declare(strict_types=1);

namespace App\Mail\Account;

/**
 * When a Plus payment fails: Plus carries on while Stripe tries again.
 */
final class PaymentFailed extends AccountMail
{
    protected function key(): string
    {
        return 'payment_failed';
    }

    protected function url(): string
    {
        return url('/settings/plus');
    }

    protected function lines(): array
    {
        return [__('account.mail.payment_failed.body')];
    }
}
