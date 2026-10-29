<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Support\Mail\Bold;

/**
 * To the account's old address, when support helped someone back in (docs/operations-plan.md,
 * "Getting back into an account"): what changed, and to reply if it wasn't them.
 */
final class RecoveredBySupport extends AccountMail
{
    public function __construct(public readonly string $newEmail) {}

    protected function key(): string
    {
        return 'recovered';
    }

    protected function url(): string
    {
        return url('/');
    }

    protected function lines(): array
    {
        return [Bold::words('account.mail.recovered.body', ['email' => $this->newEmail])];
    }
}
