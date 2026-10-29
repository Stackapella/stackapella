<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Support\Mail\Bold;

/**
 * To the old address, when the person asks to change their email: with "This wasn't me".
 */
final class EmailChanging extends AccountMail
{
    public function __construct(public readonly string $newEmail, public readonly string $revertToken) {}

    protected function key(): string
    {
        return 'changed';
    }

    protected function url(): string
    {
        return url('/email/revert?token='.$this->revertToken);
    }

    protected function lines(): array
    {
        return [Bold::words('account.mail.changed.body', ['email' => $this->newEmail])];
    }
}
