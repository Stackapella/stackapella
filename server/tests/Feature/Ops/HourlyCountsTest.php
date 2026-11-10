<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Counts\RecordCounts;
use App\Models\StoreSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The anonymous counts by the hour (usage_count_hours), for Admin > Growth's pro-rated comparisons
 * (Hillel, 2026-11-09): each count also lands in its UTC hour, with its kind, guest or account and a
 * funnel kind's source; never a country or an arm.
 */
final class HourlyCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_count_lands_in_its_hour_too_with_no_country_or_arm(): void
    {
        config(['counts.enabled' => true]);
        $this->travelTo(now()->utc()->setTime(9, 40));
        $this->postJson('/api/counts', ['events' => [['kind' => 'lesson_done', 'n' => 3], ['kind' => 'f_arrive'], ['kind' => 'site_visit']], 'source' => 'reddit'], ['CF-IPCountry' => 'GB'])->assertNoContent();
        $this->travelTo(now()->utc()->setTime(9, 55));
        $this->postJson('/api/counts', ['events' => [['kind' => 'lesson_done']], 'signedIn' => true])->assertNoContent();
        $this->travelTo(now()->utc()->setTime(10, 5));
        $this->postJson('/api/counts', ['events' => [['kind' => 'lesson_done']]])->assertNoContent();
        // The server's own counts too.
        app(RecordCounts::class)->server('plus_started', true, 'GB');

        $rows = DB::table('usage_count_hours')->orderBy('hour')->orderBy('kind')->orderBy('audience')->get(['hour', 'kind', 'audience', 'source', 'count'])
            ->map(fn (object $r): array => [substr((string) $r->hour, 11, 5), (string) $r->kind, (string) $r->audience, (string) $r->source, (int) $r->count])->all();
        $this->assertSame([
            ['09:00', 'f_arrive', 'guest', 'reddit', 1],
            ['09:00', 'lesson_done', 'account', '', 1],
            ['09:00', 'lesson_done', 'guest', '', 3],
            ['09:00', 'site_visit', 'guest', 'reddit', 1],
            ['10:00', 'lesson_done', 'guest', '', 1],
            ['10:00', 'plus_started', 'account', '', 1],
        ], $rows);
        $this->assertSame(['id', 'hour', 'kind', 'audience', 'source', 'count'], array_keys((array) DB::table('usage_count_hours')->first()));

        // The day's own counts are as before.
        $this->assertSame(5, (int) DB::table('usage_counts')->where('kind', 'lesson_done')->sum('count'));
    }

    public function test_an_hours_counts_add_up_to_the_days_and_n_is_capped_as_the_days_is(): void
    {
        config(['counts.enabled' => true, 'counts.max_n' => 20]);
        $this->postJson('/api/counts', ['events' => [['kind' => 'lesson_done', 'n' => 500], ['kind' => 'f_arrive', 'n' => 9]]])->assertNoContent();
        $this->postJson('/api/counts', ['events' => [['kind' => 'lesson_done', 'n' => 2]]])->assertNoContent();

        foreach (['lesson_done' => 22, 'f_arrive' => 1] as $kind => $total) {
            $this->assertSame($total, (int) DB::table('usage_count_hours')->where('kind', $kind)->sum('count'));
            $this->assertSame($total, (int) DB::table('usage_counts')->where('kind', $kind)->sum('count'));
        }
    }

    public function test_hours_past_100_days_are_pruned_and_the_rest_kept(): void
    {
        $this->travelTo(now()->utc()->setTime(12, 0));
        foreach ([101 => 'old', 100 => 'edge', 99 => 'kept', 0 => 'today'] as $days => $kind) {
            DB::table('usage_count_hours')->insert(['hour' => now()->utc()->subDays($days)->startOfHour(), 'kind' => $kind, 'audience' => 'guest', 'source' => '', 'count' => 1]);
        }
        DB::table('usage_counts')->insert(['day' => now()->subDays(101)->toDateString(), 'kind' => 'old', 'audience' => 'guest', 'country' => '', 'count' => 1]);

        $this->artisan('app:counts:prune-hours')->expectsOutputToContain('Deleted 1 ')->assertSuccessful();

        $this->assertSame(['edge', 'kept', 'today'], DB::table('usage_count_hours')->orderBy('hour')->pluck('kind')->all());
        // The day's own count stays forever.
        $this->assertSame(1, DB::table('usage_counts')->where('kind', 'old')->count());
    }

    public function test_sign_ups_and_plus_are_filled_in_from_their_own_records_once(): void
    {
        $this->travelTo(now()->utc()->setTime(12, 0));
        // The records only (a store purchase counts itself live when made; not here).
        config(['counts.enabled' => false]);
        $at = static fn (int $days, int $hour): string => now()->utc()->subDays($days)->setTime($hour, 17)->format('Y-m-d H:i:s');
        User::factory()->create(['created_at' => $at(3, 9)]);
        User::factory()->create(['created_at' => $at(3, 9)]);
        User::factory()->admin()->create(['created_at' => $at(3, 9)]);
        User::factory()->create(['created_at' => $at(120, 9)]);
        $web = static fn (string $status, string $created) => DB::table('subscriptions')->insert(['user_id' => User::factory()->create(['created_at' => $at(200, 1)])->id, 'type' => 'default', 'stripe_id' => 'sub_'.Str::random(12), 'stripe_status' => $status, 'stripe_price' => 'price_month', 'quantity' => 1, 'created_at' => $created, 'updated_at' => $created]);
        $web('active', $at(2, 14));
        $web('incomplete', $at(2, 14));
        StoreSubscription::factory()->create(['user_id' => User::factory()->create(['created_at' => $at(200, 1)])->id, 'created_at' => $at(2, 14)]);
        StoreSubscription::factory()->create(['user_id' => User::factory()->create(['created_at' => $at(200, 1)])->id, 'created_at' => $at(2, 14), 'environment' => 'Sandbox']);
        // An hour already counted live is never written over.
        DB::table('usage_count_hours')->insert(['hour' => now()->utc()->subDays(1)->setTime(8, 0), 'kind' => 'sign_up', 'audience' => 'account', 'source' => '', 'count' => 5]);
        User::factory()->create(['created_at' => $at(1, 8)]);

        $this->artisan('app:counts:backfill-hours')->expectsOutputToContain('Filled 2 hours')->assertSuccessful();
        $this->artisan('app:counts:backfill-hours')->expectsOutputToContain('Filled 0 hours')->assertSuccessful();

        $rows = DB::table('usage_count_hours')->orderBy('hour')->get(['hour', 'kind', 'count'])->map(fn (object $r): array => [(string) $r->hour, (string) $r->kind, (int) $r->count])->all();
        $this->assertSame([
            [now()->utc()->subDays(3)->setTime(9, 0)->format('Y-m-d H:i:s'), 'sign_up', 2],
            [now()->utc()->subDays(2)->setTime(14, 0)->format('Y-m-d H:i:s'), 'plus_started', 2],
            [now()->utc()->subDays(1)->setTime(8, 0)->format('Y-m-d H:i:s'), 'sign_up', 5],
        ], $rows);
    }

    public function test_nothing_is_kept_while_counts_are_off_or_from_a_crawler(): void
    {
        config(['counts.enabled' => false]);
        $this->postJson('/api/counts', ['events' => [['kind' => 'lesson_done']]])->assertNoContent();
        config(['counts.enabled' => true]);
        $this->withHeader('User-Agent', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)')->postJson('/api/counts', ['events' => [['kind' => 'lesson_done']]])->assertNoContent();

        $this->assertSame(0, DB::table('usage_count_hours')->count());
    }
}
