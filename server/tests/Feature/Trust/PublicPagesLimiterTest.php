<?php

declare(strict_types=1);

namespace Tests\Feature\Trust;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The public pages' rate limiter counts on this instance (cache.instance_store; App\Http\Middleware\Throttle), so a page
 * view writes nothing to the database, while a flood from one address is still turned away. Every other limiter counts
 * in the shared store as before.
 */
final class PublicPagesLimiterTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_page_view_writes_no_database_row_and_a_flood_is_still_turned_away(): void
    {
        config(['limits.public-pages.ip.max' => 3]);
        $counters = static fn (): array => DB::table('cache')->pluck('key')->filter(static fn (string $key): bool => str_ends_with($key, ':timer'))->values()->all();

        $this->get('/pricing')->assertOk()->assertHeader('X-RateLimit-Limit', '3');
        $after = DB::table('cache')->count();  // a first view may keep what a page reads once (its preloads, the overrides)
        foreach (range(2, 3) as $view) {
            $this->get('/pricing')->assertOk()->assertHeader('X-RateLimit-Remaining', (string) (3 - $view));
        }
        $this->assertSame($after, DB::table('cache')->count(), 'no row a view in the database cache');
        $this->assertSame([], $counters(), 'no limiter counter in the database');
        $this->get('/pricing')->assertStatus(429);

        // Another limiter still counts in the shared store.
        config(['limits.api.signed_out.max' => 1]);
        $this->getJson('/api/templates')->assertOk();
        $this->assertNotSame([], $counters(), 'the API limiter counts in the database');
    }
}
