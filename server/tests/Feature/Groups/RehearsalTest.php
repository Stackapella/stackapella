<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\Group;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CloudSave;
use Tests\TestCase;

/**
 * A choir group's next rehearsal (contract/api.md, "Choir groups"): the director sets a date and
 * whether it's weekly, everyone in the group sees the next one, worked out on read, and the director
 * sees how many began and recorded each song since the last one, as numbers only.
 */
final class RehearsalTest extends TestCase
{
    use CloudSave;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SyncOfficialTemplates::class)->handle();
    }

    private function as(User $user): User
    {
        $user = $user->fresh() ?? $user;
        $this->actingAs($user);

        return $user;
    }

    public function test_the_director_sets_a_weekly_rehearsal_and_sees_who_began_since_the_last_one(): void
    {
        $this->travelTo('2026-11-10T10:00:00Z');
        $director = $this->as(User::factory()->create(['name' => 'Ana Ruiz']));
        $this->withPlus();
        $id = (string) $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true])->assertCreated()->assertJsonPath('data.rehearsal', null)->json('data.id');
        $token = Str::afterLast((string) $this->postJson('/api/groups/'.$id.'/link')->assertCreated()->json('data.url'), '/choir/');
        $this->postJson('/api/groups/'.$id.'/songs', ['templateId' => 'amazing-grace'])->assertOk();

        $members = [];
        foreach (['Sam', 'Kim', 'Lee'] as $name) {
            $members[$name] = $this->as(User::factory()->create(['name' => $name]));
            $this->postJson('/api/choir/'.$token, ['adult' => true])->assertOk();
        }

        // A member can't set it.
        $this->assertMatchesContract($this->patchJson('/api/groups/'.$id, ['rehearsal' => ['date' => '2026-11-12', 'weekly' => true]]), 'groups.update', 403);

        $this->as($director);
        $this->putJson('/api/groups/'.$id.'/songs/amazing-grace/assignments', ['assignments' => [
            ['memberId' => $members['Sam']->sqid(), 'partKey' => 'alto'],
            ['memberId' => $members['Kim']->sqid(), 'partKey' => 'tenor'],
            ['memberId' => $members['Lee']->sqid(), 'partKey' => 'bass'],
        ]])->assertOk();

        // Sam records the alto before the first rehearsal.
        $this->as($members['Sam']);
        [, $part] = $this->projectWithPart();
        $take = $this->recordTake($part);
        $this->savePart($part, Part::query()->where('uuid', $part)->firstOrFail()->project->uuid, 1, ['currentTakeId' => $take->uuid])->assertOk();

        // Dates: a real one, from yesterday to a year ahead; one of name or rehearsal.
        $this->as($director);
        $this->assertMatchesContract($this->patchJson('/api/groups/'.$id, ['rehearsal' => ['date' => '2026-11-08', 'weekly' => true]]), 'groups.update', 422);
        foreach (['2026-11-31', '2027-11-11', '12/11/2026', ''] as $bad) {
            $this->patchJson('/api/groups/'.$id, ['rehearsal' => ['date' => $bad, 'weekly' => true]])->assertJsonValidationErrors('rehearsal.date');
        }
        $this->patchJson('/api/groups/'.$id, ['rehearsal' => ['date' => '2026-11-12']])->assertJsonValidationErrors('rehearsal.weekly');
        $this->patchJson('/api/groups/'.$id, [])->assertJsonValidationErrors('name');

        $set = $this->patchJson('/api/groups/'.$id, ['rehearsal' => ['date' => '2026-11-12', 'weekly' => true]]);
        $this->assertMatchesContract($set, 'groups.update', 200);
        $set->assertJsonPath('data.rehearsal', ['date' => '2026-11-12', 'weekly' => true, 'next' => '2026-11-12', 'previous' => null])
            ->assertJsonPath('data.name', 'Thursday Singers')->assertJsonPath('data.songs.0.sinceRehearsal', null);

        // Still the 12th somewhere: it stays next.
        $this->travelTo('2026-11-13T11:00:00Z');
        $this->getJson('/api/groups/'.$id)->assertJsonPath('data.rehearsal.next', '2026-11-12');

        // Over everywhere: next week's, and Kim begins the song after it.
        $this->travelTo('2026-11-13T13:00:00Z');
        $this->as($members['Kim']);
        $this->saveProject((string) Str::uuid())->assertOk();

        $this->as($director);
        $view = $this->getJson('/api/groups/'.$id);
        $this->assertMatchesContract($view, 'groups.show', 200);
        $view->assertJsonPath('data.rehearsal', ['date' => '2026-11-12', 'weekly' => true, 'next' => '2026-11-19', 'previous' => '2026-11-12'])
            ->assertJsonPath('data.songs.0.sinceRehearsal', ['started' => 1, 'recorded' => 0]);

        // Sam records again after it: counted as recorded since, but not as begun since.
        $this->as($members['Sam']);
        $again = $this->recordTake($part);
        $this->savePart($part, Part::query()->where('uuid', $part)->firstOrFail()->project->uuid, 2, ['currentTakeId' => $again->uuid])->assertOk();
        $this->as($director);
        $this->getJson('/api/groups/'.$id)->assertJsonPath('data.songs.0.sinceRehearsal', ['started' => 1, 'recorded' => 1]);

        // A member sees the rehearsal, never the counts.
        $this->as($members['Lee']);
        $member = $this->getJson('/api/groups/'.$id);
        $this->assertMatchesContract($member, 'groups.show', 200);
        $member->assertJsonPath('data.rehearsal.next', '2026-11-19')->assertJsonPath('data.songs.0.sinceRehearsal', null);

        // Renaming keeps it; null clears it.
        $this->as($director);
        $this->patchJson('/api/groups/'.$id, ['name' => 'Thursday Choir'])->assertOk()->assertJsonPath('data.rehearsal.date', '2026-11-12');
        $this->patchJson('/api/groups/'.$id, ['rehearsal' => null])->assertOk()->assertJsonPath('data.rehearsal', null)->assertJsonPath('data.name', 'Thursday Choir');
        $this->assertSame([null, false], [Group::query()->sole()->rehearsal_on, Group::query()->sole()->rehearsal_weekly]);
    }

    public function test_a_one_off_rehearsal_that_has_passed_shows_no_next_one(): void
    {
        $this->travelTo('2026-11-10T10:00:00Z');
        $this->as(User::factory()->create());
        $this->withPlus();
        $id = (string) $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true])->assertCreated()->json('data.id');
        $this->patchJson('/api/groups/'.$id, ['name' => 'Concert Choir', 'rehearsal' => ['date' => '2026-11-11', 'weekly' => false]])->assertOk()->assertJsonPath('data.name', 'Concert Choir');

        $this->travelTo('2026-11-20T10:00:00Z');
        $this->getJson('/api/groups/'.$id)->assertJsonPath('data.rehearsal', ['date' => '2026-11-11', 'weekly' => false, 'next' => null, 'previous' => '2026-11-11'])
            ->assertJsonPath('data.songs', []);
    }
}
