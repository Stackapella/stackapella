<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Support\Mail\Bold;

/**
 * A sign-in from a browser this account hasn't used before (docs/trust-and-safety-plan.md, proposed),
 * or on a new device in the app (named by its platform only: the device's own name is whatever
 * the person signing in typed).
 */
final class NewSignIn extends AccountMail
{
    public function __construct(public readonly string $browser, public readonly bool $app = false) {}

    protected function key(): string
    {
        return 'new_sign_in';
    }

    protected function url(): string
    {
        return url('/account/password');
    }

    protected function lines(): array
    {
        return [Bold::words($this->app ? 'account.mail.new_sign_in.body_app' : 'account.mail.new_sign_in.body', ['browser' => $this->browser])];
    }
}
