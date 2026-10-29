<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Support\Mail\Bold;

/**
 * A statement of reasons by email, when the person can't sign in to read it (their account is
 * suspended): what was done, why, and the private link to ask for a review.
 */
final class NoticeIssued extends AccountMail
{
    public function __construct(public readonly string $what, public readonly string $why, public readonly string $link, public readonly ?string $facts = null) {}

    protected function key(): string
    {
        return 'notice';
    }

    protected function url(): string
    {
        return $this->link;
    }

    protected function lines(): array
    {
        // The facts behind it, as the admin wrote them (the DSA's statement of reasons asks for them).
        return array_values(array_filter([Bold::keep($this->what), Bold::keep($this->why), $this->facts !== null ? Bold::keep($this->facts) : null]));
    }
}
