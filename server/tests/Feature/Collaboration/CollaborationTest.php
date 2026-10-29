<?php

declare(strict_types=1);

namespace Tests\Feature\Collaboration;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Actions\Users\DeleteAccount;
use App\Jobs\ProcessTake;
use App\Models\Part;
use App\Models\Project;
use App\Models\ProjectInvite;
use App\Models\ProjectMember;
use App\Models\Take;
use App\Models\User;
use App\Support\Usage\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CloudSave;
use Tests\Support\Wav;
use Tests\TestCase;

final class CollaborationTest extends TestCase
{
    use CloudSave;
    use RefreshDatabase;

    private function as(User $user): User
    {
        $user = $user->fresh() ?? $user;
        $this->actingAs($user);

        return $user;
    }

    /**
     * The owner's project with one part, and a singer who joined it by an invite.
     *
     * @return array{0: User, 1: User, 2: string, 3: string}
     */
    private function sharedProject(): array
    {
        $owner = $this->owner();
        [$project, $part] = $this->projectWithPart();
        $token = $this->inviteToken($project);

        $singer = $this->as(User::factory()->create(['name' => 'Sam Lee']));
        $this->postJson('/api/join/'.$token)->assertOk();

        return [$owner, $singer, $project, $part];
    }

    private function inviteToken(string $project): string
    {
        $url = (string) $this->postJson('/api/projects/'.$project.'/invites', ['partKey' => 'tenor', 'partName' => 'Tenor'])->assertCreated()->json('data.url');

        return Str::afterLast($url, '/join/');
    }

    public function test_an_invite_link_shows_what_its_for_and_joining_it_shares_the_project(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $owner = $this->owner();
        [$project, $part] = $this->projectWithPart();

        $made = $this->postJson('/api/projects/'.$project.'/invites', ['partKey' => 'tenor', 'partName' => 'Tenor']);
        $this->assertMatchesContract($made, 'invites.create', 201);
        $token = Str::afterLast((string) $made->json('data.url'), '/join/');
        $this->assertSame(hash('sha256', $token), ProjectInvite::query()->value('token_hash'));

        $listed = $this->getJson('/api/projects/'.$project.'/invites');
        $this->assertMatchesContract($listed, 'invites.list', 200);
        $listed->assertJsonCount(1, 'data');

        // Anyone holding the link sees what it's for, signed out too.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $shown = $this->getJson('/api/join/'.$token);
        $this->assertMatchesContract($shown, 'invites.show', 200);
        $shown->assertJsonPath('data.owner.name', $owner->name)->assertJsonPath('data.partName', 'Tenor')->assertJsonPath('data.song.id', 'amazing-grace');
        $this->assertMatchesContract($this->getJson('/api/join/nope'), 'invites.show', 404);

        $singer = $this->as(User::factory()->create());
        $joined = $this->postJson('/api/join/'.$token);
        $this->assertMatchesContract($joined, 'invites.accept', 200);
        $this->assertMatchesContract($this->postJson('/api/join/nope'), 'invites.accept', 404);
        $joined->assertJsonPath('data.project.id', $project);
        // Joining again, or the owner's own link: the project as it is.
        $this->postJson('/api/join/'.$token)->assertOk();

        $shared = $this->getJson('/api/projects/shared');
        $this->assertMatchesContract($shared, 'projects.shared', 200);
        $shared->assertJsonPath('data.0.project.id', $project)->assertJsonPath('data.0.owner.id', $owner->sqid())->assertJsonPath('data.0.parts.0.id', $part);
        $this->getJson('/api/projects/'.$project)->assertOk();

        $members = $this->getJson('/api/projects/'.$project.'/members');
        $this->assertMatchesContract($members, 'projects.members.list', 200);
        $members->assertJsonPath('data.0.role', 'owner')->assertJsonPath('data.1.id', $singer->sqid());

        // The link is used up for anyone else.
        $this->as(User::factory()->create());
        $used = $this->postJson('/api/join/'.$token);
        $this->assertMatchesContract($used, 'invites.accept', 410);
        $used->assertJsonPath('code', 'invite_used');
        $this->assertMatchesContract($this->getJson('/api/join/'.$token), 'invites.show', 410);
        $this->assertMatchesContract($this->getJson('/api/projects/'.$project.'/members'), 'projects.members.list', 404);
    }

    public function test_a_singer_sings_their_own_parts_and_everything_counts_toward_the_owner(): void
    {
        [$owner, $singer, $project, $ownersPart] = $this->sharedProject();
        $theirs = (string) Str::uuid();

        $made = $this->savePart($theirs, $project, 0, ['name' => 'Tenor', 'templatePartKey' => 'tenor']);
        $made->assertOk()->assertJsonPath('data.createdBy.id', $singer->sqid())->assertJsonPath('data.createdBy.name', 'Sam Lee');

        $take = (string) Str::uuid();
        $wav = Wav::tone(1.0);
        $this->startTake($take, $theirs, $wav)->assertCreated();
        $row = Take::query()->where('uuid', $take)->firstOrFail();
        $this->assertSame([$owner->id, $singer->id], [$row->user_id, $row->recorded_by]);
        Storage::disk('private')->put($row->upload_key, $wav);
        $this->postJson('/api/takes/'.$take.'/complete')->assertOk();
        app()->call([new ProcessTake($row->id), 'handle']);
        $this->savePart($theirs, $project, 1, ['name' => 'Tenor', 'templatePartKey' => 'tenor', 'currentTakeId' => $take])->assertOk();

        // The singer can't touch the owner's part or the project itself.
        $this->assertMatchesContract($this->savePart($ownersPart, $project, 1, ['name' => 'Mine now']), 'parts.save', 403);
        $this->assertMatchesContract($this->deleteJson('/api/parts/'.$ownersPart.'?version=1&changeId='.Str::uuid()), 'parts.delete', 403);
        $this->assertMatchesContract($this->startTake((string) Str::uuid(), $ownersPart, $wav), 'takes.create', 403);
        $this->assertMatchesContract($this->saveProject($project, 1, ['title' => 'Renamed']), 'projects.save', 403);
        $this->assertMatchesContract($this->deleteJson('/api/projects/'.$project.'?version=1&changeId='.Str::uuid()), 'projects.delete', 403);
        $this->assertMatchesContract($this->postJson('/api/projects/'.$project.'/restore'), 'projects.restore', 403);
        $this->assertMatchesContract($this->postJson('/api/takes/'.$take.'/restore'), 'takes.restore', 200);
        // Nor bring back a take of the owner's part.
        $ownersTake = Take::factory()->create(['part_id' => Part::query()->where('uuid', $ownersPart)->value('id'), 'stored_bytes' => 0]);
        $this->assertMatchesContract($this->postJson('/api/takes/'.$ownersTake->uuid.'/restore'), 'takes.restore', 403);
        $this->postJson('/api/takes/'.$ownersTake->uuid.'/restore')->assertJsonPath('code', 'not_your_part');
        $this->getJson('/api/takes/'.$take)->assertOk();

        // The owner's devices get the singer's part and take in their own pull, and their storage counts it.
        $this->as($owner);
        $pull = $this->pull()->assertOk();
        $this->assertContains($theirs, array_column((array) $pull->json('data.parts'), 'id'));
        $this->assertContains($take, array_column((array) $pull->json('data.takes'), 'id'));
        $this->assertSame((int) $row->fresh()?->stored_bytes, app(Plans::class)->storageUsed($owner->fresh() ?? $owner));
        $this->assertSame(0, app(Plans::class)->storageUsed($singer->fresh() ?? $singer));
        // The owner may change anything, the singer's part included.
        $this->savePart($theirs, $project, 2, ['name' => 'Tenor (Sam)', 'templatePartKey' => 'tenor', 'currentTakeId' => $take])->assertOk();
    }

    public function test_how_many_may_sing_follows_the_owners_plan(): void
    {
        $this->owner();
        [$project] = $this->projectWithPart();

        $this->inviteToken($project);
        $this->inviteToken($project);
        $full = $this->postJson('/api/projects/'.$project.'/invites', []);
        $this->assertMatchesContract($full, 'invites.create', 403);
        $full->assertJsonPath('code', 'invite_limit');

        $this->assertMatchesContract($this->postJson('/api/projects/'.Str::uuid().'/invites', []), 'invites.create', 404);

        // A revoked invite frees its place, and its link stops working.
        $invite = ProjectInvite::query()->firstOrFail();
        $this->assertMatchesContract($this->deleteJson('/api/invites/'.$invite->sqid()), 'invites.revoke', 204);
        $token = $this->inviteToken($project);
        $this->assertNotSame('', $token);

        $revoked = ProjectInvite::query()->whereNotNull('revoked_at')->firstOrFail();
        $revoked->forceFill(['token_hash' => hash('sha256', 'revoked-token')])->save();
        $this->as(User::factory()->create());
        $this->assertMatchesContract($this->postJson('/api/join/revoked-token'), 'invites.accept', 410);
        $this->getJson('/api/join/revoked-token')->assertJsonPath('code', 'invite_revoked');

        $expired = ProjectInvite::query()->whereNull('revoked_at')->firstOrFail();
        $expired->forceFill(['token_hash' => hash('sha256', 'expired-token'), 'expires_at' => now()->subMinute()])->save();
        $this->postJson('/api/join/expired-token')->assertStatus(410)->assertJsonPath('code', 'invite_expired');

    }

    public function test_a_full_project_refuses_one_more_singer(): void
    {
        $owner = $this->owner();
        [$project] = $this->projectWithPart();
        $tokens = [$this->inviteToken($project), $this->inviteToken($project)];
        $singers = [];
        foreach ($tokens as $token) {
            $singers[] = $this->as(User::factory()->create());
            $this->postJson('/api/join/'.$token)->assertOk();
        }

        // Two singers is the free limit: an invite made before can't take a third.
        ProjectInvite::factory()->create(['project_id' => Project::query()->where('uuid', $project)->value('id'), 'created_by' => $owner->id, 'token_hash' => hash('sha256', 'third')]);
        $this->as(User::factory()->create());
        $full = $this->postJson('/api/join/third');
        $this->assertMatchesContract($full, 'invites.accept', 403);
        $full->assertJsonPath('code', 'invite_limit');

        // A singer on it can't invite, or see the invites.
        $this->as($singers[0]);
        $this->assertMatchesContract($this->postJson('/api/projects/'.$project.'/invites', []), 'invites.create', 403);
        $this->assertMatchesContract($this->getJson('/api/projects/'.$project.'/invites'), 'invites.list', 403);
    }

    public function test_leaving_keeps_or_takes_away_the_singers_parts_and_the_owner_cant_leave(): void
    {
        [$owner, $singer, $project] = $this->sharedProject();
        $kept = (string) Str::uuid();
        $gone = (string) Str::uuid();
        $this->savePart($kept, $project, 0, ['name' => 'Tenor'])->assertOk();

        $this->as($owner);
        $this->assertMatchesContract($this->deleteJson('/api/projects/'.$project.'/members/'.$owner->sqid()), 'projects.members.delete', 403);

        // Leaving, keeping the parts: they become the owner's.
        $this->as($singer);
        $this->assertMatchesContract($this->deleteJson('/api/projects/'.$project.'/members/'.$singer->sqid().'?keepParts=1'), 'projects.members.delete', 204);
        $this->assertNull(Part::query()->where('uuid', $kept)->value('created_by'));
        $this->assertMatchesContract($this->getJson('/api/projects/'.$project.'/members'), 'projects.members.list', 404);

        // Invited again, a part of theirs, then removed by the owner without it.
        $this->as($owner);
        $token = $this->inviteToken($project);
        $this->as($singer);
        $this->postJson('/api/join/'.$token)->assertOk();
        $this->savePart($gone, $project, 0, ['name' => 'Bass'])->assertOk();
        $this->as($owner);
        $cursor = (string) $this->pull()->json('meta.cursor');
        $this->deleteJson('/api/projects/'.$project.'/members/'.$singer->sqid().'?keepParts=0')->assertNoContent();
        $this->assertNull(Part::query()->where('uuid', $gone)->first());
        $this->assertContains($gone, array_column((array) $this->pull($cursor)->json('data.removed'), 'id'));
        $this->assertSame(0, ProjectMember::query()->count());
    }

    public function test_a_singer_cant_remove_anyone_else(): void
    {
        [$owner, $singer, $project] = $this->sharedProject();
        $this->as($owner);
        $other = User::factory()->create();
        ProjectMember::factory()->create(['project_id' => Project::query()->where('uuid', $project)->value('id'), 'user_id' => $other->id]);

        $this->as($singer);
        $this->deleteJson('/api/projects/'.$project.'/members/'.$other->sqid())->assertForbidden()->assertJsonPath('code', 'owner_only');
        $this->assertMatchesContract($this->deleteJson('/api/projects/'.Str::uuid().'/members/'.$other->sqid()), 'projects.members.delete', 404);
    }

    public function test_a_singer_deleting_their_account_takes_their_singing_out_of_the_owners_project(): void
    {
        [$owner, $singer, $project] = $this->sharedProject();
        $theirs = (string) Str::uuid();
        $this->savePart($theirs, $project, 0, ['name' => 'Tenor'])->assertOk();
        $take = (string) Str::uuid();
        $wav = Wav::tone(1.0);
        $this->startTake($take, $theirs, $wav)->assertCreated();
        $this->savePart($theirs, $project, 1, ['name' => 'Tenor', 'currentTakeId' => $take])->assertOk();

        app(DeleteAccount::class)->handle($singer->fresh() ?? $singer, '203.0.113.9');

        $this->assertNull(Take::query()->where('uuid', $take)->first());
        $part = Part::query()->where('uuid', $theirs)->firstOrFail();
        $this->assertNull($part->current_take_id);
        $this->assertNull($part->created_by);
        $this->assertSame(0, ProjectMember::query()->count());
        $this->assertNotNull($owner->fresh());
    }

    public function test_a_singer_meets_the_owners_bin_and_read_only_state_and_a_removed_singer_meets_nothing(): void
    {
        [$owner, $singer, $project] = $this->sharedProject();
        $theirs = (string) Str::uuid();
        $this->savePart($theirs, $project, 0, ['name' => 'Tenor'])->assertOk();
        $take = (string) Str::uuid();
        $wav = Wav::tone(1.0);
        $this->startTake($take, $theirs, $wav)->assertCreated();

        // The owner's plan made the project read-only: the singer is refused too.
        $this->as($owner);
        $owner->forceFill(['editable_project_ids' => []])->save();
        Project::query()->where('uuid', $project)->update(['template_slug' => 'not-a-plus-song']);
        $cursor = (string) $this->pull()->json('meta.cursor');

        // A singer's part deleted reaches the owner's pull as gone.
        $this->as($singer);
        $this->deleteJson('/api/parts/'.$theirs.'?version=1&changeId='.Str::uuid())->assertNoContent();
        $this->as($owner);
        $this->assertContains($theirs, array_column((array) $this->pull($cursor)->json('data.removed'), 'id'));

        $this->deleteJson('/api/projects/'.$project.'?version=1&changeId='.Str::uuid())->assertNoContent();
        $this->as($singer);
        $this->savePart((string) Str::uuid(), $project, 0, ['name' => 'Bass'])->assertStatus(409)->assertJsonPath('code', 'binned');
        $this->getJson('/api/projects/shared')->assertJsonCount(0, 'data');

        // Removed (leaving without parameters keeps the parts): nothing of the project answers.
        $this->as($owner);
        $this->postJson('/api/projects/'.$project.'/restore')->assertOk();
        $this->deleteJson('/api/projects/'.$project.'/members/'.$singer->sqid())->assertNoContent();
        $this->as($singer);
        $removed = $this->getJson('/api/projects/'.$project);
        $this->assertMatchesContract($removed, 'projects.show', 410);
        $removed->assertJsonPath('code', 'removed_from_project');
        $this->savePart((string) Str::uuid(), $project, 0, ['name' => 'Bass'])->assertNotFound();
        $this->postJson('/api/takes/'.$take.'/complete')->assertNotFound();
    }

    public function test_a_singer_opens_the_owners_private_template_their_project_was_made_from(): void
    {
        $owner = $this->owner();
        $uuid = (string) Str::uuid();
        $json = [...(array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true), 'id' => $uuid, 'version' => 1, 'reviewed' => true];
        $this->putJson('/api/templates/'.$uuid, ['changeId' => (string) Str::uuid(), 'template' => $json])->assertOk();
        $project = (string) Str::uuid();
        $this->saveProject($project, 0, ['template' => ['id' => $uuid, 'version' => 1]])->assertOk();
        $token = $this->inviteToken($project);

        $singer = $this->as(User::factory()->create());
        $this->getJson('/api/templates/'.$uuid)->assertNotFound();
        $this->postJson('/api/join/'.$token)->assertOk();
        $shown = $this->getJson('/api/templates/'.$uuid);
        $this->assertMatchesContract($shown, 'templates.show', 200);
        $shown->assertJsonPath('data.grant', 'project');
        $this->assertNotNull($owner->fresh());
        $this->assertNotNull($singer);

        // Someone who only learns its id can't make a project that names it, to open it that way.
        $this->as(User::factory()->create());
        $refused = $this->saveProject((string) Str::uuid(), 0, ['template' => ['id' => $uuid, 'version' => 1]]);
        $this->assertMatchesContract($refused, 'projects.save', 422);
        $refused->assertJsonValidationErrors('template.id');
        $this->getJson('/api/templates/'.$uuid)->assertNotFound();
        // An official song, or one that doesn't exist, is named freely.
        $this->saveProject((string) Str::uuid(), 0, ['template' => ['id' => 'amazing-grace', 'version' => 1]])->assertOk();
    }
}
