<?php

declare(strict_types=1);

namespace App\Mail\Outreach;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;

/**
 * One outreach email to an organization (docs/outreach-plan.md): exactly the plain text Hillel
 * wrote, no HTML, no images, no pixel and no tracked link, from outreach's own sender. Sent only
 * by his press of Send (App\Actions\Outreach\SendOutreach), never queued in a batch.
 */
final class OutreachEmail extends Mailable
{
    public function __construct(public readonly string $mailSubject, public readonly string $text, public readonly string $tag) {}

    public function envelope(): Envelope
    {
        $from = (string) config('outreach.from');
        $replyTo = (string) config('outreach.reply_to');

        return new Envelope(
            from: new Address($from, (string) config('outreach.from_name')),
            replyTo: [new Address($replyTo !== '' ? $replyTo : $from)],
            subject: $this->mailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(text: 'emails.outreach-text');
    }

    public function headers(): Headers
    {
        // Ours, so the Worker can match a delivery or a bounce to this email.
        return new Headers(text: ['X-Stackapella-Outreach' => $this->tag]);
    }
}
