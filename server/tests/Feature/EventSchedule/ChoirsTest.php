<?php

declare(strict_types=1);

namespace Tests\Feature\EventSchedule;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\EsConnection;
use App\Models\Part;
use App\Models\User;
use App\Services\EventSchedule\EventScheduleError;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\EventScheduling;
use Tests\TestCase;

/**
 * A choir across both apps (contract/api.md, "Event Schedule together (M9)", choirs).
 */
final class ChoirsTest extends TestCase
{
    use EventScheduling;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(SyncOfficialTemplates::class)->handle();
        $this->fakeEventSchedule();
    }

    /**
     * A director with Plus and Event Schedule, their group with a song, and a member assigned the alto.
     *
     * @return array{0: User, 1: User, 2: string, 3: EsConnection}
     */
    private function choir(): array
    {
        $director = $this->as(User::factory()->create(['plan' => 'plus', 'name' => 'Ana Ruiz']));
        $connection = $this->esConnection($director, 'riverside-choir');
        $id = (string) $this->postJson('/api/groups', ['name' => 'Riverside Choir', 'adult' => true])->assertCreated()->json('data.id');
        $token = Str::afterLast((string) $this->postJson('/api/groups/'.$id.'/link')->assertCreated()->json('data.url'), '/choir/');
        $this->postJson('/api/groups/'.$id.'/songs', ['templateId' => 'amazing-grace'])->assertOk();

        $member = $this->as(User::factory()->create(['name' => 'Sam']));
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertOk();

        $this->as($director);
        $this->putJson('/api/groups/'.$id.'/songs/amazing-grace/assignments', ['assignments' => [['memberId' => $member->sqid(), 'partKey' => 'alto']]])->assertOk();

        return [$director, $member, $id, $connection];
    }

    public function test_a_director_links_their_schedule_and_everyone_sees_what_to_practise_for_each_rehearsal(): void
    {
        [$director, $member, $id] = $this->choir();

        $none = $this->getJson('/api/groups/'.$id.'/rehearsals');
        $this->assertMatchesContract($none, 'groups.rehearsals.list', 200);
        $none->assertJsonPath('data.schedule', null)->assertJsonPath('data.events', []);

        $this->assertMatchesContract($this->putJson('/api/groups/'.$id.'/event-schedule', ['schedule' => 'not-mine']), 'groups.eventSchedule.update', 422);
        $linked = $this->putJson('/api/groups/'.$id.'/event-schedule', ['schedule' => 'riverside-choir']);
        $this->assertMatchesContract($linked, 'groups.eventSchedule.update', 200);
        $linked->assertJsonPath('data.schedule.name', 'Riverside Choir')->assertJsonPath('data.available', true)->assertJsonPath('data.events.0.name', 'Thursday rehearsal');
        $event = (string) $linked->json('data.events.0.id');

        $this->assertMatchesContract($this->putJson('/api/groups/'.$id.'/rehearsals/'.$event.'/songs', ['templateIds' => ['silent-night']]), 'groups.rehearsals.songs', 422);
        $this->assertMatchesContract($this->putJson('/api/groups/'.$id.'/rehearsals/Ev-not-there/songs', ['templateIds' => []]), 'groups.rehearsals.songs', 404);
        $picked = $this->putJson('/api/groups/'.$id.'/rehearsals/'.$event.'/songs', ['templateIds' => ['amazing-grace']]);
        $this->assertMatchesContract($picked, 'groups.rehearsals.songs', 200);

        // The member sees their own part, and how far they've got.
        $this->as($member);
        $seen = $this->getJson('/api/groups/'.$id.'/rehearsals');
        $seen->assertJsonPath('data.events.0.songs.0.templateId', 'amazing-grace')->assertJsonPath('data.events.0.songs.0.partKey', 'alto')->assertJsonPath('data.events.0.songs.0.status', 'none');
        [, $part] = $this->projectWithPart();
        $take = $this->recordTake($part);
        $this->savePart($part, Part::query()->where('uuid', $part)->firstOrFail()->project->uuid, 1, ['currentTakeId' => $take->uuid])->assertOk();
        $this->getJson('/api/groups/'.$id.'/rehearsals')->assertJsonPath('data.events.0.songs.0.status', 'recorded');

        // A member can't direct it.
        $this->assertMatchesContract($this->putJson('/api/groups/'.$id.'/event-schedule', ['schedule' => 'riverside-choir']), 'groups.eventSchedule.update', 403);
        $this->assertMatchesContract($this->deleteJson('/api/groups/'.$id.'/event-schedule'), 'groups.eventSchedule.delete', 403);
        $this->assertMatchesContract($this->getJson('/api/groups/'.$id.'/event-schedule/members'), 'groups.eventSchedule.members', 403);
        $this->assertMatchesContract($this->putJson('/api/groups/'.$id.'/rehearsals/'.$event.'/songs', ['templateIds' => []]), 'groups.rehearsals.songs', 403);

        // Someone outside the group finds nothing.
        $this->as(User::factory()->create());
        foreach (['groups.rehearsals.list' => ['GET', '/rehearsals'], 'groups.eventSchedule.update' => ['PUT', '/event-schedule'], 'groups.eventSchedule.delete' => ['DELETE', '/event-schedule'], 'groups.eventSchedule.members' => ['GET', '/event-schedule/members'], 'groups.rehearsals.songs' => ['PUT', '/rehearsals/'.$event.'/songs']] as $operation => [$method, $path]) {
            $this->assertMatchesContract($this->json($method, '/api/groups/'.$id.$path, ['schedule' => 'riverside-choir', 'templateIds' => []]), $operation, 404);
        }

        // Unlinked: the picks go too.
        $this->as($director);
        $this->deleteJson('/api/groups/'.$id.'/event-schedule')->assertNoContent();
        $this->assertDatabaseCount('group_event_songs', 0);
    }

    public function test_the_director_sees_the_schedules_members_and_nothing_breaks_when_es_cant_answer(): void
    {
        [$director, , $id, $connection] = $this->choir();
        $this->putJson('/api/groups/'.$id.'/event-schedule', ['schedule' => 'riverside-choir'])->assertOk();

        $members = $this->getJson('/api/groups/'.$id.'/event-schedule/members');
        $this->assertMatchesContract($members, 'groups.eventSchedule.members', 200);
        $members->assertJsonPath('data.0.name', 'Ana Ruiz')->assertJsonPath('meta.nextCursor', null);

        $this->es->failNext(EventScheduleError::UNAVAILABLE);
        $this->assertMatchesContract($this->getJson('/api/groups/'.$id.'/event-schedule/members'), 'groups.eventSchedule.members', 424);
        $this->es->failNext(EventScheduleError::UNAVAILABLE);
        $this->assertMatchesContract($this->putJson('/api/groups/'.$id.'/event-schedule', ['schedule' => 'riverside-choir']), 'groups.eventSchedule.update', 424);

        // The director's connection needs reconnecting: the page still works, saying so.
        $connection->forceFill(['status' => EsConnection::NEEDS_RECONNECT])->save();
        $this->getJson('/api/groups/'.$id.'/rehearsals')->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.events', []);
        $this->assertMatchesContract($this->getJson('/api/groups/'.$id.'/event-schedule/members'), 'groups.eventSchedule.members', 409);
        $this->assertMatchesContract($this->putJson('/api/groups/'.$id.'/event-schedule', ['schedule' => 'riverside-choir']), 'groups.eventSchedule.update', 409);

        $connection->delete();
        $this->assertMatchesContract($this->putJson('/api/groups/'.$id.'/event-schedule', ['schedule' => 'riverside-choir']), 'groups.eventSchedule.update', 409);
        $this->assertSame('event_schedule_required', $this->putJson('/api/groups/'.$id.'/event-schedule', ['schedule' => 'riverside-choir'])->json('code'));
    }
}
