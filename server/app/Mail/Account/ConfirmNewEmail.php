<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Support\Http\Hosts;

/**
 * To the new address, when the person changes their email.
 */
final class ConfirmNewEmail extends AccountMail
{
    public function __construct(public readonly string $token) {}

    protected function key(): string
    {
        return 'confirm_new';
    }

    protected function url(): string
    {
        return Hosts::appLink('/email/confirm?token='.$this->token);
    }
}
