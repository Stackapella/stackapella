<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\DropoffReport;
use App\Models\GrowthViewer;
use App\Models\User;
use App\Support\Admin\SecondFactor;
use App\Support\Stats\ReportDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** admin.growth.export: a Growth page's numbers as CSV, the page's own anonymous totals, for admins and growth viewers. */
final class GrowthExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-23 12:00:00', 'UTC'));
    }

    private function counted(string $kind, int $n, string $source = ''): void
    {
        DB::table('usage_counts')->insert(['day' => ReportDay::today(), 'kind' => $kind, 'audience' => 'guest', 'country' => '', 'variant' => '', 'source' => $source, 'count' => $n]);
    }

    /**
     * @return list<list<string>>
     */
    private static function rows(string $csv): array
    {
        return array_map(static fn (string $line): array => str_getcsv($line, ',', '"', ''), array_values(array_filter(explode("\n", $csv), static fn (string $l): bool => $l !== '')));
    }

    public function test_each_page_downloads_its_own_numbers_as_csv(): void
    {
        $this->actingAsAdmin();
        $this->counted('choir_made_free', 2);
        $this->counted('own_choir_friend_ready', 1);

        foreach (['growth' => 'period=7d', 'funnel' => 'days=30', 'dropoffs' => '', 'sources' => 'days=7', 'choirs' => 'period=7d'] as $page => $query) {
            $made = $this->getJson("/api/admin/growth/export?page={$page}&{$query}");
            $this->assertMatchesContract($made, 'admin.growth.export', 200);
            $this->assertMatchesRegularExpression("/^stackapella-growth-{$page}-\\d{4}-\\d{2}-\\d{2}_\\d{4}-\\d{2}-\\d{2}\\.csv$/", (string) $made->json('data.filename'));
            $lines = self::rows((string) $made->json('data.csv'));
            $this->assertSame((int) $made->json('data.rows'), count($lines) - 1, "{$page}: the rows, after the header");
        }

        $choirs = $this->getJson('/api/admin/growth/export?page=choirs&period=7d');
        $lines = self::rows((string) $choirs->json('data.csv'));
        $this->assertSame(['date', 'made', 'free', 'plus', 'comped', 'together', 'part_minutes', 'failed_ours', 'failed_take'], $lines[0]);
        $this->assertCount(8, $lines, 'A row a day for 7 days.');
        $this->assertSame(['2026-11-23', '2', '2', '0', '0', '1'], array_slice($lines[7], 0, 6));
        $this->assertSame('stackapella-growth-choirs-2026-11-17_2026-11-23.csv', $choirs->json('data.filename'));

        // Each download is logged: who, which page and its period.
        $log = AuditLog::query()->where('action', 'admin.growth.export')->latest('id')->firstOrFail();
        $this->assertSame(['choirs', '7d', '2026-11-17', '2026-11-23'], [$log->after['page'] ?? null, $log->after['period'] ?? null, $log->after['from'] ?? null, $log->after['to'] ?? null]);
        $this->assertSame(6, AuditLog::query()->where('action', 'admin.growth.export')->count());
    }

    public function test_the_latest_drop_off_week_and_a_tag_that_looks_like_a_formula(): void
    {
        $this->actingAsAdmin();
        DropoffReport::query()->forceCreate(['week_end' => '2026-11-22', 'data' => [
            'weekStart' => '2026-11-16', 'weekEnd' => '2026-11-22',
            'paths' => [['path' => 'lesson:4-words', 'label' => 'Lesson 4, Words to a tune', 'steps' => [
                ['step' => 'fl_s:4-words', 'label' => 'started to step 1', 'entered' => 120, 'passed' => 110, 'passThrough' => 0.917, 'before' => 0.9, 'tooFew' => false, 'drop' => false, 'lost' => 0],
            ]]],
        ]]);
        $drops = self::rows((string) $this->getJson('/api/admin/growth/export?page=dropoffs')->assertOk()->json('data.csv'));
        $this->assertSame(['Lesson 4, Words to a tune', 'started to step 1', '120', '110', '0.917', '0.9', 'false', 'false', '0'], $drops[1]);

        // A tag anyone can put in a link, with enough arrivals to be shown on its own: kept as text, never a formula.
        $this->counted('site_visit', 9, '=HYPERLINK("http://example.com")');
        $this->counted('f_arrive', 6, '=HYPERLINK("http://example.com")');
        $sources = self::rows((string) $this->getJson('/api/admin/growth/export?page=sources&days=7')->assertOk()->json('data.csv'));
        $this->assertSame(['source', 'visits', 'arrivals', 'lesson1', 'course_lesson', 'signed_up', 'day2', 'week1'], $sources[0]);
        $this->assertSame(['\'=HYPERLINK("http://example.com")', '9', '6'], array_slice($sources[1], 0, 3));
    }

    public function test_a_growth_viewer_downloads_too_and_anyone_else_is_refused(): void
    {
        $person = User::factory()->create(['google_id' => 'viewer-google-id', 'email' => 'advisor@example.com']);
        GrowthViewer::factory()->create(['email' => 'advisor@example.com', 'google_id' => 'viewer-google-id']);
        app(SecondFactor::class)->enableTotp($person, app(SecondFactor::class)->newTotpSecret());
        $this->actingAs($person)->withSession($this->adminSession($person));

        $this->getJson('/api/admin/growth/export?page=funnel&days=7')->assertOk();
        $this->assertSame($person->id, AuditLog::query()->where('action', 'admin.growth.export')->sole()->actor_id);
        $this->getJson('/api/admin/growth/export?page=people')->assertUnprocessable();
        $this->getJson('/api/admin/growth/export?page=choirs&period=12w')->assertUnprocessable();
        $this->getJson('/api/admin/growth/export?page=sources&days=2')->assertUnprocessable();

        $this->flushSession();
        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/growth/export?page=funnel')->assertForbidden();
    }

    public function test_the_ai_choice_card_downloads_by_band_with_too_few_left_empty(): void
    {
        $this->actingAsAdmin();
        $this->counted('ai_card:shown:25-34', 10);
        $this->counted('ai_card:try:25-34', 4);
        $this->counted('ai_card:not:25-34', 2);

        $made = $this->getJson('/api/admin/growth/export?page=ai&period=90d');
        $this->assertMatchesContract($made, 'admin.growth.export', 200);
        $lines = self::rows((string) $made->json('data.csv'));
        $this->assertSame(['band', 'card_shown', 'card_try', 'card_not'], array_slice($lines[0], 0, 4));
        $this->assertContains('unset_back_d1', $lines[0]);
        $band = collect(array_slice($lines, 1))->firstWhere(0, '25-34');
        $this->assertSame(['25-34', '10', '4', ''], array_slice((array) $band, 0, 4), 'Two not-for-me answers are too few: empty.');
        $this->assertSame('all', $lines[count($lines) - 1][0]);
        $this->getJson('/api/admin/growth/export?page=ai&period=7d')->assertUnprocessable();
    }
}
