<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Counts\RecordCounts;
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
        $this->assertSame(['id', 'day', 'kind', 'audience', 'country', 'variant', 'count', 'source'], Schema::getColumnListing('usage_counts'));

        // A guest, with no country known: their own row, the same day's numbers adding up.
        $this->flushHeaders();
        $this->postJson('/api/counts', ['events' => [['kind' => 'app_open']]])->assertNoContent();
        $this->postJson('/api/counts', ['events' => [['kind' => 'app_open']]])->assertNoContent();
        $this->assertSame(2, (int) DB::table('usage_counts')->where('audience', 'guest')->where('country', '')->value('count'));
    }

    public function test_a_first_lesson_in_any_course_keeps_where_the_device_came_from_and_counts_once(): void
    {
        config(['counts.enabled' => true]);
        $this->postJson('/api/counts', ['events' => [['kind' => 'f_course_lesson', 'n' => 5], ['kind' => 'lesson_done']], 'source' => 'tool-pitch-pipe'])->assertNoContent();

        $rows = DB::table('usage_counts')->orderBy('kind')->get(['kind', 'source', 'count'])->map(fn (object $r): array => [(string) $r->kind, (string) $r->source, (int) $r->count])->all();
        // Its source kept, as every funnel kind's, and once a request; lesson_done keeps none.
        $this->assertSame([['f_course_lesson', 'tool-pitch-pipe', 1], ['lesson_done', '', 1]], $rows);

        // The tools' share cards keep their own tags, never folded into other.
        $this->assertSame(['share-pitch-matching', 'share-interval-ear-training', 'share-chord-ear-training'], array_map(RecordCounts::source(...), ['share-pitch-matching', 'Share-Interval-Ear-Training', 'share-chord-ear-training']));
    }

    public function test_the_usage_pages_kinds_and_a_course_or_lesson_only_by_a_real_id(): void
    {
        config(['counts.enabled' => true]);
        $this->postJson('/api/counts', ['events' => [['kind' => 'open_game'], ['kind' => 'take_undo'], ['kind' => 'sug_melody'], ['kind' => 'pl_d:vibrato'], ['kind' => 'fl_s:1-comfortable'], ['kind' => 'pl_d:no-such-course'], ['kind' => 'fl_s:nope'], ['kind' => 'zz:vibrato'], ['kind' => 'fs_r:1-comfortable.1'], ['kind' => 'fs_r:1-comfortable.99'], ['kind' => 'fs_r:nope.0']]])->assertNoContent();

        // A free lesson's step reached counts only for a real lesson and a step it has.
        $this->assertSame(['fl_s:1-comfortable', 'fs_r:1-comfortable.1', 'open_game', 'pl_d:vibrato', 'sug_melody', 'take_undo'], DB::table('usage_counts')->orderBy('kind')->pluck('kind')->all());
    }

    public function test_the_improvement_loops_kinds_count_and_a_share_counts_only_by_a_real_surface_and_method(): void
    {
        // docs/retention-plan.md, section 8: returns, the thread back, Home's new card, breadth and shares.
        $plain = ['back_d1', 'back_d7', 'back_d14', 'back_d30', 'back_w1', 'back_link_tap', 'back_cal_tap', 'ask_nudge_shown', 'ask_nudge_yes', 'ask_nudge_not', 'home_new_shown', 'home_new_tap'];
        // A tool's share (sh:tool-interval-ear-training.image, 36 characters) fits: the kind column is 64 wide (was 32).
        $detailed = ['ft:lesson', 'ft:together', 'br:0', 'br:11', 'sh:song.sheet', 'sh:page-carols.copy', 'sh:tool-pitch-pipe.embed', 'sh:tool-interval-ear-training.image'];
        $dropped = ['ft:nope', 'br:12', 'br:-1', 'sh:song', 'sh:song.fax', 'sh:nope.copy', 'sh:tool-nope.copy'];
        foreach (array_chunk([...$plain, ...$detailed, ...$dropped], 20) as $kinds) {
            $this->postJson('/api/counts', ['events' => array_map(static fn (string $kind): array => ['kind' => $kind], $kinds)])->assertNoContent();
        }

        $kept = DB::table('usage_counts')->pluck('kind')->all();
        sort($kept);
        $expected = [...$plain, ...$detailed];
        sort($expected);
        $this->assertSame($expected, $kept);
    }

    public function test_the_loops_paths_weekly_use_tools_and_try_next_kinds_count(): void
    {
        $kinds = ['sing_open', 'sing_kept', 'song_part3', 'lesson_song_back', 'cr_shared', 'grp_sang', 'part_sing', 'invite_sung', 'use_boss', 'remind_on',
            'tool_result:pitch-pipe', 'tool_try:pitch-pipe', 'try_shown:record', 'try_tap:stack'];
        $this->postJson('/api/counts', ['events' => array_map(static fn (string $kind): array => ['kind' => $kind], [...$kinds, 'tool_try:nope', 'try_tap:nope'])])->assertNoContent();

        $kept = DB::table('usage_counts')->pluck('kind')->all();
        sort($kept);
        sort($kinds);
        $this->assertSame($kinds, $kept);
    }

    public function test_a_units_song_offer_counts_by_its_place_only(): void
    {
        // Shown, tapped and a take kept, at each place a unit's end offers a song (config counts.song_offers).
        $kinds = ['us_shown:u1', 'us_tap:u1', 'us_kept:u1', 'us_shown:wall', 'us_kept:u4'];
        $this->postJson('/api/counts', ['events' => array_map(static fn (string $kind): array => ['kind' => $kind], [...$kinds, 'us_shown:u9', 'us_tap:anywhere', 'us_kept'])])->assertNoContent();

        $kept = DB::table('usage_counts')->pluck('kind')->all();
        sort($kept);
        sort($kinds);
        $this->assertSame($kinds, $kept);
    }

    public function test_be_the_voice_and_the_librarys_filters_count_only_by_their_known_ids(): void
    {
        // Be the voice (the engagement review) and the app's Browse all filters (frontend, 2026-11-12).
        $kinds = ['bv_sung', 'bv_shown', 'bv_later', 'bv_resume', 'bv_sheet_tap', 'bv_share_tap:guest', 'bv_share_tap:member', 'bv_stop:age', 'bv_stop:upload',
            'library_filter:all', 'library_filter:search', 'library_filter:part:alto', 'library_filter:folk-songs', 'library_filter:more', 'sh:voice.copy',
            'mic_primer', 'mic_granted', 'mic_denied', 'mic_failed_inapp', 'mic_failed_other', 'l1_inapp'];
        // Two posts: one takes at most 20 events.
        $post = fn (array $kinds): mixed => $this->postJson('/api/counts', ['events' => array_map(static fn (string $kind): array => ['kind' => $kind], $kinds)])->assertNoContent();
        $post([...array_slice($kinds, 0, 15), 'bv_stop:why', 'bv_share_tap:admin']);
        $post([...array_slice($kinds, 15), 'library_filter:part:descant', 'library_filter:anything']);

        $kept = DB::table('usage_counts')->pluck('kind')->all();
        sort($kept);
        sort($kinds);
        $this->assertSame($kinds, $kept);
    }

    public function test_every_courses_lessons_and_steps_count_only_by_a_real_lesson_and_step(): void
    {
        // A Plus course's and a song course's lessons, as the free course's are (docs/retention-plan.md, section 8).
        $plus = (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/course/plus/vibrato/course.json'), true);
        $lesson = (array) $plus['units'][0]['lessons'][0];
        $last = count((array) $lesson['steps']) - 1;
        $kinds = ['cl_s:'.$lesson['id'], 'cl_d:'.$lesson['id'], 'cs_r:'.$lesson['id'].'.0', 'cs_r:'.$lesson['id'].'.'.$last];
        $this->postJson('/api/counts', ['events' => array_map(static fn (string $kind): array => ['kind' => $kind], [...$kinds, 'cl_s:nope', 'cs_r:'.$lesson['id'].'.'.($last + 5), 'cs_r:nope.0', 'cl_s:1-comfortable'])])->assertNoContent();

        $kept = DB::table('usage_counts')->pluck('kind')->all();
        sort($kept);
        sort($kinds);
        // The free course's lessons are fl_s's, never cl_s's.
        $this->assertSame($kinds, $kept);
    }

    public function test_a_reddit_campaigns_tag_is_kept_as_written_and_nothing_else_like_it(): void
    {
        $this->assertSame('reddit-judaism', RecordCounts::source('reddit-judaism'));
        $this->assertSame('reddit-judaism', RecordCounts::source(' Reddit-Judaism '));
        $this->assertSame('reddit', RecordCounts::source('reddit'));
        $this->assertSame('reddit-'.str_repeat('a', 24), RecordCounts::source('reddit-'.str_repeat('a', 24)));
        $this->assertSame('other', RecordCounts::source('reddit-'.str_repeat('a', 25)), 'too long');
        $this->assertSame('other', RecordCounts::source('reddit-'), 'no subreddit');
        $this->assertSame('other', RecordCounts::source('reddit-ju_daism'), 'a character outside the tag');
        $this->assertSame('other', RecordCounts::source('reddit-ju daism'));
        $this->assertSame('other', RecordCounts::source('xreddit-judaism'));
    }

    public function test_the_loops_sources_and_every_share_surfaces_tag_are_kept_as_written(): void
    {
        foreach (['welcome', 'today', 'back-link', 'back-calendar', 'share-song', 'share-page-carols', 'share-lesson', 'share-pitch-pipe'] as $tag) {
            $this->assertSame($tag, RecordCounts::source($tag), $tag);
        }
        $this->assertSame('other', RecordCounts::source('share-nope'));
    }

    public function test_off_counts_nothing_and_flooding_is_limited(): void
    {
        config(['counts.enabled' => false]);
        $this->postJson('/api/counts', ['events' => [['kind' => 'app_open']]])->assertNoContent();
        $this->assertSame(0, DB::table('usage_counts')->count());
        $this->getJson('/api/auth/config')->assertJsonPath('data.usageCounts', false);

        // A minute's allowance from one connection (30 here), the one sent while off among them.
        config(['counts.enabled' => true, 'limits.counts.ip.max' => 30]);
        foreach (range(1, 29) as $i) {
            $this->postJson('/api/counts', ['events' => [['kind' => 'app_open']]])->assertNoContent();
        }
        // Past it, the same quiet 204 (a beacon's 429 is a console error), and nothing is counted.
        $this->assertMatchesContract($this->postJson('/api/counts', ['events' => [['kind' => 'app_open']]]), 'counts.create', 204);
        $this->postJson('/api/counts', ['events' => [['kind' => 'lesson_done']]])->assertNoContent();
        $this->assertSame(29, (int) DB::table('usage_counts')->sum('count'));
    }

    public function test_a_courses_lessons_count_by_its_id_and_an_unknown_id_is_dropped(): void
    {
        config(['counts.enabled' => true]);
        $plus = (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/course/plus/courses.json'), true);
        $course = (string) ($plus['courses'][0]['id'] ?? '');
        // A song's course counts too, once the content carries it (web/course/songs).
        // A few of them: one request takes at most 20 events.
        $songs = array_slice(RecordCounts::songCourses(), 0, 5);
        $kinds = ['pl_s:'.$course, 'pl_s:no-such-course', ...array_map(static fn (string $id): string => 'pl_d:'.$id, $songs)];

        $this->postJson('/api/counts', ['events' => array_map(static fn (string $kind): array => ['kind' => $kind], $kinds)])->assertNoContent();

        $this->assertSame(['pl_s:'.$course, ...array_map(static fn (string $id): string => 'pl_d:'.$id, $songs)], DB::table('usage_counts')->orderBy('id')->pluck('kind')->all());
    }

    public function test_the_courses_account_ask_is_counted_by_where_it_was_met_beside_its_total(): void
    {
        config(['counts.enabled' => true]);
        $kinds = ['ask_acct_course_shown', 'ask_acct_course_home_shown', 'ask_acct_course_lesson_shown', 'ask_acct_course_map_yes', 'ask_acct_course_page_yes', 'ask_acct_course_lesson_not'];
        $this->postJson('/api/counts', ['events' => array_map(static fn (string $kind): array => ['kind' => $kind], $kinds)])->assertNoContent();

        // Each place's shown and yes are kept; the Not now is the total's alone, so a place's is dropped.
        $this->assertSame(['ask_acct_course_home_shown', 'ask_acct_course_lesson_shown', 'ask_acct_course_map_yes', 'ask_acct_course_page_yes', 'ask_acct_course_shown'], DB::table('usage_counts')->orderBy('kind')->pluck('kind')->all());
    }

    public function test_a_well_known_crawler_counts_nothing_of_any_kind(): void
    {
        config(['counts.enabled' => true]);
        $events = ['events' => [['kind' => 'site_visit'], ['kind' => 'sv_lesson'], ['kind' => 'f_arrive']], 'source' => 'reddit'];

        foreach (['Mozilla/5.0 (Linux; Android 6.0.1; Nexus 5X Build/MMB29P) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)', 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)'] as $agent) {
            $this->withHeader('User-Agent', $agent)->postJson('/api/counts', $events)->assertNoContent();
        }
        $this->assertSame(0, DB::table('usage_counts')->count());

        // A phone whose agent holds "bot" in its brand still counts.
        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 12; Cubot KingKong 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Mobile Safari/537.36')->postJson('/api/counts', $events)->assertNoContent();
        $this->assertSame(['f_arrive', 'site_visit', 'sv_lesson'], DB::table('usage_counts')->orderBy('kind')->pluck('kind')->all());
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
