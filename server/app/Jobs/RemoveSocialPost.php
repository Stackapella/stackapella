<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SocialPost;
use App\Services\Social\SocialPoster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Takes a post of ours about a harmony down from its network, once its singer withdrew their yes,
 * deleted it, or deleted their account (docs/decisions.md). The row stays, cancelled, saying so.
 */
final class RemoveSocialPost implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly int $socialPostId) {}

    public function handle(SocialPoster $poster): void
    {
        $post = SocialPost::query()->find($this->socialPostId);

        if ($post === null || $post->status !== SocialPost::POSTED || $post->remote_id === null) {
            return;
        }

        if (config('services.social.live') === true) {
            $poster->client($post->network)->delete($post->remote_id);
        }

        $post->forceFill(['status' => SocialPost::CANCELLED, 'url' => null, 'error' => 'Taken down: its singer withdrew.'])->save();
    }
}
