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
            ->where(fn ($query) => $query->whereNull('emailed_at')->orWhere('emailed_at', '<=', now()->subDay()))
            ->get();

        foreach ($ideas as $idea) {
            $voices = IdeaVoice::query()->with('user')->where('idea_id', $idea->id)->where('status', IdeaVoice::READY)->whereNull('hidden_at')->whereNull('notified_at')->orderBy('id')->get();
            $writer = $idea->user;
            // Read afresh with the voices: "complete" goes once, in the first email after it is.
            $idea->refresh();
            $completed = $idea->complete_told_at !== null && $idea->complete_emailed_at === null;
            IdeaVoice::query()->whereKey($voices->pluck('id'))->update(['notified_at' => now()]);

            // Their own voices on their own tune aren't news to them.
            $news = $voices->reject(fn (IdeaVoice $voice): bool => $voice->user_id === $idea->user_id);

            if ($news->isEmpty() && ! $completed) {
                continue;
            }

            $idea->forceFill(['emailed_at' => now(), 'complete_emailed_at' => $completed ? now() : $idea->complete_emailed_at])->save();

            if (! $writer->idea_emails || $writer->email === null || EmailSuppression::isSuppressed($writer->email) || $writer->isSuspended()) {
                continue;
            }

            $parts = self::partNames($idea);
            $instruments = self::instrumentParts($idea);
            Mail::to($writer->email)->queue(new IdeaVoicesMail(
                $writer->name,
                $idea->template->title,
                array_values($news->map(fn (IdeaVoice $voice): array => ['name' => GalleryViews::person($voice->user)['name'], 'part' => mb_strtolower($parts[$voice->part_key] ?? __('ideas.mail.a_part')), 'played' => in_array($voice->part_key, $instruments, true)])->all()),
                $completed,
                Hosts::app('/ideas/'.$idea->public_id),
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
