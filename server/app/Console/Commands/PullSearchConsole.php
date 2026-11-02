<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Google\SearchConsoleClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\File;

/**
 * Search Console's numbers pulled onto this machine for analysis (docs/search-console.md, "For
 * agents"; ported from Event Schedule). Run on a DEV machine with the service account in this
 * machine's server/.env (SEARCH_CONSOLE_CREDENTIALS). Each pull is saved as JSON under
 * storage/app/growth/search-console/ (gitignored, never committed: the repository is public), and it
 * prints the last --days days against the same number before, by page and by query, with the
 * sitemaps. --inspect asks Google's URL Inspection about pages: indexed or not, the last crawl, and
 * the canonical Google chose. The window ends 3 days ago (Search Analytics lags).
 *
 * Never scheduled: production has app:search-console:sync and Admin > Growth > Search.
 *
 * @phpstan-type Row array{keys: list<string>, clicks: int, impressions: int, ctr: float, position: float}
 * @phpstan-type Totals array{clicks: int, impressions: int, ctr: float, position: float}
 * @phpstan-type Inspection array{verdict: string|null, coverage: string|null, indexing: string|null, fetch: string|null, lastCrawl: string|null, googleCanonical: string|null, userCanonical: string|null}
 * @phpstan-type Pull array{property: string, pulledAt: string, window: list<string>, previousWindow: list<string>, totals: Totals, previousTotals: Totals, pages: list<Row>, previousPages: list<Row>, queries: list<Row>, previousQueries: list<Row>, sitemaps: list<array{path: string, errors: int, warnings: int, lastDownloaded: string|null}>, inspections: array<string, Inspection>}
 */
final class PullSearchConsole extends Command
{
    protected $signature = 'app:pull-search-console
                            {--days=28 : The window, compared with the same number of days before it}
                            {--inspect=* : A page to look up in Google\'s index (a full URL or a path), repeatable}
                            {--top=15 : How many rows each table shows}
                            {--dir= : Where pulls are kept (default storage/app/growth/search-console)}';

    protected $description = 'Pull Search Console clicks, impressions and index status onto this machine for analysis';

    public function handle(SearchConsoleClient $console): int
    {
        if (! $console->configured()) {
            $this->error('SEARCH_CONSOLE_CREDENTIALS is not set, or is not a service account key.');
            $this->line('Set it in this machine\'s server/.env (the JSON key, raw or base64); docs/search-console.md has the steps.');

            return self::FAILURE;
        }

        $days = max(1, min(240, (int) $this->option('days')));
        $top = max(1, (int) $this->option('top'));
        $to = CarbonImmutable::now('UTC')->startOfDay()->subDays(3);
        $from = $to->subDays($days - 1);
        $prevTo = $from->subDay();
        $prevFrom = $prevTo->subDays($days - 1);
        [$f, $t, $pf, $pt] = [$from->toDateString(), $to->toDateString(), $prevFrom->toDateString(), $prevTo->toDateString()];

        $this->line("Search Console {$console->property()}: {$f} to {$t}, against the {$days} days before.");

        try {
            $pull = [
                'property' => $console->property(),
                'pulledAt' => now()->utc()->toIso8601ZuluString(),
                'window' => [$f, $t],
                'previousWindow' => [$pf, $pt],
                'totals' => self::totals($console->query([], $f, $t, 1)),
                'previousTotals' => self::totals($console->query([], $pf, $pt, 1)),
                'pages' => $console->query(['page'], $f, $t, 5000),
                'previousPages' => $console->query(['page'], $pf, $pt, 5000),
                'queries' => $console->query(['query'], $f, $t, 1000),
                'previousQueries' => $console->query(['query'], $pf, $pt, 1000),
                'sitemaps' => $console->sitemaps(),
                'inspections' => [],
            ];

            foreach ((array) $this->option('inspect') as $url) {
                $url = $this->absolute((string) $url);
                $pull['inspections'][$url] = $console->inspect($url);
            }
        } catch (RequestException $e) {
            $message = $e->response->json('error.message');
            $this->error('Google answered '.$e->response->status().': '.(is_string($message) ? $message : 'no message'));

            if ($e->response->status() === 403) {
                $this->line('The service account is probably not a user on '.$console->property().' yet (Search Console: Settings, Users and permissions).');
            }

            return self::FAILURE;
        }

        $dir = rtrim((string) ($this->option('dir') ?: storage_path('app/growth/search-console')), '/');
        File::ensureDirectoryExists($dir);
        $json = (string) json_encode($pull, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $file = $dir.'/sc-'.now()->utc()->format('Y-m-d-His').'.json';
        File::put($file, $json);
        File::put($dir.'/latest.json', $json);
        $this->line("Saved {$file}");

        $this->summary($pull, $top);

        return self::SUCCESS;
    }

    /**
     * @param  list<Row>  $rows
     * @return Totals
     */
    private static function totals(array $rows): array
    {
        $row = $rows[0] ?? ['clicks' => 0, 'impressions' => 0, 'ctr' => 0.0, 'position' => 0.0];

        return ['clicks' => $row['clicks'], 'impressions' => $row['impressions'], 'ctr' => $row['ctr'], 'position' => $row['position']];
    }

    private function absolute(string $url): string
    {
        return preg_match('#^https?://#i', $url) === 1 ? $url : 'https://'.$this->host().'/'.ltrim($url, '/');
    }

    private function host(): string
    {
        $property = (string) config('services.search_console.property');

        return str_starts_with($property, 'sc-domain:') ? substr($property, 10) : (string) parse_url($property, PHP_URL_HOST);
    }

    /** A page on our own host reads as its path. */
    private function path(string $url): string
    {
        foreach (['https://'.$this->host(), 'https://www.'.$this->host()] as $prefix) {
            if ($url === $prefix || str_starts_with($url, $prefix.'/')) {
                return substr($url, strlen($prefix)) ?: '/';
            }
        }

        return $url;
    }

    /**
     * @param  Pull  $pull
     */
    private function summary(array $pull, int $top): void
    {
        $now = $pull['totals'];
        $was = $pull['previousTotals'];
        $change = static fn (int $a, int $b): string => $b > 0 ? sprintf('%+.0f%%', ($a - $b) / $b * 100) : '-';

        $this->table(['', 'Now', 'Before', 'Change'], [
            ['Clicks', number_format($now['clicks']), number_format($was['clicks']), $change($now['clicks'], $was['clicks'])],
            ['Impressions', number_format($now['impressions']), number_format($was['impressions']), $change($now['impressions'], $was['impressions'])],
            ['CTR', sprintf('%.2f%%', $now['ctr'] * 100), sprintf('%.2f%%', $was['ctr'] * 100), ''],
            // Lower is better, so its change is in places, not percent.
            ['Average position', sprintf('%.1f', $now['position']), sprintf('%.1f', $was['position']), $was['position'] > 0 ? sprintf('%+.1f', $now['position'] - $was['position']) : '-'],
        ]);
        $pages = $pull['pages'];
        $previous = $pull['previousPages'];
        $before = [];

        foreach ($previous as $row) {
            $before[$row['keys'][0] ?? ''] = $row['impressions'];
        }

        usort($pages, static fn (array $a, array $b): int => [$b['clicks'], $b['impressions']] <=> [$a['clicks'], $a['impressions']]);
        $this->line('Top pages by clicks');
        $this->table(['Page', 'Clicks', 'Impressions', 'Position', 'Impressions before'], array_map(fn (array $r): array => [
            $this->path($r['keys'][0] ?? ''), $r['clicks'], $r['impressions'], sprintf('%.1f', $r['position']), $before[$r['keys'][0] ?? ''] ?? 0,
        ], array_slice($pages, 0, $top)));

        // Movers by impressions, which move before clicks do and show an indexing drop first.
        $movers = [];

        foreach ($pages as $r) {
            $page = $r['keys'][0] ?? '';
            $movers[$page] = [$page, $r['impressions'], $before[$page] ?? 0];
        }

        foreach ($before as $page => $impressions) {
            $movers[$page] ??= [(string) $page, 0, $impressions];
        }

        $movers = array_values($movers);
        usort($movers, static fn (array $a, array $b): int => ($a[1] - $a[2]) <=> ($b[1] - $b[2]));
        $row = fn (array $m): array => [$this->path($m[0]), $m[2], $m[1], sprintf('%+d', $m[1] - $m[2])];
        $this->line('Biggest impression losses');
        $this->table(['Page', 'Before', 'Now', 'Change'], array_map($row, array_slice(array_values(array_filter($movers, static fn (array $m): bool => $m[1] < $m[2])), 0, $top)));
        $this->line('Biggest impression gains');
        $this->table(['Page', 'Before', 'Now', 'Change'], array_map($row, array_slice(array_reverse(array_values(array_filter($movers, static fn (array $m): bool => $m[1] > $m[2]))), 0, $top)));
        $queries = $pull['queries'];
        usort($queries, static fn (array $a, array $b): int => [$b['clicks'], $b['impressions']] <=> [$a['clicks'], $a['impressions']]);
        $this->line('Top queries');
        $this->table(['Query', 'Clicks', 'Impressions', 'CTR', 'Position'], array_map(static fn (array $r): array => [
            $r['keys'][0] ?? '', $r['clicks'], $r['impressions'], sprintf('%.1f%%', $r['ctr'] * 100), sprintf('%.1f', $r['position']),
        ], array_slice($queries, 0, $top)));
        $sitemaps = $pull['sitemaps'];

        if ($sitemaps !== []) {
            $this->line('Sitemaps');
            $this->table(['Sitemap', 'Errors', 'Warnings', 'Last read'], array_map(static fn (array $s): array => [$s['path'], $s['errors'], $s['warnings'], $s['lastDownloaded'] ?? '-'], $sitemaps));
        }
        $inspections = $pull['inspections'];

        foreach ($inspections as $url => $i) {
            $this->line("Index status of {$url}");
            $this->table(['', ''], [
                ['Verdict', $i['verdict'] ?? '-'], ['Coverage', $i['coverage'] ?? '-'], ['Last crawl', $i['lastCrawl'] ?? '-'],
                ['Fetch', $i['fetch'] ?? '-'], ['Google\'s canonical', $i['googleCanonical'] ?? '-'], ['Declared canonical', $i['userCanonical'] ?? '-'],
            ]);
        }
    }
}
