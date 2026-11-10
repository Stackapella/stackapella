<?php

declare(strict_types=1);

namespace App\Actions\Site;

use App\Models\IndexNowUrl;
use App\Support\Flags;
use App\Support\Http\Hosts;
use App\Support\Site\SitemapPaths;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * IndexNow (Hillel, 2026-11-09: "Yes, build it"): our own public pages that are new to the sitemap
 * (a song opening on its Monday, a course's lesson pages, a new site page) or whose words changed
 * (the sitemap's lastmod moved) go to api.indexnow.org in one batch, which shares them with Bing,
 * Yandex and the other engines. Run by the scheduler (app:indexnow, hourly), never on a request.
 *
 * Only while Admin's `indexnow` switch is on, never on a self-hosted install, at most
 * services.indexnow.daily_cap addresses a day. A refused or failed batch is logged and the next try
 * waits PAUSE_HOURS: never a retry loop. What we send is our own addresses, with our User-Agent.
 */
final class SubmitToIndexNow
{
    public const USER_AGENT = 'StackapellaIndexNow/1.0';

    /** After a failure, the next batch waits this long. */
    public const PAUSE_HOURS = 6;

    private const PAUSED = 'indexnow.paused';

    public function __construct(private readonly Flags $flags, private readonly SitemapPaths $sitemap) {}

    /** The key: INDEXNOW_KEY, or one worked out from APP_KEY (32 hex characters, stable while it is). */
    public static function key(): string
    {
        $key = (string) config('services.indexnow.key');

        return $key !== '' ? $key : substr(hash_hmac('sha256', 'indexnow', (string) config('app.key')), 0, 32);
    }

    public function on(): bool
    {
        return config('selfhost.enabled') !== true && $this->flags->on('indexnow') && (string) config('app.key') !== '';
    }

    /**
     * One batch: the addresses it sent (none when switched off, paused, capped or nothing changed).
     *
     * @return list<string>
     */
    public function handle(): array
    {
        if (! $this->on() || Cache::has(self::PAUSED)) {
            return [];
        }

        $base = Hosts::publicUrl();
        $paths = array_values(array_unique($this->sitemap->all()));
        $dates = $this->sitemap->lastModified();
        $known = IndexNowUrl::query()->get(['path_hash', 'lastmod'])->mapWithKeys(static fn (IndexNowUrl $u): array => [$u->path_hash => $u->lastmod?->toDateString()])->all();

        // A page that left the sitemap (a song back in the queue) is forgotten, so its return is news.
        $gone = array_keys(array_diff_key($known, array_flip(array_map(IndexNowUrl::hashOf(...), $paths))));
        foreach (array_chunk($gone, 500) as $chunk) {
            IndexNowUrl::query()->whereIn('path_hash', $chunk)->delete();
        }

        $due = array_values(array_filter($paths, static function (string $path) use ($known, $dates): bool {
            $hash = IndexNowUrl::hashOf($path);

            return ! array_key_exists($hash, $known) || (isset($dates[$path]) && $dates[$path] !== $known[$hash]);
        }));

        $left = max(0, (int) config('services.indexnow.daily_cap') - IndexNowUrl::query()->where('submitted_at', '>=', now()->startOfDay())->count());
        $batch = array_slice($due, 0, $left);

        if ($batch === []) {
            return [];
        }

        $urls = array_map(static fn (string $path): string => $base.$path, $batch);

        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])->timeout(20)->post((string) config('services.indexnow.endpoint'), [
                'host' => (string) parse_url($base, PHP_URL_HOST),
                'key' => self::key(),
                'keyLocation' => $base.'/'.self::key().'.txt',
                'urlList' => $urls,
            ]);
            $ok = $response->successful();
            $status = $response->status();
        } catch (ConnectionException $e) {
            $ok = false;
            $status = $e->getMessage();
        }

        if (! $ok) {
            Log::warning('IndexNow refused or missed a batch; the next waits '.self::PAUSE_HOURS.' hours', ['status' => $status, 'urls' => count($urls)]);
            Cache::put(self::PAUSED, true, now()->addHours(self::PAUSE_HOURS));

            return [];
        }

        $now = now();
        DB::transaction(static function () use ($batch, $dates, $now): void {
            foreach (array_chunk($batch, 500) as $chunk) {
                IndexNowUrl::query()->upsert(array_map(static fn (string $path): array => [
                    'path_hash' => IndexNowUrl::hashOf($path),
                    'path' => $path,
                    'lastmod' => $dates[$path] ?? null,
                    'submitted_at' => $now,
                ], $chunk), ['path_hash'], ['path', 'lastmod', 'submitted_at']);
            }
        });

        return $urls;
    }
}
