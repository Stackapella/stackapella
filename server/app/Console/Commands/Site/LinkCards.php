<?php

declare(strict_types=1);

namespace App\Console\Commands\Site;

use App\Support\Site\SitePages;
use Illuminate\Console\Command;

/**
 * The public pages' link cards to draw (tool/link_cards.py reads this): each page's key, eyebrow
 * and headline, as JSON.
 */
final class LinkCards extends Command
{
    protected $signature = 'app:site:link-cards';

    protected $description = 'List the public pages that get their own link card, as JSON';

    public function handle(): int
    {
        $this->line((string) json_encode(SitePages::linkCards(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
