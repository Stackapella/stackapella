<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Support\Http\Hosts;
use App\Support\Mail\Bold;

/**
 * A growth viewer's invite (app:viewer:grant): who asked, what they can see, how to sign in step by step, and that it's
 * view-only. Sent once, when the address is granted, from a console. English only (Admin is English only). An optional
 * note from staff (the grant's --note) is its own line, kept as written.
 */
final class GrowthViewerInvite extends AccountMail
{
    public function __construct(public readonly string $email, public readonly ?string $extra = null) {}

    protected function key(): string
    {
        return 'growth_viewer';
    }

    protected function url(): string
    {
        return Hosts::appLink('/admin/sign-in?intended=%2Fadmin%2Fgrowth');
    }

    protected function lines(): array
    {
        $note = trim((string) $this->extra);

        return array_values(array_filter([
            (string) __('account.mail.growth_viewer.body'),
            (string) __('account.mail.growth_viewer.sees'),
            (string) __('account.mail.growth_viewer.steps'),
            Bold::words('account.mail.growth_viewer.step_1', ['url' => $this->url()]),
            Bold::words('account.mail.growth_viewer.step_2', ['email' => $this->email]),
            (string) __('account.mail.growth_viewer.step_3'),
            (string) __('account.mail.growth_viewer.after'),
            $note !== '' ? Bold::keep($note) : null,
        ], static fn (mixed $line): bool => is_string($line) && $line !== ''));
    }
}
