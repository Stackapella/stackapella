<?php

declare(strict_types=1);

namespace App\Support\Outreach;

use App\Actions\Counts\Funnel;
use App\Models\OutreachMessage;
use App\Models\OutreachReply;
use App\Models\OutreachSuppression;
use App\Models\OutreachTarget;
use App\Support\Http\Hosts;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Outreach's rules in one place (docs/outreach-plan.md): whether it may send at all, the daily cap,
 * when the one follow-up is due, the one link every email carries, and what the anonymous counts say
 * came of the sends that still carried an organization's ?ref= tag.
 */
final class Outreach
{
    /** The organizations' ?ref= tags, cached for the counts (forgotten when one is added). */
    public const REFS_CACHE = 'outreach.refs';

    /** The ?ref= tag's prefix: the counts keep an "o-" tag only when it's one of ours. */
    public const REF_PREFIX = 'o-';

    /** The anonymous count kinds a target's results add up (the counts' own kinds). */
    public const RESULTS = ['arrivals' => Funnel::ARRIVALS, 'lesson1' => ['f_lesson1'], 'signedUp' => ['f_account'], 'schoolCodes' => ['school_redeemed']];

    /** Fewer arrivals than this and a target's numbers are folded, as Growth's sources are. */
    public const FOLD_UNDER = 5;

    public static function configured(): bool
    {
        return (string) config('outreach.from') !== '' && (string) config('outreach.token') !== '' && (string) config('outreach.account_id') !== '';
    }

    public static function cap(): int
    {
        return max(0, (int) config('outreach.daily_cap'));
    }

    /**
     * First emails sent (or marked sent) today, in the report's day, by hand or automatically: the
     * daily cap's count. Follow-ups are never counted (Hillel, 2026-11-09: "5 new + follow-ups").
     */
    public static function firstsSentToday(): int
    {
        return OutreachMessage::query()->where('kind', OutreachMessage::FIRST)->whereNotNull('sent_at')->where('sent_at', '>=', ReportDay::start(ReportDay::today()))->count();
    }

    public static function suppressed(OutreachTarget $target): bool
    {
        return $target->status === 'do_not_contact' || OutreachSuppression::blocks($target->contact_email);
    }

    /**
     * When the one follow-up may go: follow_up_days after the first email, while there's no
     * follow-up yet, no reply, and they can still be written to. Null when it never will.
     *
     * @param  Collection<int, OutreachMessage>  $messages
     */
    public static function followUpFrom(OutreachTarget $target, Collection $messages, int $replies): ?Carbon
    {
        $first = $messages->first(fn (OutreachMessage $m): bool => $m->kind === OutreachMessage::FIRST && $m->sent_at !== null);
        $followed = $messages->contains(fn (OutreachMessage $m): bool => $m->kind === OutreachMessage::FOLLOW_UP && $m->sent_at !== null);

        if ($first === null || $followed || $replies > 0 || in_array($target->status, ['closed', 'do_not_contact', 'replied', 'partner'], true)) {
            return null;
        }

        $from = $first->sent_at?->copy()->addDays((int) config('outreach.follow_up_days'));
        // Never before the day it was set to wait for (after their event).
        $wait = $target->follow_up_not_before !== null ? ReportDay::start($target->follow_up_not_before->toDateString()) : null;

        return $from !== null && $wait !== null && $wait->greaterThan($from) ? $wait : $from;
    }

    /** Within this many days of its send-by day, a first email not sent yet shows as needing Hillel. */
    public const SEND_BY_SOON_DAYS = 3;

    /**
     * Whether the first email would go after its send-by day (its wording may be stale).
     */
    public static function pastSendBy(OutreachTarget $target): bool
    {
        return $target->send_by !== null && ReportDay::end($target->send_by->toDateString())->isPast();
    }

    /**
     * Whether the first email would go before its earliest day (a busy week, spacing, a hold).
     */
    public static function beforeSendNotBefore(OutreachTarget $target): bool
    {
        return $target->send_not_before !== null && ReportDay::start($target->send_not_before->toDateString())->isFuture();
    }

    /**
     * A first email not sent yet whose send-by day is near (or past).
     */
    public static function sendBySoon(OutreachTarget $target, bool $firstSent): bool
    {
        return ! $firstSent && $target->send_by !== null && ! self::suppressed($target)
            && ReportDay::start($target->send_by->toDateString())->subDays(self::SEND_BY_SOON_DAYS)->isPast();
    }

    /**
     * How many organizations' send-by days are near and their first email not sent.
     */
    public static function sendBySoonCount(): int
    {
        $soon = Carbon::parse(ReportDay::today())->addDays(self::SEND_BY_SOON_DAYS)->toDateString();

        return OutreachTarget::query()->whereNotNull('send_by')->where('send_by', '<=', $soon)->whereNotIn('status', ['do_not_contact', 'closed', 'partner'])
            ->whereDoesntHave('messages', fn ($q) => $q->where('kind', OutreachMessage::FIRST)->whereNotNull('sent_at'))->count();
    }

    /**
     * Targets whose follow-up is due now.
     */
    public static function followUpsDue(): int
    {
        $days = (int) config('outreach.follow_up_days');

        return OutreachTarget::query()->where('status', 'sent')
            ->whereHas('messages', fn ($q) => $q->where('kind', OutreachMessage::FIRST)->whereNotNull('sent_at')->where('sent_at', '<=', now()->subDays($days)))
            ->whereDoesntHave('messages', fn ($q) => $q->where('kind', OutreachMessage::FOLLOW_UP)->whereNotNull('sent_at'))
            ->whereDoesntHave('replies')
            ->where(fn ($q) => $q->whereNull('follow_up_not_before')->orWhere('follow_up_not_before', '<=', ReportDay::today()))
            ->count();
    }

    public static function unreadReplies(): int
    {
        return OutreachReply::query()->whereNull('read_at')->count();
    }

    /**
     * The one link every email carries: our domain, plain (Hillel, 2026-11-05: "Make the URL just our
     * domain, no extra tracking"). An email's words go as written: nothing adds a ?ref= tag any more.
     */
    public static function link(): string
    {
        return rtrim(Hosts::public('/'), '/');
    }

    /**
     * Whether an email that was sent carried the organization's ?ref= tag (the sends before
     * 2026-11-05 did): only those can be counted, so a newer send reads "not tracked", never a 0.
     */
    public static function tagged(OutreachMessage $message, string $ref): bool
    {
        return $message->sent_at !== null && str_contains($message->body, 'ref='.$ref);
    }

    /**
     * Whether a ?ref= tag is one of our organizations' (the one test the counts and the site share:
     * RecordCounts::source keeps it, and the public pages offer "Here from Hillel's email?").
     */
    public static function known(?string $ref): bool
    {
        $ref = strtolower(trim((string) $ref));

        return str_starts_with($ref, self::REF_PREFIX) && in_array($ref, self::refs(), true);
    }

    /**
     * A tag as an import row may bring it: "o-" and at most 14 letters, digits or hyphens.
     */
    public static function validRef(mixed $ref): bool
    {
        return is_string($ref) && preg_match('/^o-[a-z0-9-]{1,14}$/', $ref) === 1;
    }

    /**
     * A new ?ref= tag from the organization's name: "o-" and at most 14 more, unique.
     */
    public static function newRef(string $name): string
    {
        $slug = trim(Str::limit(Str::slug(preg_replace('/^the\s+/i', '', $name) ?? $name), 14, ''), '-');
        $slug = $slug !== '' ? $slug : 'org';
        $ref = self::REF_PREFIX.$slug;

        for ($n = 2; OutreachTarget::query()->where('ref', $ref)->exists(); $n++) {
            $ref = self::REF_PREFIX.trim(Str::limit($slug, 14 - strlen((string) $n) - 1, ''), '-').'-'.$n;
        }

        return $ref;
    }

    /**
     * Every organization's tag, for the counts (RecordCounts::source).
     *
     * @return list<string>
     */
    public static function refs(): array
    {
        /** @var list<string> */
        return Cache::remember(self::REFS_CACHE, 600, static fn (): array => OutreachTarget::query()->pluck('ref')->map(static fn (mixed $r): string => (string) $r)->values()->all());
    }

    /**
     * What came of each tag in the anonymous counts (all days): arrivals, first lessons, accounts
     * made and school codes redeemed. Under FOLD_UNDER arrivals the numbers are folded (`few`), so
     * a handful never stands for a person.
     *
     * @param  list<string>  $refs
     * @return array<string, array{arrivals: int|null, lesson1: int|null, signedUp: int|null, schoolCodes: int|null, few: bool}>
     */
    public static function results(array $refs): array
    {
        $kinds = array_values(array_unique(array_merge(...array_values(self::RESULTS))));
        $totals = [];

        if ($refs !== []) {
            foreach (DB::table('usage_counts')->whereIn('source', $refs)->whereIn('kind', $kinds)->selectRaw('source, kind, sum(count) as total')->groupBy('source', 'kind')->get() as $row) {
                $totals[(string) $row->source][(string) $row->kind] = (int) $row->total;
            }
        }

        $out = [];

        foreach ($refs as $ref) {
            $sum = static fn (string $column): int => array_sum(array_map(static fn (string $kind): int => $totals[$ref][$kind] ?? 0, self::RESULTS[$column]));
            $arrivals = $sum('arrivals');
            $few = $arrivals < self::FOLD_UNDER;
            $out[$ref] = [
                'arrivals' => $few ? null : $arrivals,
                'lesson1' => $few ? null : $sum('lesson1'),
                'signedUp' => $few ? null : $sum('signedUp'),
                'schoolCodes' => $few ? null : $sum('schoolCodes'),
                'few' => $few,
            ];
        }

        return $out;
    }
}
