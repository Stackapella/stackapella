<?php

declare(strict_types=1);

namespace Tests\Feature\Boards;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\AuditLog;
use App\Models\BellItem;
use App\Models\BoardConnection;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Part;
use App\Models\PartScore;
use App\Models\Project;
use App\Models\Report;
use App\Models\Take;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Part leaderboards' foundation (docs/decisions.md, "part leaderboards"): qualifying scores only,
 * the best kept, versioned boards, an 18+ opt-in global board, friends by challenge (adults only),
 * shared song or group, and the admin's removal.
 */
final class BoardsTest extends TestCase
{
    use RefreshDatabase;

    private Template $song;

    protected function setUp(): void
    {
        parent::setUp();
        app(SyncOfficialTemplates::class)->handle();
        $this->song = Template::query()->with('currentVersion')->where('slug', 'amazing-grace')->firstOrFail();
    }

    private function person(bool $adult = true, string $name = 'Ana Ruiz'): User
    {
        return User::factory()->create(['name' => $name, 'adult_confirmed_at' => $adult ? now() : null, 'birth_year' => $adult ? 1990 : (int) now()->year - 15, 'birth_month' => 5]);
    }

    /**
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    private function score(int $score, array $with = []): array
    {
        return ['templateId' => 'amazing-grace', 'templateVersion' => (int) $this->song->currentVersion?->version, 'partKey' => 'alto', 'scoringVersion' => 1, 'pitchDetectorVersion' => 1, 'score' => $score, 'mode' => 'own', 'keyOffset' => 0, 'speed' => 1, 'guideMuted' => true, 'timingChecked' => true, 'output' => 'wired', 'alignmentMs' => 42, 'notes' => [['lit' => true, 'cents' => -8, 'timingMs' => 12]], ...$with];
    }

    public function test_only_a_qualifying_score_counts_and_only_the_best_is_kept(): void
    {
        $this->actingAs($this->person());

        // Sung, but not ranked, and the app is told why.
        foreach ([['mode', ['mode' => 'along']], ['key', ['keyOffset' => 2]], ['speed', ['speed' => 0.75]], ['guide', ['guideMuted' => false]], ['timing', ['timingChecked' => false]], ['bluetooth', ['output' => 'bluetooth']]] as [$why, $unfair]) {
            $refused = $this->postJson('/api/boards/scores', $this->score(80, $unfair));
            $this->assertMatchesContract($refused, 'boards.scores.submit', 200);
            $refused->assertJsonPath('data.counted', false)->assertJsonPath('data.why', $why);
        }
        $this->assertSame(0, PartScore::query()->count());

        $this->assertMatchesContract($this->postJson('/api/boards/scores', $this->score(80, ['templateVersion' => 99])), 'boards.scores.submit', 409);
        $this->assertMatchesContract($this->postJson('/api/boards/scores', $this->score(80, ['templateId' => 'no-such-song'])), 'boards.scores.submit', 404);
        $this->postJson('/api/boards/scores', $this->score(80, ['scoringVersion' => 2]))->assertJsonPath('code', 'scoring_outdated');

        $first = $this->postJson('/api/boards/scores', $this->score(80));
        $this->assertMatchesContract($first, 'boards.scores.submit', 200);
        $first->assertJsonPath('data.improved', true)->assertJsonPath('data.before', null);
        $this->postJson('/api/boards/scores', $this->score(70))->assertJsonPath('data.best', 80)->assertJsonPath('data.improved', false)->assertJsonPath('data.before', 80);
        // "Up from 80", whichever device set it.
        $this->postJson('/api/boards/scores', $this->score(91))->assertJsonPath('data.best', 91)->assertJsonPath('data.before', 80);

        $row = PartScore::query()->sole();
        $this->assertSame([91, 1, 1, 42], [$row->score, $row->scoring_version, $row->pitch_detector_version, $row->alignment_ms]);
        $this->assertSame([['lit' => true, 'cents' => -8, 'timingMs' => 12]], $row->notes);
    }

    public function test_a_private_template_has_no_board_and_a_score_takes_only_your_own_voice(): void
    {
        $ana = $this->person();
        $this->actingAs($ana);
        $private = Template::factory()->create(['owner_id' => $ana->id, 'visibility' => 'private']);
        $this->postJson('/api/boards/scores', $this->score(80, ['templateId' => $private->slug]))->assertNotFound();

        // A take a collaborator sang on Ana's project isn't Ana's voice.
        $project = Project::factory()->create(['user_id' => $ana->id, 'template_slug' => 'amazing-grace']);
        $part = Part::factory()->create(['project_id' => $project->id, 'template_part_key' => 'alto']);
        $theirs = Take::factory()->create(['part_id' => $part->id, 'recorded_by' => $this->person(name: 'Ben Ode')->id]);
        $this->postJson('/api/boards/scores', $this->score(80, ['takeId' => $theirs->uuid]))->assertOk()->assertJsonPath('data.why', 'take');
        $mine = Take::factory()->create(['part_id' => $part->id]);
        $this->postJson('/api/boards/scores', $this->score(80, ['takeId' => $mine->uuid]))->assertOk()->assertJsonPath('data.counted', true);
    }

    public function test_the_global_board_is_adults_who_opted_in_and_blocks_hide_each_other(): void
    {
        $ana = $this->person();
        $teen = $this->person(adult: false, name: 'Teo Young');
        $ben = $this->person(name: 'Ben Ode');

        foreach ([[$ana, 90], [$teen, 95], [$ben, 70]] as [$who, $score]) {
            $this->actingAs($who)->postJson('/api/boards/scores', $this->score($score))->assertOk();
        }

        $this->actingAs($teen);
        $this->assertMatchesContract($this->putJson('/api/boards/global', ['on' => true]), 'boards.global.update', 403);
        $this->actingAs($ana)->putJson('/api/boards/global', ['on' => true])->assertOk()->assertJsonPath('data.onGlobal', true);
        // The account says so too (as a request loads it: every column).
        $this->actingAs($ana->refresh())->getJson('/api/account')->assertJsonPath('data.boardsGlobal', true);

        $board = $this->getJson('/api/boards/amazing-grace/alto?scope=global');
        $this->assertMatchesContract($board, 'boards.show', 200);
        $this->assertSame(['Ana Ruiz'], array_column((array) $board->json('data.entries'), 'name'));
        $this->actingAs($ben)->getJson('/api/boards/amazing-grace/alto?scope=global')->assertJsonPath('data.you', ['score' => 70, 'rank' => null]);

        UserBlock::query()->forceCreate(['blocker_id' => $ben->id, 'blocked_id' => $ana->id]);
        $this->getJson('/api/boards/amazing-grace/alto?scope=global')->assertJsonCount(0, 'data.entries');

        // Opting out takes them off at once; deleting the account deletes the scores.
        $this->actingAs($ana)->putJson('/api/boards/global', ['on' => false])->assertOk();
        $this->getJson('/api/boards/amazing-grace/alto?scope=global')->assertJsonCount(0, 'data.entries');
        $ana->delete();
        $this->assertSame(0, PartScore::query()->where('user_id', $ana->id)->count());
    }

    public function test_a_challenge_connects_two_adults_but_never_someone_under_18(): void
    {
        $ana = $this->person();
        $ben = $this->person(name: 'Ben Ode');
        $teen = $this->person(adult: false, name: 'Teo Young');

        $made = $this->actingAs($ana)->postJson('/api/boards/challenges', ['templateId' => 'amazing-grace', 'partKey' => 'alto']);
        $this->assertMatchesContract($made, 'boards.challenges.create', 201);
        $token = (string) $made->json('data.token');

        // The link shows the song, the part and the challenger, but not to someone under 18 or signed out.
        $this->actingAs($ben);
        $shown = $this->getJson('/api/boards/challenges/'.$token);
        $this->assertMatchesContract($shown, 'boards.challenges.show', 200);
        $shown->assertJsonPath('data.by', 'Ana Ruiz')->assertJsonPath('data.partKey', 'alto');
        $this->actingAs($teen)->getJson('/api/boards/challenges/'.$token)->assertJsonPath('data.by', null);
        $this->assertMatchesContract($this->getJson('/api/boards/challenges/nope'), 'boards.challenges.show', 404);

        // Someone under 18 can sing the part, but no connection is made.
        $this->actingAs($teen)->postJson('/api/boards/challenges/'.$token.'/accept')->assertOk()->assertJsonPath('data.connected', false);
        $accepted = $this->actingAs($ben)->postJson('/api/boards/challenges/'.$token.'/accept');
        $this->assertMatchesContract($accepted, 'boards.challenges.accept', 200);
        $accepted->assertJsonPath('data.connected', true);
        $this->assertSame(2, BoardConnection::query()->count());
        $this->assertMatchesContract($this->postJson('/api/boards/challenges/nope/accept'), 'boards.challenges.accept', 404);

        // Friends see each other on any song's friends' board; a teen in the same group by first name.
        $group = Group::factory()->create(['director_id' => $ana->id]);
        GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $teen->id]);
        foreach ([[$ben, 77], [$teen, 88]] as [$who, $score]) {
            $this->actingAs($who)->postJson('/api/boards/scores', $this->score($score))->assertOk();
        }
        $names = array_column((array) $this->actingAs($ana)->getJson('/api/boards/amazing-grace/alto?scope=friends')->json('data.entries'), 'name');
        $this->assertSame(['Teo', 'Ben Ode'], $names);
    }

    public function test_the_admin_takes_a_score_off_or_resets_a_person_and_both_are_audited(): void
    {
        $ana = $this->person();
        $score = PartScore::factory()->create(['user_id' => $ana->id, 'template_id' => $this->song->id, 'template_version_id' => (int) $this->song->current_version_id, 'part_key' => 'alto']);
        $this->actingAsAdmin();

        $this->assertMatchesContract($this->deleteJson('/api/admin/boards/scores/'.$score->sqid(), ['reason' => 'Looks wrong.']), 'admin.boards.scores.delete', 204);
        $this->assertNotNull($score->refresh()->hidden_at);
        $reset = $this->postJson('/api/admin/people/'.$ana->sqid().'/boards/reset', ['reason' => 'Asked us to.']);
        $this->assertMatchesContract($reset, 'admin.boards.reset', 200);
        $reset->assertJsonPath('data.removed', 1);
        $this->assertSame(2, AuditLog::query()->where('action', 'like', 'boards.%')->count());
    }

    public function test_a_perfect_or_surprising_score_is_held_off_the_global_board_until_cleared_or_48_hours(): void
    {
        $sing = function (User $who, int $score): void {
            $this->actingAs($who)->putJson('/api/boards/global', ['on' => true])->assertOk();
            $this->postJson('/api/boards/scores', $this->score($score))->assertOk()->assertJsonPath('data.counted', true);
        };
        $names = fn (User $viewer): array => array_column((array) $this->actingAs($viewer)->getJson('/api/boards/amazing-grace/alto?scope=global')->json('data.entries'), 'name');
        $ben = $this->person(name: 'Ben Ode');
        $sing($ben, 60);

        // A perfect score: its singer sees it, nobody else does yet.
        $ana = $this->person();
        $sing($ana, 100);
        $this->assertSame(['Ben Ode'], $names($ben));
        $this->assertSame(['Ana Ruiz', 'Ben Ode'], $names($ana));

        // A jump of 25 or more over their own best.
        $sing($ben, 85);
        $this->assertSame([], $names($this->person(name: 'Cy Lee')));

        // Far above everyone else's best, once the board has five: held; just below that line: not.
        foreach (['Di', 'Ed', 'Flo', 'Gus', 'Hal'] as $name) {
            $sing($this->person(name: $name), 60);
        }
        $ida = $this->person(name: 'Ida');
        $sing($ida, 75);
        $sing($this->person(name: 'Jo'), 74);
        $this->assertEqualsCanonicalizing([$ana->id, $ben->id, $ida->id], PartScore::query()->whereNotNull('flagged_at')->pluck('user_id')->map(fn ($id): int => (int) $id)->all());

        $this->artisan('app:digest --print')->expectsOutputToContain('3 leaderboard scores to check')->assertSuccessful();

        $admin = $this->actingAsAdmin();
        $flags = $this->getJson('/api/admin/boards/flags');
        $this->assertMatchesContract($flags, 'admin.boards.flags.list', 200);
        $reasons = array_column((array) $flags->json('data'), 'reason');
        sort($reasons);
        $this->assertSame(['outlier', 'outlier', 'perfect'], $reasons);
        $anas = PartScore::query()->where('user_id', $ana->id)->sole();
        $this->assertMatchesContract($this->postJson('/api/admin/boards/scores/'.$anas->sqid().'/review'), 'admin.boards.scores.review', 204);
        $this->assertMatchesContract($this->postJson('/api/admin/boards/scores/nope/review'), 'admin.boards.scores.review', 404);
        $this->assertSame(1, AuditLog::query()->where('action', 'boards.score_reviewed')->count());
        $this->assertContains('Ana Ruiz', $names($admin));
        $this->assertNotContains('Ben Ode', $names($admin));

        // Nobody looked: after 48 hours it shows anyway.
        $this->travel(49)->hours();
        $this->assertContains('Ben Ode', $names($admin));
    }

    public function test_a_name_reported_by_two_people_waits_off_the_global_board_for_an_admin(): void
    {
        $ana = $this->person(name: 'Rude Name');
        $this->actingAs($ana)->putJson('/api/boards/global', ['on' => true])->assertOk();
        $this->postJson('/api/boards/scores', $this->score(70))->assertOk();
        [$ben, $cy] = [$this->person(name: 'Ben Ode'), $this->person(name: 'Cy Lee')];
        $board = fn (User $viewer) => $this->actingAs($viewer)->getJson('/api/boards/amazing-grace/alto?scope=global');
        $id = (string) $board($ben)->json('data.entries.0.id');
        $report = fn (User $who, array $with = []) => $this->actingAs($who)->postJson('/api/reports', ['boardEntryId' => $id, 'reason' => 'offensive', ...$with]);

        $this->assertMatchesContract($report($ben), 'reports.create', 204);
        $report($ben)->assertNoContent();
        $report($ana)->assertNoContent();
        $this->assertSame(['Rude Name'], array_column((array) $board($ben)->json('data.entries'), 'name'));
        $this->assertMatchesContract($report($cy, ['boardEntryId' => 'nope']), 'reports.create', 404);

        // A second person: held, however long it waits, but its singer still sees it.
        $report($cy)->assertNoContent();
        $this->assertSame(2, Report::query()->where('reportable_type', 'part_score')->count());
        $this->travel(3)->days();
        $board($ben)->assertJsonCount(0, 'data.entries');
        $board($ana)->assertJsonCount(1, 'data.entries');

        $this->actingAsAdmin();
        $flags = $this->getJson('/api/admin/boards/flags');
        $this->assertMatchesContract($flags, 'admin.boards.flags.list', 200);
        $flags->assertJsonPath('data.0.reason', 'reported')->assertJsonPath('data.0.reports', 2);

        // Taken off: the reports were right.
        $this->deleteJson('/api/admin/boards/scores/'.$id, ['reason' => 'Offensive name.'])->assertNoContent();
        $this->assertSame(2, Report::query()->where('status', Report::UPHELD)->count());
        $this->getJson('/api/admin/boards/flags')->assertJsonCount(0, 'data');
    }

    public function test_signed_out_nobody_reports_a_board_entry(): void
    {
        $score = PartScore::factory()->create(['template_id' => $this->song->id, 'template_version_id' => (int) $this->song->current_version_id, 'part_key' => 'alto']);
        $this->postJson('/api/reports', ['boardEntryId' => $score->sqid(), 'reason' => 'offensive'])->assertNotFound();
    }

    public function test_an_earlier_arrangements_board_stays_readable_and_takes_no_new_scores(): void
    {
        $ana = $this->person();
        $current = (int) $this->song->currentVersion?->version;
        $old = TemplateVersion::factory()->create(['template_id' => $this->song->id, 'version' => $current - 1]);
        PartScore::factory()->create(['user_id' => $ana->id, 'template_id' => $this->song->id, 'template_version_id' => $old->id, 'part_key' => 'alto', 'score' => 77]);
        $this->actingAs($ana);

        $now = $this->getJson('/api/boards/amazing-grace/alto?scope=friends');
        $this->assertMatchesContract($now, 'boards.show', 200);
        $now->assertJsonPath('data.current', true)->assertJsonPath('data.olderVersions', [$current - 1])->assertJsonCount(0, 'data.entries');

        $then = $this->getJson('/api/boards/amazing-grace/alto?scope=friends&version='.($current - 1));
        $this->assertMatchesContract($then, 'boards.show', 200);
        $then->assertJsonPath('data.current', false)->assertJsonPath('data.templateVersion', $current - 1)->assertJsonPath('data.entries.0.score', 77);

        // A version with no entries here, and new scores on an old version: neither.
        $this->assertMatchesContract($this->getJson('/api/boards/amazing-grace/alto?scope=friends&version=99'), 'boards.show', 404);
        $this->postJson('/api/boards/scores', $this->score(90, ['templateVersion' => $current - 1]))->assertStatus(409);
    }

    public function test_a_friend_whose_score_you_beat_hears_it_in_the_bell_once_a_day(): void
    {
        $ana = $this->person(adult: false, name: 'Ana Young');
        [$ben, $cy, $dan, $eve] = [$this->person(name: 'Ben Ode'), $this->person(name: 'Cy Lee'), $this->person(name: 'Dan Ray'), $this->person(name: 'Eve Ng')];
        foreach ([$ben, $cy, $dan] as $friend) {
            BoardConnection::query()->forceCreate(['user_id' => $ana->id, 'friend_id' => $friend->id, 'source' => 'challenge']);
            BoardConnection::query()->forceCreate(['user_id' => $friend->id, 'friend_id' => $ana->id, 'source' => 'challenge']);
        }
        // Ben leads until Ana's 87, Cy stays ahead, Dan is passed by Ana's first score; Eve isn't a friend.
        foreach ([[$ben, 80], [$cy, 95], [$dan, 60], [$eve, 75], [$ana, 70]] as [$who, $score]) {
            $this->actingAs($who)->postJson('/api/boards/scores', $this->score($score))->assertOk();
        }

        $this->actingAs($ana)->postJson('/api/boards/scores', $this->score(87))->assertOk();
        $this->actingAs($ana)->postJson('/api/boards/scores', $this->score(90))->assertOk();
        $this->assertEqualsCanonicalizing([$dan->id, $ben->id], BellItem::query()->where('kind', 'board_beaten')->pluck('user_id')->map(fn ($id): int => (int) $id)->all());

        $bell = $this->actingAs($ben)->getJson('/api/notifications');
        $this->assertMatchesContract($bell, 'notifications.list', 200);
        $bell->assertJsonPath('data.0.kind', 'board_beaten')->assertJsonPath('data.0.actor.name', 'Ana')
            ->assertJsonPath('data.0.board', ['templateId' => 'amazing-grace', 'title' => $this->song->title, 'partKey' => 'alto', 'partName' => 'Alto', 'score' => 90]);
    }
}
