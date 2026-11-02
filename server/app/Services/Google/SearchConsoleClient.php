<?php

declare(strict_types=1);

namespace App\Services\Google;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Read-only Search Console access with a service account added as a user on the property
 * (docs/search-console.md). It signs its own JWT with openssl, so no Google SDK is needed (ported
 * from iaw).
 */
final class SearchConsoleClient
{
    private const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    private const API = 'https://www.googleapis.com/webmasters/v3/sites/';

    public function configured(): bool
    {
        return $this->credentials() !== null;
    }

    public function property(): string
    {
        return (string) config('services.search_console.property');
    }

    /**
     * Clicks, impressions, CTR (0 to 1) and average position by `page` or `query` over a date
     * range (the data lags about 3 days).
     *
     * @return list<array{key: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function rows(string $dimension, string $from, string $to, int $limit = 1000): array
    {
        return array_map(static fn (array $row): array => ['key' => $row['keys'][0] ?? '', 'clicks' => $row['clicks'], 'impressions' => $row['impressions'], 'ctr' => $row['ctr'], 'position' => $row['position']], $this->query([$dimension], $from, $to, $limit));
    }

    /**
     * Search Analytics by any dimensions (none gives one row of totals).
     *
     * @param  list<string>  $dimensions
     * @return list<array{keys: list<string>, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function query(array $dimensions, string $from, string $to, int $limit = 1000): array
    {
        $rows = Http::withToken($this->token())->timeout(60)
            ->post(self::API.rawurlencode($this->property()).'/searchAnalytics/query', [
                'startDate' => $from, 'endDate' => $to, 'dimensions' => $dimensions, 'rowLimit' => $limit,
            ])->throw()->json('rows') ?? [];

        return array_values(array_map(static fn (array $row): array => [
            'keys' => array_values(array_map('strval', (array) ($row['keys'] ?? []))),
            'clicks' => (int) ($row['clicks'] ?? 0),
            'impressions' => (int) ($row['impressions'] ?? 0),
            'ctr' => (float) ($row['ctr'] ?? 0),
            'position' => (float) ($row['position'] ?? 0),
        ], is_array($rows) ? array_filter($rows, 'is_array') : []));
    }

    /**
     * What Google's index says about one URL (URL Inspection; about 2,000 a day per property).
     *
     * @return array{verdict: string|null, coverage: string|null, indexing: string|null, fetch: string|null, lastCrawl: string|null, googleCanonical: string|null, userCanonical: string|null}
     */
    public function inspect(string $url): array
    {
        $result = Http::withToken($this->token())->timeout(60)
            ->post('https://searchconsole.googleapis.com/v1/urlInspection/index:inspect', ['inspectionUrl' => $url, 'siteUrl' => $this->property()])
            ->throw()->json('inspectionResult.indexStatusResult') ?? [];
        $get = static fn (string $key): ?string => is_array($result) && isset($result[$key]) ? (string) $result[$key] : null;

        return [
            'verdict' => $get('verdict'), 'coverage' => $get('coverageState'), 'indexing' => $get('indexingState'), 'fetch' => $get('pageFetchState'),
            'lastCrawl' => $get('lastCrawlTime'), 'googleCanonical' => $get('googleCanonical'), 'userCanonical' => $get('userCanonical'),
        ];
    }

    /**
     * Each submitted sitemap's status.
     *
     * @return list<array{path: string, errors: int, warnings: int, lastDownloaded: string|null}>
     */
    public function sitemaps(): array
    {
        $items = Http::withToken($this->token())->timeout(30)->get(self::API.rawurlencode($this->property()).'/sitemaps')->throw()->json('sitemap') ?? [];

        return array_values(array_map(static fn (array $s): array => [
            'path' => (string) ($s['path'] ?? ''),
            'errors' => (int) ($s['errors'] ?? 0),
            'warnings' => (int) ($s['warnings'] ?? 0),
            'lastDownloaded' => isset($s['lastDownloaded']) ? (string) $s['lastDownloaded'] : null,
        ], is_array($items) ? array_filter($items, 'is_array') : []));
    }

    private function token(): string
    {
        $credentials = $this->credentials() ?? throw new RuntimeException('Set SEARCH_CONSOLE_CREDENTIALS (the service account JSON, base64-encoded).');

        // Keyed by the account, so a key swapped in .env isn't answered with the old one's token.
        return Cache::remember('search-console:token:'.sha1($credentials['client_email']), 3000, function () use ($credentials): string {
            $now = time();
            $segment = static fn (array $data): string => rtrim(strtr(base64_encode((string) json_encode($data)), '+/', '-_'), '=');
            $unsigned = $segment(['alg' => 'RS256', 'typ' => 'JWT']).'.'.$segment([
                'iss' => $credentials['client_email'], 'scope' => self::SCOPE, 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
            ]);

            if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('The Search Console private key could not sign the request.');
            }

            return (string) Http::asForm()->timeout(15)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.rtrim(strtr(base64_encode((string) $signature), '+/', '-_'), '='),
            ])->throw()->json('access_token');
        });
    }

    /**
     * @return array{client_email: string, private_key: string}|null
     */
    private function credentials(): ?array
    {
        $raw = trim((string) config('services.search_console.credentials'));
        $json = json_decode(str_starts_with(ltrim($raw), '{') ? $raw : (string) base64_decode($raw, true), true);

        return is_array($json) && isset($json['client_email'], $json['private_key'])
            ? ['client_email' => (string) $json['client_email'], 'private_key' => (string) $json['private_key']]
            : null;
    }
}
