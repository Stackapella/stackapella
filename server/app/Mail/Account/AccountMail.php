<?php

declare(strict_types=1);

namespace App\Mail\Account;

use App\Support\Mail\EmailSkin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Lang;

/**
 * Every account email: one title, a greeting, a few lines, one button, the link written out, a note
 * and a footer saying who it went to and why, all translated (resources/views/emails/action). Sent only
 * about something the person did (docs/conventions.md, "No bulk or recurring email"), and never
 * with text someone else wrote. Queued encrypted: its links sign in, reset or confirm, and the
 * queue (and failed_jobs) sits in the database and its backups.
 */
abstract class AccountMail extends Mailable implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;
    use SerializesModels;

    /** The skin to wear (EmailSkin); null: the recipient's own, looked up when it's rendered. */
    public ?string $skin = null;

    /**
     * An accent to wear instead of the recipient's (the renderer's), as appearance's fields.
     *
     * @var array{accent?: string|null, customAccent?: string|null}|null
     */
    public ?array $accent = null;

    abstract protected function key(): string;

    /**
     * Wear this skin whoever it's to (the email renderer, which shows both).
     */
    /**
     * @param  array{accent?: string|null, customAccent?: string|null}|null  $accent
     */
    public function skinned(string $skin, ?array $accent = null): static
    {
        $this->skin = $skin;
        $this->accent = $accent;

        return $this;
    }

    /**
     * One of the email's words, its game version in the game skin when it has one
     * (account.mail.<key>.game.<part>, the app's `.game` convention).
     */
    protected function word(string $part, string $skin): string
    {
        $key = 'account.mail.'.$this->key();

        return (string) ($skin === EmailSkin::GAME && Lang::has($key.'.game.'.$part) ? __($key.'.game.'.$part) : __($key.'.'.$part));
    }

    abstract protected function url(): string;

    /**
     * @return list<string>
     */
    protected function lines(): array
    {
        return [__('account.mail.'.$this->key().'.body')];
    }

    /**
     * The small line under the button (how long the link works), from the words when they have one.
     */
    protected function soon(): ?string
    {
        return Lang::has('account.mail.'.$this->key().'.soon') ? (string) __('account.mail.'.$this->key().'.soon') : null;
    }

    /**
     * The note under the divider, a bold lead and its words: the email's own, else "Didn't ask for
     * this? You can ignore this email."
     *
     * @return array{lead: string|null, text: string}|null
     */
    protected function note(): ?array
    {
        $key = 'account.mail.'.$this->key();

        return Lang::has($key.'.note')
            ? ['lead' => Lang::has($key.'.note_lead') ? (string) __($key.'.note_lead') : null, 'text' => (string) __($key.'.note')]
            : ['lead' => (string) __('account.mail.ignore_lead'), 'text' => (string) __('account.mail.ignore')];
    }

    /**
     * Why it was sent, for the footer ("Sent to … because …").
     */
    protected function because(): string
    {
        return (string) (Lang::has('account.mail.'.$this->key().'.because') ? __('account.mail.'.$this->key().'.because') : __('emails.because.default'));
    }

    /**
     * Who it's to, for the footer.
     */
    protected function recipient(): ?string
    {
        $to = $this->to[0]['address'] ?? null;

        return is_string($to) ? $to : null;
    }

    protected function name(): ?string
    {
        return null;
    }

    /**
     * Whether it opens with "Hi :name," on a line of its own.
     */
    protected function greeting(): bool
    {
        return true;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('account.mail.'.$this->key().'.subject'));
    }

    public function content(): Content
    {
        $name = $this->name();
        $palette = EmailSkin::paletteFor($this->recipient(), $this->skin, $this->accent);
        $skin = $palette['skin'];

        return new Content(view: 'emails.action', text: 'emails.action-text', with: [
            'palette' => $palette,
            // The game skin's small label over the heading, as the cabinet's HUD.
            'hud' => $skin === EmailSkin::GAME ? __('emails.game.hud') : null,
            'title' => $this->word('title', $skin),
            'hello' => ! $this->greeting() ? null : ($name !== null && $name !== '' ? __('account.mail.hello', ['name' => $name]) : __('account.mail.hello_plain')),
            'lines' => $this->lines(),
            'button' => $this->word('button', $skin),
            'url' => $this->url(),
            'soon' => $this->soon(),
            'note' => $this->note(),
            'sentTo' => $this->recipient(),
            'because' => $this->because(),
            'postal' => (string) config('mail.mudeo.postal_address'),
        ]);
    }
}
