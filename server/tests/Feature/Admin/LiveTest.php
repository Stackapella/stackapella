<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Admin > Live (docs/realtime-dashboard-plan.md; Hillel, 2026-11-12: "Allow, cache only", "Totals only"): the
 * heartbeat's anonymous per-minute counts, who is signed in from the sessions already kept, and the day's pulse.
 */
final class LiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['counts.live_store' => 'array']);
        $this->travelTo(now()->startOfMinute()->addSeconds(10));
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    private function beat(array $body, array $headers = [], bool $staff = false): void
    {
        $request = $staff ? $this->withCredentials()->withUnencryptedCookie('sa_staff', '1') : $this;
        $request->withHeaders($headers)->postJson('/api/live/beat', $body)->assertNoContent();
        $this->flushHeaders();
        $this->defaultCookies = [];
        $this->unencryptedCookies = [];
        $this->withCredentials = false;
    }

    public function test_a_beat_is_a_quiet_204_with_no_session_and_only_a_good_one_counts(): void
    {
        $response = $this->postJson('/api/live/beat', ['area' => 'courses', 'page' => 'lesson', 'view' => true]);
        $this->assertMatchesContract($response, 'live.beat', 204);
        $this->assertSame([], array_values(array_filter($response->headers->getCookies(), static fn ($c): bool => str_contains($c->getName(), 'session'))), 'no session cookie');

        // An unknown area, a bad page name or a crawler counts nothing; the answer is the same.
        $this->beat(['area' => 'admin', 'page' => 'admin', 'view' => true]);
        $this->beat(['area' => 'courses', 'page' => '<script>', 'view' => true]);
        $this->beat(['area' => 'courses', 'page' => 'lesson', 'view' => true], ['User-Agent' => 'Googlebot/2.1']);
        $this->beat(['page' => 'lesson']);

        $this->travel(1)->minutes();
        $this->flushHeaders();
        $this->actingAsAdmin();
        $live = $this->getJson('/api/admin/live')->assertOk();
        $this->assertSame(1, $live->json('data.views.perMinute.28'));
        $courses = collect((array) $live->json('data.areas'))->firstWhere('key', 'courses');
        $this->assertSame([['page' => 'lesson', 'views' => 1]], $courses['top']);
    }

    public function test_right_now_counts_beats_as_people_signed_in_from_sessions_and_views_by_the_minute(): void
    {
        // The last whole minute: six beats from three apps (two each); four of them on screen changes.
        foreach ([['courses', 'lesson', true], ['courses', 'lesson', false], ['studio', 'sing', true], ['studio', 'sing', false], ['app', 'projects', true], ['app', 'projects', true]] as [$area, $page, $view]) {
            $this->beat(['area' => $area, 'page' => $page, 'view' => $view]);
        }
        // A staff device's beat counts only with ?admins=1.
        $this->beat(['area' => 'courses', 'page' => 'course', 'view' => true], staff: true);
        $this->travel(1)->minutes();

        // One singer signed in a minute ago, one an hour ago (not now), and an admin.
        $this->flushHeaders();
        $singer = User::factory()->create();
        $idle = User::factory()->create();
        foreach ([[$singer, 60], [$idle, 3600]] as [$user, $ago]) {
            DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->subSeconds($ago)->getTimestamp()]);
        }
        $admin = $this->actingAsAdmin();
        DB::table('sessions')->insert(['id' => Str::random(40), 'user_id' => $admin->id, 'payload' => '', 'last_activity' => now()->getTimestamp()]);

        $live = $this->getJson('/api/admin/live')->assertOk();
        $this->assertMatchesContract($live, 'admin.live.show', 200);
        $live->assertJsonPath('data.refreshSeconds', 15)
            ->assertJsonPath('data.now', ['total' => 3, 'signedIn' => 1, 'guests' => 2])
            ->assertJsonPath('data.views.last30', 4);
        $this->assertCount(30, (array) $live->json('data.views.perMinute'));
        $this->assertSame(4, $live->json('data.views.perMinute.28'), "the last whole minute's views; the one so far is last");
        $this->assertSame(0, $live->json('data.views.perMinute.29'));

        $staff = $this->getJson('/api/admin/live?admins=1')->assertOk();
        $staff->assertJsonPath('data.now.signedIn', 2)->assertJsonPath('data.views.last30', 5);
    }

    public function test_the_pulse_has_the_days_sign_ups_by_step_and_hour_and_its_activity(): void
    {
        $ada = User::factory()->create(['name' => 'Ada Lovelace', 'tried' => ['lesson' => '2026-11-13', 'whole' => '2026-11-13']]);
        User::factory()->create(['created_at' => now()->subDays(3)]);
        DB::table('usage_count_hours')->insert(['kind' => 'lesson_done', 'hour' => now()->startOfHour()->utc()->format('Y-m-d H:i:s'), 'count' => 7, 'audience' => 'new', 'source' => '']);

        $this->actingAsAdmin();
        $pulse = $this->getJson('/api/admin/pulse')->assertOk();
        $this->assertMatchesContract($pulse, 'admin.pulse.show', 200);
        $pulse->assertJsonPath('data.signUps24h.funnel', ['created' => 1, 'lesson1' => 1, 'wholeSong' => 1, 'keptPart' => 0, 'shared' => 0])
            ->assertJsonPath('data.signUps24h.people.0.id', $ada->sqid())
            ->assertJsonPath('data.signUps24h.people.0.steps', ['created', 'lesson1', 'wholeSong']);
        $this->assertCount(24, (array) $pulse->json('data.signUps24h.hourly'));
        $this->assertSame(1, $pulse->json('data.signUps24h.hourly.23'));
        $this->assertSame(['kind' => 'lesson_done', 'n' => 7], $pulse->json('data.activity24h.0'));
        $this->assertCount(12, (array) $pulse->json('data.active.weeks'));

        $this->assertMatchesContract($this->getJson('/api/admin/revenue'), 'admin.revenue.show', 200);
    }

    public function test_only_admins_see_it(): void
    {
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/live')->assertForbidden();
        $this->getJson('/api/admin/pulse')->assertForbidden();
    }
}
