<?php

declare(strict_types=1);

namespace App\Mail\Support;

use App\Mail\Account\AccountMail;
use App\Models\SupportReply;
use App\Support\Mail\Bold;

/**
 * Hillel's answer to someone's own support message (a reply to what they sent; never bulk). The
 * one email with words a person wrote: Hillel's, from the admin.
 */
final class SupportAnswer extends AccountMail
{
    public function __construct(public readonly SupportReply $reply, public readonly ?string $recipient) {}

    protected function key(): string
    {
        return 'support_answer';
    }

    protected function url(): string
    {
        return url('/help');
    }

    protected function lines(): array
    {
        return array_map(Bold::keep(...), array_values(array_filter(array_map('trim', preg_split('/\R{2,}/', $this->reply->body) ?: []))));
    }

    protected function name(): ?string
    {
        return $this->recipient;
    }
}
