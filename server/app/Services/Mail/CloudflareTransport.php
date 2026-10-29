<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Models\EmailSuppression;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;
use Throwable;

/**
 * Laravel's mail through Cloudflare Email Service's REST API (docs/backend-plan.md, D9): each
 * message as its JSON (from, to, subject, html, text, attachments), with the `Http` facade and no
 * new dependency. A recipient Cloudflare reports as a permanent bounce is recorded at once, so the
 * person can be told in the app to fix their address.
 *
 * MAIL_MAILER=cloudflare in production; `log` locally and `array` in tests.
 */
final class CloudflareTransport extends AbstractTransport
{
    private const ENDPOINT = 'https://api.cloudflare.com/client/v4/accounts/%s/email/sending/send';

    public function __construct(private readonly string $accountId, private readonly string $token)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if (! $original instanceof Message) {
            throw new TransportException('Only a full message (with headers) can go through Cloudflare.');
        }

        $email = MessageConverter::toEmail($original);
        $envelope = $message->getEnvelope();

        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->timeout(15)
                ->post(sprintf(self::ENDPOINT, $this->accountId), $this->payload($email, $envelope));
        } catch (Throwable $e) {
            throw new TransportException('Cloudflare Email Service could not be reached: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful() || $response->json('success') !== true) {
            throw new TransportException('Cloudflare Email Service refused the message: '.mb_strimwidth((string) $response->body(), 0, 500, '...'));
        }

        foreach ((array) $response->json('result.permanent_bounces') as $address) {
            if (is_string($address)) {
                EmailSuppression::record($address, 'permanent_bounce');
            }
        }
    }

    /**
     * A header's words on one line: control characters (a CR or LF above all) become spaces.
     */
    public static function oneLine(string $text): string
    {
        return trim((string) preg_replace('/[\p{Cc}\x{2028}\x{2029}]+/u', ' ', $text));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Email $email, Envelope $envelope): array
    {
        $from = $envelope->getSender();
        $named = static fn (Address $address): string|array => $address->getName() !== '' ? ['address' => $address->getAddress(), 'name' => self::oneLine($address->getName())] : $address->getAddress();

        $payload = [
            'from' => $named($email->getFrom()[0] ?? $from),
            'to' => array_map($named, $email->getTo()),
            // A subject can carry someone's name or a title: never a line break into the headers.
            'subject' => self::oneLine((string) $email->getSubject()),
        ];

        if ($email->getCc() !== []) {
            $payload['cc'] = array_map(static fn (Address $a): string => $a->getAddress(), $email->getCc());
        }

        if ($email->getBcc() !== []) {
            $payload['bcc'] = array_map(static fn (Address $a): string => $a->getAddress(), $email->getBcc());
        }

        if (($replyTo = $email->getReplyTo()[0] ?? null) !== null) {
            $payload['reply_to'] = $replyTo->getAddress();
        }

        // Only the headers Cloudflare allows (it refuses the whole message otherwise): one-click
        // unsubscribe for the mudeo email, and our own X- ones.
        foreach ($email->getHeaders()->all() as $header) {
            $name = $header->getName();

            if (in_array(strtolower($name), ['list-unsubscribe', 'list-unsubscribe-post'], true) || str_starts_with(strtolower($name), 'x-')) {
                $payload['headers'][$name] = $header->getBodyAsString();
            }
        }

        if (($html = $email->getHtmlBody()) !== null) {
            $payload['html'] = is_resource($html) ? (string) stream_get_contents($html) : (string) $html;
        }

        if (($text = $email->getTextBody()) !== null) {
            $payload['text'] = is_resource($text) ? (string) stream_get_contents($text) : (string) $text;
        }

        foreach ($email->getAttachments() as $attachment) {
            $payload['attachments'][] = [
                'content' => base64_encode($attachment->getBody()),
                'filename' => (string) $attachment->getFilename(),
                'type' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
                'disposition' => 'attachment',
            ];
        }

        return $payload;
    }

    public function __toString(): string
    {
        return 'cloudflare';
    }
}
