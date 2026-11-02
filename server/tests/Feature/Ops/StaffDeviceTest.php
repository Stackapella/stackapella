<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Models\Take;
use App\Models\User;
use App\Support\Stats\Counts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * An admin's own use isn't counted (Hillel: "make sure my admin visits aren't counted in
 * analytics"): a device that has been in Admin carries sa_staff, which the anonymous counts
 * ignore, and the admins' accounts are left out of what counts people.
 */
final class StaffDeviceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_device_is_marked_once_with_a_cookie_that_holds_no_id(): void
    {
        $this->actingAsAdmin();
        $first = $this->getJson('/api/admin/me')->assertOk();
        $first->assertPlainCookie('sa_staff', '1');
        $cookie = collect($first->headers->getCookies())->first(fn ($c): bool => $c->getName() === 'sa_staff');
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
        $this->assertGreaterThan(now()->addDays(360)->getTimestamp(), $cookie->getExpiresTime());

        // Already marked: not set again.
        $this->withCredentials()->withUnencryptedCookie('sa_staff', '1')->getJson('/api/admin/me')->assertOk()->assertCookieMissing('sa_staff');

        // Someone who isn't an admin never gets it.
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/me')->assertCookieMissing('sa_staff');
    }

    public function test_the_anonymous_counts_ignore_a_staff_device_and_count_everyone_else(): void
    {
        config(['counts.enabled' => true]);
        DB::table('experiments')->insert(['key' => 'short1', 'what' => 'A one-step lesson 1', 'hypothesis' => 'More finish lesson 3.', 'arms' => json_encode([['arm' => 'a', 'weight' => 50], ['arm' => 'b', 'weight' => 50]]),
            'metric' => 'f_lesson3', 'guardrails' => json_encode(['f_note']), 'audience' => json_encode(['new' => true]), 'excludes' => json_encode([]), 'planned_per_arm' => 1500,
            'starts_on' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now()]);
        $events = ['events' => [['kind' => 'app_open'], ['kind' => 'site_visit'], ['kind' => 'f_arrive'], ['kind' => 'exp_enrol']], 'signedIn' => false, 'variants' => ['short1-b']];

        $this->postJson('/api/counts', $events)->assertNoContent();
        $this->assertSame(3, (int) DB::table('usage_counts')->sum('count'));
        $this->assertSame(2, (int) DB::table('experiment_counts')->sum('count')); // f_arrive and exp_enrol for short1-b

        // The same from a staff device: nothing more.
        $this->withCredentials()->withUnencryptedCookie('sa_staff', '1')->postJson('/api/counts', $events)->assertNoContent();
        $this->assertSame(3, (int) DB::table('usage_counts')->sum('count'));
        $this->assertSame(2, (int) DB::table('experiment_counts')->sum('count')); // f_arrive and exp_enrol for short1-b
    }

    public function test_admins_own_accounts_are_left_out_of_what_counts_people(): void
    {
        $admin = $this->actingAsAdmin();
        $singer = User::factory()->create();
        Take::factory()->create(['recorded_by' => $admin->id, 'created_at' => now()->subDay()]);
        Take::factory()->create(['recorded_by' => $singer->id, 'created_at' => now()->subDay()]);

        $this->assertSame(1, Counts::activeSingers());

        $this->getJson('/api/admin/overview')->assertJsonPath('data.growth.activeSingers', 1)
            // The singer, and the takes' own project owners; never the admin.
            ->assertJsonPath('data.growth.signUps', User::query()->where('is_admin', false)->count());
    }
}
