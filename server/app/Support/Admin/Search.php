<?php

declare(strict_types=1);

namespace App\Support\Admin;

use App\Models\Setting;
use App\Services\Google\SearchConsoleClient;
use App\Support\Ops\Readiness;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use stdClass;
use Throwable;

/**
 * Admin > Growth > Search (docs/search-console.md): Search Console's last 28 days by page and by
 * query, synced daily into `search_console_rows`, the sitemaps' status, and the pages to improve,
 * which marketing works through (titles, descriptions, content).
 */
final class Search
{
    /** The setting the last sync's range, sitemaps and any error are kept in. */
    public const SETTING = 'search_console';

    /** Days of data, ending this many days ago (Search Console lags about 3 days). */
    public const DAYS = 28;

    public const LAG = 3;

    /** A page to improve: at least this many impressions, and a CTR under this or a position in this range. */
    public const MIN_IMPRESSIONS = 50;

    public const LOW_CTR = 0.02;

    public const NEAR_TOP = [8.0, 20.0];

    public function __construct(private readonly SearchConsoleClient $client) {}

    /**
     * Replaces the kept rows with the last 28 days. A failure keeps the last good rows and records
     * the error for the doctor and the page.
     */
    public function sync(): ?string
    {
        $to = now()->utc()->subDays(self::LAG)->toDateString();
        $from = now()->utc()->subDays(self::LAG + self::DAYS - 1)->toDateString();

        try {
            $pages = $this->client->rows('page', $from, $to);
            $queries = $this->client->rows('query', $from, $to);
            $sitemaps = $this->client->sitemaps();
        } catch (Throwable $e) {
            $error = Str::limit($e->getMessage(), 300);
            Readiness::stamp(self::SETTING, [...$this->state(), 'failedAt' => now()->utc()->toIso8601ZuluString(), 'error' => $error]);

            return $error;
        }

        DB::transaction(function () use ($pages, $queries): void {
            DB::table('search_console_rows')->delete();

            foreach (['page' => $pages, 'query' => $queries] as $dimension => $rows) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('search_console_rows')->insert(array_map(static fn (array $r): array => [
                        'dimension' => $dimension, 'key' => mb_substr($r['key'], 0, 500), 'clicks' => $r['clicks'], 'impressions' => $r['impressions'],
                        'ctr' => round($r['ctr'], 4), 'position' => round($r['position'], 2),
                    ], $chunk));
                }
            }
        });

        Readiness::stamp(self::SETTING, ['at' => now()->utc()->toIso8601ZuluString(), 'from' => $from, 'to' => $to, 'sitemaps' => $sitemaps, 'failedAt' => null, 'error' => null]);

        return null;
    }

    /**
     * admin.search.show.
     *
     * @return array<string, mixed>
     */
    public function show(): array
    {
        $state = $this->state();
        $rows = fn (string $dimension, string $order, int $limit): array => DB::table('search_console_rows')->where('dimension', $dimension)
            ->orderByDesc($order)->orderByDesc('impressions')->orderBy('key')->limit($limit)->get()->map(fn (stdClass $r): array => self::row($r))->all();
        $total = DB::table('search_console_rows')->where('dimension', 'page')->selectRaw('coalesce(sum(clicks), 0) as clicks, coalesce(sum(impressions), 0) as impressions')->first();
        $clicks = (int) ($total->clicks ?? 0);
        $impressions = (int) ($total->impressions ?? 0);

        $improve = DB::table('search_console_rows')->where('dimension', 'page')->where('impressions', '>=', self::MIN_IMPRESSIONS)
            ->where(fn ($q) => $q->where('ctr', '<', self::LOW_CTR)->orWhereBetween('position', self::NEAR_TOP))
            ->orderByDesc('impressions')->orderBy('key')->limit(25)->get()
            ->map(fn (stdClass $r): array => [...self::row($r), 'reasons' => array_values(array_filter([
                (float) $r->ctr < self::LOW_CTR ? 'lowCtr' : null,
                (float) $r->position >= self::NEAR_TOP[0] && (float) $r->position <= self::NEAR_TOP[1] ? 'nearTop' : null,
            ]))])->all();

        return [
            'connected' => $this->client->configured(),
            'syncedAt' => $state['at'] ?? null,
            'from' => $state['from'] ?? null,
            'to' => $state['to'] ?? null,
            'error' => $state['error'] ?? null,
            'totals' => ['clicks' => $clicks, 'impressions' => $impressions, 'ctr' => $impressions > 0 ? round($clicks / $impressions, 4) : 0],
            'pages' => $rows('page', 'clicks', 50),
            'queries' => $rows('query', 'clicks', 50),
            'toImprove' => $improve,
            'sitemaps' => array_values((array) ($state['sitemaps'] ?? [])),
            'rules' => ['minImpressions' => self::MIN_IMPRESSIONS, 'lowCtr' => self::LOW_CTR, 'nearTop' => self::NEAR_TOP],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function state(): array
    {
        $value = Setting::query()->where('key', self::SETTING)->value('value');

        return is_array($value) ? $value : [];
    }

    /**
     * @return array{key: string, clicks: int, impressions: int, ctr: float, position: float}
     */
    private static function row(stdClass $r): array
    {
        return ['key' => (string) $r->key, 'clicks' => (int) $r->clicks, 'impressions' => (int) $r->impressions, 'ctr' => (float) $r->ctr, 'position' => (float) $r->position];
    }
}
