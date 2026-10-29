<?php

declare(strict_types=1);

namespace App\Mail\Account;

/**
 * Download my data is ready (the person asked for it).
 */
final class ExportReady extends AccountMail
{
    public function __construct(public readonly string $downloadUrl, public readonly string $until) {}

    protected function key(): string
    {
        return 'export';
    }

    protected function url(): string
    {
        return $this->downloadUrl;
    }

    protected function lines(): array
    {
        return [__('account.mail.export.body')];
    }

    protected function soon(): string
    {
        return __('account.mail.export.soon', ['date' => $this->until]);
    }
}
