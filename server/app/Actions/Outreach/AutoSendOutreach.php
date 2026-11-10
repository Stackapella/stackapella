<?php

declare(strict_types=1);

namespace App\Actions\Outreach;

use App\Exceptions\Refusal;
use App\Models\OutreachMessage;
use App\Support\Flags;
use App\Support\Outreach\Outreach;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Hillel's switch (2026-11-05: "can you auto send 5 a day"; docs/outreach-plan.md): with
 * outreach_autosend on, each scheduled turn (every 30 minutes, weekdays from 9:00 to 12:00 in the
 * report's zone) sends at most one first email, until the day's cap of first emails is reached,
 * Hillel's own first sends counted too, his follow-ups never (2026-11-09: "5 new + follow-ups"). The next is the drafted organization whose send-by day is soonest (then the oldest),
 * with a published, checked contact address. It goes through SendOutreach's own path, so every rule
 * holds (suppression, the earliest and send-by days, never twice). A send that fails is logged and
 * that organization skipped for a week, never retried in a loop. Follow-ups are never sent here.
 */
final class AutoSendOutreach
{
    /** The turns' window, in the report's zone: from 9:00 until noon. */
    public const FROM_HOUR = 9;

    public const UNTIL_HOUR = 12;

    /** After a failed send, the organization waits this long before a turn tries it again. */
    public const SKIP_DAYS = 7;

    public function __construct(private readonly Flags $flags, private readonly SendOutreach $send) {}

    /** Whether `at` is inside the turns' window: a weekday, 9:00 to 11:59 in the report's zone. */
    public static function inWindow(Carbon $at): bool
    {
        $local = $at->copy()->setTimezone(ReportDay::zone());

        return $local->isWeekday() && $local->hour >= self::FROM_HOUR && $local->hour < self::UNTIL_HOUR;
    }

    /**
     * One turn: the first email it sent, or null (switched off, outside the window, the cap reached,
     * or nothing ready).
     */
    public function handle(): ?OutreachMessage
    {
        if (! $this->flags->on('outreach_autosend') || ! Outreach::configured() || ! self::inWindow(now())) {
            return null;
        }

        foreach ($this->candidates() as $message) {
            if (Outreach::firstsSentToday() >= Outreach::cap()) {
                return null;
            }

            $target = $message->target;

            if (Cache::has(self::skipKey($target->id)) || Outreach::suppressed($target) || Outreach::beforeSendNotBefore($target) || Outreach::pastSendBy($target)) {
                continue;
            }

            try {
                return $this->send->automatic($message);
            } catch (Refusal $refusal) {
                if ($refusal->refusalCode === 'outreach_daily_cap') {
                    return null;
                }

                // Not sent (the mail service, or a rule that changed since): this organization waits.
                Log::warning('An automatic outreach email didn\'t send; skipping that organization for a week', ['target' => $target->id, 'code' => $refusal->refusalCode]);
                Cache::put(self::skipKey($target->id), true, now()->addDays(self::SKIP_DAYS));
            }
        }

        return null;
    }

    /**
     * The first emails waiting as drafts for organizations ready to be written to, soonest send-by
     * day first (those without one last), then the oldest.
     *
     * @return iterable<int, OutreachMessage>
     */
    private function candidates(): iterable
    {
        return OutreachMessage::query()
            ->select('outreach_messages.*')
            ->join('outreach_targets', 'outreach_targets.id', '=', 'outreach_messages.outreach_target_id')
            ->where('outreach_messages.kind', OutreachMessage::FIRST)
            ->where('outreach_messages.status', 'draft')
            // Drafted, with an address on the organization's own page (a row marked unchecked stays "to research").
            ->where('outreach_targets.status', 'drafted')
            ->whereNotNull('outreach_targets.contact_email')
            ->whereNotNull('outreach_targets.source_url')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('outreach_messages as sent')->whereColumn('sent.outreach_target_id', 'outreach_targets.id')->where('sent.kind', OutreachMessage::FIRST)->whereNotNull('sent.sent_at'))
            ->orderByRaw('outreach_targets.send_by is null')
            ->orderBy('outreach_targets.send_by')
            ->orderBy('outreach_targets.id')
            ->with('target')
            ->limit(50)
            ->get();
    }

    private static function skipKey(int $target): string
    {
        return 'outreach.autosend.skip.'.$target;
    }
}
