<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Counts\RecordCounts;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sharing (docs/retention-plan.md; Hillel, 2026-11-11: "make sure this is measured everywhere"): each surface's
 * shares, shared pages opened (counted by the server, never for a crawler), and what the arrivals by its tag went on
 * to do, beside last week; a shared song's h- tag reads as share-song.
 */
final class SharingTest extends TestCase
{
    use RefreshDatabase;

    private static function counted(string $day, string $kind, int $n, string $source = '', string $audience = 'guest'): void
    {
        DB::table('usage_counts')->insert(['day' => $day, 'kind' => $kind, 'audience' => $audience, 'country' => '', 'variant' => '', 'source' => $source, 'count' => $n]);
    }

    public function test_each_surface_reads_its_shares_opens_and_arrivals_beside_last_week(): void
    {
        $this->travelTo(Carbon::parse('2026-11-12 12:00:00', 'UTC'));
        self::counted('2026-11-10', 'sh:group.copy', 7);
        self::counted('2026-11-10', 'sh:group.sheet', 2);
        self::counted('2026-11-10', 'so:group', 40);
        self::counted('2026-11-10', 'f_arrive', 31, 'share-group');
        self::counted('2026-11-10', 'f_lesson1', 18, 'share-group');
        self::counted('2026-11-10', 'f_account', 9, 'share-group');
        self::counted('2026-11-10', 'app_week', 420);
        // A shared song's links before today were read as "harmony": still the song surface.
        self::counted('2026-11-09', 'f_arrive', 4, 'harmony');
        self::counted('2026-11-09', 'sh:song.copy', 2);
        // Last week.
        self::counted('2026-11-01', 'sh:group.copy', 3);
        self::counted('2026-11-01', 'app_week', 300);

        $this->actingAsAdmin();
        $shown = $this->getJson('/api/admin/sharing');
        $this->assertMatchesContract($shown, 'admin.sharing.show', 200);
        $surfaces = collect((array) $shown->json('data.surfaces'))->keyBy('surface');
        $this->assertSame([9, 7, 40, 31, 18, 9, 3.44], [$surfaces['group']['shares'], $surfaces['group']['methods']['copy'], $surfaces['group']['opened'], $surfaces['group']['arrivals'], $surfaces['group']['lesson1'], $surfaces['group']['signedUp'], $surfaces['group']['arrivalsPerShare']]);
        $this->assertSame([2, 4, 2.0], [$surfaces['song']['shares'], $surfaces['song']['arrivals'], (float) $surfaces['song']['arrivalsPerShare']]);
        $this->assertNull($surfaces['note']['opened']);
        $this->assertTrue($surfaces->has('tool-pitch-pipe'));

        $shown->assertJsonPath('data.headline.shares', 11)->assertJsonPath('data.headline.active', 420)->assertJsonPath('data.before.shares', 3);
        $this->assertEquals(2.6, $shown->json('data.headline.sharesPer100'));
        $this->assertEquals(round(18 * 100 / 420, 1), $shown->json('data.headline.eachHundredBring'));
        $this->assertNotNull($this->getJson('/api/admin/sharing')->json('data.before.sharesPer100'));
    }

    public function test_a_shared_page_opened_counts_by_surface_and_never_for_a_crawler(): void
    {
        config(['counts.enabled' => true]);
        $director = User::factory()->create()->fresh() ?? throw new \RuntimeException('no user');
        $this->actingAs($director);
        $group = (string) $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true])->assertCreated()->json('data.id');
        $token = (string) str($this->postJson('/api/groups/'.$group.'/link')->assertCreated()->json('data.url'))->afterLast('/choir/');
        auth()->forgetGuards();

        $this->getJson('/api/choir/'.$token)->assertOk();
        $this->getJson('/api/choir/nope')->assertNotFound();
        $this->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->getJson('/api/choir/'.$token)->assertOk();

        $this->assertSame(1, (int) DB::table('usage_counts')->where('kind', 'so:group')->sum('count'));
        // A shared song's own tag reads as the song surface's.
        $this->assertSame('share-song', RecordCounts::source('h-AbCdEf123456'));
    }
}
