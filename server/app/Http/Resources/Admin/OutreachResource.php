<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin;

use App\Models\OutreachMessage;
use App\Models\OutreachReply;
use App\Models\OutreachTarget;
use App\Models\SchoolClass;
use App\Support\Outreach\Outreach;
use Illuminate\Support\Carbon;

/**
 * Outreach's shapes (contract/schemas/admin.outreach.*.json): an organization's row, its page (the
 * emails, the replies and what the counts say came of it), an email and a reply.
 */
final class OutreachResource
{
    /**
     * @param  list<string>|null  $suppressed  the listed addresses on either do-not-contact list, read once for a whole list
     * @return array<string, mixed>
     */
    public static function target(OutreachTarget $target, ?array $suppressed = null): array
    {
        $messages = $target->relationLoaded('messages') ? $target->messages : $target->messages()->get();
        $replies = $target->relationLoaded('replies') ? $target->replies : $target->replies()->get();
        $followUp = Outreach::followUpFrom($target, $messages, $replies->count());

        return [
            'id' => $target->sqid(),
            'ref' => $target->ref,
            'name' => $target->name,
            'group' => $target->group,
            'country' => $target->country,
            'reach' => $target->reach,
            'fit' => $target->fit,
            'contact' => ['email' => $target->contact_email, 'formUrl' => $target->contact_form_url, 'sourceUrl' => $target->source_url],
            'angle' => $target->angle,
            'sendBy' => $target->send_by?->toDateString(),
            'followUpNotBefore' => $target->follow_up_not_before?->toDateString(),
            'sendNotBefore' => $target->send_not_before?->toDateString(),
            'tooEarly' => Outreach::beforeSendNotBefore($target),
            'timingNote' => $target->timing_note,
            'sendBySoon' => Outreach::sendBySoon($target, $messages->contains(fn (OutreachMessage $m): bool => $m->kind === OutreachMessage::FIRST && $m->sent_at !== null)),
            'pastSendBy' => Outreach::pastSendBy($target),
            'status' => $target->status,
            'followUpFrom' => self::at($followUp),
            'followUpDue' => $followUp !== null && ! $followUp->isFuture(),
            'lastSentAt' => self::at($messages->whereNotNull('sent_at')->max('sent_at')),
            'replies' => $replies->count(),
            'unreadReplies' => $replies->whereNull('read_at')->count(),
            'outcome' => $target->outcome,
            'outcomeNote' => $target->outcome_note,
            'notes' => $target->notes,
            'research' => $target->research_note !== null || $target->research_links !== null ? ['note' => $target->research_note, 'links' => array_values($target->research_links ?? [])] : null,
            'suppressed' => $suppressed === null ? Outreach::suppressed($target) : ($target->status === 'do_not_contact' || in_array(strtolower((string) $target->contact_email), $suppressed, true)),
            // A partner's codes: seats and seats used only, never who (docs/outreach-plan.md).
            'partnerCodes' => ($target->relationLoaded('partnerCodes') ? $target->partnerCodes->sortBy('id') : $target->partnerCodes()->orderBy('id')->get())->map(static fn (SchoolClass $c): array => ['id' => $c->sqid(), 'code' => $c->shown(), 'seats' => $c->seats, 'used' => $c->used, 'status' => $c->status(), 'expiresAt' => $c->expires_at->utc()->toIso8601ZuluString()])->values()->all(),
            'updatedAt' => self::at($target->updated_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function detail(OutreachTarget $target): array
    {
        $target->load(['messages' => fn ($q) => $q->orderBy('id'), 'replies' => fn ($q) => $q->orderBy('received_at')]);

        return [
            ...self::target($target),
            'link' => Outreach::link(),
            'messages' => $target->messages->map(static fn (OutreachMessage $m): array => self::message($m))->values()->all(),
            'replyLog' => $target->replies->map(static fn (OutreachReply $r): array => self::reply($r))->values()->all(),
            // Counted only while a sent email carried the organization's tag (the sends before 2026-11-05).
            'results' => [...Outreach::results([$target->ref])[$target->ref], 'tracked' => $target->messages->contains(static fn (OutreachMessage $m): bool => Outreach::tagged($m, $target->ref))],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function message(OutreachMessage $message): array
    {
        return [
            'id' => $message->sqid(),
            'kind' => $message->kind,
            'subject' => $message->subject,
            'body' => $message->body,
            'status' => $message->status,
            'toEmail' => $message->to_email,
            'sentVia' => $message->sent_via,
            'sentAt' => self::at($message->sent_at),
            'deliveredAt' => self::at($message->delivered_at),
            'bouncedAt' => self::at($message->bounced_at),
            'bounceNote' => $message->bounce_note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function reply(OutreachReply $reply): array
    {
        return [
            'id' => $reply->sqid(),
            'targetId' => $reply->target?->sqid(),
            'fromEmail' => $reply->from_email,
            'subject' => $reply->subject,
            'body' => $reply->body,
            'receivedAt' => self::at($reply->received_at),
            'readAt' => self::at($reply->read_at),
        ];
    }

    private static function at(mixed $at): ?string
    {
        return $at !== null ? Carbon::parse($at)->utc()->toIso8601ZuluString() : null;
    }
}
