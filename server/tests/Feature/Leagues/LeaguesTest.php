<?php

declare(strict_types=1);

namespace Tests\Feature\Leagues;

use App\Actions\Leagues\Leagues;
use App\Models\Follow;
use App\Models\League;
use App\Models\LeagueMember;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserLearning;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Leagues and friends' leaderboards (N2; contract/api.md, "Leagues (N2)").
 */
final class LeaguesTest extends TestCase
{
    use RefreshDatabase;

    private function singer(string $handle, bool $adult = true): User
    {
        return User::factory()->create(['handle' => $handle, 'name' => ucfirst($handle), 'adult_confirmed_at' => $adult ? now() : null, 'trust_level_override' => 2])->fresh() ?? throw new \LogicException;
    }

    /**
     * @param  array<string, int>  $days
     */
    private function practised(User $user, array $days, int $total = 0): void
    {
        $learning = UserLearning::query()->where('user_id', $user->id)->first() ?? new UserLearning;
        $learning->forceFill(['user_id' => $user->id, 'progress' => ['xp' => ['total' => $total, 'days' => $days]], 'version' => 1])->save();
    }

    public function test_closed_leagues_answer_nothing(): void
    {
        $this->actingAs($this->singer('ana'));

        $this->assertMatchesContract($this->getJson('/api/leagues/current'), 'leagues.current', 404);
        $this->assertMatchesContract($this->getJson('/api/leagues/friends'), 'friends.leaderboard', 404);
        $this->assertMatchesContract($this->postJson('/api/leagues/membership'), 'leagues.join', 409);
    }

    public function test_a_singer_joins_and_is_ranked_by_capped_xp_this_week(): void
    {
        Carbon::setTestNow('2026-11-04 12:00:00');
        app(Flags::class)->set('leagues', 'on', null, 'Opening leagues');
        $ana = $this->singer('ana');
        $sam = $this->singer('sam');
        $this->practised($ana, ['2026-11-02' => 120, '2026-11-03' => 900, '2026-10-30' => 300], 1320);
        $this->practised($sam, ['2026-11-04' => 80]);

        $this->actingAs($sam);
        $this->assertMatchesContract($this->postJson('/api/leagues/membership'), 'leagues.join', 200);
        $this->actingAs($ana);
        $joined = $this->postJson('/api/leagues/membership');
        $this->assertMatchesContract($joined, 'leagues.join', 200);

        $current = $this->getJson('/api/leagues/current');
        $this->assertMatchesContract($current, 'leagues.current', 200);
        // 120 + 300 (900 capped) this week; last week's day doesn't count, but is in the level.
        $current->assertJsonPath('data.members.0.handle', 'ana')->assertJsonPath('data.members.0.xp', 420)->assertJsonPath('data.members.0.movingUp', true)
            ->assertJsonPath('data.members.1.xp', 80)->assertJsonPath('data.tier', 0)->assertJsonPath('data.tiers', 10)->assertJsonPath('data.week', '2026-11-02');
        $this->assertSame(Leagues::level(720), $current->json('data.members.0.level'));
        $this->assertSame(1, League::query()->count());

        $this->assertMatchesContract($this->deleteJson('/api/leagues/membership'), 'leagues.leave', 204);
        $this->getJson('/api/leagues/current')->assertJsonPath('data.joined', false)->assertJsonPath('data.members', []);
        Carbon::setTestNow();
    }

    public function test_the_top_five_move_up_on_monday_and_nobody_moves_down(): void
    {
        Carbon::setTestNow('2026-11-04 12:00:00');
        app(Flags::class)->set('leagues', 'on', null, 'Opening leagues');
        $people = [];
        for ($i = 0; $i < 7; $i++) {
            $people[$i] = $this->singer('singer'.$i);
            $this->practised($people[$i], ['2026-11-03' => 10 * ($i + 1)]);
            app(Leagues::class)->join($people[$i]);
        }
        $idle = $this->singer('idle');
        app(Leagues::class)->join($idle);
        $over = $this->singer('over');
        $this->practised($over, ['2026-11-03' => 5000]);
        app(Leagues::class)->join($over);

        Carbon::setTestNow('2026-11-09 00:10:00');
        $this->artisan('app:leagues:weekly')->assertSuccessful();

        // The five most active move up, the cheat among them clamped and flagged; nobody down.
        $this->assertSame(2, $over->refresh()->league_tier);
        $this->assertNotNull(LeagueMember::query()->where('user_id', $over->id)->value('flagged_at'));
        foreach ([6, 5, 4, 3] as $i) {
            $this->assertSame(2, $people[$i]->refresh()->league_tier);
        }
        $this->assertSame(1, $people[0]->refresh()->league_tier);
        $this->assertSame(1, $idle->refresh()->league_tier);

        // This week's leagues are formed by tier, and Monday says how last week went.
        $this->assertSame(2, League::query()->where('week', '2026-11-09')->count());
        $this->actingAs($people[6]);
        $this->getJson('/api/leagues/current')->assertJsonPath('data.tier', 1)->assertJsonPath('data.lastWeek.movedUp', true);
        Carbon::setTestNow();
    }

    public function test_friends_under_18s_and_blocks(): void
    {
        app(Flags::class)->set('leagues', 'on', null, 'Opening leagues');
        $ana = $this->singer('ana');
        $sam = $this->singer('sam');
        $kim = $this->singer('kim');
        $teen = $this->singer('teen', adult: false);
        foreach ([$sam, $kim] as $person) {
            app(Leagues::class)->join($person);
        }
        Follow::factory()->create(['follower_id' => $ana->id, 'followee_id' => $sam->id]);
        Follow::factory()->create(['follower_id' => $ana->id, 'followee_id' => $kim->id]);
        UserBlock::factory()->create(['blocker_id' => $kim->id, 'blocked_id' => $ana->id]);

        $this->actingAs($ana);
        $friends = $this->getJson('/api/leagues/friends');
        $this->assertMatchesContract($friends, 'friends.leaderboard', 200);
        $this->assertSame(['ana', 'sam'], collect((array) $friends->json('data.members'))->pluck('handle')->sort()->values()->all());

        $this->actingAs($teen);
        $this->assertMatchesContract($this->postJson('/api/leagues/membership'), 'leagues.join', 403);
        $this->getJson('/api/leagues/friends')->assertOk()->assertJsonPath('data.members.0.me', true);

        $this->actingAs(User::factory()->create(['handle' => null, 'adult_confirmed_at' => now()])->fresh() ?? throw new \LogicException);
        $this->assertMatchesContract($this->postJson('/api/leagues/membership'), 'leagues.join', 422);
        $this->actingAs(User::factory()->create(['handle' => 'new_one', 'adult_confirmed_at' => now(), 'trust_level_override' => 0])->fresh() ?? throw new \LogicException);
        $this->assertMatchesContract($this->postJson('/api/leagues/membership'), 'leagues.join', 403);
    }

    public function test_paused_comments_and_follows_are_refused(): void
    {
        app(Flags::class)->set('gallery', 'on', null, 'Open');
        app(Flags::class)->set('pause_follows', 'on', null, 'A flood');
        app(Flags::class)->set('pause_comments', 'on', null, 'A flood');
        $this->actingAs($this->singer('ana'));

        $this->assertMatchesContract($this->putJson('/api/profiles/sam/follow'), 'follows.create', 423);
        $this->assertMatchesContract($this->postJson('/api/harmonies/nope/comments', ['body' => 'Hi']), 'comments.create', 423);
    }
}
