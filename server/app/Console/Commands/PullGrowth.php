<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Growth\GrowthExport;
use App\Http\Controllers\Internal\GrowthDataController;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;

/**
 * The growth data pulled onto this machine for analysis (docs/growth-data.md; ported from Event
 * Schedule). Run on a DEV machine with the same GROWTH_DATA_TOKEN as the server in server/.env. It
 * calls GET /api/internal/growth, saves the JSON under storage/app/growth/ (gitignored, never
 * committed: the repository is public), and prints the headline numbers against the previous pull.
 * --local prints the latest pull's again without downloading.
 *
 * Never scheduled.
 */
final class PullGrowth extends Command
{
    protected $signature = 'app:pull-growth
                            {--range=30 : The window of the range-scoped sections: 7, 30, 90 or all}
                            {--url= : The install (default GROWTH_DATA_URL); the token goes only to that host or a local one}
                            {--dir= : Where pulls are kept (default storage/app/growth)}
                            {--local : Print the latest pull on disk again instead of downloading}';

    protected $description = 'Pull the anonymous growth numbers onto this machine and summarise them';

    public function handle(): int
    {
        $dir = rtrim((string) ($this->option('dir') ?: storage_path('app/growth')), '/');
        File::ensureDirectoryExists($dir);

        if ($this->option('local') === true) {
            $pulls = $this->pulls($dir);
            $latest = $pulls !== [] ? $this->read(array_pop($pulls)) : null;

            if ($latest === null) {
                $this->error("No pull in {$dir} yet. Run php artisan app:pull-growth first.");

                return self::FAILURE;
            }

            $this->summary($latest, $this->previous($pulls));

            return self::SUCCESS;
        }

        $token = (string) config('services.growth_data.token');

        if (strlen($token) < GrowthDataController::MIN_TOKEN_LENGTH) {
            $this->error('GROWTH_DATA_TOKEN is missing or shorter than '.GrowthDataController::MIN_TOKEN_LENGTH.' characters.');
            $this->line('docs/growth-data.md, "Setup": the same value goes in this machine\'s server/.env and on the server.');

            return self::FAILURE;
        }

        $range = (string) $this->option('range');

        if (! array_key_exists($range, GrowthExport::RANGES)) {
            $this->error('--range must be one of: '.implode(', ', array_keys(GrowthExport::RANGES)));

            return self::FAILURE;
        }

        $base = rtrim((string) ($this->option('url') ?: config('services.growth_data.url')), '/');

        if (($problem = self::urlProblem($base)) !== null) {
            $this->error("Refusing {$base}: {$problem}");

            return self::FAILURE;
        }

        $this->line("Pulling {$base}/api/internal/growth (range {$range})...");

        try {
            // Never follow a redirect: it's always a wrong URL, and the token would go along.
            $response = Http::withToken($token)->acceptJson()->withUserAgent('Stackapella-PullGrowth/1')->timeout(120)
                ->withOptions(['allow_redirects' => false])->get($base.'/api/internal/growth', ['range' => $range]);
        } catch (ConnectionException) {
            $this->error("Could not connect to {$base}.");

            return self::FAILURE;
        }

        if (($problem = self::failure($response)) !== null) {
            $this->error($problem);

            return self::FAILURE;
        }

        $data = $response->json();

        if (! is_array($data) || ! isset($data['meta']['schema_version'])) {
            $this->error('The answer was not the growth data (no meta.schema_version). Nothing was saved.');

            return self::FAILURE;
        }

        $previous = $this->previous($this->pulls($dir));
        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        $file = $dir.'/growth-'.now()->utc()->format('Y-m-d-His').'.json';
        File::put($file, $json);
        File::put($dir.'/latest.json.tmp', $json);
        File::move($dir.'/latest.json.tmp', $dir.'/latest.json');
        $this->line(sprintf('Saved %s (%s KB) and %s/latest.json', $file, number_format(strlen($json) / 1024, 1), $dir));
        $this->summary($data, $previous);

        return self::SUCCESS;
    }

    /**
     * Why the token must not go to this URL, or null when it may: only GROWTH_DATA_URL's own host
     * or a local install, and https unless local. Another host takes an edit to .env, a person's
     * decision, never an argument an agent could be talked into.
     */
    public static function urlProblem(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $local = in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with($host, '.test');
        $configured = strtolower((string) parse_url((string) config('services.growth_data.url'), PHP_URL_HOST));

        return match (true) {
            $host === '' => 'not a URL.',
            ! $local && $host !== $configured => "the token goes only to GROWTH_DATA_URL's host ({$configured}) or a local install; another host takes an edit to server/.env.",
            $scheme !== 'https' && ! ($scheme === 'http' && $local) => 'the token would travel in the clear: use https (plain http only for localhost or *.test).',
            default => null,
        };
    }

    /**
     * The failure named from the status and headers alone, never the body.
     */
    public static function failure(Response $response): ?string
    {
        $status = $response->status();
        $type = (string) $response->header('Content-Type');

        return match (true) {
            $response->redirect() => "Redirected ({$status}) to ".($response->header('Location') ?: 'somewhere else').': set --url or GROWTH_DATA_URL to the final address.',
            $response->header('cf-mitigated') !== '' || ($status === 403 && str_contains($type, 'text/html')) => 'Stopped by a Cloudflare challenge: check Security, Events, or add a skip rule for /api/internal/growth.',
            $status === 401 => 'Token mismatch: GROWTH_DATA_TOKEN here differs from the server\'s (or a proxy dropped the Authorization header).',
            $status === 404 => 'Not found: the server has no GROWTH_DATA_TOKEN (or one under '.GrowthDataController::MIN_TOKEN_LENGTH.' characters), or the endpoint is not deployed yet.',
            $status === 429 => 'Rate limited, or a pull is already running: try again in '.($response->header('Retry-After') ?: '60').' seconds.',
            $response->successful() && ! str_contains($type, 'json') => "Unexpected {$type} answer, not JSON.",
            ! $response->successful() => "The server answered {$status}.",
            default => null,
        };
    }

    /**
     * About fifteen headline numbers against the previous pull.
     *
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>|null  $previous
     */
    private function summary(array $current, ?array $previous): void
    {
        $meta = (array) $current['meta'];
        $this->newLine();
        $this->line(sprintf('Schema %s, generated %s, range %s', $meta['schema_version'], $meta['generated_at'] ?? '?', $meta['range']['name'] ?? '?'));

        if ($previous !== null) {
            $this->line('Against the pull generated '.($previous['meta']['generated_at'] ?? '?')
                .(($previous['meta']['schema_version'] ?? null) !== $meta['schema_version'] ? ' (another schema: read docs/growth-data.md before comparing)' : ''));
        }

        if (($deploy = $this->deployLine($meta)) !== null) {
            $this->line($deploy);
        }

        $now = self::headlines($current);
        $before = $previous !== null ? self::headlines($previous) : [];
        $rows = [];

        foreach ($now as $label => $value) {
            $was = $before[$label] ?? null;
            $rows[] = [$label, self::show($value), $was === null ? '' : self::show($was), $was === null || $value === null ? '' : self::change($value, $was)];
        }

        $this->table(['Headline', 'Now', 'Previous pull', 'Change'], $rows);
        $this->line(count((array) ($meta['notes'] ?? [])).' notes travel with this data (meta.notes): read them before drawing conclusions. Fields: docs/growth-data.md');
    }

    /**
     * @param  array<string, mixed>  $pull
     * @return array<string, int|float|null>
     */
    public static function headlines(array $pull): array
    {
        $daily = (array) ($pull['daily'] ?? []);
        // The last 7 whole days (the newest day is partial).
        $week = static function (array $kinds) use ($daily): int {
            $total = 0;

            foreach ($kinds as $kind) {
                foreach (['guest', 'account'] as $who) {
                    $series = (array) ($daily['kinds'][$kind][$who] ?? []);
                    $total += (int) array_sum(array_slice($series, -8, 7));
                }
            }

            return $total;
        };
        $signups = (array) ($pull['signups']['days']['accounts'] ?? []);
        $mrr = (array) ($pull['plus']['mrr'][0] ?? []);
        $subscribers = array_sum(array_map(static fn (mixed $s): int => (int) (is_array($s) ? ($s['count'] ?? 0) : 0), (array) ($pull['plus']['subscribers'] ?? [])));
        $tools = array_sum(array_map(static fn (mixed $s): int => is_array($s) && ($s['type'] ?? '') === 'tool' ? (int) ($s['arrivals'] ?? 0) : 0, (array) ($pull['sources'] ?? [])));
        $askShown = $week(['ask_acct_course_shown']);
        $sent = (array) ($pull['outreach']['firstEmails']['sent'] ?? []);

        return [
            'Site visits, last 7 days' => $week(['site_visit']),
            'Arrivals in the app, last 7 days' => $week(['f_arrive', 'f_arrive_start']),
            'First lesson done, last 7 days' => $week(['f_lesson1']),
            'Accounts made on devices, last 7 days' => $week(['f_account']),
            'Accounts created, last 7 days' => (int) array_sum(array_slice($signups, -8, 7)),
            'Accounts in all' => isset($pull['signups']['total']) ? (int) $pull['signups']['total'] : null,
            'Device-days in the app, last 7 days' => $week(['app_day']),
            'Lessons started, last 7 days' => $week(['lesson_start']),
            'Lessons finished, last 7 days' => $week(['lesson_done']),
            'Takes recorded, last 7 days' => $week(['take_recorded']),
            'Songs made, last 7 days' => $week(['song_created']),
            'Course account ask taken, last 7 days (%)' => $askShown > 0 ? round(100 * $week(['ask_acct_course_yes']) / $askShown, 1) : null,
            'Arrivals from the free tools, range' => $tools,
            'Plus subscribers' => $subscribers,
            'MRR ('.strtoupper((string) ($mrr['currency'] ?? '-')).', cents)' => isset($mrr['cents']) ? (int) $mrr['cents'] : null,
            'Outreach first emails, last whole week' => isset($sent[count($sent) - 2]) ? (int) $sent[count($sent) - 2] : null,
        ];
    }

    private static function show(int|float|null $value): string
    {
        return $value === null ? '-' : number_format($value, is_float($value) ? 1 : 0);
    }

    private static function change(int|float $now, int|float $was): string
    {
        $delta = $now - $was;

        return ($delta > 0 ? '+' : '').number_format($delta, is_float($delta) ? 1 : 0).($was != 0 ? sprintf(' (%+d%%)', (int) round(100 * $delta / $was)) : '');
    }

    /**
     * Which commit production runs, and how far this checkout's HEAD is past it. The hash comes from
     * the server, so it's checked to be one before git sees it, and git runs without a shell.
     *
     * @param  array<string, mixed>  $meta
     */
    private function deployLine(array $meta): ?string
    {
        $commit = $meta['app_commit'] ?? null;

        if (! is_string($commit) || preg_match('/^[0-9a-f]{7,40}$/', $commit) !== 1) {
            return null;
        }

        $process = new Process(['git', 'rev-list', '--count', $commit.'..HEAD'], base_path());
        $process->run();
        $ahead = $process->isSuccessful() ? trim($process->getOutput()) : null;

        return 'Production runs '.substr($commit, 0, 12).': '.($ahead === null ? 'that commit is not in this checkout (git fetch?)' : "{$ahead} commits on this checkout's HEAD are not deployed").'.';
    }

    /**
     * @param  list<string>  $earlier
     * @return array<string, mixed>|null
     */
    private function previous(array $earlier): ?array
    {
        foreach (array_reverse($earlier) as $path) {
            if (($pull = $this->read($path)) !== null) {
                return $pull;
            }
        }

        return null;
    }

    /**
     * Timestamped pulls, oldest first (their names sort by time).
     *
     * @return list<string>
     */
    private function pulls(string $dir): array
    {
        $files = glob($dir.'/growth-*.json') ?: [];
        sort($files);

        return $files;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(string $path): ?array
    {
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) && isset($data['meta']['schema_version']) ? $data : null;
    }
}
