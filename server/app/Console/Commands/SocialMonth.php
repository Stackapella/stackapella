<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SocialPost;
use App\Models\Template;
use App\Services\Social\SocialPoster;
use Illuminate\Console\Command;

/**
 * Each morning (docs/backend-plan.md D7): a song of the month whose free month starts today is
 * proposed to Hillel as a post on our accounts.
 */
final class SocialMonth extends Command
{
    protected $signature = 'app:social:month';

    protected $description = 'Propose posts about a song of the month that starts today';

    public function handle(SocialPoster $poster): int
    {
        $made = 0;

        foreach (Template::query()->listed()->whereDate('free_from', now()->toDateString())->get() as $template) {
            $made += $poster->propose($template, SocialPost::MONTH.':'.now()->format('Y-m'));
        }

        $this->info("Proposed {$made} posts.");

        return self::SUCCESS;
    }
}
