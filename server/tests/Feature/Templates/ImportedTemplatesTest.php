<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\Harmony;
use App\Models\Notice;
use App\Models\Project;
use App\Models\Template;
use App\Models\User;
use App\Support\Flags;
use App\Support\Gallery\Gallery;
use App\Support\Ops\Readiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Sharing;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * Songs made from imported sheet music (Hillel: "please try your best to keep us out of any
 * trouble"): the singer confirms the rights, and the song stays private to them and their choir
 * groups: every way out is refused with imported_private, and an admin can take it down.
 */
final class ImportedTemplatesTest extends TestCase
{
    use RefreshDatabase;
    use Sharing;

    protected function setUp(): void
    {
        parent::setUp();
        app(SyncOfficialTemplates::class)->handle();
        // Import is on here; closed, it's refused (test_a_new_import_waits_for_the_switch...).
        app(Flags::class)->set('sheet_import', 'on', null, 'Testing import');
    }

    public function test_a_new_import_waits_for_the_switch_and_turns_on_only_with_the_agent_named(): void
    {
        app(Flags::class)->set('sheet_import', 'off', null, 'Closed');
        $this->actingAs($this->adult());
        $this->assertMatchesContract($this->save((string) Str::uuid(), ['source' => 'sheet-music', 'rightsConfirmed' => true]), 'templates.save', 403);

        config(['site.dmca_agent' => ['name' => '', 'address' => '', 'phone' => '', 'email' => '']]);
        $this->actingAsAdmin();
        $this->assertMatchesContract($this->putJson('/api/admin/flags/sheet_import', ['value' => 'on', 'reason' => 'Ready']), 'admin.flags.update', 409);
    }

    private function adult(string $name = 'Ana Ruiz', string $plan = 'plus'): User
    {
        $user = User::factory()->create(['name' => $name, 'handle' => Str::slug($name), 'plan' => $plan, 'adult_confirmed_at' => now(), 'public_profile_at' => now(), 'trust_level_override' => 2])->fresh() ?? throw new \LogicException;
        $this->actingAs($user);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function save(string $id, array $body = [], int $version = 1): TestResponse
    {
        $data = (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);

        return $this->putJson('/api/templates/'.$id, ['changeId' => (string) Str::uuid(), 'template' => [...$data, 'id' => $id, 'version' => $version, 'title' => 'From My Score', 'reviewed' => true], ...$body]);
    }

    private function imported(): string
    {
        $id = (string) Str::uuid();
        $this->save($id, ['source' => 'sheet-music', 'rightsConfirmed' => true])->assertOk();

        return $id;
    }

    public function test_an_import_needs_the_rights_confirmed_and_is_marked_for_good(): void
    {
        $this->adult();
        $id = (string) Str::uuid();
        $this->save($id, ['source' => 'sheet-music'])->assertJsonValidationErrors('rightsConfirmed');

        $made = $this->save($id, ['source' => 'sheet-music', 'rightsConfirmed' => true]);
        $this->assertMatchesContract($made, 'templates.save', 200);
        $made->assertJsonPath('data.imported', true)->assertJsonPath('data.visibility', 'private');
        $this->assertNotNull($made->json('data.rightsConfirmedAt'));

        // A later version without the source stays imported.
        $this->save($id, [], 2)->assertOk()->assertJsonPath('data.imported', true);
        // A copy of it is imported too; a copy of someone's private song is not found.
        $copy = (string) Str::uuid();
        $this->save($copy, ['copiedFrom' => $id])->assertOk()->assertJsonPath('data.imported', true);
        $theirs = Template::factory()->create(['owner_id' => User::factory()->create()->id, 'visibility' => 'private']);
        $this->save((string) Str::uuid(), ['copiedFrom' => $theirs->slug])->assertNotFound();
        // A plain song isn't.
        $this->save((string) Str::uuid())->assertOk()->assertJsonPath('data.imported', false)->assertJsonPath('data.rightsConfirmedAt', null);
    }

    public function test_the_rights_wording_is_kept_and_a_repeat_infringer_makes_no_new_import(): void
    {
        $ana = $this->adult();
        $id = $this->imported();
        $this->assertSame('import-rights-v1', Template::query()->where('slug', $id)->value('rights_statement'));

        // Three copyright takedowns that stand: no new import (one reversed on appeal doesn't count).
        Notice::factory()->count(3)->create(['user_id' => $ana->id, 'kind' => Notice::TEMPLATE_HIDDEN, 'rule' => 'copyright']);
        $this->assertMatchesContract($this->save((string) Str::uuid(), ['source' => 'sheet-music', 'rightsConfirmed' => true]), 'templates.save', 403);
        // A new version of one they already have is still theirs to save.
        $this->save($id, [], 2)->assertOk();
        Notice::query()->where('user_id', $ana->id)->first()?->forceFill(['decision' => Notice::REVERSED])->save();
        $this->save((string) Str::uuid(), ['source' => 'sheet-music', 'rightsConfirmed' => true])->assertOk();
    }

    public function test_the_copyright_page_names_the_agent_and_the_doctor_says_when_import_can_go_on(): void
    {
        $status = fn (): ?string => collect(app(Readiness::class)->run())->firstWhere('key', 'sheet-import')?->status;
        app(Flags::class)->set('sheet_import', 'off', null, 'Not yet');
        $this->get('/legal/copyright')->assertOk()->assertSee('being registered with the US Copyright Office');
        $this->assertSame('warn', $status());
        app(Flags::class)->set('sheet_import', 'on', null, 'Too soon');
        $this->assertSame('fail', $status());

        config(['site.dmca_agent' => ['name' => 'Copyright Agent', 'address' => 'PO Box 1, Example City', 'phone' => '+1 555 0100', 'email' => 'copyright@example.com']]);
        $this->assertSame('pass', $status());
        $this->get('/legal/copyright')->assertOk()->assertSee('Our designated agent: Copyright Agent, PO Box 1, Example City, telephone +1 555 0100, copyright@example.com.')
            ->assertDontSee(':dmca_agent');
    }

    public function test_an_imported_song_is_never_unlisted_public_published_or_an_idea(): void
    {
        $this->adult();
        app(Flags::class)->set('template_library', 'on', null, 'Open');
        app(Flags::class)->set('ideas', 'on', null, 'Open');
        $this->assertMatchesContract($this->save((string) Str::uuid(), ['source' => 'sheet-music', 'rightsConfirmed' => true, 'visibility' => 'unlisted']), 'templates.save', 422);
        $id = $this->imported();
        $this->assertMatchesContract($this->save($id, ['visibility' => 'unlisted'], 2), 'templates.save', 422);
        $this->assertMatchesContract($this->patchJson('/api/templates/'.$id, ['visibility' => 'unlisted']), 'templates.update', 422);
        $this->patchJson('/api/templates/'.$id, ['libraryOptIn' => true])->assertStatus(422)->assertJsonPath('code', 'imported_private');
        $this->assertMatchesContract($this->putJson('/api/templates/'.$id.'/publication', ['song' => 'original', 'ownWork' => true]), 'templates.publish', 422);
        $this->assertMatchesContract($this->postJson('/api/ideas', ['templateId' => $id]), 'ideas.create', 422);
        $this->assertSame('private', Template::query()->where('slug', $id)->value('visibility'));
    }

    public function test_nothing_sung_from_it_is_shared_and_no_one_is_invited_onto_it(): void
    {
        $this->owner();
        $id = $this->imported();
        [$project] = $this->recordedProject();
        Project::query()->where('uuid', $project)->update(['template_slug' => $id]);

        $this->assertMatchesContract($this->startHarmony($project, Wav::stereo(2.0)), 'harmonies.create', 422);
        $this->assertMatchesContract($this->postJson('/api/projects/'.$project.'/invites', []), 'invites.create', 422);

        $harmony = $this->shareHarmony($project, null, ['visibility' => 'private']);
        $this->assertMatchesContract($this->patchJson('/api/harmonies/'.$harmony->public_id, ['visibility' => 'unlisted']), 'harmonies.update', 422);
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['inGallery' => true])->assertStatus(422)->assertJsonPath('code', 'imported_private');
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['mayFeature' => true])->assertStatus(422)->assertJsonPath('code', 'imported_private');

        // Whatever was set before, it's never public.
        Harmony::query()->whereKey($harmony->id)->update(['visibility' => Harmony::UNLISTED, 'may_feature_at' => now()]);
        $this->assertFalse(app(Gallery::class)->isPublic($harmony->refresh()));
    }

    public function test_its_choir_group_sings_and_reports_it_and_an_admin_takes_it_down(): void
    {
        $director = $this->adult('Ana Ruiz');
        $id = $this->imported();
        $group = (string) $this->postJson('/api/groups', ['name' => 'Thursday Singers', 'adult' => true])->assertCreated()->json('data.id');
        $this->postJson('/api/groups/'.$group.'/songs', ['templateId' => $id])->assertOk();
        $token = Str::afterLast((string) $this->postJson('/api/groups/'.$group.'/link')->assertCreated()->json('data.url'), '/choir/');

        $member = $this->adult('Ben Ode', 'free');
        $this->postJson('/api/choir/'.$token, ['adult' => true])->assertSuccessful();
        $this->getJson('/api/templates/'.$id)->assertOk();
        $this->postJson('/api/reports', ['templateId' => $id, 'reason' => 'copyright'])->assertSuccessful();

        // Someone outside the group can neither open nor report it.
        $this->adult('Cy Stranger', 'free');
        $this->getJson('/api/templates/'.$id)->assertNotFound();
        $this->postJson('/api/reports', ['templateId' => $id, 'reason' => 'copyright'])->assertNotFound();

        // The report reaches the admin's queue, though the song is private to its group.
        $this->actingAsAdmin();
        $held = $this->getJson('/api/admin/library/held');
        $this->assertMatchesContract($held, 'admin.library.held', 200);
        $this->assertSame([[$id, true]], array_map(fn (array $t): array => [$t['id'], $t['imported']], (array) $held->json('data')));
        $this->postJson('/api/admin/library/'.$id, ['decision' => 'hide', 'note' => 'A copyright notice from the publisher.'])->assertOk();
        $this->assertSame('copyright', Notice::query()->where('user_id', $director->id)->where('kind', Notice::TEMPLATE_HIDDEN)->value('rule'));
        $this->actingAs($member)->getJson('/api/templates/'.$id)->assertNotFound();
        $this->actingAs($director)->getJson('/api/templates/'.$id)->assertOk();
    }
}
