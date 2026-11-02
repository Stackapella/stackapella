<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Support\Http\Hosts;

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
        return Hosts::appLink('/settings/plus');
    }

    protected function lines(): array
    {
        return [__('account.mail.payment_failed.body')];
    }
}
