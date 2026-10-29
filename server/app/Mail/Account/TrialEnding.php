<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Support\Mail\Bold;

/**
 * When a Plus trial is about to turn into a charge (some places require it; Stripe sends the
 * receipts).
 */
final class TrialEnding extends AccountMail
{
    public function __construct(public readonly string $on) {}

    protected function key(): string
    {
        return 'trial_ending';
    }

    protected function url(): string
    {
        return url('/settings/plus');
    }

    protected function lines(): array
    {
        return [Bold::words('account.mail.trial_ending.body', ['date' => $this->on])];
    }
}
