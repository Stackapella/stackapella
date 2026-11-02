<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\User;
use App\Support\Ops\Check;
use App\Support\Ops\Readiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Search Console (docs/search-console.md): off and quiet until its key is set, then a daily sync of
 * the last 28 days by page and by query, and the pages to improve. Google is faked.
 */
final class SearchConsoleTest extends TestCase
{
    use RefreshDatabase;

    private string $publicKey = '';

    /** What Google's search analytics answers with (a fake is set once per test). */
    private int $status = 200;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-01 05:40:00', 'UTC'));
    }

    private function connect(): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $this->assertNotFalse($key);
        openssl_pkey_export($key, $private);
        $this->publicKey = (string) (openssl_pkey_get_details($key)['key'] ?? '');
        config(['services.search_console.credentials' => base64_encode((string) json_encode(['client_email' => 'reader@stackapella.iam.gserviceaccount.com', 'private_key' => $private]))]);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $rows  by dimension
     */
    private function fakeGoogle(array $rows): void
    {
        Http::fake(function (Request $request) use ($rows) {
            if (str_starts_with($request->url(), 'https://oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'ya29.token', 'expires_in' => 3600]);
            }

            if (str_ends_with($request->url(), '/searchAnalytics/query')) {
                return $this->status === 200 ? Http::response(['rows' => $rows[$request['dimensions'][0]] ?? []]) : Http::response(['error' => ['message' => 'User does not have sufficient permission for site']], $this->status);
            }

            return Http::response(['sitemap' => [['path' => 'https://stackapella.com/sitemap.xml', 'errors' => '0', 'warnings' => '1', 'lastDownloaded' => '2026-10-31T22:14:03.000Z']]]);
        });
    }

    private function doctor(): Check
    {
        $check = collect(app(Readiness::class)->run())->first(fn (Check $c): bool => $c->key === 'search_console');
        $this->assertInstanceOf(Check::class, $check);

        return $check;
    }

    public function test_it_stays_off_and_quiet_until_its_key_is_set(): void
    {
        Http::preventStrayRequests();
        $this->artisan('app:search-console:sync')->expectsOutputToContain('nothing to do')->assertSuccessful();
        $this->assertSame([Check::WARN, 'Not set up: Admin > Growth > Search stays empty.'], [$this->doctor()->status, $this->doctor()->message]);

        $this->actingAsAdmin();
        $search = $this->getJson('/api/admin/search');
        $this->assertMatchesContract($search, 'admin.search.show', 200);
        $search->assertJsonPath('data.connected', false)->assertJsonPath('data.syncedAt', null)->assertJsonPath('data.pages', [])->assertJsonPath('data.toImprove', []);
    }

    public function test_a_sync_keeps_the_last_28_days_and_names_the_pages_to_improve(): void
    {
        $this->connect();
        $page = fn (string $path, int $clicks, int $impressions, float $ctr, float $position): array => ['keys' => ['https://stackapella.com'.$path], 'clicks' => $clicks, 'impressions' => $impressions, 'ctr' => $ctr, 'position' => $position];
        $this->fakeGoogle([
            'page' => [
                $page('/', 220, 5000, 0.044, 6.2),                         // doing well
                $page('/courses/perfect-your-pitch', 61, 2900, 0.021, 9.4), // near the top of page one
                $page('/songs/amazing-grace', 18, 2400, 0.0075, 14.8),      // both
                $page('/warmup', 6, 600, 0.01, 3.0),                        // seen high up, rarely clicked
                $page('/blog/one', 0, 40, 0.0, 12.0),                       // too few impressions to say
            ],
            'query' => [['keys' => ['stackapella'], 'clicks' => 150, 'impressions' => 420, 'ctr' => 0.357, 'position' => 1.1]],
        ]);

        $this->artisan('app:search-console:sync')->expectsOutput('Search Console synced.')->assertSuccessful();
        // The last 28 days, ending 3 days ago; a service account's own signed JWT.
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/sites/sc-domain%3Astackapella.com/searchAnalytics/query') && $r['startDate'] === '2026-10-02' && $r['endDate'] === '2026-10-29' && $r['dimensions'] === ['page']);
        Http::assertSent(function (Request $r): bool {
            if (! str_starts_with($r->url(), 'https://oauth2.googleapis.com/token')) {
                return false;
            }
            [$head, $claims, $signature] = explode('.', (string) $r['assertion']);
            $decode = fn (string $part): string => (string) base64_decode(strtr($part, '-_', '+/'), true);
            $payload = json_decode($decode($claims), true);

            return openssl_verify($head.'.'.$claims, $decode($signature), $this->publicKey, OPENSSL_ALGO_SHA256) === 1
                && $payload['iss'] === 'reader@stackapella.iam.gserviceaccount.com' && $payload['scope'] === 'https://www.googleapis.com/auth/webmasters.readonly';
        });
        $this->assertSame(6, DB::table('search_console_rows')->count());
        $this->assertSame([Check::PASS, 'Synced 2026-11-01T05:40:00Z.'], [$this->doctor()->status, $this->doctor()->message]);

        $admin = $this->actingAsAdmin();
        $search = $this->getJson('/api/admin/search');
        $this->assertMatchesContract($search, 'admin.search.show', 200);
        $search->assertJsonPath('data.connected', true)->assertJsonPath('data.from', '2026-10-02')->assertJsonPath('data.to', '2026-10-29')->assertJsonPath('data.error', null)
            ->assertJsonPath('data.totals', ['clicks' => 305, 'impressions' => 10940, 'ctr' => 0.0279])
            ->assertJsonPath('data.pages.0.key', 'https://stackapella.com/')
            ->assertJsonPath('data.queries', [['key' => 'stackapella', 'clicks' => 150, 'impressions' => 420, 'ctr' => 0.357, 'position' => 1.1]])
            ->assertJsonPath('data.toImprove', [
                ['key' => 'https://stackapella.com/courses/perfect-your-pitch', 'clicks' => 61, 'impressions' => 2900, 'ctr' => 0.021, 'position' => 9.4, 'reasons' => ['nearTop']],
                ['key' => 'https://stackapella.com/songs/amazing-grace', 'clicks' => 18, 'impressions' => 2400, 'ctr' => 0.0075, 'position' => 14.8, 'reasons' => ['lowCtr', 'nearTop']],
                ['key' => 'https://stackapella.com/warmup', 'clicks' => 6, 'impressions' => 600, 'ctr' => 0.01, 'position' => 3, 'reasons' => ['lowCtr']],
            ])
            ->assertJsonPath('data.sitemaps', [['path' => 'https://stackapella.com/sitemap.xml', 'errors' => 0, 'warnings' => 1, 'lastDownloaded' => '2026-10-31T22:14:03.000Z']]);

        // A failed sync keeps the last good numbers and says why, on the page and in the doctor.
        $this->travel(1)->days();
        $this->status = 403;
        $this->artisan('app:search-console:sync')->expectsOutputToContain('Search Console:')->assertSuccessful();
        $this->assertSame(6, DB::table('search_console_rows')->count());
        $this->assertSame(Check::WARN, $this->doctor()->status);
        $this->assertStringStartsWith('The last sync failed:', $this->doctor()->message);
        $this->actingAsAdmin($admin); // a day on: a fresh second-factor check
        $this->getJson('/api/admin/search')->assertJsonPath('data.syncedAt', '2026-11-01T05:40:00Z')->assertJsonPath('data.toImprove.0.reasons', ['nearTop'])
            ->assertJson(fn ($json) => $json->where('data.error', fn (mixed $e): bool => is_string($e) && $e !== '')->etc());
    }

    public function test_the_local_pull_saves_json_and_compares_the_window_with_the_one_before(): void
    {
        $this->artisan('app:pull-search-console')->expectsOutputToContain('SEARCH_CONSOLE_CREDENTIALS is not set')->assertFailed();

        $this->connect();
        $row = fn (array $keys, int $clicks, int $impressions, float $ctr = 0.02, float $position = 9.0): array => ['keys' => $keys, 'clicks' => $clicks, 'impressions' => $impressions, 'ctr' => $ctr, 'position' => $position];
        Http::fake(function (Request $request) use ($row) {
            $url = $request->url();

            if (str_starts_with($url, 'https://oauth2.googleapis.com/token')) {
                return Http::response(['access_token' => 'ya29.token']);
            }

            if (str_ends_with($url, '/index:inspect')) {
                return Http::response(['inspectionResult' => ['indexStatusResult' => ['verdict' => 'PASS', 'coverageState' => 'Submitted and indexed', 'lastCrawlTime' => '2026-10-30T08:00:00Z', 'googleCanonical' => $request['inspectionUrl']]]]);
            }

            if (str_ends_with($url, '/searchAnalytics/query')) {
                $now = $request['startDate'] === '2026-10-02';
                $rows = match ($request['dimensions'][0] ?? 'totals') {
                    'totals' => [$now ? $row([], 300, 10000, 0.03, 8.5) : $row([], 200, 8000, 0.025, 9.5)],
                    'page' => $now
                        ? [$row(['https://stackapella.com/'], 220, 5000), $row(['https://stackapella.com/songs/amazing-grace'], 10, 2000)]
                        : [$row(['https://stackapella.com/'], 150, 4000), $row(['https://stackapella.com/old'], 0, 900)],
                    default => [$row(['stackapella'], 150, 420, 0.357, 1.1)],
                };

                return Http::response(['rows' => $rows]);
            }

            return Http::response(['sitemap' => [['path' => 'https://stackapella.com/sitemap.xml', 'errors' => 0, 'warnings' => 0]]]);
        });
        $dir = storage_path('framework/testing/search-console-'.uniqid());

        $this->artisan('app:pull-search-console', ['--dir' => $dir, '--inspect' => ['/warmup']])
            ->expectsOutputToContain('2026-10-02 to 2026-10-29, against the 28 days before')
            ->expectsOutputToContain('Biggest impression losses')
            ->expectsOutputToContain('Index status of https://stackapella.com/warmup')
            ->assertSuccessful();
        // The window before is the 28 days just ahead of it.
        Http::assertSent(fn (Request $r): bool => str_ends_with($r->url(), '/searchAnalytics/query') && $r['startDate'] === '2026-09-04' && $r['endDate'] === '2026-10-01' && $r['dimensions'] === ['page']);

        $pull = json_decode((string) file_get_contents($dir.'/latest.json'), true);
        $this->assertCount(2, glob($dir.'/*.json') ?: []);
        $this->assertSame(['2026-10-02', '2026-10-29'], $pull['window']);
        $this->assertSame(['clicks' => 300, 'impressions' => 10000, 'ctr' => 0.03, 'position' => 8.5], $pull['totals']);
        $this->assertSame(200, $pull['previousTotals']['clicks']);
        $this->assertSame('https://stackapella.com/old', $pull['previousPages'][1]['keys'][0]);
        $this->assertSame(['verdict' => 'PASS', 'coverage' => 'Submitted and indexed', 'indexing' => null, 'fetch' => null, 'lastCrawl' => '2026-10-30T08:00:00Z', 'googleCanonical' => 'https://stackapella.com/warmup', 'userCanonical' => null], $pull['inspections']['https://stackapella.com/warmup']);
        // Never written to the database: the pull is this machine's.
        $this->assertSame(0, DB::table('search_console_rows')->count());

        array_map('unlink', glob($dir.'/*') ?: []);
        rmdir($dir);
    }

    public function test_only_an_admin_reads_it(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/search')->assertForbidden();
    }
}
