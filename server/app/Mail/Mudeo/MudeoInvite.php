<?php

declare(strict_types=1);

namespace App\Mail\Mudeo;

use App\Support\Mail\EmailSkin;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * Hillel's one email to one of mudeo's users (docs/mudeo-transition-plan.md), from
 * stackapella.com (config mail.mudeo), in small capped batches that stop on a high bounce rate, so
 * an old list's bounces never weigh on the account emails. Sent at once,
 * not queued, so a bounce Cloudflare reports is known when it returns. One-click unsubscribe by
 * the List-Unsubscribe headers (RFC 8058) and the link in the footer.
 */
final class MudeoInvite extends Mailable
{
    /** The email's words, in order (lang/en/mudeo.php, "mail"). */
    public const PARAGRAPHS = ['paragraph_1', 'paragraph_2', 'paragraph_3', 'paragraph_4'];

    public const POINTS = ['point_1', 'point_2', 'point_3', 'point_4', 'point_5', 'point_6'];

    /** After the points, before the invitation and its link. */
    public const BEFORE_INVITE = ['after_0'];

    public const AFTER = ['after_1', 'after_3'];

    /** Under a divider at the end: the P.S. */
    public const POSTSCRIPT = ['after_2'];

    public function __construct(public readonly ?string $firstName, public readonly string $link, public readonly string $unsubscribe) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address((string) config('mail.mudeo.address'), __('mudeo.mail.from_name')),
            replyTo: [new Address((string) config('mail.mudeo.reply_to'))],
            subject: __('mudeo.mail.subject'),
        );
    }

    public function headers(): Headers
    {
        return new Headers(text: [
            'List-Unsubscribe' => '<'.$this->unsubscribe.'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ]);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.mudeo.invite', text: 'emails.mudeo.invite-text', with: [
            // Someone on the list who already has an account sees their own skin and accent (EmailSkin).
            'palette' => EmailSkin::paletteFor(is_string($to = $this->to[0]['address'] ?? null) ? $to : null),
            'hello' => $this->firstName !== null && $this->firstName !== '' ? __('mudeo.mail.hi', ['first_name' => $this->firstName]) : __('mudeo.mail.hi_plain'),
            'link' => $this->link,
            'unsubscribe' => $this->unsubscribe,
            'postal' => (string) config('mail.mudeo.postal_address'),
            'paragraphs' => self::words(self::PARAGRAPHS),
            'points' => self::words(self::POINTS),
            'beforeInvite' => self::words(self::BEFORE_INVITE),
            'after' => self::words(self::AFTER),
            'postscript' => self::words(self::POSTSCRIPT),
            // The footer either side of its link.
            'footer' => explode('[link]', __('mudeo.mail.footer', ['email' => (string) ($this->to[0]['address'] ?? '')]), 2) + [1 => ''],
        ]);
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private static function words(array $keys): array
    {
        return array_map(static fn (string $key): string => __('mudeo.mail.'.$key), $keys);
    }
}
