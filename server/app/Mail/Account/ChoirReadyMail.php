<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Support\Http\Hosts;
use App\Support\Ids;
use App\Support\Mail\Bold;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Support\Facades\URL;

/**
 * The choir's one email (the choir wait, 2026-11-24): their AI choir is ready, or couldn't be made and why, with one
 * link back into the app (only `ref=choir-email`: no pixel, nothing about who) and a one-click unsubscribe (RFC 8058).
 */
final class ChoirReadyMail extends AccountMail
{
    /**
     * @param  string|null  $failure  why it couldn't be made (null when it's ready)
     * @param  bool  $givenBack  a failed song that didn't use their free choir or minutes
     */
    public function __construct(
        public readonly int $userId,
        public readonly string $person,
        public readonly string $song,
        public readonly string $songId,
        public readonly ?string $failure = null,
        public readonly bool $givenBack = false,
    ) {}

    protected function key(): string
    {
        return $this->failure === null ? 'choir_ready' : 'choir_failed';
    }

    protected function name(): ?string
    {
        return $this->person !== '' ? $this->person : null;
    }

    protected function url(): string
    {
        return Hosts::appLink('/choir/'.$this->songId.'?ref=choir-email');
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: (string) __('account.mail.'.$this->key().'.subject', ['song' => $this->song]));
    }

    protected function lines(): array
    {
        if ($this->failure === null) {
            return [Bold::words('account.mail.choir_ready.body', ['song' => $this->song])];
        }

        return array_values(array_filter([
            Bold::words('account.mail.choir_failed.body', ['song' => $this->song]),
            (string) __('account.mail.choir_failed.'.($this->failure === 'clip_unusable' ? 'take' : 'ours')),
            $this->givenBack ? (string) __('account.mail.choir_failed.given_back') : null,
        ]));
    }

    public function headers(): Headers
    {
        return new Headers(text: ['List-Unsubscribe' => '<'.self::unsubscribeUrl($this->userId).'>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click']);
    }

    /** The footer's and the mail provider's one-click way to turn these emails off. */
    public static function unsubscribeUrl(int $userId): string
    {
        return Hosts::public(URL::signedRoute('choir-emails.unsubscribe', ['user' => Ids::encode($userId)], absolute: false));
    }

    /**
     * The footer line: one email a choir, and how to turn them off.
     *
     * @return array{lead: string|null, text: string}
     */
    protected function note(): array
    {
        return ['lead' => null, 'text' => (string) __('account.mail.choir_ready.footer', ['unsubscribe' => self::unsubscribeUrl($this->userId)])];
    }
}
