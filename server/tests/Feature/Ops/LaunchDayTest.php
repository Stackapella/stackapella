<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Console\Commands\OpsDigest;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use App\Support\Ops\Readiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Launch day (docs/launch-day.md): an outside check can tell when the scheduler has stopped, the
 * alerts say when sign-up emails are waiting or jobs fail, and a busy shared address isn't
 * refused as one visitor.
 */
final class LaunchDayTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_scheduler_check_fails_once_the_heartbeat_is_five_minutes_old(): void
    {
        $this->getJson('/up/scheduler')->assertStatus(503)->assertJsonPath('status', 'late')->assertJsonPath('lastBeat', null);

        $this->artisan('app:heartbeat')->assertSuccessful();
        $this->getJson('/up/scheduler')->assertOk()->assertJsonPath('status', 'ok');

        $this->travel(6)->minutes();
        $this->getJson('/up/scheduler')->assertStatus(503)->assertJsonPath('status', 'late');
        $this->assertNotNull(Readiness::lastHeartbeat());
    }

    public function test_the_alerts_say_when_emails_wait_and_jobs_fail(): void
    {
        Notification::fake();
        $admin = User::factory()->admin()->create();
        config(['ops.alerts.mail_waiting' => 1, 'ops.alerts.failed_jobs_an_hour' => 1]);
        $job = ['queue' => 'default', 'payload' => '{}', 'attempts' => 0, 'reserved_at' => null, 'available_at' => now()->getTimestamp(), 'created_at' => now()->subMinutes(10)->getTimestamp()];
        DB::table('jobs')->insert([$job, $job]);
        DB::table('failed_jobs')->insert([
            ['uuid' => 'a', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()],
            ['uuid' => 'b', 'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x', 'failed_at' => now()],
        ]);

        $this->artisan('app:alerts')->assertSuccessful();
        Notification::assertSentTo($admin, OpsAlert::class, fn (OpsAlert $alert): bool => count(array_filter($alert->lines, static fn (string $l): bool => str_contains($l, 'sign-up links are late') || str_contains($l, 'jobs failed'))) === 2);
    }

    public function test_the_digest_says_where_new_visitors_came_from_folding_the_few(): void
    {
        $row = static fn (string $kind, string $source, int $count): array => ['day' => '2026-11-03', 'kind' => $kind, 'audience' => 'guest', 'country' => '', 'variant' => '', 'source' => $source, 'count' => $count];
        DB::table('usage_counts')->insert([
            $row('f_arrive', 'producthunt', 120), $row('f_arrive_start', 'producthunt', 8), $row('f_account', 'producthunt', 14),
            $row('f_arrive', 'harmony', 30), $row('f_account', 'harmony', 2),
            $row('f_arrive', 'reddit', 9), $row('f_arrive', 'newsletter', 6),
            // Under five: never named on its own.
            $row('f_arrive', 'o-tiny-choir', 3), $row('f_account', 'o-tiny-choir', 1),
            // No tag, and another day: not in it.
            $row('f_arrive', '', 500), ['day' => '2026-11-02', 'kind' => 'f_arrive', 'audience' => 'guest', 'country' => '', 'variant' => '', 'source' => 'producthunt', 'count' => 99],
        ]);

        $this->assertSame('Where new visitors came from: producthunt 128 (14 signed up), harmony 30 (2 signed up), reddit 9 (0 signed up), others 9 (1 signed up).', OpsDigest::sources('2026-11-03'));
        $this->assertNull(OpsDigest::sources('2026-10-01'));
    }

    public function test_the_counts_beacon_spends_only_its_own_limit_not_the_apis(): void
    {
        // Every public page sends a count now: on a shared address they mustn't use up the API's 120 a minute.
        $route = app('router')->getRoutes()->getByName('counts.create');
        $this->assertNotNull($route);
        $middleware = app('router')->gatherRouteMiddleware($route);
        $this->assertContains('throttle:counts', $middleware);
        $this->assertNotContains('throttle:api', $middleware);
    }

    public function test_a_shared_address_has_room_for_several_visitors(): void
    {
        $this->assertSame(120, config('limits.api.signed_out.max'));
        // 120 a minute in production; local and testing take more, since every agent's browser flows share one address.
        $this->assertGreaterThanOrEqual(120, config('limits.counts.ip.max'));
    }
}
