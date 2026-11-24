<?php

declare(strict_types=1);

namespace Tests\Feature\SingingTest;

use App\Models\BoardConnection;
use App\Models\FlagUser;
use App\Models\SingingTest;
use App\Models\User;
use App\Support\Flags;
use Database\Factories\SingingTestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The singing test's boards (docs/singing-test-v1.md, "The boards"): seasons of a month, Best and Most improved, places
 * shared within 8 points, the part boards' rules for who's on them, and tests held for a look.
 */
final class SingingTestBoardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 12:00:00', 'UTC'));
        config(['counts.enabled' => true]);
        app(Flags::class)->set('singing_test', 'on', null, 'test');
        app(Flags::class)->set('singing_test_boards', 'on', null, 'test');
    }

    private function adult(string $name, bool $global = true): User
    {
        $user = User::factory()->create(['name' => $name]);
        $user->forceFill(['adult_confirmed_at' => now()->subYear(), 'board_global_at' => $global ? now()->subDay() : null])->save();

        return $user->fresh() ?? throw new \LogicException;
    }

    /**
     * Counted tests with these totals, a day apart from $from days ago (oldest first).
     *
     * @param  list<int>  $totals
     */
    private function tests(User $user, array $totals, int $from, int $step = 1): void
    {
        foreach ($totals as $i => $total) {
            $test = SingingTest::factory()->create(['user_id' => $user->id, 'taken_at' => now()->subDays($from - $i * $step)]);
            $test->forceFill(['total' => $total])->saveQuietly();
        }
    }

    public function test_best_ranks_each_seasons_best_three_of_its_first_four_and_shares_places_within_eight(): void
    {
        $ada = $this->adult('Ada Lovelace');
        $grace = $this->adult('Grace Hopper');
        $alan = $this->adult('Alan Turing');
        $hidden = $this->adult('Not Opted In', global: false);
        // Best 3 of the first 4 (90, 80, 70; the 20 left out); the fifth goes to history only.
        $this->tests($ada, [90, 80, 20, 70, 100], 20);
        $this->tests($grace, [70, 74, 72], 10);
        $this->tests($alan, [50, 50, 50], 6);
        $this->tests($hidden, [99, 99, 99], 6);

        $this->actingAs($ada);
        $shown = $this->getJson('/api/singing-tests/boards?board=best');
        $this->assertMatchesContract($shown, 'singingTests.boards.show', 200);
        $entries = collect($shown->json('data.entries'));
        $this->assertSame(['Ada Lovelace', 'Grace Hopper', 'Alan Turing'], $entries->pluck('name')->all());
        $this->assertSame([80.0, 72.0, 50.0], $entries->pluck('score')->map(fn ($s): float => (float) $s)->all());
        // 80 and 72 are within 8: a shared first place; 50 is more than 8 below both.
        $this->assertSame([1, 1, 3], $entries->pluck('rank')->all());
        $this->assertSame([true, 4], [$entries[0]['you'], $entries[0]['tests']]);
        $shown->assertJsonPath('data.you.rank', 1)->assertJsonPath('data.you.needs', ['tests' => 0, 'days' => 0])->assertJsonPath('data.onGlobal', true);

        $this->getJson('/api/singing-tests/boards?board=best&month=2026-10')->assertOk()->assertJsonPath('data.entries', []);
        $this->assertMatchesContract($this->getJson('/api/singing-tests/boards?board=fastest'), 'singingTests.boards.show', 422);
    }

    public function test_a_season_needs_three_tests_on_two_days_and_you_says_what_is_missing(): void
    {
        $ada = $this->adult('Ada Lovelace');
        // Three tests the same day.
        $this->tests($ada, [80, 80, 80], 3, step: 0);
        $this->actingAs($ada);

        $shown = $this->getJson('/api/singing-tests/boards?board=best')->assertOk();
        $this->assertSame([], $shown->json('data.entries'));
        $shown->assertJsonPath('data.you.needs', ['tests' => 0, 'days' => 1])->assertJsonPath('data.you.score', null);
    }

    public function test_most_improved_counts_from_the_better_of_the_first_two_and_is_capped(): void
    {
        $ada = $this->adult('Ada Lovelace');
        $grace = $this->adult('Grace Hopper');
        // Last month's first two (40, 30): a baseline of 40. This month: 85, 90, 80, the mean 85, so 45 capped at 30.
        $this->tests($ada, [40, 30], 40);
        $this->tests($ada, [85, 90, 80], 10);
        // Baseline 70 (the better of 60 and 70): this month's three are the first ones too, mean 75, so 5.
        $this->tests($grace, [60, 70, 95], 8);
        $this->actingAs($grace);

        $entries = collect($this->getJson('/api/singing-tests/boards?board=improved')->assertOk()->json('data.entries'));
        $this->assertSame(['Ada Lovelace', 'Grace Hopper'], $entries->pluck('name')->all());
        $this->assertEquals([30, 5], $entries->pluck('score')->all());
    }

    public function test_friends_boards_show_friends_and_under_18s_by_initials(): void
    {
        $ada = $this->adult('Ada Lovelace', global: false);
        $kid = User::factory()->create(['name' => 'Young Singer']);
        $kid->forceFill(['initials' => 'YS'])->save();
        BoardConnection::factory()->create(['user_id' => $ada->id, 'friend_id' => $kid->id]);
        $this->tests($ada, [70, 70, 70], 6);
        $this->tests($kid, [60, 60, 60], 6);
        $this->actingAs($ada);

        $this->assertSame([], $this->getJson('/api/singing-tests/boards?board=best')->json('data.entries'));
        $friends = collect($this->getJson('/api/singing-tests/boards?board=best&scope=friends')->assertOk()->json('data.entries'));
        // Ada by name; the under-18 by initials only, never a name.
        $this->assertSame([true, false], $friends->pluck('you')->all());
        $this->assertSame([null, 'YS'], $friends->pluck('initials')->all());
        $this->assertNotNull($friends[0]['name']);
        $this->assertNull($friends[1]['name']);
    }

    public function test_a_held_test_keeps_its_season_off_the_global_board_until_cleared_or_two_days_pass(): void
    {
        $ada = $this->adult('Ada Lovelace');
        $grace = $this->adult('Grace Hopper');
        FlagUser::query()->forceCreate(['flag' => 'singing_test', 'user_id' => $grace->id]);
        $this->tests($grace, [60, 62, 61], 6);
        $this->actingAs($grace);

        // A jump of 25 or more over her best before, and notes flat as a synth's: held, and counted.
        $items = SingingTestFactory::items(2.0);
        array_walk($items['match'], static function (array &$item): void {
            $item['jitterCents'] = 0.2;
        });
        array_walk($items['back'], static function (array &$pattern): void {
            $pattern['notes'] = array_map(static fn (array $note): array => [...$note, 'jitterCents' => 0.2], $pattern['notes']);
        });
        $body = ['id' => (string) Str::uuid(), 'testVersion' => 1, 'scoringVersion' => 1, 'takenAt' => now()->toIso8601ZuluString(), 'anchor' => 57.0, 'device' => 'computer', 'warmUp' => false, ...$items];
        $this->postJson('/api/singing-tests', $body)->assertCreated();
        $held = SingingTest::query()->latest('id')->firstOrFail();
        $this->assertSame(['jump', 'flat'], $held->flag_reasons);
        $this->assertSame(1, (int) DB::table('usage_counts')->where('kind', 'st_held')->sum('count'));
        // The same items again under another id: a copy.
        $this->postJson('/api/singing-tests', [...$body, 'id' => (string) Str::uuid(), 'takenAt' => now()->subMinute()->toIso8601ZuluString()])->assertCreated();
        $this->assertContains('duplicate', (array) SingingTest::query()->latest('id')->firstOrFail()->flag_reasons);

        // Grace still sees her season; Ada doesn't, until it's cleared.
        $this->assertSame(['Grace Hopper'], collect($this->getJson('/api/singing-tests/boards?board=best')->json('data.entries'))->pluck('name')->all());
        $this->actingAs($ada);
        $this->assertSame([], $this->getJson('/api/singing-tests/boards?board=best')->json('data.entries'));

        $admin = $this->actingAsAdmin();
        $flags = $this->getJson('/api/admin/singing-tests/flags');
        $this->assertMatchesContract($flags, 'admin.singingTests.flags.list', 200);
        $this->assertSame([$held->uuid], collect($flags->json('data'))->where('id', $held->uuid)->pluck('id')->values()->all());
        $this->postJson('/api/admin/singing-tests/'.$held->uuid.'/review')->assertNoContent();
        $this->assertMatchesContract($this->postJson('/api/admin/singing-tests/'.Str::uuid().'/review'), 'admin.singingTests.review', 404);
        $copy = SingingTest::query()->latest('id')->firstOrFail();
        $this->deleteJson('/api/admin/singing-tests/'.$copy->uuid, ['reason' => 'A copy of another test.'])->assertNoContent();
        $this->assertMatchesContract($this->deleteJson('/api/admin/singing-tests/'.$copy->uuid, ['reason' => 'Again.']), 'admin.singingTests.hide', 404);
        $this->assertNotNull($copy->refresh()->hidden_at);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'singing_test.hidden')->where('actor_id', $admin->id)->count());

        $this->actingAs($ada);
        $this->assertSame(['Grace Hopper'], collect($this->getJson('/api/singing-tests/boards?board=best')->json('data.entries'))->pluck('name')->all());
    }

    public function test_the_boards_switch(): void
    {
        $ada = $this->adult('Ada Lovelace');
        app(Flags::class)->set('singing_test_boards', 'off', null, 'test');
        $this->actingAs($ada);
        $this->assertMatchesContract($this->getJson('/api/singing-tests/boards?board=best'), 'singingTests.boards.show', 404);
        $this->getJson('/api/auth/config')->assertJsonPath('data.singingTest', true)->assertJsonPath('data.singingTestBoards', false);
    }
}
