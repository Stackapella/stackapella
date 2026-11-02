<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SocialPost;
use App\Models\Template;
use App\Services\Social\SocialPoster;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

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

        // A song of the month that started in the last week (a missed day is caught), keyed by its
        // own month, so proposing it again changes nothing.
        foreach (Template::query()->listed()->whereDate('free_from', '>=', now()->subDays(7)->toDateString())->whereDate('free_from', '<=', now()->toDateString())->get() as $template) {
            $made += $poster->propose($template, SocialPost::MONTH.':'.Carbon::parse((string) $template->free_from)->format('Y-m'));
        }

        $this->info("Proposed {$made} posts.");

        return self::SUCCESS;
    }
}
