<?php

declare(strict_types=1);

namespace Tests\Feature\Groups;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Part;
use App\Models\Template;
use App\Models\User;
use App\Support\Ids;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CloudSave;
use Tests\TestCase;

final class GroupsTest extends TestCase
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

    /**
     * A director with Plus, their group, and its join token.
     *
     * @return array{0: User, 1: string, 2: string}
     */
    private function group(): array
    {
        $director = $this->as(User::factory()->create(['plan' => 'plus', 'name' => 'Ana Ruiz']));
        $id = (string) $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true])->assertCreated()->json('data.id');
        $token = Str::afterLast((string) $this->postJson('/api/groups/'.$id.'/link')->assertCreated()->json('data.url'), '/choir/');

        return [$director, $id, $token];
    }

    public function test_a_director_makes_a_group_shares_songs_and_assigns_parts_and_sees_who_has_recorded_theirs(): void
    {
        $director = $this->as(User::factory()->create(['plan' => 'plus', 'name' => 'Ana Ruiz']));

        $made = $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true]);
        $this->assertMatchesContract($made, 'groups.create', 201);
        $id = (string) $made->json('data.id');
        $link = $this->postJson('/api/groups/'.$id.'/link');
        $this->assertMatchesContract($link, 'groups.link.create', 201);
        $token = Str::afterLast((string) $link->json('data.url'), '/choir/');

        $this->assertMatchesContract($this->postJson('/api/groups/'.$id.'/songs', ['templateId' => 'amazing-grace']), 'groups.songs.create', 200);
        $this->assertMatchesContract($this->postJson('/api/groups/'.$id.'/songs', ['templateId' => 'not-a-song']), 'groups.songs.create', 404);

        // Three members join by the link (anyone holding it sees what it's for).
        $members = [];
        foreach (['Sam', 'Kim', 'Lee'] as $name) {
            $members[$name] = $this->as(User::factory()->create(['name' => $name]));
            $shown = $this->getJson('/api/choir/'.$token);
            $this->assertMatchesContract($shown, 'groups.join.show', 200);
            $joined = $this->postJson('/api/choir/'.$token, ['adult' => true]);
            $this->assertMatchesContract($joined, 'groups.join', 200);
        }
        $this->assertMatchesContract($this->getJson('/api/choir/nope'), 'groups.join.show', 404);
        $this->assertMatchesContract($this->postJson('/api/choir/nope', ['adult' => true]), 'groups.join', 404);

        // A member sees only themselves, and can't direct.
        $this->getJson('/api/groups/'.$id)->assertOk()->assertJsonPath('data.role', 'member')->assertJsonCount(1, 'data.members')->assertJsonPath('data.link', null);
        $this->assertMatchesContract($this->patchJson('/api/groups/'.$id, ['name' => 'Mine']), 'groups.update', 403);
        $this->assertMatchesContract($this->postJson('/api/groups/'.$id.'/link'), 'groups.link.create', 403);
        $this->assertMatchesContract($this->postJson('/api/groups/'.$id.'/songs', ['templateId' => 'amazing-grace']), 'groups.songs.create', 403);
        $this->assertMatchesContract($this->putJson('/api/groups/'.$id.'/songs/amazing-grace/assignments', ['assignments' => []]), 'groups.assignments.update', 403);
        $this->assertMatchesContract($this->deleteJson('/api/groups/'.$id.'/songs/amazing-grace'), 'groups.songs.delete', 403);
        $this->assertMatchesContract($this->deleteJson('/api/groups/'.$id), 'groups.delete', 403);
        $this->assertMatchesContract($this->deleteJson('/api/groups/'.$id.'/members/'.$members['Sam']->sqid()), 'groups.members.delete', 403);

        $this->as($director);
        $assigned = $this->putJson('/api/groups/'.$id.'/songs/amazing-grace/assignments', ['assignments' => [
            ['memberId' => $members['Sam']->sqid(), 'partKey' => 'alto'],
            ['memberId' => $members['Kim']->sqid(), 'partKey' => 'tenor'],
            ['memberId' => $members['Lee']->sqid(), 'partKey' => 'bass'],
        ]]);
        $this->assertMatchesContract($assigned, 'groups.assignments.update', 200);
        $bad = $this->putJson('/api/groups/'.$id.'/songs/amazing-grace/assignments', ['assignments' => [['memberId' => $members['Sam']->sqid(), 'partKey' => 'descant']]]);
        $this->assertMatchesContract($bad, 'groups.assignments.update', 422);

        // Sam records the alto in a project from the song; Kim has only started.
        $this->as($members['Sam']);
        [, $part] = $this->projectWithPart();
        $take = $this->recordTake($part);
        $this->savePart($part, Part::query()->where('uuid', $part)->firstOrFail()->project->uuid, 1, ['currentTakeId' => $take->uuid])->assertOk();
        $this->as($members['Kim']);
        $this->saveProject((string) Str::uuid())->assertOk();

        $this->as($director);
        $view = $this->getJson('/api/groups/'.$id);
        $this->assertMatchesContract($view, 'groups.show', 200);
        $status = collect((array) $view->json('data.members'))->mapWithKeys(fn (array $m): array => [$m['name'] => $m['assignments'][0]['status'] ?? null])->all();
        $this->assertSame(['Ana Ruiz' => null, 'Sam' => 'recorded', 'Kim' => 'started', 'Lee' => 'none'], $status);

        $list = $this->getJson('/api/groups');
        $this->assertMatchesContract($list, 'groups.list', 200);
        $list->assertJsonPath('data.0.members', 4)->assertJsonPath('data.0.songs', 1);
        $this->assertMatchesContract($this->patchJson('/api/groups/'.$id, ['name' => 'See www.x.com']), 'groups.update', 422);
        $this->assertMatchesContract($this->patchJson('/api/groups/'.$id, ['name' => 'Thursday Choir']), 'groups.update', 200);
    }

    public function test_the_director_sees_the_join_link_again_and_a_member_never_does(): void
    {
        [$director, $id, $token] = $this->group();
        $shown = $this->getJson('/api/groups/'.$id);
        $this->assertMatchesContract($shown, 'groups.show', 200);
        $shown->assertJsonPath('data.link.url', rtrim((string) config('app.url'), '/').'/choir/'.$token);
        $this->assertNotSame($token, Group::query()->sole()->getRawOriginal('link_token'));

        $this->as(User::factory()->create());
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertOk();
        $this->getJson('/api/groups/'.$id)->assertJsonPath('data.link', null);

        // A link made before links were kept: no address to show, so the app offers Reset link.
        Group::query()->update(['link_token' => null]);
        $this->as($director);
        $this->getJson('/api/groups/'.$id)->assertJsonPath('data.link.url', null);
        $again = Str::afterLast((string) $this->postJson('/api/groups/'.$id.'/link')->json('data.url'), '/choir/');
        $this->getJson('/api/groups/'.$id)->assertJsonPath('data.link.url', rtrim((string) config('app.url'), '/').'/choir/'.$again);
    }

    public function test_members_learn_the_groups_plus_songs_while_theyre_in_it(): void
    {
        [, $id, $token] = $this->group();
        $plusSong = Template::query()->official()->where('access', 'plus')->firstOrFail();
        $this->postJson('/api/groups/'.$id.'/songs', ['templateId' => $plusSong->slug])->assertOk();

        $member = $this->as(User::factory()->create());
        $this->getJson('/api/templates/'.$plusSong->slug)->assertForbidden();
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertOk();
        $this->getJson('/api/templates/'.$plusSong->slug)->assertOk()->assertJsonPath('data.grant', 'group');

        // Only while the director has Plus: a month of Plus doesn't give the group the songs for good.
        $director = User::query()->whereKey(Group::query()->whereSqid($id)->value('director_id'))->firstOrFail();
        $director->forceFill(['plan' => 'free'])->save();
        $this->getJson('/api/templates/'.$plusSong->slug)->assertForbidden();
        $director->forceFill(['plan' => 'plus'])->save();
        $this->getJson('/api/templates/'.$plusSong->slug)->assertOk();

        $this->assertMatchesContract($this->deleteJson('/api/groups/'.$id.'/members/'.$member->sqid()), 'groups.members.delete', 204);
        $this->as($member);
        $this->getJson('/api/templates/'.$plusSong->slug)->assertForbidden();
    }

    public function test_a_directors_own_template_is_shared_with_the_group_only(): void
    {
        [$director, $id, $token] = $this->group();
        $uuid = (string) Str::uuid();
        $json = [...(array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true), 'id' => $uuid, 'version' => 1, 'reviewed' => true];
        $this->putJson('/api/templates/'.$uuid, ['changeId' => (string) Str::uuid(), 'template' => $json])->assertOk();
        $this->postJson('/api/groups/'.$id.'/songs', ['templateId' => $uuid])->assertOk()->assertJsonPath('data.songs.0.own', true);

        $this->as(User::factory()->create());
        $this->getJson('/api/templates/'.$uuid)->assertNotFound();
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertOk();
        $this->getJson('/api/templates/'.$uuid)->assertOk()->assertJsonPath('data.grant', 'group');

        $this->as($director);
        $this->assertMatchesContract($this->deleteJson('/api/groups/'.$id.'/songs/'.$uuid), 'groups.songs.delete', 204);
    }

    public function test_groups_are_plus_with_limits_and_go_read_only_when_plus_ends(): void
    {
        $free = $this->as(User::factory()->create());
        $this->assertMatchesContract($this->postJson('/api/groups', ['name' => 'Choir', 'adult' => true]), 'groups.create', 403);
        $this->assertMatchesContract($this->postJson('/api/groups', ['name' => '', 'adult' => true]), 'groups.create', 422);
        $this->assertMatchesContract($this->getJson('/api/groups/nope'), 'groups.show', 404);

        [$director, $id, $token] = $this->group();
        foreach (range(2, 5) as $i) {
            $this->postJson('/api/groups', ['name' => 'Choir '.$i, 'adult' => true])->assertCreated();
        }
        $this->assertMatchesContract($this->postJson('/api/groups', ['name' => 'One too many', 'adult' => true]), 'groups.create', 403);

        config(['usage.group_members' => 1]);
        $this->as(User::factory()->create());
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertOk();
        $this->as(User::factory()->create());
        $this->assertMatchesContract($this->postJson('/api/choir/'.$token, ['adult' => true]), 'groups.join', 403);

        // Plus ends: the group stays, but takes nothing new.
        $director->forceFill(['plan' => 'free'])->save();
        $this->as($director);
        $this->getJson('/api/groups/'.$id)->assertJsonPath('data.readOnly', true);
        $this->assertMatchesContract($this->postJson('/api/groups/'.$id.'/songs', ['templateId' => 'amazing-grace']), 'groups.songs.create', 403);
        $this->assertMatchesContract($this->postJson('/api/groups/'.$id.'/link'), 'groups.link.create', 403);
        $this->assertMatchesContract($this->putJson('/api/groups/'.$id.'/songs/amazing-grace/assignments', ['assignments' => []]), 'groups.assignments.update', 403);
        config(['usage.group_members' => 60]);
        $this->as(User::factory()->create());
        $this->assertMatchesContract($this->postJson('/api/choir/'.$token, ['adult' => true]), 'groups.join', 403);

        $this->as($director);
        $this->assertMatchesContract($this->deleteJson('/api/groups/'.$id), 'groups.delete', 204);
        $this->assertNull(Group::query()->find(Ids::decode($id)));
        $this->assertSame(0, GroupMember::query()->whereNull('group_id')->count());
        $this->assertNotNull($free);
    }

    public function test_leaving_a_group_tells_the_members_devices_their_plus_song_project_changed(): void
    {
        [, $id, $token] = $this->group();
        $plusSong = Template::query()->official()->where('access', 'plus')->firstOrFail();
        $this->postJson('/api/groups/'.$id.'/songs', ['templateId' => $plusSong->slug])->assertOk();

        $member = $this->as(User::factory()->create());
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertOk();
        $project = (string) Str::uuid();
        $this->saveProject($project, 0, ['template' => ['id' => $plusSong->slug, 'version' => (int) $plusSong->currentVersion?->version]])->assertOk();
        $this->getJson('/api/projects/'.$project)->assertJsonPath('data.project.readOnly', null);
        $cursor = (string) $this->pull()->json('meta.cursor');

        $this->deleteJson('/api/groups/'.$id.'/members/'.$member->sqid())->assertNoContent();

        $this->as($member);
        $pulled = $this->pull($cursor)->assertOk();
        $this->assertSame([$project], array_column((array) $pulled->json('data.projects'), 'id'));
        $pulled->assertJsonPath('data.projects.0.readOnly', 'plus_song');
    }

    public function test_groups_are_18_and_over_asked_once_until_hillel_decides(): void
    {
        [, , $token] = $this->group();

        $teen = $this->as(User::factory()->create());
        $asked = $this->postJson('/api/choir/'.$token);
        $this->assertMatchesContract($asked, 'groups.join', 403);
        $asked->assertJsonPath('code', 'adult_required');
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertOk();
        $this->assertNotNull($teen->fresh()?->adult_confirmed_at);

        $plus = $this->as(User::factory()->create(['plan' => 'plus']));
        $this->assertMatchesContract($this->postJson('/api/groups', ['name' => 'Choir']), 'groups.create', 403);
        $this->assertNotNull($plus);

        // Opened to every account (13 and over) once Hillel says so.
        config(['usage.group_min_age' => 13]);
        $this->as(User::factory()->create());
        $this->postJson('/api/choir/'.$token)->assertOk();
    }

    public function test_someone_outside_a_group_finds_nothing_to_change_or_read_in_it(): void
    {
        [$director, $id] = $this->group();
        $this->postJson('/api/groups/'.$id.'/songs', ['templateId' => 'amazing-grace'])->assertOk();
        $memberId = $director->sqid();
        $this->as(User::factory()->create(['plan' => 'plus']));

        foreach ([
            ['groups.update', 'patchJson', '', ['name' => 'Mine now']],
            ['groups.members.delete', 'deleteJson', '/members/'.$memberId, []],
            ['groups.songs.delete', 'deleteJson', '/songs/amazing-grace', []],
            ['groups.assignments.update', 'putJson', '/songs/amazing-grace/assignments', ['assignments' => []]],
            ['groups.rehearsals.list', 'getJson', '/rehearsals', []],
            ['groups.eventSchedule.update', 'putJson', '/event-schedule', ['schedule' => 'any']],
            ['groups.eventSchedule.members', 'getJson', '/event-schedule/members', []],
            ['groups.eventSchedule.delete', 'deleteJson', '/event-schedule', []],
            ['groups.delete', 'deleteJson', '', []],
        ] as [$operation, $method, $path, $body]) {
            $this->assertMatchesContract($this->{$method}('/api/groups/'.$id.$path, $body), $operation, 404);
        }

        $this->as($director);
        $this->getJson('/api/groups/'.$id)->assertOk()->assertJsonPath('data.name', 'Thursday Singers');
    }
}
