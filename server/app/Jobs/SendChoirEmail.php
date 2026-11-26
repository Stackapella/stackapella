<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Counts\RecordCounts;
use App\Enums\OwnChoirStatus;
use App\Mail\Account\ChoirReadyMail;
use App\Models\EmailSuppression;
use App\Models\OwnChoirRequest;
use App\Support\Accounts\AiChoice;
use App\Support\Flags;
use App\Support\OwnChoir\ChoirWait;
use App\Support\OwnChoir\OwnChoir;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * The choir's one email (the choir wait, Hillel 2026-11-24): when a song of theirs is ready or couldn't be made, sent
 * only if it still isn't heard, their choir emails are on, AI features aren't off and the address is verified and not
 * suppressed; and if the app has looked in the last two minutes, ten minutes later instead. Once a song, whatever
 * happens after.
 */
final class SendChoirEmail implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $requestId) {}

    public function handle(): void
    {
        $song = OwnChoirRequest::query()->with('user')->find($this->requestId);
        $user = $song?->user;

        // Not until the app says it will email (the choir_email switch); a song made while it's off never gets one.
        if (! app(Flags::class)->on('choir_email')) {
            return;
        }

        if ($song === null || $user === null || $song->seen_at !== null || $song->email_sent_at !== null
            || ! in_array($song->status, [OwnChoirStatus::Ready, OwnChoirStatus::Failed], true)) {
            return;
        }

        // Still watching: try again in ten minutes.
        if (ChoirWait::lookedRecently($user->id) && $this->job !== null && $this->attempts() < $this->tries) {
            $this->release(ChoirWait::EMAIL_DELAY_MINUTES * 60);

            return;
        }

        // AI features turned off by then (docs/ai-optional-plan.md): no email, as no banner.
        $aiOff = ! AiChoice::aiOn($user);

        if (! $user->choir_email || $aiOff || $user->email === null || $user->email_verified_at === null || EmailSuppression::isSuppressed((string) $user->email)) {
            return;
        }

        // One email a song, even with two of these queued: the first to mark it sends it.
        if (DB::table('own_choir_requests')->where('id', $song->id)->whereNull('email_sent_at')->update(['email_sent_at' => now()]) !== 1) {
            return;
        }

        $ready = $song->status === OwnChoirStatus::Ready;
        // The song's title and id, one row (never the version's notes).
        $template = DB::table('template_versions')->join('templates', 'templates.id', '=', 'template_versions.template_id')
            ->where('template_versions.id', $song->template_version_id)->first(['templates.title', 'templates.slug']);
        Mail::to((string) $user->email)->locale($user->preferredLocale())->send(new ChoirReadyMail(
            $user->id, (string) $user->name, (string) ($template->title ?? ''), (string) ($template->slug ?? ''),
            $ready ? null : (string) $song->failure, ! $ready && OwnChoir::countedAgainst($song) === false,
        ));
        app(RecordCounts::class)->serverAmount($ready ? 'choir_email_sent_ready' : 'choir_email_sent_failed', 1);
    }
}
