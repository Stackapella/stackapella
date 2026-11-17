<?php

declare(strict_types=1);

namespace Tests\Feature\Plus;

use App\Models\Take;
use App\Models\Template;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\CloudSave;
use Tests\Support\Plus;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * Private arrangements shared with anyone need Plus for every person in them (Hillel, 2026-11-17; the plus_private
 * switch; docs/pricing-plan.md, top): creator or someone else, Plus or free, alone or shared, and read-only (listed,
 * every take kept, nothing deleted) until Plus. Choir groups stay covered by their leader, songs we give stay exempt.
 */
final class PrivateArrangementsTest extends TestCase
{
    use CloudSave;
    use Plus;
    use RefreshDatabase;

    private function as(User $user): User
    {
        $user = $user->fresh() ?? $user;
        $this->actingAs($user);

        return $user;
    }

    private function on(): void
    {
        app(Flags::class)->set('plus_private', 'on', null, 'test');
    }

    private function plan(User $user, string $plan): void
    {
        User::query()->whereKey($user->id)->update(['plan' => $plan]);
    }

    /**
     * The signed-in person's own arrangement (Amazing Grace's notes under a new id), saved.
     *
     * @param  array<string, mixed>  $with
     */
    private function arrangement(array $with = []): string
    {
        $id = (string) Str::uuid();
        $data = (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);
        $this->putJson('/api/templates/'.$id, ['changeId' => (string) Str::uuid(), 'template' => [...$data, 'id' => $id, 'version' => 1, 'title' => 'Our Song for Gran', 'reviewed' => true], ...$with])->assertOk();

        return $id;
    }

    /**
     * A project made from it, with one part: [projectId, partId].
     *
     * @return array{0: string, 1: string}
     */
    private function projectFrom(string $template): array
    {
        $project = (string) Str::uuid();
        $part = (string) Str::uuid();
        $this->saveProject($project, 0, ['title' => 'Our Song for Gran', 'template' => ['id' => $template, 'version' => 1]])->assertOk();
        $this->savePart($part, $project)->assertOk();

        return [$project, $part];
    }

    private function inviteToken(string $project): string
    {
        $url = (string) $this->postJson('/api/projects/'.$project.'/invites', ['partKey' => 'tenor', 'partName' => 'Tenor'])->assertCreated()->json('data.url');

        return Str::afterLast($url, '/join/');
    }

    private function take(string $part): TestResponse
    {
        return $this->startTake((string) Str::uuid(), $part, Wav::tone(0.5));
    }

    /**
     * A creator with Plus, their arrangement, its project, and a friend with Plus singing on it, each with a take.
     *
     * @return array{owner: User, friend: User, template: string, project: string, part: string, theirs: string, ownersTake: Take, friendsTake: Take}
     */
    private function shared(): array
    {
        $owner = $this->owner();
        $this->withPlus();
        $template = $this->arrangement();
        [$project, $part] = $this->projectFrom($template);
        $ownersTake = $this->recordTake($part);
        $token = $this->inviteToken($project);

        $friend = $this->as(User::factory()->create(['name' => 'Sam Lee', 'plan' => 'plus']));
        $this->postJson('/api/join/'.$token)->assertOk();
        $theirs = (string) Str::uuid();
        $this->savePart($theirs, $project, 0, ['name' => 'Tenor', 'templatePartKey' => 'tenor'])->assertOk();
        $friendsTake = $this->recordTake($theirs);

        return ['owner' => $owner, 'friend' => $friend, 'template' => $template, 'project' => $project, 'part' => $part, 'theirs' => $theirs, 'ownersTake' => $ownersTake, 'friendsTake' => $friendsTake];
    }

    public function test_with_the_switch_off_a_free_creator_shares_by_link_and_invite_as_before(): void
    {
        $this->owner();
        $template = $this->arrangement(['visibility' => 'unlisted']);
        [$project] = $this->projectFrom($template);
        $token = $this->inviteToken($project);
        $this->getJson('/api/templates/mine')->assertJsonPath('data.0.plusNeeded', false);

        $this->as(User::factory()->create());
        $this->getJson('/api/templates/'.$template)->assertOk()->assertJsonPath('data.grant', 'link');
        $shown = $this->getJson('/api/join/'.$token);
        $this->assertMatchesContract($shown, 'invites.show', 200);
        $shown->assertJsonPath('data.plusNeeded', false);
        $this->postJson('/api/join/'.$token)->assertOk()->assertJsonPath('data.project.readOnly', null);
    }

    public function test_alone_a_free_creators_private_song_stays_free_and_sharing_it_needs_plus(): void
    {
        $this->on();
        $owner = $this->owner();
        $template = $this->arrangement();
        [$project, $part] = $this->projectFrom($template);

        // Creator, free, alone: everything as before.
        $mine = $this->getJson('/api/templates/mine');
        $this->assertMatchesContract($mine, 'templates.mine', 200);
        $mine->assertJsonPath('data.0.plusNeeded', false);
        $this->getJson('/api/templates/'.$template)->assertOk()->assertJsonPath('data.grant', 'own');
        $this->getJson('/api/projects/'.$project)->assertOk()->assertJsonPath('data.project.readOnly', null);
        $this->take($part)->assertCreated();

        // Sharing it is where Plus comes in: a link, or an invite (whatever plus_teams says).
        $link = $this->patchJson('/api/templates/'.$template, ['visibility' => 'unlisted']);
        $this->assertMatchesContract($link, 'templates.update', 403);
        $link->assertJsonPath('code', 'plus_required');
        $this->postJson('/api/projects/'.$project.'/invites', [])->assertForbidden()->assertJsonPath('code', 'plus_required');

        // With Plus (a trial counts), both work.
        $this->fakeStripe();
        $this->subscribe($owner, 'trialing');
        $this->as($owner);
        $this->patchJson('/api/templates/'.$template, ['visibility' => 'unlisted'])->assertOk();
        $this->inviteToken($project);
        $this->getJson('/api/templates/mine')->assertJsonPath('data.0.plusNeeded', false);
    }

    public function test_by_its_link_everyone_needs_their_own_plus_and_a_guest_an_account_first(): void
    {
        $this->on();
        $this->owner();
        $this->withPlus();
        $template = $this->arrangement(['visibility' => 'unlisted']);

        // Someone else, free: the Plus sheet (never the song, never "not found").
        $this->as(User::factory()->create());
        $refused = $this->getJson('/api/templates/'.$template);
        $this->assertMatchesContract($refused, 'templates.show', 403);
        $refused->assertJsonPath('code', 'plus_required');
        $named = $this->saveProject((string) Str::uuid(), 0, ['template' => ['id' => $template, 'version' => 1]]);
        $this->assertMatchesContract($named, 'projects.save', 403);
        $named->assertJsonPath('code', 'plus_required');

        // Someone else with Plus: open.
        $this->withPlus();
        $this->getJson('/api/templates/'.$template)->assertOk()->assertJsonPath('data.grant', 'link');

        // A guest: an account first.
        auth()->forgetGuards();
        $guest = $this->getJson('/api/templates/'.$template);
        $guest->assertForbidden()->assertJsonPath('code', 'account_required');
    }

    public function test_an_invite_needs_the_joiners_own_plus_and_a_lapsed_friend_keeps_the_song_and_their_takes_read_only(): void
    {
        $this->on();
        $owner = $this->owner();
        $this->withPlus();
        $template = $this->arrangement();
        [$project, $part] = $this->projectFrom($template);
        $ownersTake = $this->recordTake($part);
        $token = $this->inviteToken($project);

        // Someone else, free: told before Join, refused at Join (no member made).
        $friend = $this->as(User::factory()->create(['name' => 'Sam Lee']));
        $shown = $this->getJson('/api/join/'.$token);
        $this->assertMatchesContract($shown, 'invites.show', 200);
        $shown->assertJsonPath('data.plusNeeded', true);
        $refused = $this->postJson('/api/join/'.$token);
        $this->assertMatchesContract($refused, 'invites.accept', 403);
        $refused->assertJsonPath('code', 'plus_required');
        $this->getJson('/api/projects/'.$project)->assertNotFound();

        // With Plus: joins and sings ("I'll cover it" is gone for private songs: each pays their own).
        $this->plan($friend, 'plus');
        $this->as($friend);
        $this->getJson('/api/join/'.$token)->assertJsonPath('data.plusNeeded', false);
        $this->postJson('/api/join/'.$token)->assertOk()->assertJsonPath('data.project.readOnly', null);
        $theirs = (string) Str::uuid();
        $this->savePart($theirs, $project, 0, ['name' => 'Tenor', 'templatePartKey' => 'tenor'])->assertOk();
        $friendsTake = $this->recordTake($theirs);
        $this->getJson('/api/templates/'.$template)->assertOk()->assertJsonPath('data.grant', 'project');
        $this->getJson('/api/takes/'.$ownersTake->uuid)->assertOk();

        // Their Plus ends: still listed, every take kept, nothing new, the Plus sheet on opening.
        $this->plan($friend, 'free');
        $this->as($friend);
        $takes = Take::query()->count();
        $listed = $this->getJson('/api/projects/shared');
        $this->assertMatchesContract($listed, 'projects.shared', 200);
        $listed->assertJsonPath('data.0.project.id', $project)->assertJsonPath('data.0.project.readOnly', 'plus_needed');
        $read = $this->getJson('/api/projects/'.$project);
        $this->assertMatchesContract($read, 'projects.show', 200);
        $read->assertJsonPath('data.project.readOnly', 'plus_needed');
        $this->assertMatchesContract($this->getJson('/api/templates/'.$template), 'templates.show', 403);
        $refusedTake = $this->take($theirs);
        $this->assertMatchesContract($refusedTake, 'takes.create', 403);
        $refusedTake->assertJsonPath('code', 'plus_required');
        $other = $this->getJson('/api/takes/'.$ownersTake->uuid);
        $this->assertMatchesContract($other, 'takes.show', 403);
        $other->assertJsonPath('code', 'plus_required');
        $this->getJson('/api/takes/'.$friendsTake->uuid)->assertOk();
        $this->getJson('/api/parts/'.$part.'/takes')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/parts/'.$theirs.'/takes')->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($takes, Take::query()->count());

        // Plus again: open again at once.
        $this->plan($friend, 'plus');
        $this->as($friend);
        $this->getJson('/api/projects/'.$project)->assertJsonPath('data.project.readOnly', null);
        $this->take($theirs)->assertCreated();
        $this->assertSame('plus', User::query()->whereKey($owner->id)->value('plan'));
    }

    public function test_when_the_creators_plus_ends_their_shared_song_is_read_only_for_them_and_friends_keep_singing(): void
    {
        $this->on();
        app(Flags::class)->set('plus_teams', 'on', null, 'test');
        ['owner' => $owner, 'friend' => $friend, 'template' => $template, 'project' => $project, 'part' => $part, 'theirs' => $theirs, 'friendsTake' => $friendsTake] = $this->shared();

        $this->plan($owner, 'free');
        $this->as($owner);
        $mine = $this->getJson('/api/templates/mine');
        $this->assertMatchesContract($mine, 'templates.mine', 200);
        $mine->assertJsonPath('data.0.plusNeeded', true);
        $this->getJson('/api/projects/'.$project)->assertJsonPath('data.project.readOnly', 'plus_needed');
        $this->take($part)->assertForbidden()->assertJsonPath('code', 'plus_required');
        $this->getJson('/api/takes/'.$friendsTake->uuid)->assertForbidden()->assertJsonPath('code', 'plus_required');
        $data = (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);
        $saved = $this->putJson('/api/templates/'.$template, ['changeId' => (string) Str::uuid(), 'template' => [...$data, 'id' => $template, 'version' => 2, 'title' => 'Our Song for Gran', 'reviewed' => true]]);
        $this->assertMatchesContract($saved, 'templates.save', 403);
        $saved->assertJsonPath('code', 'plus_required');
        $wav = Wav::tone(1.0);
        $guide = $this->postJson('/api/templates/'.$template.'/recordings', ['id' => (string) Str::uuid(), 'partKey' => 'alto', 'sampleRate' => 48000, 'frames' => 48000, 'offsetFrames' => 0, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)]);
        $this->assertMatchesContract($guide, 'templates.recordings.create', 403);
        $guide->assertJsonPath('code', 'plus_required');
        $this->getJson('/api/templates/'.$template)->assertOk()->assertJsonPath('data.grant', 'own');

        // The rule is per person (Hillel: "They keep singing"): the friend's own Plus keeps the song open for them,
        // with no pause from plus_teams.
        $this->as($friend);
        $this->getJson('/api/projects/'.$project)->assertJsonPath('data.project.readOnly', null);
        $this->take($theirs)->assertCreated();
        $this->getJson('/api/templates/'.$template)->assertOk();
    }

    public function test_a_creator_whose_song_is_shared_only_by_link_gets_it_back_free_by_turning_the_link_off(): void
    {
        $this->on();
        $owner = $this->owner();
        $this->withPlus();
        $template = $this->arrangement(['visibility' => 'unlisted']);
        $this->plan($owner, 'free');
        $this->as($owner);
        $this->getJson('/api/templates/mine')->assertJsonPath('data.0.plusNeeded', true);

        $this->patchJson('/api/templates/'.$template, ['visibility' => 'private'])->assertOk()->assertJsonPath('data.plusNeeded', false);
        $data = (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);
        $this->putJson('/api/templates/'.$template, ['changeId' => (string) Str::uuid(), 'template' => [...$data, 'id' => $template, 'version' => 2, 'title' => 'Our Song for Gran', 'reviewed' => true]])->assertOk();
    }

    public function test_a_choir_group_stays_covered_by_its_leader_and_a_song_we_gave_stays_exempt(): void
    {
        $this->on();
        $leader = $this->owner();
        $this->withPlus();
        $template = $this->arrangement();
        $group = (string) $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true])->assertCreated()->json('data.id');
        $token = Str::afterLast((string) $this->postJson('/api/groups/'.$group.'/link')->assertCreated()->json('data.url'), '/choir/');
        $this->postJson('/api/groups/'.$group.'/songs', ['templateId' => $template])->assertOk();

        // A free member: the group's song opens, they sing it on their own project, and that never makes it shared.
        $member = $this->as(User::factory()->create());
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertOk();
        $this->getJson('/api/templates/'.$template)->assertOk()->assertJsonPath('data.grant', 'group');
        [, $part] = $this->projectFrom($template);
        $this->take($part)->assertCreated();
        $this->plan($leader, 'free');
        $this->as($leader);
        $this->getJson('/api/templates/mine')->assertJsonPath('data.0.plusNeeded', false);

        // A song we gave (the partner's named confirmation): exempt, by its link too.
        Template::query()->where('slug', $template)->update(['visibility' => 'unlisted', 'rights_statement' => 'partner', 'rights_confirmed_by' => 'A Partner <partner@example.com>']);
        $this->as(User::factory()->create());
        $this->getJson('/api/templates/'.$template)->assertOk()->assertJsonPath('data.grant', 'link');
        $this->assertNotNull($member->id);
    }
}
