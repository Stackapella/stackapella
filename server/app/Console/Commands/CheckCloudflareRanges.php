<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Cloudflare's published ranges against config/proxies.php. A difference means a visitor could be
 * seen as Cloudflare's address (and share its limit), so it's reported for a person to update the
 * list by hand; nothing changes on its own.
 */
final class CheckCloudflareRanges extends Command
{
    protected $signature = 'app:check-cloudflare-ranges';

    protected $description = 'Check config/proxies.php still matches Cloudflare\'s published IP ranges';

    public function handle(): int
    {
        if (config('selfhost.behind_cloudflare') !== true) {
            $this->info('Not behind Cloudflare (BEHIND_CLOUDFLARE): nothing to check.');

            return self::SUCCESS;
        }

        try {
            $published = [];
            foreach (['https://www.cloudflare.com/ips-v4', 'https://www.cloudflare.com/ips-v6'] as $url) {
                $published = [...$published, ...array_filter(array_map('trim', explode("\n", Http::timeout(10)->get($url)->throw()->body())))];
            }
        } catch (Throwable $e) {
            report($e);
            $this->error('Could not fetch Cloudflare\'s ranges.');

            return self::FAILURE;
        }

        /** @var list<string> $configured */
        $configured = (array) config('proxies.cloudflare');
        $missing = array_values(array_diff($published, $configured));
        $stale = array_values(array_diff($configured, $published));

        if ($missing === [] && $stale === []) {
            $this->info('config/proxies.php matches Cloudflare\'s ranges.');

            return self::SUCCESS;
        }

        report(new RuntimeException('Cloudflare\'s IP ranges changed. Add: '.implode(', ', $missing).'. Remove: '.implode(', ', $stale).'.'));
        $this->warn('Update config/proxies.php. Add: '.implode(', ', $missing).' Remove: '.implode(', ', $stale));

        return self::FAILURE;
    }
}
