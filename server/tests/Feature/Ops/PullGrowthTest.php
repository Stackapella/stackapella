<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Console\Commands\PullGrowth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * app:pull-growth (docs/growth-data.md): saves each pull and latest.json, sends the token only to
 * GROWTH_DATA_URL's host or a local one, and names each way a pull fails.
 */
final class PullGrowthTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'a3f1c9e07b2d4e6f8a1b3c5d7e9f0a2b4c6d8e0f';

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/stk-growth-'.bin2hex(random_bytes(4));
        config(['services.growth_data.token' => self::TOKEN, 'services.growth_data.url' => 'https://app.stackapella.example']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private static function pull(int $visits): array
    {
        return [
            'meta' => ['schema_version' => 1, 'generated_at' => '2026-11-07T12:00:00Z', 'range' => ['name' => '30'], 'notes' => ['a note']],
            'daily' => ['dates' => [], 'kinds' => ['site_visit' => ['guest' => array_fill(0, 8, $visits), 'account' => array_fill(0, 8, 0)]]],
            'signups' => ['total' => 12, 'days' => ['accounts' => array_fill(0, 8, 1)]],
        ];
    }

    public function test_a_pull_is_saved_and_summarised_against_the_one_before(): void
    {
        Http::fake(['app.stackapella.example/api/internal/growth*' => Http::sequence()->push(self::pull(2))->push(self::pull(3))]);

        $this->artisan('app:pull-growth', ['--dir' => $this->dir])->assertSuccessful();
        $this->travel(2)->seconds();
        $this->artisan('app:pull-growth', ['--dir' => $this->dir, '--range' => '7'])->expectsOutputToContain('Against the pull generated')->assertSuccessful();

        $this->assertCount(2, File::glob($this->dir.'/growth-*.json'));
        $this->assertSame(self::pull(3), json_decode((string) File::get($this->dir.'/latest.json'), true));
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer '.self::TOKEN) && ! str_contains($request->url(), self::TOKEN) && str_contains($request->url(), 'range=7'));

        // Site visits over the last 7 whole days: 14, then 21.
        $this->assertSame(21, PullGrowth::headlines(self::pull(3))['Site visits, last 7 days']);
        $this->artisan('app:pull-growth', ['--dir' => $this->dir, '--local' => true])->expectsOutputToContain('notes travel with this data')->assertSuccessful();
    }

    public function test_the_token_goes_only_to_the_configured_host_or_a_local_one(): void
    {
        Http::fake();

        $this->artisan('app:pull-growth', ['--dir' => $this->dir, '--url' => 'https://elsewhere.example'])->expectsOutputToContain('Refusing')->assertFailed();
        $this->artisan('app:pull-growth', ['--dir' => $this->dir, '--url' => 'http://app.stackapella.example'])->expectsOutputToContain('in the clear')->assertFailed();
        Http::assertNothingSent();

        $this->assertNull(PullGrowth::urlProblem('http://stackapella.test'));
        $this->assertNull(PullGrowth::urlProblem('http://localhost:8000'));
    }

    public function test_without_a_token_it_says_how_to_set_one_up(): void
    {
        config(['services.growth_data.token' => 'short']);
        $this->artisan('app:pull-growth', ['--dir' => $this->dir])->expectsOutputToContain('GROWTH_DATA_TOKEN is missing')->assertFailed();
    }

    public function test_each_failure_is_named_and_nothing_is_saved(): void
    {
        $cases = [
            'Token mismatch' => Http::response(['error' => 'unauthorized'], 401),
            'Not found' => Http::response(['error' => 'not_found'], 404),
            'Rate limited' => Http::response(['error' => 'busy'], 429, ['Retry-After' => '30']),
            'Redirected' => Http::response('', 301, ['Location' => 'https://stackapella.example/api/internal/growth']),
            'Cloudflare' => Http::response('<html>challenge</html>', 403, ['Content-Type' => 'text/html', 'cf-mitigated' => 'challenge']),
            'not the growth data' => Http::response(['something' => 'else']),
        ];

        $sequence = Http::sequence();

        foreach ($cases as $response) {
            $sequence->pushResponse($response);
        }

        Http::fake(['*' => $sequence]);

        foreach (array_keys($cases) as $says) {
            $this->artisan('app:pull-growth', ['--dir' => $this->dir])->expectsOutputToContain($says)->assertFailed();
        }

        $this->assertSame([], File::glob($this->dir.'/*.json'));
    }
}
