<?php

declare(strict_types=1);

namespace App\Mail\Account;

/**
 * Someone signed up with an address that already has an account: no enumeration, so this goes to the owner instead, with a way back in.
 */
final class AlreadyHaveAnAccount extends AccountMail
{
    public function __construct(public readonly string $resetToken, public readonly string $email) {}

    protected function key(): string
    {
        return 'already';
    }

    protected function url(): string
    {
        return url('/reset?token='.rawurlencode($this->resetToken).'&email='.rawurlencode($this->email));
    }
}
