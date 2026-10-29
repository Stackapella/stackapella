<?php

declare(strict_types=1);

namespace App\Mail\Account;

/**
 * The sign-in link (auth.link.send): one button that signs in, or makes the account for an address
 * that has none. Its words never say which.
 */
final class SignInLinkMail extends AccountMail
{
    public function __construct(public readonly string $token) {}

    protected function key(): string
    {
        return 'sign_in_link';
    }

    protected function url(): string
    {
        return rtrim((string) config('app.url'), '/').'/signin/link?'.http_build_query(['token' => $this->token]);
    }
}
