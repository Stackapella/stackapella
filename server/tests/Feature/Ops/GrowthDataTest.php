<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Growth\GrowthExport;
use App\Console\Commands\RecordPageVersions;
use App\Models\AuditLog;
use App\Models\OutreachMessage;
use App\Models\OutreachTarget;
use App\Models\Project;
use App\Models\Score;
use App\Models\SupportMessage;
use App\Models\Take;
use App\Models\User;
use App\Support\Ops\Releases;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The growth data (docs/growth-data.md): GET /api/internal/growth behind its bearer token, and the
 * payload aggregates only, with no person in it.
 */
final class GrowthDataTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'a3f1c9e07b2d4e6f8a1b3c5d7e9f0a2b4c6d8e0f';

    private const URL = '/api/internal/growth';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-07 12:00:00', 'UTC'));
        config(['services.growth_data.token' => self::TOKEN]);
    }

    private function counted(string $day, string $kind, int $n, string $audience = 'guest', string $source = '', string $country = ''): void
    {
        DB::table('usage_counts')->insert(['day' => $day, 'kind' => $kind, 'audience' => $audience, 'country' => $country, 'variant' => '', 'source' => $source, 'count' => $n]);
    }

    public function test_it_is_off_without_a_long_enough_token(): void
    {
        config(['services.growth_data.token' => null]);
        $this->getJson(self::URL, ['Authorization' => 'Bearer '.self::TOKEN])->assertNotFound();

        config(['services.growth_data.token' => 'too-short-to-guard-anything']);
        $this->getJson(self::URL, ['Authorization' => 'Bearer too-short-to-guard-anything'])->assertNotFound();
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_a_missing_or_wrong_token_is_refused_and_the_first_refusal_an_hour_is_logged(): void
    {
        $this->getJson(self::URL)->assertUnauthorized();
        $this->getJson(self::URL)->assertUnauthorized();
        $this->getJson(self::URL, ['Authorization' => 'Bearer '.str_repeat('x', 40)])->assertUnauthorized();
        // Never from the address: a token there would be in every access log.
        $this->getJson(self::URL.'?token='.self::TOKEN)->assertUnauthorized();

        $refusals = AuditLog::query()->where('action', 'growth.refused')->get();
        $this->assertSame(['no_bearer', 'mismatch'], $refusals->map(fn (AuditLog $log): string => (string) ($log->after['code'] ?? ''))->all());
        $this->assertStringNotContainsString(self::TOKEN, (string) json_encode($refusals->pluck('after')));
    }

    public function test_the_right_token_gets_every_section_never_cached_and_each_pull_is_logged(): void
    {
        $response = $this->getJson(self::URL.'?range=7', ['Authorization' => 'Bearer '.self::TOKEN])->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame(GrowthExport::SECTIONS, array_keys((array) $response->json()));
        $response->assertJsonPath('meta.schema_version', GrowthExport::SCHEMA_VERSION)->assertJsonPath('meta.range.name', '7')->assertJsonPath('meta.range.days', 7);
        $this->assertSame(['range' => '7'], array_intersect_key((array) AuditLog::query()->where('action', 'growth.pulled')->sole()->after, ['range' => true]));

        // An unknown range is the default, never an error.
        $this->getJson(self::URL.'?range=999', ['Authorization' => 'Bearer '.self::TOKEN])->assertOk()->assertJsonPath('meta.range.name', '30');
    }

    public function test_one_build_at_a_time_and_throttled(): void
    {
        $lock = Cache::lock('growth-data:build', 60);
        $this->assertTrue($lock->get());
        $this->getJson(self::URL, ['Authorization' => 'Bearer '.self::TOKEN])->assertStatus(429)->assertHeader('Retry-After', '30');
        $lock->release();

        // Ten a minute: the refused build above was one of them.
        for ($i = 0; $i < 9; $i++) {
            $this->getJson(self::URL, ['Authorization' => 'Bearer '.self::TOKEN])->assertOk();
        }
        $this->getJson(self::URL, ['Authorization' => 'Bearer '.self::TOKEN])->assertStatus(429);
    }

    public function test_no_person_organization_or_free_text_ever_appears(): void
    {
        $person = User::factory()->create(['name' => 'Zebulon Quiverfeather', 'email' => 'zebulon.sentinel@example.com', 'handle' => 'zqsentinel', 'country' => 'NZ', 'created_at' => now()->subDays(20)]);
        $project = Project::factory()->create(['user_id' => $person->id, 'title' => 'Xyzzy Sentinel Ballad']);
        Take::factory()->create(['recorded_by' => $person->id, 'created_at' => now()->subDays(18)]);
        Score::factory()->create(['user_id' => $person->id, 'recorded_at' => now()->subDays(12)]);
        SupportMessage::factory()->create(['email' => 'plugh.sentinel@example.com', 'body' => 'Plugh sentinel feedback text']);
        $org = OutreachTarget::factory()->create(['name' => 'Frobozz Sentinel Chorus', 'contact_email' => 'frobozz@example.org', 'ref' => 'o-frobozz', 'angle' => 'school']);
        OutreachMessage::factory()->sent(now()->subDays(3))->create(['outreach_target_id' => $org->id, 'body' => 'Hello Frobozz Sentinel Chorus, '.url('/?ref=o-frobozz')]);
        $this->counted('2026-11-05', 'f_arrive', 9, source: 'o-frobozz');
        $this->counted('2026-11-05', 'site_visit', 4, source: 'tool-tuner', country: 'NZ');

        $json = (string) json_encode(app(GrowthExport::class)->build('all'));

        foreach (['Zebulon', 'Quiverfeather', 'zebulon', 'zqsentinel', 'Xyzzy', 'Plugh', 'plugh', 'Frobozz', 'frobozz', '@', $project->uuid, (string) $person->google_id, self::TOKEN] as $sentinel) {
            $this->assertStringNotContainsString($sentinel, $json, "The payload carries {$sentinel}.");
        }
    }

    public function test_the_numbers_are_aggregates_folded_and_suppressed(): void
    {
        // Three sign-ups in the week of 2026-10-05: one sang the next day and again on day 8.
        $a = User::factory()->create(['created_at' => '2026-10-05 10:00:00', 'country' => 'GB']);
        User::factory()->count(2)->create(['created_at' => '2026-10-06 10:00:00', 'country' => 'GB']);
        Take::factory()->create(['recorded_by' => $a->id, 'created_at' => '2026-10-06 22:00:00']);
        Score::factory()->create(['user_id' => $a->id, 'recorded_at' => '2026-10-13 12:00:00']);
        // Three in the week of 2026-10-19, and an admin, who never counts.
        User::factory()->count(2)->create(['created_at' => '2026-10-20 10:00:00', 'country' => 'GB']);
        User::factory()->create(['created_at' => '2026-10-20 10:00:00', 'country' => 'IS']);
        User::factory()->admin()->create(['created_at' => '2026-10-20 10:00:00']);

        $this->counted('2026-11-05', 'site_visit', 40, source: 'reddit');
        $this->counted('2026-11-05', 'f_arrive', 12, source: 'reddit');
        $this->counted('2026-11-05', 'f_arrive', 2, source: 'o-somechoir');
        $this->counted('2026-11-05', 'site_visit', 2, source: 'embed-example-org');
        $this->counted('2026-11-05', 'site_visit', 6, source: 'tool-metronome');
        $this->counted('2026-11-05', 'f_arrive', 1, source: 'tool-pitch-pipe');
        $this->counted('2026-11-05', 'f_course_lesson', 1, source: 'tool-pitch-pipe');
        $this->counted('2026-11-06', 'ask_account_shown', 3);
        $this->counted('2026-11-06', 'ask_acct_week_not', 2);
        $this->counted('2026-11-05', 'fl_s:1-comfortable', 5);
        $this->counted('2026-11-05', 'fl_s:1-comfortable', 2, 'account');
        $this->counted('2026-11-05', 'pl_d:barbershop', 1, 'account');
        $this->counted('2026-11-06', 'ask_acct_course_shown', 4);
        $this->counted('2026-11-06', 'ask_acct_course_yes', 1);
        $this->counted('2026-11-06', 'ask_acct_course_lesson_shown', 3);
        $this->counted('2026-11-06', 'sv_courses', 6);
        $this->counted('2026-11-05', 'sv_courses', 2);
        $this->counted('2026-11-06', 'sv_tools', 1);
        $this->counted('2026-11-06', 'sv_part', 9);
        $this->counted('2026-11-06', 'part_play', 4);
        $this->counted('2026-11-06', 'lesson_start', 5, country: 'GB');
        $this->counted('2026-11-06', 'lesson_start', 1, 'account', country: 'IS');
        DB::table('releases')->insert(['build' => 'abc1234def5678', 'first_seen_at' => '2026-11-06 09:00:00']);

        $data = app(GrowthExport::class)->build('30');

        $week = collect($data['cohorts']['weeks'])->firstWhere('week', '2026-10-05');
        $this->assertSame(['week' => '2026-10-05', 'signedUp' => 3, 'suppressed' => false, 'day1' => 1, 'days7to13' => 1, 'days30to59' => null], $week);
        $small = collect($data['cohorts']['weeks'])->firstWhere('week', '2026-10-19');
        $this->assertSame([3, false], [$small['signedUp'], $small['suppressed']]);
        $this->assertSame(['week' => '2026-10-26', 'signedUp' => 0, 'suppressed' => false, 'day1' => null, 'days7to13' => null, 'days30to59' => null], collect($data['cohorts']['weeks'])->firstWhere('week', '2026-10-26'));

        // The admin never counts; the take's project owner (no country) and IS are folded together.
        $this->assertSame(User::query()->where('is_admin', false)->count(), $data['signups']['total']);
        $this->assertSame(['GB' => 5, '(other)' => 2], $data['signups']['countries']);
        $this->assertSame(['GB' => 5, '(other)' => 1], array_intersect_key($data['daily']['countries'], ['GB' => 1, '(other)' => 1]));
        $this->assertSame(5, $data['daily']['kinds']['lesson_start']['guest'][array_search('2026-11-06', $data['daily']['dates'], true)]);

        $sources = collect($data['sources'])->keyBy('source');
        $this->assertSame(['campaign', 40, 12], [$sources['reddit']['type'], $sources['reddit']['visits'], $sources['reddit']['arrivals']]);
        $this->assertSame(['tool', 6], [$sources['tool-metronome']['type'], $sources['tool-metronome']['visits']]);
        // An organization's tag reads as outreach; under three of anything is folded.
        $this->assertFalse($sources->has('o-somechoir'));
        $this->assertFalse($sources->has('embed-example-org'));
        $this->assertSame(2, $sources['(other)']['groups']);
        // A tool's own tag is never folded, and every live tool page has its row, at nothing too.
        $this->assertSame(['tool', 1], [$sources['tool-pitch-pipe']['type'], $sources['tool-pitch-pipe']['arrivals']]);
        // A tool that sends singers to another course's first lesson: arrivals, then that lesson done.
        $this->assertSame([0, 1], [$sources['tool-pitch-pipe']['lesson1'], $sources['tool-pitch-pipe']['courseLesson']]);
        foreach ([...SitePages::listed(), ...SitePages::variants()] as $slug) {
            $this->assertTrue($sources->has('tool-'.$slug), "tool-{$slug} has its row");
        }
        $this->assertSame([0, 0], [$sources['tools-embed']['visits'], $sources['tools-embed']['arrivals']]);
        // So does every share tag: a friend from a shared card or lesson, never folded.
        $this->assertSame(['share', 0, 0], [$sources['share-lesson']['type'], $sources['share-lesson']['visits'], $sources['share-lesson']['arrivals']]);
        $this->assertSame('share', $sources['share-range']['type']);
        // A tool's share card: its own row from its first day, never folded.
        $this->assertSame(['share', 0], [$sources['share-interval-ear-training']['type'], $sources['share-interval-ear-training']['arrivals']]);

        $this->assertSame([['lesson' => '1-comfortable', 'started' => ['guest' => 5, 'account' => 2], 'finished' => ['guest' => 0, 'account' => 0]]], $data['courses']['freeCourse']);
        $this->assertSame([['course' => 'barbershop', 'started' => ['guest' => 0, 'account' => 0], 'finished' => ['guest' => 0, 'account' => 1]]], $data['courses']['byCourse']);
        $this->assertSame(['shown' => ['guest' => 4, 'account' => 0], 'yes' => ['guest' => 1, 'account' => 0], 'notNow' => ['guest' => 0, 'account' => 0]], $data['courses']['accountAsk']);
        // Every ask for an account, by what asked; the first-run ask has no Not now count.
        $asks = collect($data['courses']['accountAsks'])->keyBy('ask');
        $this->assertSame(['ask' => 'acct_course', ...$data['courses']['accountAsk']], $asks['acct_course']);
        $this->assertSame(['ask' => 'account', 'shown' => ['guest' => 3, 'account' => 0], 'yes' => ['guest' => 0, 'account' => 0], 'notNow' => null], $asks['account']);
        $this->assertSame(['guest' => 2, 'account' => 0], $asks['acct_week']['notNow']);
        // The course's ask by where it was met: each place its own row, with no Not now of its own.
        $this->assertSame(['ask' => 'acct_course_lesson', 'shown' => ['guest' => 3, 'account' => 0], 'yes' => ['guest' => 0, 'account' => 0], 'notNow' => null], $asks['acct_course_lesson']);
        $this->assertSame(['acct_course_home', 'acct_course_lesson', 'acct_course_map', 'acct_course_page'], $asks->keys()->filter(static fn (string $ask): bool => str_starts_with($ask, 'acct_course_'))->values()->all());

        // Every public page's loads by section, each section listed, at nothing too.
        $this->assertSame(['home' => 0, 'courses' => 8, 'course' => 0, 'lesson' => 0, 'tools' => 1, 'songs' => 0, 'part' => 9, 'guides' => 0, 'other' => 0], array_column($data['siteSections'], 'visits', 'section'));
        // A part page's loads, and those that played its part.
        $this->assertSame(['visits' => 9, 'plays' => 4], $data['partPlays']);

        $this->assertSame([['commit' => 'abc1234def5678', 'firstSeenAt' => '2026-11-06T09:00:00Z']], $data['releases']);
        // No dating run yet: null; after one, its summary (SitemapDatesTest).
        $this->assertNull($data['meta']['sitemapDates']);
        Cache::forever(RecordPageVersions::LAST, ['at' => '2026-11-08T02:20:00Z', 'read' => 2, 'dated' => 1, 'skipped' => 1, 'skippedPages' => ['/about (500)']]);
        $this->assertSame(1, app(GrowthExport::class)->build('30')['meta']['sitemapDates']['skipped']);
    }

    public function test_a_cohort_of_two_is_suppressed(): void
    {
        $a = User::factory()->create(['created_at' => '2026-10-05 10:00:00']);
        User::factory()->create(['created_at' => '2026-10-05 11:00:00']);
        Take::factory()->create(['recorded_by' => $a->id, 'created_at' => '2026-10-06 22:00:00']);

        $week = collect(app(GrowthExport::class)->build('30')['cohorts']['weeks'])->firstWhere('week', '2026-10-05');
        $this->assertSame(['week' => '2026-10-05', 'signedUp' => 2, 'suppressed' => true, 'day1' => null, 'days7to13' => null, 'days30to59' => null], $week);
    }

    public function test_the_heartbeat_notes_each_deployed_commit_once(): void
    {
        config(['app.build' => 'abc1234def5678']);
        $this->artisan('app:heartbeat')->assertSuccessful();
        $this->artisan('app:heartbeat')->assertSuccessful();
        Cache::flush();
        Releases::stamp();
        $this->assertSame(1, DB::table('releases')->count());

        // A container that doesn't know its commit notes nothing.
        config(['app.build' => null]);
        Releases::stamp();
        config(['app.build' => 'not a commit']);
        Releases::stamp();
        $this->assertSame(1, DB::table('releases')->count());
    }

    public function test_every_section_and_the_schema_version_are_documented(): void
    {
        // The public repo's tree has no docs/ at all (its CI gates deploys since 2026-11-10); here it always does.
        if (! is_dir(dirname(base_path()).'/docs')) {
            $this->markTestSkipped('docs/ is not in the public tree.');
        }

        $doc = (string) file_get_contents(dirname(base_path()).'/docs/growth-data.md');

        foreach (GrowthExport::SECTIONS as $section) {
            $this->assertStringContainsString("### `{$section}`", $doc, "docs/growth-data.md has no section for {$section}.");
        }

        $this->assertStringContainsString('Schema version: '.GrowthExport::SCHEMA_VERSION, $doc);
    }
}
