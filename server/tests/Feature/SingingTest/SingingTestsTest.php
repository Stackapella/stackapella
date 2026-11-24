<?php

declare(strict_types=1);

namespace Tests\Feature\SingingTest;

use App\Actions\Users\DeleteAccount;
use App\Models\FlagUser;
use App\Models\SingingTest;
use App\Models\User;
use App\Support\Flags;
use App\Support\SingingTest\Scoring;
use Database\Factories\SingingTestFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The singing test's results (docs/singing-test-v1.md): numbers only, scored by the server exactly as the app scores
 * them (the shared cases in contract/singing-test/), the first counted test a warm-up afterwards, and the switch.
 */
final class SingingTestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 12:00:00', 'UTC'));
        config(['counts.enabled' => true]);
    }

    private function singer(): User
    {
        $user = User::factory()->create();
        FlagUser::query()->forceCreate(['flag' => 'singing_test', 'user_id' => $user->id]);

        return $user->fresh() ?? throw new \LogicException;
    }

    /**
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    private function body(float $cents = 15.0, array $with = []): array
    {
        return ['id' => (string) Str::uuid(), 'testVersion' => 1, 'scoringVersion' => 1, 'takenAt' => now()->toIso8601ZuluString(), 'anchor' => 57.4, 'device' => 'phone', 'warmUp' => false,
            ...SingingTestFactory::items($cents), 'score' => ['total' => null, 'match' => null, 'back' => null, 'hold' => null, 'band' => null], ...$with];
    }

    public function test_the_server_scores_every_shared_case_as_the_app_does(): void
    {
        $doc = json_decode((string) file_get_contents(base_path('../contract/singing-test/scoring-v1.json')), true);
        $this->assertSame(Scoring::VERSION, $doc['scoringVersion']);
        $this->assertGreaterThanOrEqual(13, count($doc['cases']));

        foreach ($doc['cases'] as $case) {
            $test = $case['test'];
            // JSON's numbers as the app sends them: 45.0 and 45 alike.
            $this->assertEquals($case['expected'], Scoring::score($test['match'], $test['back'], $test['hold'], $test['warmUp']), $case['name']);
        }
    }

    public function test_a_test_is_kept_as_numbers_scored_by_the_server_and_the_same_one_again_answers_the_same(): void
    {
        $this->actingAs($this->singer());
        $body = $this->body(15.0, ['score' => ['total' => 96, 'match' => 43.4, 'back' => 40.0, 'hold' => 12.0, 'band' => 'inTune']]);

        $made = $this->postJson('/api/singing-tests', $body);
        $this->assertMatchesContract($made, 'singingTests.create', 201);
        // 15 cents everywhere: match 43.4, back 40, hold (10 + 4 and 12 + 3: 14.5) 13.65.
        $made->assertJsonPath('data.status', 'counted')->assertJsonPath('data.band', 'inTune')->assertJsonPath('data.scores.match', 43.4)->assertJsonPath('data.total', 97);
        // The device's 96 isn't the server's 97: counted, and the server's kept.
        $this->assertSame(1, (int) DB::table('usage_counts')->where('kind', 'st_mismatch')->sum('count'));

        $again = $this->postJson('/api/singing-tests', $body);
        $this->assertMatchesContract($again, 'singingTests.create', 200);
        $this->assertSame(1, SingingTest::query()->count());

        $changed = $body;
        $changed['match'][0]['centsOff'] = 3.0;
        $this->postJson('/api/singing-tests', $changed)->assertStatus(422)->assertJsonValidationErrors('id');

        $bad = $this->body();
        $bad['match'][3]['centsOff'] = 5000;
        $this->assertMatchesContract($this->postJson('/api/singing-tests', $bad), 'singingTests.create', 422);
        $this->postJson('/api/singing-tests', $this->body(15.0, ['match' => [['target' => 60, 'centsOff' => 4, 'octave' => 0, 'landingMs' => 200, 'jitterCents' => 3, 'startAfterPromptMs' => 100, 'audio' => 'UklGRg==']]]))->assertStatus(422);
        $this->postJson('/api/singing-tests', $this->body(15.0, ['testVersion' => 2]))->assertStatus(422);
    }

    public function test_too_few_heard_is_kept_as_practice_without_a_total_or_band(): void
    {
        $this->actingAs($this->singer());
        $body = $this->body(40.0);

        foreach ([0, 1, 2, 3, 4] as $i) {
            $body['match'][$i]['centsOff'] = null;
        }

        $this->postJson('/api/singing-tests', $body)->assertCreated()->assertJsonPath('data.status', 'practice')->assertJsonPath('data.total', null)->assertJsonPath('data.band', null);
    }

    public function test_the_history_is_newest_first_and_scored_again_with_a_newer_scoring(): void
    {
        $user = $this->singer();
        $old = SingingTest::factory()->create(['user_id' => $user->id, 'taken_at' => now()->subDays(3)]);
        $new = SingingTest::factory()->create(['user_id' => $user->id, 'taken_at' => now()->subDay()]);
        SingingTest::factory()->create(['taken_at' => now()]);
        // Scored by an older version, with a stale total.
        $old->forceFill(['scored_version' => 0, 'total' => 1])->save();

        $this->actingAs($user);
        $list = $this->getJson('/api/singing-tests');
        $this->assertMatchesContract($list, 'singingTests.list', 200);
        $this->assertSame([$new->uuid, $old->uuid], array_column((array) $list->json('data'), 'id'));
        $this->assertSame([Scoring::VERSION, 94], [$list->json('data.1.scoringVersion'), $list->json('data.1.total')]);
        $this->assertSame(Scoring::VERSION, $old->refresh()->scored_version);
    }

    public function test_only_the_first_counted_test_becomes_a_warm_up(): void
    {
        $user = $this->singer();
        $practice = SingingTest::factory()->create(['user_id' => $user->id, 'taken_at' => now()->subDays(4), 'items' => SingingTestFactory::items(40.0)]);
        $practice->forceFill(['items' => ['match' => [], 'back' => [], 'hold' => []]])->rescore()->save();
        $first = SingingTest::factory()->create(['user_id' => $user->id, 'taken_at' => now()->subDays(3)]);
        $second = SingingTest::factory()->create(['user_id' => $user->id, 'taken_at' => now()->subDays(2)]);
        $this->actingAs($user);

        $marked = $this->patchJson('/api/singing-tests/'.$first->uuid, ['warmUp' => true]);
        $this->assertMatchesContract($marked, 'singingTests.update', 200);
        $marked->assertJsonPath('data.status', 'warmup')->assertJsonPath('data.warmUp', true);

        $this->assertMatchesContract($this->patchJson('/api/singing-tests/'.$second->uuid, ['warmUp' => true]), 'singingTests.update', 422);
        $this->patchJson('/api/singing-tests/'.$practice->uuid, ['warmUp' => true])->assertStatus(422);
        // Counted again, it's counted.
        $this->patchJson('/api/singing-tests/'.$first->uuid, ['warmUp' => false])->assertOk()->assertJsonPath('data.status', 'counted');
        $this->assertMatchesContract($this->patchJson('/api/singing-tests/'.Str::uuid(), ['warmUp' => true]), 'singingTests.update', 404);
    }

    public function test_the_switch_staff_first_then_everyone_and_the_tests_go_with_the_account(): void
    {
        $someone = User::factory()->create();
        $this->actingAs($someone);
        $this->getJson('/api/auth/config')->assertJsonPath('data.singingTest', false)->assertJsonPath('data.singingTestBoards', false);
        $this->assertMatchesContract($this->getJson('/api/singing-tests'), 'singingTests.list', 404);
        $this->assertMatchesContract($this->postJson('/api/singing-tests', $this->body()), 'singingTests.create', 404);

        app(Flags::class)->set('singing_test', 'on', null, 'test');
        app(Flags::class)->forgetAccounts();
        $this->getJson('/api/auth/config')->assertJsonPath('data.singingTest', true)->assertJsonPath('data.singingTestBoards', false);
        $this->postJson('/api/singing-tests', $this->body())->assertCreated();

        app(DeleteAccount::class)->handle($someone->fresh() ?? $someone, '203.0.113.5');
        $this->assertSame(0, SingingTest::query()->count());
    }
}
