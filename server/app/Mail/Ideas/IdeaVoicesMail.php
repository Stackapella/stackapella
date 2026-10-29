<?php

declare(strict_types=1);

namespace App\Mail\Ideas;

use App\Mail\Account\AccountMail;
use App\Support\Mail\Bold;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * The writer of an idea hears who sang it (docs/your-tune-plan.md, step 3): at most one a day for
 * each idea, that day's new voices, and once when every part has one. Hillel allowed it as email
 * about the person's own idea; it has a setting, and one-click unsubscribe (RFC 8058).
 */
final class IdeaVoicesMail extends AccountMail
{
    /**
     * @param  list<array{name: string, part: string, played?: bool}>  $voices
     */
    public function __construct(public readonly ?string $writer, public readonly string $title, public readonly array $voices, public readonly bool $complete, public readonly string $link, public readonly string $unsubscribe) {}

    protected function key(): string
    {
        return 'ideas';
    }

    protected function url(): string
    {
        return $this->link;
    }

    protected function name(): ?string
    {
        return $this->writer;
    }

    public function envelope(): Envelope
    {
        $first = $this->voices[0] ?? null;

        return new Envelope(subject: match (true) {
            $this->complete => __('ideas.mail.subject_complete'),
            count($this->voices) === 1 && $first !== null => __(($first['played'] ?? false) ? 'ideas.mail.subject_one_played' : 'ideas.mail.subject_one', ['name' => $first['name'], 'part' => $first['part']]),
            default => __('ideas.mail.subject_many', ['count' => count($this->voices)]),
        });
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribe.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    protected function lines(): array
    {
        return [
            ...array_map(static fn (array $voice): string => Bold::words(($voice['played'] ?? false) ? 'ideas.mail.voice_played' : 'ideas.mail.voice', ['name' => $voice['name'], 'part' => $voice['part']]), $this->voices),
            ...($this->complete ? [Bold::words('ideas.mail.complete', ['title' => $this->title])] : []),
        ];
    }

    protected function note(): array
    {
        return ['lead' => __('ideas.mail.note_lead'), 'text' => __('ideas.mail.note', ['unsubscribe' => $this->unsubscribe])];
    }

    protected function because(): string
    {
        return __('ideas.mail.because');
    }

    public function content(): Content
    {
        return parent::content()->with(['title' => __('ideas.mail.title', ['title' => $this->title]), 'button' => __('ideas.mail.button')]);
    }
}
