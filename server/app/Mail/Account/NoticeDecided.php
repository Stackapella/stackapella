<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Models\Notice;
use App\Support\Mail\Bold;

/**
 * The answer to someone's own request for a review of a statement of reasons: kept or undone,
 * Hillel's reason, and where else they can take it (the DSA's redress). A reply to what they
 * asked; never bulk.
 */
final class NoticeDecided extends AccountMail
{
    public function __construct(public readonly string $decision, public readonly string $reason) {}

    protected function key(): string
    {
        return 'notice_decided';
    }

    protected function url(): string
    {
        return url('/');
    }

    protected function lines(): array
    {
        return array_values(array_filter([
            (string) __('account.mail.notice_decided.'.($this->decision === Notice::REVERSED ? 'reversed' : 'kept')),
            trim($this->reason) !== '' ? Bold::keep(trim($this->reason)) : null,
        ]));
    }
}
