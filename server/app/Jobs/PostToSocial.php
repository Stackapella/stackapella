<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SocialPost;
use App\Services\Social\SocialPoster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Sends one approved post once its undo window has passed (a port of iaw's). The row is claimed
 * first (queued, then posting), so a retry, a second worker or a rollback can never post it
 * twice; what it says is made and checked again here. Outside production nothing is sent.
 */
final class PostToSocial implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    /** A share video can take X a couple of minutes to process (XClient waits at most 150 s). */
    public int $timeout = 280;

    public function __construct(public readonly int $socialPostId)
    {
        // On the long worker, whose timeout a video upload fits under.
        $this->onQueue('long');
    }

    public function handle(SocialPoster $poster): void
    {
        $claimed = SocialPost::query()->whereKey($this->socialPostId)->where('status', SocialPost::QUEUED)
            ->where(fn ($query) => $query->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now()->addSecond()))
            ->update(['status' => SocialPost::POSTING, 'updated_at' => now()]);

        if ($claimed !== 1) {
            return;
        }

        $post = SocialPost::query()->with('postable')->findOrFail($this->socialPostId);
        $message = $poster->message($post);

        if ($message === null || config('services.social.live') !== true) {
            $post->forceFill(['status' => SocialPost::CANCELLED, 'error' => $message === null ? 'Nothing to say any more: it changed or was taken down.' : 'Not sent: posting is live only in production.'])->save();

            return;
        }

        try {
            $result = $poster->client($post->network)->post($message);
            $post->forceFill(['status' => SocialPost::POSTED, 'remote_id' => mb_substr($result['id'], 0, 250), 'url' => mb_substr($result['url'], 0, 250), 'posted_at' => now(), 'error' => null])->save();
        } catch (Throwable $e) {
            report($e);
            $post->forceFill(['status' => SocialPost::FAILED, 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
        }
    }

    /**
     * Killed mid-post (past its timeout): it shows as failed in admin, never as posting forever.
     */
    public function failed(?Throwable $e): void
    {
        SocialPost::query()->whereKey($this->socialPostId)->where('status', SocialPost::POSTING)
            ->update(['status' => SocialPost::FAILED, 'error' => mb_substr('Stopped before it finished: '.($e?->getMessage() ?? 'timed out'), 0, 1000), 'updated_at' => now()]);
    }
}
