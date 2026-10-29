<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\PostToSocial;
use App\Models\SocialPost;
use App\Services\Social\SocialPoster;
use Illuminate\Console\Command;

/**
 * Every minute: approved posts whose undo window has passed are sent (each job also claims its
 * row, so one sent by its own delayed job isn't sent again).
 */
final class SocialSend extends Command
{
    protected $signature = 'app:social:send';

    protected $description = 'Send approved posts on our accounts once their undo window has passed';

    public function handle(SocialPoster $poster): int
    {
        // Waiting posts that can't go any more are cancelled as they turn, not only when looked at.
        $poster->tidy();
        $due = SocialPost::query()->where('status', SocialPost::QUEUED)->where('scheduled_for', '<=', now())->pluck('id');

        foreach ($due as $id) {
            PostToSocial::dispatch((int) $id);
        }

        $this->info("Sent {$due->count()} posts.");

        return self::SUCCESS;
    }
}
