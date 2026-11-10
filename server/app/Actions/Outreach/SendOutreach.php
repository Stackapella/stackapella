<?php

declare(strict_types=1);

namespace App\Actions\Outreach;

use App\Exceptions\Refusal;
use App\Mail\Outreach\OutreachEmail;
use App\Models\AuditLog;
use App\Models\OutreachMessage;
use App\Models\OutreachTarget;
use App\Models\User;
use App\Services\Mail\CloudflareTransport;
use App\Support\Outreach\Outreach;
use App\Support\Stats\ReportDay;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Drafts and sending (docs/outreach-plan.md, slices 2 and 3), with the rules that keep it from
 * being spam enforced here, not in the screen: one email per press of Send (or, with Hillel's
 * outreach_autosend switch on, one first email per scheduled turn: automatic()), never a batch;
 * plain text from outreach's own sender, its words as written; at most config('outreach.daily_cap')
 * first emails a day (the report's day, sent or marked sent, by hand or automatically; follow-ups
 * never count and are never held by it, Hillel's "5 new + follow-ups"); never to a suppressed
 * address or an organization that said no; one follow-up at most, always by hand, no sooner than
 * config('outreach.follow_up_days') after the first, and none once they've replied.
 */
final class SendOutreach
{
    /** Tests to himself an admin may send a day. */
    public const TESTS_A_DAY = 20;

    /**
     * A draft for the organization from its angle's start (or its follow-up's), for Hillel to edit.
     * An unsent draft of the same kind is handed back rather than doubled.
     */
    public function draft(OutreachTarget $target, string $kind, User $admin, ?string $ip = null): OutreachMessage
    {
        if (Outreach::suppressed($target)) {
            throw new Refusal(409, 'outreach_suppressed');
        }

        $messages = $target->messages()->get();
        $waiting = $messages->first(fn (OutreachMessage $m): bool => $m->kind === $kind && $m->status === 'draft');

        if ($waiting !== null) {
            return $waiting;
        }

        $first = $messages->first(fn (OutreachMessage $m): bool => $m->kind === OutreachMessage::FIRST && $m->sent_at !== null);

        if ($kind === OutreachMessage::FIRST && $first !== null) {
            throw new Refusal(409, 'outreach_already_sent');
        }

        if ($kind === OutreachMessage::FIRST) {
            [$subject, $body] = $this->opening($target);
        } else {
            if ($first === null) {
                throw new Refusal(409, 'outreach_follow_up_not_due');
            }

            if ($messages->contains(fn (OutreachMessage $m): bool => $m->kind === OutreachMessage::FOLLOW_UP && $m->sent_at !== null)) {
                throw new Refusal(409, 'outreach_already_sent');
            }

            [$subject, $body] = $this->followUp($target, $first);
        }
        $message = new OutreachMessage(['kind' => $kind, 'subject' => $subject, 'body' => $body]);
        $message->forceFill(['outreach_target_id' => $target->id, 'status' => 'draft'])->save();

        if (in_array($target->status, ['to_research', 'ready'], true)) {
            $target->forceFill(['status' => 'drafted'])->save();
        }

        AuditLog::record('outreach.drafted', $target, $admin, after: ['kind' => $kind], ip: $ip);

        return $message;
    }

    public function edit(OutreachMessage $message, ?string $subject, ?string $body, User $admin, ?string $ip = null): OutreachMessage
    {
        $this->mustBeDraft($message);
        $message->forceFill(array_filter(['subject' => $subject !== null ? CloudflareTransport::oneLine($subject) : null, 'body' => $body], static fn (?string $v): bool => $v !== null))->save();
        AuditLog::record('outreach.draft_edited', $message->target, $admin, ip: $ip);

        return $message;
    }

    public function discard(OutreachMessage $message, User $admin, ?string $ip = null): void
    {
        $this->mustBeDraft($message);
        $target = $message->target;
        $message->delete();

        if ($target->status === 'drafted' && ! $target->messages()->exists()) {
            $target->forceFill(['status' => $target->contact_email !== null || $target->contact_form_url !== null ? 'ready' : 'to_research'])->save();
        }

        AuditLog::record('outreach.draft_discarded', $target, $admin, ip: $ip);
    }

    /**
     * Hillel pressed Send: this one email goes, now, from outreach's sender, or nothing does.
     */
    public function send(OutreachMessage $message, User $admin, ?string $ip = null, bool $staleOk = false, bool $earlyOk = false): OutreachMessage
    {
        return $this->deliver($message, $admin, $ip, $staleOk, $earlyOk);
    }

    /**
     * A first email sent by the outreach_autosend switch's scheduled turn (Hillel, 2026-11-05: "can you
     * auto send 5 a day"): the same path as Send, under every one of its rules (the cap, counting
     * Hillel's own sends; suppression; the earliest and send-by days, never waived; never twice),
     * with no admin behind it, audited as automatic. Never a follow-up: replies go to Hillel himself,
     * so a follow-up stays his own Send.
     */
    public function automatic(OutreachMessage $message): OutreachMessage
    {
        if ($message->kind !== OutreachMessage::FIRST) {
            throw new Refusal(409, 'outreach_not_automatic');
        }

        return $this->deliver($message, null, null, false, false);
    }

    private function deliver(OutreachMessage $message, ?User $admin, ?string $ip, bool $staleOk, bool $earlyOk): OutreachMessage
    {
        if (! Outreach::configured()) {
            throw new Refusal(409, 'outreach_off');
        }

        return $this->underLock(function () use ($message, $admin, $ip, $staleOk, $earlyOk): OutreachMessage {
            $message->refresh();
            $target = $message->target;
            $this->mayGo($message, $target, $staleOk, $earlyOk);

            if ($target->contact_email === null) {
                throw new Refusal(409, 'outreach_no_address');
            }

            $tag = Str::lower(Str::random(24));

            try {
                Mail::mailer('outreach')->to($target->contact_email)->send(new OutreachEmail($message->subject, $message->body, $tag));
            } catch (Throwable $e) {
                Log::warning('An outreach email didn\'t send', ['target' => $target->id, 'error' => $e->getMessage()]);

                throw new Refusal(502, 'outreach_send_failed');
            }

            return $this->sent($message, $target, 'email', $target->contact_email, $tag, $admin, $ip);
        });
    }

    /**
     * A test to Hillel himself (2026-11-02): the exact plain-text email, from outreach's sender by
     * its real path, to the signed-in admin's own address only, "[Test]" in the subject. Never counts
     * toward the daily cap, never marks anything sent; logged as a test. 20 a day each.
     *
     * @return array{sentTo: string, sentAt: string}
     */
    public function test(OutreachMessage $message, User $admin, ?string $ip = null): array
    {
        if (! Outreach::configured()) {
            throw new Refusal(409, 'outreach_off');
        }

        if ($admin->email === null || $admin->email === '') {
            throw new Refusal(409, 'outreach_no_admin_email');
        }

        $key = 'outreach.tests.'.$admin->id.'.'.ReportDay::today();

        if ((int) Cache::get($key, 0) >= self::TESTS_A_DAY) {
            throw new Refusal(429, 'outreach_test_limit');
        }

        try {
            Mail::mailer('outreach')->to($admin->email)->send(new OutreachEmail(CloudflareTransport::oneLine('[Test] '.$message->subject), $message->body, 'test-'.Str::lower(Str::random(20))));
        } catch (Throwable $e) {
            Log::warning('An outreach test didn\'t send', ['message' => $message->id, 'error' => $e->getMessage()]);

            throw new Refusal(502, 'outreach_send_failed');
        }

        Cache::put($key, (int) Cache::get($key, 0) + 1, now()->addDay());
        AuditLog::record('outreach.test_sent', $message->target, $admin, after: ['kind' => $message->kind], ip: $ip);

        return ['sentTo' => $admin->email, 'sentAt' => now()->utc()->toIso8601ZuluString()];
    }

    /**
     * Sent another way (their contact form): recorded as sent, under the same rules and the same cap.
     */
    public function markSent(OutreachMessage $message, User $admin, ?string $ip = null, bool $staleOk = false, bool $earlyOk = false): OutreachMessage
    {
        return $this->underLock(function () use ($message, $admin, $ip, $staleOk, $earlyOk): OutreachMessage {
            $message->refresh();
            $target = $message->target;
            $this->mayGo($message, $target, $staleOk, $earlyOk);

            return $this->sent($message, $target, 'form', null, null, $admin, $ip);
        });
    }

    /**
     * @template T
     *
     * @param  callable(): T  $then
     * @return T
     */
    private function underLock(callable $then): mixed
    {
        // One send at a time, so two presses (or two admins) can't both pass the cap.
        return Cache::lock('outreach.send', 30)->block(10, $then);
    }

    private function mayGo(OutreachMessage $message, OutreachTarget $target, bool $staleOk, bool $earlyOk): void
    {
        $this->mustBeDraft($message);

        // Before its earliest day (a busy week, two emails spaced, or held for the lawyer): only
        // when Hillel says so again.
        if ($message->kind === OutreachMessage::FIRST && ! $earlyOk && Outreach::beforeSendNotBefore($target)) {
            throw new Refusal(409, 'outreach_too_early');
        }

        // Past its send-by day, the first email's wording may be stale ("before the festival"):
        // it goes only when Hillel says so again.
        if ($message->kind === OutreachMessage::FIRST && ! $staleOk && Outreach::pastSendBy($target)) {
            throw new Refusal(409, 'outreach_past_send_by');
        }

        if (Outreach::suppressed($target)) {
            throw new Refusal(409, 'outreach_suppressed');
        }

        // The cap is on new organizations: a follow-up always goes when it's due.
        if ($message->kind === OutreachMessage::FIRST && Outreach::firstsSentToday() >= Outreach::cap()) {
            throw new Refusal(409, 'outreach_daily_cap');
        }

        $messages = $target->messages()->whereKeyNot($message->id)->get();

        if ($messages->contains(fn (OutreachMessage $m): bool => $m->kind === $message->kind && $m->sent_at !== null)) {
            throw new Refusal(409, 'outreach_already_sent');
        }

        if ($message->kind === OutreachMessage::FOLLOW_UP) {
            $from = Outreach::followUpFrom($target, $messages, $target->replies()->count());

            if ($from === null || $from->isFuture()) {
                throw new Refusal(409, 'outreach_follow_up_not_due');
            }
        }
    }

    private function sent(OutreachMessage $message, OutreachTarget $target, string $via, ?string $to, ?string $tag, ?User $admin, ?string $ip): OutreachMessage
    {
        $message->forceFill(['status' => 'sent', 'sent_via' => $via, 'to_email' => $to, 'message_id' => $tag, 'sent_at' => now(), 'sent_by' => $admin?->id])->save();

        if (! in_array($target->status, ['replied', 'closed'], true)) {
            $target->forceFill(['status' => 'sent'])->save();
        }

        // No admin: the outreach_autosend switch sent it.
        AuditLog::record('outreach.sent', $target, $admin, after: ['kind' => $message->kind, 'via' => $via, ...($admin === null ? ['automatic' => true] : [])], ip: $ip);

        return $message;
    }

    private function mustBeDraft(OutreachMessage $message): void
    {
        if ($message->status !== 'draft') {
            throw new Refusal(409, 'outreach_not_a_draft');
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function opening(OutreachTarget $target): array
    {
        $words = ['org' => $target->name, 'link' => Outreach::link()];

        return [CloudflareTransport::oneLine((string) __("outreach.angles.{$target->angle}.subject", $words)), trim((string) __("outreach.angles.{$target->angle}.body", $words))];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function followUp(OutreachTarget $target, OutreachMessage $first): array
    {
        // The angle's own follow-up where it has one (the choirs' learning tracks offer), else everyone's.
        $key = Lang::has('outreach.follow_up.angles.'.$target->angle) ? 'outreach.follow_up.angles.'.$target->angle : 'outreach.follow_up.body';
        $body = (string) __($key, ['org' => $target->name, 'link' => Outreach::link()]);

        return [CloudflareTransport::oneLine((string) __('outreach.follow_up.subject', ['subject' => $first->subject])), trim($body)];
    }
}
