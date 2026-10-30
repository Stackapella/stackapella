<?php

declare(strict_types=1);

namespace Tests\Feature\Boards;

use App\Actions\Boards\Boards;
use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\DailyScore;
use App\Models\Ghost;
use App\Models\PartScore;
use App\Models\Template;
use App\Models\User;
use App\Support\Boards\Daily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Game mode's boards: the daily challenge, arcade initials in place of a name, and the ghost of a
 * singer's best take.
 */
final class GameBoardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(SyncOfficialTemplates::class)->handle();
    }

    private function person(bool $adult = true, string $name = 'Ana Ruiz', bool $global = true): User
    {
        return User::factory()->create(['name' => $name, 'adult_confirmed_at' => $adult ? now() : null, 'birth_year' => $adult ? 1990 : (int) now()->year - 15, 'birth_month' => 5, 'board_global_at' => $adult && $global ? now() : null])->refresh();
    }

    /**
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    private function score(string $song, string $part, int $score, array $with = []): array
    {
        $version = (int) Template::query()->with('currentVersion')->where('slug', $song)->firstOrFail()->currentVersion?->version;

        return ['templateId' => $song, 'templateVersion' => $version, 'partKey' => $part, 'scoringVersion' => 1, 'pitchDetectorVersion' => 1, 'score' => $score, 'mode' => 'own', 'keyOffset' => 0, 'speed' => 1, 'guideMuted' => true, 'timingChecked' => true, 'output' => 'wired', ...$with];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function today(): array
    {
        $pick = Daily::pick(Daily::today());
        $this->assertNotNull($pick);

        return [$pick['template']->slug, $pick['part']];
    }

    public function test_the_day_has_one_pick_for_everyone_and_its_board_keeps_each_singers_best(): void
    {
        [$song, $part] = $this->today();
        $date = Daily::today();
        $ana = $this->person();
        $ben = $this->person(name: 'Ben Ode');

        $this->actingAs($ana);
        $sung = $this->postJson('/api/boards/scores', $this->score($song, $part, 80, ['daily' => $date]));
        $this->assertMatchesContract($sung, 'boards.scores.submit', 200);
        $sung->assertJsonPath('data.daily', ['counted' => true, 'best' => 80, 'rank' => 1]);
        $this->postJson('/api/boards/scores', $this->score($song, $part, 70, ['daily' => $date]))->assertJsonPath('data.daily.best', 80);
        // Without `daily`, null; another part doesn't count for the day.
        $this->postJson('/api/boards/scores', $this->score($song, $part, 75))->assertJsonPath('data.daily', null);
        $other = $part === 'alto' ? 'soprano' : 'alto';
        $this->postJson('/api/boards/scores', $this->score($song, $other, 99, ['daily' => $date]))->assertJsonPath('data.daily.counted', false);
        $this->actingAs($ben)->postJson('/api/boards/scores', $this->score($song, $part, 90, ['daily' => $date]))->assertJsonPath('data.daily.rank', 1);
        $this->assertSame([80, 90], DailyScore::query()->orderBy('user_id')->pluck('score')->all());

        $board = $this->actingAs($ana)->getJson('/api/boards/daily');
        $this->assertMatchesContract($board, 'boards.daily.show', 200);
        $board->assertJsonPath('data.templateId', $song)->assertJsonPath('data.partKey', $part)->assertJsonPath('data.date', $date)
            ->assertJsonPath('data.you', ['score' => 80, 'rank' => 2, 'initials' => null]);
        $this->assertSame(['Ben Ode', 'Ana Ruiz'], array_column((array) $board->json('data.entries'), 'name'));

        // Signed out: the day's pick, no one's names.
        auth()->forgetGuards();
        $this->getJson('/api/boards/daily')->assertOk()->assertJsonPath('data.entries', [])->assertJsonPath('data.you', null);

        // Their stamps: the days of the week they took it (today here; held counts, binned doesn't).
        DailyScore::factory()->for($ana)->create(['date' => now()->utc()->subDays(9)->toDateString()]);
        $this->actingAs($ana)->getJson('/api/boards/daily')->assertJsonPath('data.days', [$date]);
        PartScore::query()->where('user_id', $ana->id)->where('part_key', $part)->update(['flagged_at' => now()]);
        $this->getJson('/api/boards/daily?scope=friends')->assertJsonPath('data.days', [$date]);
        PartScore::query()->where('user_id', $ana->id)->where('part_key', $part)->update(['flagged_at' => null]);
        auth()->forgetGuards();
        $this->getJson('/api/boards/daily')->assertJsonPath('data.days', []);

        // A part score taken off takes its day's score off too.
        PartScore::query()->where('user_id', $ben->id)->where('part_key', $part)->update(['hidden_at' => now()]);
        $this->assertSame(['Ana Ruiz'], array_column((array) $this->actingAs($ana)->getJson('/api/boards/daily')->json('data.entries'), 'name'));
        $this->actingAs($ben)->getJson('/api/boards/daily')->assertJsonPath('data.days', []);
    }

    public function test_only_the_last_week_can_be_looked_at_and_a_past_day_takes_no_scores(): void
    {
        $this->actingAs($this->person());
        $this->assertMatchesContract($this->getJson('/api/boards/daily?date='.now()->utc()->subDays(8)->toDateString()), 'boards.daily.show', 422);
        $this->getJson('/api/boards/daily?date='.now()->utc()->addDay()->toDateString())->assertUnprocessable();
        $this->getJson('/api/boards/daily?date='.now()->utc()->subDays(3)->toDateString())->assertOk();

        $yesterday = now()->utc()->subDay()->toDateString();
        $pick = Daily::pick($yesterday);
        $this->assertNotNull($pick);
        $this->travelTo(now()->utc()->startOfDay()->addMinutes(10));
        $this->postJson('/api/boards/scores', $this->score($pick['template']->slug, $pick['part'], 80, ['daily' => now()->utc()->subDay()->toDateString()]))->assertJsonPath('data.daily.counted', false);
        $this->assertSame(0, DailyScore::query()->count());
    }

    public function test_initials_replace_the_name_when_chosen_and_always_for_someone_under_18(): void
    {
        $ana = $this->person();
        $this->actingAs($ana);

        $this->assertMatchesContract($this->patchJson('/api/account', ['boardsName' => 'initials']), 'account.update', 422);
        $this->assertMatchesContract($this->patchJson('/api/account', ['initials' => 'fuk']), 'account.update', 422);
        $this->patchJson('/api/account', ['initials' => 'a1b'])->assertJsonValidationErrors('initials');
        $saved = $this->patchJson('/api/account', ['initials' => 'ana', 'boardsName' => 'initials']);
        $this->assertMatchesContract($saved, 'account.update', 200);
        $saved->assertJsonPath('data.initials', 'ANA')->assertJsonPath('data.boardsName', 'initials');

        $this->postJson('/api/boards/scores', $this->score('amazing-grace', 'alto', 80))->assertOk();
        $board = $this->getJson('/api/boards/amazing-grace/alto?scope=global');
        $this->assertMatchesContract($board, 'boards.show', 200);
        $board->assertJsonPath('data.entries.0.name', null)->assertJsonPath('data.entries.0.initials', 'ANA')->assertJsonPath('data.you.initials', 'ANA');

        // A challenge says who sent it the same way.
        $made = $this->postJson('/api/boards/challenges', ['templateId' => 'amazing-grace', 'partKey' => 'alto'])->assertCreated();
        $this->getJson('/api/boards/challenges/'.$made->json('data.token'))->assertJsonPath('data.by', 'ANA');

        // Someone under 18 shows initials on a friends' board, chosen or not.
        $teen = $this->person(adult: false, name: 'Teo Young');
        $this->actingAs($teen)->postJson('/api/boards/scores', $this->score('amazing-grace', 'alto', 70))->assertOk();
        $friends = $this->getJson('/api/boards/amazing-grace/alto?scope=friends');
        $this->assertMatchesContract($friends, 'boards.show', 200);
        $friends->assertJsonPath('data.entries.0.name', null)->assertJsonPath('data.entries.0.initials', null);
        $teen->forceFill(['initials' => 'TEO'])->save();
        $this->getJson('/api/boards/amazing-grace/alto?scope=friends')->assertJsonPath('data.entries.0.initials', 'TEO');

        // Back to the name: the initials stay kept.
        $this->actingAs($ana)->patchJson('/api/account', ['boardsName' => 'name'])->assertJsonPath('data.initials', 'ANA');
        $this->getJson('/api/boards/amazing-grace/alto?scope=global')->assertJsonPath('data.entries.0.name', 'Ana Ruiz');
    }

    public function test_the_ghost_is_kept_only_for_the_best_and_goes_when_the_best_does(): void
    {
        $ana = $this->person();
        $this->actingAs($ana);
        $version = (int) Template::query()->with('currentVersion')->where('slug', 'amazing-grace')->firstOrFail()->currentVersion?->version;
        $ghost = ['templateVersion' => $version, 'scoringVersion' => 1, 'score' => 80, 'rate' => 20, 'startSeconds' => 1.5, 'keyOffset' => 0, 'pitches' => base64_encode(pack('v*', 0, 6900, 6912, 0))];

        $none = $this->getJson('/api/boards/amazing-grace/alto/ghost');
        $this->assertMatchesContract($none, 'boards.ghost.show', 200);
        $none->assertJsonPath('data', null);
        $this->assertMatchesContract($this->getJson('/api/boards/amazing-grace/no-such-part/ghost'), 'boards.ghost.show', 404);
        $this->assertMatchesContract($this->putJson('/api/boards/amazing-grace/alto/ghost', $ghost), 'boards.ghost.update', 409);
        $this->postJson('/api/boards/scores', $this->score('amazing-grace', 'alto', 80))->assertOk();

        $this->assertMatchesContract($this->putJson('/api/boards/amazing-grace/alto/ghost', [...$ghost, 'pitches' => base64_encode(str_repeat("\0", 4801))]), 'boards.ghost.update', 422);
        $this->putJson('/api/boards/amazing-grace/alto/ghost', [...$ghost, 'pitches' => base64_encode("\0\0\0")])->assertJsonValidationErrors('pitches');
        $this->assertMatchesContract($this->putJson('/api/boards/amazing-grace/alto/ghost', [...$ghost, 'score' => 70]), 'boards.ghost.update', 409);
        $this->assertMatchesContract($this->putJson('/api/boards/amazing-grace/no-such-part/ghost', $ghost), 'boards.ghost.update', 404);

        $kept = $this->putJson('/api/boards/amazing-grace/alto/ghost', $ghost);
        $this->assertMatchesContract($kept, 'boards.ghost.update', 200);
        $shown = $this->getJson('/api/boards/amazing-grace/alto/ghost');
        $this->assertMatchesContract($shown, 'boards.ghost.show', 200);
        $shown->assertJsonPath('data.pitches', $ghost['pitches'])->assertJsonPath('data.startSeconds', 1.5)->assertJsonPath('data.score', 80);

        // A better take replaces the best: the old ghost no longer shows until the new one is kept.
        $this->postJson('/api/boards/scores', $this->score('amazing-grace', 'alto', 90))->assertOk();
        $this->getJson('/api/boards/amazing-grace/alto/ghost')->assertOk()->assertJsonPath('data', null);
        $this->putJson('/api/boards/amazing-grace/alto/ghost', [...$ghost, 'score' => 90])->assertOk();
        $this->assertSame(1, Ghost::query()->count());

        // Taken off the board: the ghost goes with it.
        PartScore::query()->update(['hidden_at' => now()]);
        $this->getJson('/api/boards/amazing-grace/alto/ghost')->assertOk()->assertJsonPath('data', null);
    }

    public function test_an_admin_reset_takes_the_day_and_the_ghosts_and_old_days_are_pruned(): void
    {
        $ana = $this->person();
        DailyScore::factory()->for($ana)->create(['date' => now()->utc()->subDays(31)->toDateString()]);
        DailyScore::factory()->for($ana)->create(['date' => now()->utc()->subDays(2)->toDateString()]);
        Ghost::factory()->for($ana)->create();

        Artisan::call('app:boards:prune-daily');
        $this->assertSame(1, DailyScore::query()->count());

        app(Boards::class)->reset($this->person(name: 'Root Admin'), $ana, 'Test.', null);
        $this->assertSame([0, 0], [DailyScore::query()->count(), Ghost::query()->count()]);
    }
}
