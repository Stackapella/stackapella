<?php

declare(strict_types=1);

namespace App\Mail\Account;

/**
 * The reset link, sent when the person asks for one.
 */
final class ResetPassword extends AccountMail
{
    public function __construct(public readonly string $token, public readonly string $email) {}

    protected function key(): string
    {
        return 'reset';
    }

    protected function url(): string
    {
        return url('/reset?token='.rawurlencode($this->token).'&email='.rawurlencode($this->email));
    }
}
