<?php

declare(strict_types=1);

namespace Tests\Feature\Boards;

use App\Actions\Boards\Boards;
use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\MonthlyScore;
use App\Models\PartScore;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Game mode's monthly board: each person's best counted score this UTC month, so a newcomer can
 * place, shown by the same rules as the all-time board.
 */
final class MonthlyBoardsTest extends TestCase
{
    use RefreshDatabase;

    private Template $song;

    protected function setUp(): void
    {
        parent::setUp();
        app(SyncOfficialTemplates::class)->handle();
        $this->song = Template::query()->with('currentVersion')->where('slug', 'amazing-grace')->firstOrFail();
    }

    private function person(string $name): User
    {
        return User::factory()->create(['name' => $name, 'adult_confirmed_at' => now(), 'birth_year' => 1990, 'birth_month' => 5, 'board_global_at' => now()])->refresh();
    }

    private function sing(User $who, int $score): void
    {
        $this->actingAs($who)->postJson('/api/boards/scores', ['templateId' => 'amazing-grace', 'templateVersion' => (int) $this->song->currentVersion?->version, 'partKey' => 'alto', 'scoringVersion' => 1, 'pitchDetectorVersion' => 1, 'score' => $score, 'mode' => 'own', 'keyOffset' => 0, 'speed' => 1, 'guideMuted' => true, 'timingChecked' => true, 'output' => 'wired'])->assertOk()->assertJsonPath('data.counted', true);
    }

    public function test_this_months_best_ranks_the_month_while_the_all_time_board_keeps_the_best_ever(): void
    {
        $ana = $this->person('Ana Ruiz');
        $ben = $this->person('Ben Ode');
        $this->sing($ana, 80);
        $this->sing($ana, 70);
        $this->sing($ben, 90);
        // Ben's 90 was last month: this month he has sung 60.
        MonthlyScore::query()->where('user_id', $ben->id)->update(['month' => now()->utc()->subMonthNoOverflow()->format('Y-m')]);
        $this->sing($ben, 60);

        $month = $this->actingAs($ana)->getJson('/api/boards/amazing-grace/alto?scope=global&period=month');
        $this->assertMatchesContract($month, 'boards.show', 200);
        $month->assertJsonPath('data.period', 'month')->assertJsonPath('data.endsAt', now()->utc()->startOfMonth()->addMonth()->toIso8601ZuluString())
            ->assertJsonPath('data.you', ['score' => 80, 'rank' => 1, 'initials' => null]);
        $this->assertSame([['Ana Ruiz', 80], ['Ben Ode', 60]], array_map(fn (array $e): array => [$e['name'], $e['score']], (array) $month->json('data.entries')));
        // Its ids are the part board's, for "Report this name".
        $this->assertSame(PartScore::query()->where('user_id', $ana->id)->sole()->sqid(), $month->json('data.entries.0.id'));

        $all = $this->getJson('/api/boards/amazing-grace/alto?scope=global');
        $all->assertJsonPath('data.period', 'all')->assertJsonPath('data.endsAt', null);
        $this->assertSame([['Ben Ode', 90], ['Ana Ruiz', 80]], array_map(fn (array $e): array => [$e['name'], $e['score']], (array) $all->json('data.entries')));

        // Taken off the part board, off the month's too; an earlier arrangement has no month.
        PartScore::query()->where('user_id', $ben->id)->update(['hidden_at' => now()]);
        $this->assertSame(['Ana Ruiz'], array_column((array) $this->getJson('/api/boards/amazing-grace/alto?scope=global&period=month')->json('data.entries'), 'name'));
        $this->assertMatchesContract($this->getJson('/api/boards/amazing-grace/alto?scope=global&period=month&version=1'), 'boards.show', 422);
    }

    public function test_months_before_last_are_pruned_and_a_reset_takes_the_month_too(): void
    {
        $ana = $this->person('Ana Ruiz');
        MonthlyScore::factory()->for($ana)->create(['month' => now()->utc()->startOfMonth()->subMonths(2)->format('Y-m')]);
        MonthlyScore::factory()->for($ana)->create(['month' => now()->utc()->startOfMonth()->subMonth()->format('Y-m')]);
        MonthlyScore::factory()->for($ana)->create();

        Artisan::call('app:boards:prune-daily');
        $this->assertSame(2, MonthlyScore::query()->count());

        app(Boards::class)->reset($this->person('Root Admin'), $ana, 'Test.', null);
        $this->assertSame(0, MonthlyScore::query()->count());
    }
}
