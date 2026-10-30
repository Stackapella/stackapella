<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Anonymous usage counts (config/counts.php): a day's numbers by kind, guest or account, and
 * country, and nothing that identifies anyone.
 */
final class UsageCountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_listed_kinds_are_counted_capped_and_nothing_about_anyone_is_kept(): void
    {
        config(['counts.enabled' => true, 'proxies.behind_app_platform' => true, 'selfhost.behind_cloudflare' => true]);
        $sent = $this->withHeaders(['do-connecting-ip' => '172.70.1.2', 'cf-connecting-ip' => '198.51.100.7', 'cf-ipcountry' => 'NL', 'User-Agent' => 'Safari'])
            ->postJson('/api/counts', ['events' => [['kind' => 'lesson_done', 'n' => 2], ['kind' => 'lesson_done', 'n' => 500], ['kind' => 'made_up'], ['kind' => 'app_open']], 'signedIn' => true]);
        $sent->assertNoContent();
        $this->assertMatchesContract($sent, 'counts.create', 204);
        // No session, no cookie.
        $this->assertSame([], $sent->headers->getCookies());

        $rows = DB::table('usage_counts')->orderBy('kind')->get()->map(fn (object $r): array => (array) $r)->all();
        $this->assertSame(['app_open', 'lesson_done'], array_column($rows, 'kind'));
        $this->assertSame([1, 20], array_map('intval', array_column($rows, 'count')));
        $this->assertSame(['account', 'account'], array_column($rows, 'audience'));
        $this->assertSame(['NL', 'NL'], array_column($rows, 'country'));
        // The table has room for nothing else.
        $this->assertSame(['id', 'day', 'kind', 'audience', 'country', 'variant', 'count'], Schema::getColumnListing('usage_counts'));

        // A guest, with no country known: their own row, the same day's numbers adding up.
        $this->flushHeaders();
        $this->postJson('/api/counts', ['events' => [['kind' => 'app_open']]])->assertNoContent();
        $this->postJson('/api/counts', ['events' => [['kind' => 'app_open']]])->assertNoContent();
        $this->assertSame(2, (int) DB::table('usage_counts')->where('audience', 'guest')->where('country', '')->value('count'));
    }

    public function test_the_usage_pages_kinds_and_a_course_or_lesson_only_by_a_real_id(): void
    {
        config(['counts.enabled' => true]);
        $this->postJson('/api/counts', ['events' => [['kind' => 'open_game'], ['kind' => 'take_undo'], ['kind' => 'sug_melody'], ['kind' => 'pl_d:vibrato'], ['kind' => 'fl_s:1-comfortable'], ['kind' => 'pl_d:no-such-course'], ['kind' => 'fl_s:nope'], ['kind' => 'zz:vibrato']]])->assertNoContent();

        $this->assertSame(['fl_s:1-comfortable', 'open_game', 'pl_d:vibrato', 'sug_melody', 'take_undo'], DB::table('usage_counts')->orderBy('kind')->pluck('kind')->all());
    }

    public function test_off_counts_nothing_and_flooding_is_limited(): void
    {
        config(['counts.enabled' => false]);
        $this->postJson('/api/counts', ['events' => [['kind' => 'app_open']]])->assertNoContent();
        $this->assertSame(0, DB::table('usage_counts')->count());
        $this->getJson('/api/auth/config')->assertJsonPath('data.usageCounts', false);

        // 30 a minute from one connection, the one sent while off among them.
        config(['counts.enabled' => true]);
        foreach (range(1, 29) as $i) {
            $this->postJson('/api/counts', ['events' => [['kind' => 'app_open']]])->assertNoContent();
        }
        $this->assertMatchesContract($this->postJson('/api/counts', ['events' => [['kind' => 'app_open']]]), 'counts.create', 429);
        $this->assertSame(29, (int) DB::table('usage_counts')->sum('count'));
    }

    public function test_the_admin_sees_each_day_the_totals_and_the_countries(): void
    {
        DB::table('usage_counts')->insert([
            ['day' => now()->toDateString(), 'kind' => 'lesson_done', 'audience' => 'guest', 'country' => 'US', 'count' => 3],
            ['day' => now()->toDateString(), 'kind' => 'lesson_done', 'audience' => 'account', 'country' => 'GB', 'count' => 5],
            ['day' => now()->subDays(40)->toDateString(), 'kind' => 'lesson_done', 'audience' => 'account', 'country' => '', 'count' => 9],
        ]);
        $this->actingAsAdmin();

        $week = $this->getJson('/api/admin/counts?days=7');
        $this->assertMatchesContract($week, 'admin.counts.show', 200);
        $week->assertJsonPath('data.totals.0', ['kind' => 'lesson_done', 'guest' => 3, 'account' => 5])->assertJsonPath('data.countries.0', ['country' => 'GB', 'count' => 5]);
        $this->getJson('/api/admin/counts?days=90')->assertJsonPath('data.totals.0.account', 14)->assertJsonPath('data.countries.0', ['country' => null, 'count' => 9]);

        // Today alone: yesterday's counts stay out.
        DB::table('usage_counts')->insert(['day' => now()->subDay()->toDateString(), 'kind' => 'lesson_done', 'audience' => 'guest', 'country' => 'US', 'count' => 4]);
        $today = $this->getJson('/api/admin/counts?days=1');
        $this->assertMatchesContract($today, 'admin.counts.show', 200);
        $today->assertJsonPath('data.totals.0', ['kind' => 'lesson_done', 'guest' => 3, 'account' => 5])->assertJsonPath('data.days.0.date', now()->toDateString());
    }

    public function test_the_server_counts_every_new_account_itself(): void
    {
        config(['counts.enabled' => true]);
        $this->postJson('/api/auth/register', ['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'a-fine-long-passphrase-42', 'birthMonth' => 5, 'birthYear' => 1990])->assertStatus(202);

        $this->assertSame(1, (int) DB::table('usage_counts')->where('kind', 'sign_up')->where('audience', 'account')->value('count'));
    }
}
