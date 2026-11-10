<?php

declare(strict_types=1);

namespace App\Actions\Outreach;

use App\Actions\Admin\People\ManagePerson;
use App\Models\AuditLog;
use App\Models\EmailSuppression;
use App\Models\OutreachMessage;
use App\Models\OutreachReply;
use App\Models\OutreachSuppression;
use App\Models\OutreachTarget;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * What came back (docs/outreach-plan.md, "track everything"): deliveries, bounces and complaints
 * from the outreach Worker, and replies with their text, inbound or logged by hand. A bounce, a
 * complaint or a plain "no thanks" puts the address and the organization on do not contact,
 * forever. Never anything about who read what: no opens, no clicks.
 */
final class OutreachReplies
{
    /** A reply whose first words are a no: suppressed at once (more than enough is safer than less). */
    private const NO = '/^\W*(no,?\s+thanks?|no\s+thank\s+you|unsubscribe|remove\s+(me|us)|please\s+remove|stop\b|not\s+interested)/i';

    /** Anyone can have an address at these, so a reply from one is never filed by its domain. */

    /**
     * One event from the Worker: delivered, bounce (hard or soft), complaint or reply.
     *
     * @param  array<string, mixed>  $event
     */
    public function event(array $event): void
    {
        $type = (string) ($event['type'] ?? '');
        $recipient = strtolower(trim((string) ($event['recipient'] ?? '')));

        if ($type === 'reply') {
            $from = strtolower(trim((string) ($event['from'] ?? '')));

            // Not an address we could keep (or one longer than the column): dropped, nothing logged.
            if (strlen($from) > 190 || filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
                return;
            }

            $this->receive($from, is_string($event['subject'] ?? null) ? $event['subject'] : null, (string) ($event['text'] ?? ''), $this->at($event), null);

            return;
        }

        $message = $this->messageFor(is_string($event['outreach'] ?? null) ? $event['outreach'] : null, $recipient);

        if ($message === null) {
            return;
        }

        match ($type) {
            'delivered' => $message->status === 'sent' ? $message->forceFill(['status' => 'delivered', 'delivered_at' => $this->at($event)])->save() : null,
            'bounce' => $this->bounced($message, ($event['bounceType'] ?? 'hard') !== 'soft', mb_strimwidth((string) ($event['detail'] ?? ''), 0, 500)),
            'complaint' => $this->stop($message->target, $recipient !== '' ? $recipient : (string) $message->to_email, 'complaint'),
            default => null,
        };
    }

    /**
     * A reply's text, against the organization it came from: the address it was sent to, or else
     * the one organization at that address's domain. Unmatched, it waits in Admin to be filed.
     * Only a reply from the address itself (or one Hillel logs by hand) can mark the organization
     * replied or put it on do not contact: anyone can write from the same domain.
     */
    public function receive(string $from, ?string $subject, string $text, Carbon $at, ?OutreachTarget $target, ?User $admin = null, ?string $ip = null): OutreachReply
    {
        $from = strtolower(trim($from));
        $exact = $target !== null;

        if ($target === null) {
            [$target, $exact] = $this->targetFor($from);
        }
        $message = $target?->messages()->whereNotNull('sent_at')->latest('sent_at')->first();

        $reply = new OutreachReply(['from_email' => $from, 'subject' => $subject !== null ? mb_strimwidth($subject, 0, 200) : null, 'body' => mb_strimwidth($text, 0, 20000)]);
        $reply->forceFill(['outreach_target_id' => $target?->id, 'outreach_message_id' => $message?->id, 'received_at' => $at, 'read_at' => $admin !== null ? now() : null])->save();

        if ($target !== null && $exact) {
            if (preg_match(self::NO, $text) === 1) {
                $target->outcome = 'declined';
                $this->stop($target, $from, 'no_thanks');
            } elseif ($target->status !== 'do_not_contact') {
                $target->forceFill(['status' => 'replied'])->save();
            }
        }

        AuditLog::record('outreach.reply', $target, $admin, after: ['from' => ManagePerson::fingerprint($from), 'matched' => $target !== null], ip: $ip);

        return $reply;
    }

    private function bounced(OutreachMessage $message, bool $hard, string $detail): void
    {
        $message->forceFill(['status' => 'bounced', 'bounced_at' => now(), 'bounce_note' => $detail !== '' ? $detail : null])->save();

        // A hard bounce is a dead address, for every email we send.
        if ($hard && $message->to_email !== null) {
            EmailSuppression::record($message->to_email, 'permanent_bounce');
            $this->stop($message->target, $message->to_email, 'bounce');
        }
    }

    private function stop(OutreachTarget $target, string $email, string $reason): void
    {
        if ($email !== '') {
            OutreachSuppression::record($email, $reason, $target->id);
        }

        $target->forceFill(['status' => 'do_not_contact'])->save();
    }

    private function messageFor(?string $tag, string $recipient): ?OutreachMessage
    {
        if ($tag !== null && $tag !== '') {
            $message = OutreachMessage::query()->where('message_id', $tag)->first();

            if ($message !== null) {
                return $message;
            }
        }

        return $recipient === '' ? null : OutreachMessage::query()->where('to_email', $recipient)->whereNotNull('sent_at')->latest('sent_at')->first();
    }

    /**
     * The organization a reply came from, and whether by its exact address (true) or only by the
     * address's domain (false). Never by a free-mail domain.
     *
     * @return array{0: ?OutreachTarget, 1: bool}
     */
    private function targetFor(string $from): array
    {
        $exact = OutreachTarget::query()->where('contact_email', $from)->first()
            ?? OutreachMessage::query()->where('to_email', $from)->whereNotNull('sent_at')->latest('sent_at')->first()?->target;

        if ($exact !== null) {
            return [$exact, true];
        }

        $domain = substr((string) strrchr($from, '@'), 1);

        if ($domain === '' || in_array($domain, (array) config('outreach.free_mail_domains'), true)) {
            return [null, false];
        }

        $same = OutreachTarget::query()->where('contact_email', 'like', '%@'.addcslashes($domain, '%_\\'))->limit(2)->get();

        return [$same->count() === 1 ? $same->first() : null, false];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function at(array $event): Carbon
    {
        $at = $event['at'] ?? null;

        return is_string($at) && strtotime($at) !== false ? Carbon::parse($at)->utc() : now();
    }
}
