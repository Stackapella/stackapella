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
    protected $signature = 'app:rights:withheld {country? : One country\'s withheld songs, like MX} {--all : Every country, those with none too} {--markdown= : Write the full dry run there (docs/geo-rights-dry-run.md) for the switch\'s decision}';

    protected $description = 'Count the open songs withheld in each country by its copyright term';

    public function handle(Catalogue $catalogue): int
    {
        $songs = $catalogue->open()->map(static fn (Template $t): array => ['slug' => (string) $t->slug, 'data' => (array) ($t->currentVersion->data ?? [])]);
        $one = $this->argument('country');

        if (is_string($this->option('markdown')) && $this->option('markdown') !== '') {
            $path = $this->option('markdown');
            file_put_contents($path, self::markdown(array_values($songs->all())));
            $this->info("Written: {$path}");

            return self::SUCCESS;
        }

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

    /**
     * The dry run as a dated page: what the switch would withhold now, country by country (countries with the same songs
     * held together), each song's year where it's known, the songs held everywhere (none should be), and the three
     * countries most visitors come from read out in full.
     *
     * @param  list<array{slug: string, data: array<string, mixed>}>  $songs
     */
    public static function markdown(array $songs): string
    {
        $rows = (array) config('copyright_terms.countries');
        $codes = [...array_map('strval', array_keys($rows)), ''];
        $name = static fn (string $code): string => $code === '' ? 'a visitor whose country isn\'t known' : (string) ($rows[$code]['name'] ?? $code);
        $held = [];
        foreach ($codes as $code) {
            foreach ($songs as $song) {
                $status = SongRights::status($song['data'], $code);
                if (! $status['free']) {
                    $held[$code][$song['slug']] = ($status['until'] !== null ? 'free from '.$status['until'] : 'not known yet').' ('.$status['reason'].')';
                }
            }
        }
        $everywhere = array_values(array_filter(array_column($songs, 'slug'), static function (string $slug) use ($held, $codes): bool {
            foreach ($codes as $code) {
                if ($code !== '' && ! isset($held[$code][$slug])) {
                    return false;
                }
            }

            return true;
        }));

        $lines = ['# geo_rights dry run', '', 'Generated '.now()->toDateString().' by `php artisan app:rights:withheld --markdown=docs/geo-rights-dry-run.md`',
            'from the songs open in this checkout. Nothing switches: this is what turning `geo_rights` on would withhold today.',
            'Run it again before the switch; each song\'s year is when it becomes free there.', '',
            '## Summary', '',
            '- Open songs: '.count($songs).'.',
            '- Countries (and places) with a row: '.(count($codes) - 1).'; '.count(array_filter($codes, static fn (string $c): bool => $c !== '' && ($held[$c] ?? []) !== [])).' withhold at least one song.',
            '- Withheld everywhere (every row at once): '.($everywhere === [] ? 'none.' : count($everywhere).': '.implode(', ', $everywhere).'.'),
            '- A visitor whose country isn\'t known takes the strictest row: '.count($held[''] ?? []).' songs withheld.', ''];

        foreach (['US', 'MX', 'GB', ''] as $code) {
            $lines[] = '## '.($code === '' ? 'Unknown country' : $code.', '.$name($code));
            $lines[] = '';
            $lines[] = ($held[$code] ?? []) === [] ? 'Nothing withheld.' : count($held[$code]).' withheld:';
            foreach ($held[$code] ?? [] as $slug => $why) {
                $lines[] = "- {$slug}: {$why}";
            }
            $lines[] = '';
        }

        // Countries holding exactly the same songs, together (the low-confidence rows all take the strictest rule).
        $groups = [];
        foreach ($codes as $code) {
            if ($code === '' || ($held[$code] ?? []) === []) {
                continue;
            }
            $groups[json_encode($held[$code])][] = $code;
        }
        uasort($groups, static fn (array $a, array $b): int => count($b) <=> count($a));
        $lines[] = '## Every country that withholds a song';
        $lines[] = '';
        $lines[] = 'Countries that withhold exactly the same songs are listed together.';
        $lines[] = '';
        foreach ($groups as $set => $group) {
            $songsHeld = (array) json_decode((string) $set, true);
            $lines[] = '### '.implode(', ', array_map(static fn (string $c): string => $c.' ('.$name($c).')', $group)).': '.count($songsHeld).(count($songsHeld) === 1 ? ' song' : ' songs');
            $lines[] = '';
            foreach ($songsHeld as $slug => $why) {
                $lines[] = "- {$slug}: {$why}";
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }
}
