<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Template;
use App\Support\Rights\SongRights;
use App\Support\Templates\Catalogue;
use Illuminate\Console\Command;

/**
 * How many open songs geo_rights would withhold in each country (docs/geo-rights-plan.md), by the songs' makers and first
 * printings and each country's term: the countries with any first, most first, then the songs for one country. Works
 * whether or not the switch is on; it reads, never changes anything.
 */
final class WithheldByCountry extends Command
{
    protected $signature = 'app:rights:withheld {country? : One country\'s withheld songs, like MX} {--all : Every country, those with none too}';

    protected $description = 'Count the open songs withheld in each country by its copyright term';

    public function handle(Catalogue $catalogue): int
    {
        $songs = $catalogue->open()->map(static fn (Template $t): array => ['slug' => (string) $t->slug, 'data' => (array) ($t->currentVersion->data ?? [])]);
        $one = $this->argument('country');

        if (is_string($one)) {
            $code = strtoupper($one);
            foreach ($songs as $song) {
                $status = SongRights::status($song['data'], $code);
                if (! $status['free']) {
                    $this->line($song['slug'].'  '.($status['until'] !== null ? 'free from '.$status['until'] : 'not known yet').' ('.$status['reason'].')');
                }
            }

            return self::SUCCESS;
        }

        $counts = [];
        foreach (array_keys((array) config('copyright_terms.countries')) as $code) {
            $counts[(string) $code] = $songs->filter(static fn (array $s): bool => ! SongRights::status($s['data'], (string) $code)['free'])->count();
        }
        $counts['(unknown)'] = $songs->filter(static fn (array $s): bool => ! SongRights::status($s['data'], '')['free'])->count();
        arsort($counts);
        $this->info($songs->count().' open songs.');
        foreach ($counts as $code => $n) {
            if ($n > 0 || $this->option('all')) {
                $this->line(str_pad($code, 10).$n);
            }
        }

        return self::SUCCESS;
    }
}
