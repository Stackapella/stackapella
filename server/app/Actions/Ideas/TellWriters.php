<?php

declare(strict_types=1);

namespace App\Actions\Ideas;

use App\Mail\Ideas\IdeaVoicesMail;
use App\Models\EmailSuppression;
use App\Models\Idea;
use App\Models\IdeaVoice;
use App\Models\User;
use App\Support\Gallery\GalleryViews;
use App\Support\Http\Hosts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * The writers' email (docs/your-tune-plan.md, step 3), checked hourly: an idea with new voices
 * gets one email with all of them, and that it's complete if it became so, at most once a day.
 * A writer who turned them off (or whose address is suppressed) gets none, and their voices
 * count as told.
 */
final class TellWriters
{
    public function handle(): int
    {
        $sent = 0;
        $ideas = Idea::query()->with(['user', 'template.currentVersion'])
            ->where(fn ($news) => $news->whereHas('voices', fn ($voices) => $voices->where('status', IdeaVoice::READY)->whereNull('hidden_at')->whereNull('notified_at'))
                ->orWhere(fn ($done) => $done->whereNotNull('complete_told_at')->whereNull('complete_emailed_at')))
            // Once a day (five minutes' slack, so an hourly run doesn't slip an hour each day).
            ->where(fn ($query) => $query->whereNull('emailed_at')->orWhere('emailed_at', '<=', now()->subDay()->addMinutes(5)))
            ->get();

        foreach ($ideas as $idea) {
            $writer = $idea->user;
            // Claimed under the idea's lock: a second run (a slow one outliving its lock) finds the
            // voices told and the email sent, and sends nothing again.
            $claimed = DB::transaction(function () use ($idea): ?array {
                $locked = Idea::query()->whereKey($idea->id)->lockForUpdate()->first();

                if ($locked === null || ($locked->emailed_at !== null && $locked->emailed_at->gt(now()->subDay()->addMinutes(5)))) {
                    return null;
                }

                $voices = IdeaVoice::query()->with('user')->where('idea_id', $idea->id)->where('status', IdeaVoice::READY)->whereNull('hidden_at')->whereNull('notified_at')->orderBy('id')->get();
                // "complete" goes once, in the first email after it is.
                $completed = $locked->complete_told_at !== null && $locked->complete_emailed_at === null;
                IdeaVoice::query()->whereKey($voices->pluck('id'))->update(['notified_at' => now()]);
                // Their own voices on their own tune aren't news to them.
                $news = $voices->reject(fn (IdeaVoice $voice): bool => $voice->user_id === $locked->user_id);

                if ($news->isEmpty() && ! $completed) {
                    return null;
                }

                $locked->forceFill(['emailed_at' => now(), 'complete_emailed_at' => $completed ? now() : $locked->complete_emailed_at])->save();

                return [$news, $completed];
            });

            if ($claimed === null) {
                continue;
            }

            [$news, $completed] = $claimed;

            if (! $writer->idea_emails || $writer->email === null || EmailSuppression::isSuppressed($writer->email) || $writer->isSuspended()) {
                continue;
            }

            $parts = self::partNames($idea);
            $instruments = self::instrumentParts($idea);
            Mail::to($writer->email)->locale($writer->preferredLocale())->queue(new IdeaVoicesMail(
                $writer->name,
                $idea->template->title,
                array_values($news->map(fn (IdeaVoice $voice): array => ['name' => GalleryViews::person($voice->user)['name'], 'part' => mb_strtolower($parts[$voice->part_key] ?? __('ideas.mail.a_part')), 'played' => in_array($voice->part_key, $instruments, true)])->all()),
                $completed,
                Hosts::public('/ideas/'.$idea->public_id),
                self::unsubscribeUrl($writer),
            ));
            $sent++;
        }

        return $sent;
    }

    public static function unsubscribeUrl(User $user): string
    {
        return Hosts::public(URL::signedRoute('ideas.unsubscribe', ['user' => $user->sqid()], absolute: false));
    }

    /**
     * @return array<string, string>
     */
    private static function partNames(Idea $idea): array
    {
        $names = [];

        foreach ((array) ($idea->template->currentVersion->data['parts'] ?? []) as $part) {
            if (is_array($part) && is_string($part['key'] ?? null)) {
                $names[$part['key']] = (string) ($part['name'] ?? $part['key']);
            }
        }

        return $names;
    }

    /**
     * The tune's instrument parts (docs/instrument-parts-plan.md): their voices are "played".
     *
     * @return list<string>
     */
    private static function instrumentParts(Idea $idea): array
    {
        $keys = [];

        foreach ((array) ($idea->template->currentVersion->data['parts'] ?? []) as $part) {
            if (is_array($part) && is_string($part['key'] ?? null) && ($part['kind'] ?? 'voice') === 'instrument') {
                $keys[] = $part['key'];
            }
        }

        return $keys;
    }
}
