<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Support\Mail\Bold;

/**
 * The verification link, sent when someone makes an account with a password (or asks again).
 */
final class VerifyEmailAddress extends AccountMail
{
    public function __construct(public readonly string $token, public readonly string $personName) {}

    protected function key(): string
    {
        return 'verify';
    }

    protected function url(): string
    {
        return url('/verify?token='.$this->token);
    }

    /**
     * Hillel's words: the greeting and the address in one line, and no greeting of its own.
     */
    protected function name(): ?string
    {
        return null;
    }

    protected function greeting(): bool
    {
        return false;
    }

    protected function lines(): array
    {
        $words = ['name' => $this->personName, 'email' => (string) $this->recipient()];

        return [Bold::words($this->personName !== '' ? 'account.mail.verify.body' : 'account.mail.verify.body_plain', $words)];
    }
}
