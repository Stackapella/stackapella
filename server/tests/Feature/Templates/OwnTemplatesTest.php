<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Jobs\ProcessTemplateRecording;
use App\Models\Template;
use App\Models\TemplateRecording;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Usage\Plans;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Wav;
use Tests\TestCase;

final class OwnTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private function as(User $user): User
    {
        $user = $user->fresh() ?? $user;
        $this->actingAs($user);

        return $user;
    }

    /**
     * A person's template, made on their device: Amazing Grace's JSON under a new id.
     *
     * @param  array<string, mixed>  $with
     * @return array<string, mixed>
     */
    private function template(string $id, int $version = 1, array $with = []): array
    {
        $data = (array) json_decode((string) file_get_contents(base_path('../web/templates/amazing-grace.json')), true);

        return [...$data, 'id' => $id, 'version' => $version, 'title' => 'Our Song for Gran', 'reviewed' => true, ...$with];
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $with
     */
    private function save(string $id, array $template, array $with = []): TestResponse
    {
        return $this->putJson('/api/templates/'.$id, ['changeId' => (string) Str::uuid(), 'template' => $template, ...$with]);
    }

    public function test_a_template_made_on_the_device_saves_version_by_version_and_shows_in_mine(): void
    {
        $this->as(User::factory()->create());
        $id = (string) Str::uuid();

        $saved = $this->save($id, $this->template($id));
        $this->assertMatchesContract($saved, 'templates.save', 200);
        $saved->assertJsonPath('data.id', $id)->assertJsonPath('data.visibility', 'private')->assertJsonPath('data.versions', [1]);

        // The same version again with the same JSON: as it is. Other JSON under it: refused.
        $this->save($id, $this->template($id))->assertOk();
        $clash = $this->save($id, $this->template($id, 1, ['title' => 'Changed']));
        $this->assertMatchesContract($clash, 'templates.save', 409);
        $clash->assertJsonPath('code', 'version_exists');

        $this->save($id, $this->template($id, 2, ['title' => 'Our Song, take two']), ['visibility' => 'unlisted'])->assertOk()->assertJsonPath('data.versions', [1, 2])->assertJsonPath('data.visibility', 'unlisted');

        $mine = $this->getJson('/api/templates/mine');
        $this->assertMatchesContract($mine, 'templates.mine', 200);
        $mine->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Our Song, take two');

        // It never joins the catalogue.
        $this->assertNotContains($id, array_column((array) $this->getJson('/api/templates')->json('data'), 'id'));
    }

    public function test_an_empty_description_and_credits_are_kept_as_sent(): void
    {
        $this->as(User::factory()->create());
        $id = (string) Str::uuid();

        // The creator leaves them optional: "" must reach the format check as "", not as null.
        $saved = $this->save($id, $this->template($id, 1, ['description' => '', 'credits' => ['words' => '', 'tune' => '', 'arrangement' => '']]));
        $this->assertMatchesContract($saved, 'templates.save', 200);
        $data = TemplateVersion::query()->latest('id')->firstOrFail()->data;
        $this->assertSame(['', '', '', ''], [$data['description'], $data['credits']['words'], $data['credits']['tune'], $data['credits']['arrangement']]);
    }

    public function test_the_template_must_be_well_formed_its_own_id_and_free_of_links(): void
    {
        $this->as(User::factory()->create());
        $id = (string) Str::uuid();

        $bad = $this->save($id, $this->template($id, 1, ['title' => 'Listen at mysite.com']));
        $this->assertMatchesContract($bad, 'templates.save', 422);
        $bad->assertJsonValidationErrors('template.title');

        $this->save($id, $this->template((string) Str::uuid()))->assertStatus(422)->assertJsonValidationErrors('template');
        $this->save($id, $this->template($id, 1, ['tempo' => 1000]))->assertStatus(422)->assertJsonValidationErrors('template');

        // A note with no pitch (NaN from a transcription arrives as null): a message naming it.
        $broken = $this->template($id);
        $broken['parts'][0]['guide']['notes'][3]['pitch'] = null;
        $named = $this->save($id, $broken)->assertStatus(422);
        $this->assertContains('part "soprano", note 4: pitch must be a MIDI note number', (array) $named->json('errors.template'));
        $this->assertContains('$.parts[0].guide.notes[3].pitch: expected integer, got null', (array) $named->json('errors.template'));
        $this->putJson('/api/templates/amazing-grace', ['changeId' => (string) Str::uuid(), 'template' => $this->template('amazing-grace')])->assertNotFound();
    }

    public function test_a_free_account_keeps_one_template_and_plus_any_number_and_a_downgrade_leaves_the_rest_read_only(): void
    {
        $user = $this->as(User::factory()->create());
        [$first, $second] = [(string) Str::uuid(), (string) Str::uuid()];
        $this->save($first, $this->template($first))->assertOk();

        $limit = $this->save($second, $this->template($second));
        $this->assertMatchesContract($limit, 'templates.save', 403);
        $limit->assertJsonPath('code', 'template_limit');

        $user->forceFill(['plan' => 'plus'])->save();
        $this->as($user);
        $this->save($second, $this->template($second))->assertOk();

        // Back to free: the one changed most recently stays editable, the other is read-only.
        $user->forceFill(['plan' => 'free'])->save();
        $this->as($user);
        Template::query()->where('slug', $first)->update(['updated_at' => now()->subDay()]);
        $readOnly = $this->save($first, $this->template($first, 2));
        $this->assertMatchesContract($readOnly, 'templates.save', 403);
        $readOnly->assertJsonPath('code', 'template_read_only');
        $this->getJson('/api/templates/mine')->assertJsonPath('data.1.readOnly', true)->assertJsonPath('data.0.readOnly', false);
        $this->assertMatchesContract($this->patchJson('/api/templates/'.$first, ['visibility' => 'unlisted']), 'templates.update', 403);
    }

    public function test_an_unlisted_template_opens_for_anyone_with_the_link_and_a_private_one_only_for_its_owner(): void
    {
        $owner = $this->as(User::factory()->create(['name' => 'Ana Ruiz']));
        $id = (string) Str::uuid();
        $this->save($id, $this->template($id))->assertOk();

        $this->getJson('/api/templates/'.$id)->assertOk()->assertJsonPath('data.grant', 'own')->assertJsonPath('data.template.id', $id);

        $this->as(User::factory()->create());
        $this->getJson('/api/templates/'.$id)->assertNotFound();

        $this->as($owner);
        $updated = $this->patchJson('/api/templates/'.$id, ['visibility' => 'unlisted', 'libraryOptIn' => true]);
        $this->assertMatchesContract($updated, 'templates.update', 200);
        $updated->assertJsonPath('data.libraryOptIn', true);
        $this->assertMatchesContract($this->patchJson('/api/templates/'.$id, ['visibility' => 'public']), 'templates.update', 422);
        $this->assertMatchesContract($this->patchJson('/api/templates/'.Str::uuid(), ['visibility' => 'private']), 'templates.update', 404);

        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->getJson('/api/templates/'.$id)->assertOk()->assertJsonPath('data.grant', 'link');

        $this->as($owner);
        $this->assertMatchesContract($this->deleteJson('/api/templates/'.$id), 'templates.delete', 204);
        $this->getJson('/api/templates/'.$id)->assertNotFound();
        $this->assertMatchesContract($this->save($id, $this->template($id, 3)), 'templates.save', 404);
        $this->assertNotNull($owner);
    }

    public function test_a_creators_recorded_guide_goes_up_like_a_take_with_plus_and_plays_from_templates_show(): void
    {
        $user = $this->as(User::factory()->create(['plan' => 'plus']));
        $id = (string) Str::uuid();
        $this->save($id, $this->template($id))->assertOk();
        $wav = Wav::tone(1.0);
        $recording = (string) Str::uuid();
        $body = ['id' => $recording, 'partKey' => 'alto', 'sampleRate' => 48000, 'frames' => 48000, 'offsetFrames' => 0, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)];

        $started = $this->postJson('/api/templates/'.$id.'/recordings', $body);
        $this->assertMatchesContract($started, 'templates.recordings.create', 201);
        $row = TemplateRecording::query()->where('uuid', $recording)->firstOrFail();

        $this->assertMatchesContract($this->postJson('/api/template-recordings/'.$recording.'/complete'), 'templates.recordings.complete', 409);
        Storage::disk('private')->put($row->upload_key, Wav::tone(1.0, hz: 330));
        $this->assertMatchesContract($this->postJson('/api/template-recordings/'.$recording.'/complete'), 'templates.recordings.complete', 409);
        Storage::disk('private')->put($row->upload_key, $wav);
        $this->assertMatchesContract($this->postJson('/api/template-recordings/'.$recording.'/complete'), 'templates.recordings.complete', 200);

        $row->refresh();
        $this->assertSame(TemplateRecording::READY, $row->status);
        Storage::disk('private')->assertExists((string) $row->file_key);
        $this->assertGreaterThanOrEqual((int) $row->stored_bytes, app(Plans::class)->storageUsed($user));

        $shown = $this->getJson('/api/templates/'.$id);
        $shown->assertJsonPath('data.voices.0.id', $recording)->assertJsonPath('data.voices.0.partKey', 'alto');
        $this->assertMatchesContract($shown, 'templates.show', 200);

        // A new recording for the part replaces the old once it's ready.
        $next = (string) Str::uuid();
        $this->postJson('/api/templates/'.$id.'/recordings', [...$body, 'id' => $next])->assertCreated();
        $nextRow = TemplateRecording::query()->where('uuid', $next)->firstOrFail();
        Storage::disk('private')->put($nextRow->upload_key, $wav);
        $this->postJson('/api/template-recordings/'.$next.'/complete')->assertOk();
        $this->assertSame([$next], TemplateRecording::query()->pluck('uuid')->all());
        Storage::disk('private')->assertMissing((string) $row->file_key);

        $this->assertMatchesContract($this->deleteJson('/api/template-recordings/'.$next), 'templates.recordings.delete', 204);
        $this->assertSame(0, TemplateRecording::query()->count());

        $this->assertMatchesContract($this->postJson('/api/templates/'.$id.'/recordings', [...$body, 'partKey' => 'descant']), 'templates.recordings.create', 422);
        $this->assertMatchesContract($this->postJson('/api/templates/'.Str::uuid().'/recordings', $body), 'templates.recordings.create', 404);
        $this->assertNotNull(ProcessTemplateRecording::class);
    }

    public function test_recorded_guides_are_plus_and_count_toward_storage(): void
    {
        $user = $this->as(User::factory()->create());
        $id = (string) Str::uuid();
        $this->save($id, $this->template($id))->assertOk();
        $wav = Wav::tone(1.0);
        $body = ['id' => (string) Str::uuid(), 'partKey' => 'alto', 'sampleRate' => 48000, 'frames' => 48000, 'offsetFrames' => 0, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)];

        $this->assertMatchesContract($this->postJson('/api/templates/'.$id.'/recordings', $body), 'templates.recordings.create', 403);

        $user->forceFill(['plan' => 'plus'])->save();
        $this->as($user);
        config(['usage.plans.plus.storage_bytes' => 10]);
        $full = $this->postJson('/api/templates/'.$id.'/recordings', $body);
        $this->assertMatchesContract($full, 'templates.recordings.create', 403);
        $full->assertJsonPath('code', 'storage_limit');
    }

    public function test_an_unverified_account_cant_save_a_template(): void
    {
        $this->as(User::factory()->withPassword()->unverified()->create());
        $id = (string) Str::uuid();

        $this->assertMatchesContract($this->save($id, $this->template($id)), 'templates.save', 403);
    }

    public function test_abandoned_and_failed_recordings_are_cleared_and_stop_counting_toward_storage(): void
    {
        $user = $this->as(User::factory()->create(['plan' => 'plus']));
        $id = (string) Str::uuid();
        $this->save($id, $this->template($id))->assertOk();
        $template = Template::query()->where('slug', $id)->firstOrFail();
        $stuck = TemplateRecording::factory()->create(['template_id' => $template->id, 'user_id' => $user->id, 'status' => TemplateRecording::UPLOADING, 'file_key' => null, 'stored_bytes' => null, 'created_at' => now()->subDays(2)]);
        Storage::disk('private')->put($stuck->upload_key, 'wav');
        $failed = TemplateRecording::factory()->create(['template_id' => $template->id, 'user_id' => $user->id, 'status' => TemplateRecording::FAILED, 'updated_at' => now()->subDays(31)]);
        $this->assertGreaterThan(0, app(Plans::class)->storageUsed($user));

        // A deleted template's versions go after 30 days too.
        $gone = (string) Str::uuid();
        Template::query()->whereKey($template->id)->update(['updated_at' => now()]);
        $this->artisan('app:bin:purge')->assertSuccessful();

        Storage::disk('private')->assertMissing($stuck->upload_key);
        $this->assertNull($stuck->fresh());
        $this->assertNull($failed->fresh());
        $this->assertSame(0, app(Plans::class)->storageUsed($user));

        $this->deleteJson('/api/templates/'.$id)->assertNoContent();
        $this->travel(31)->days();
        $this->artisan('app:bin:purge')->assertSuccessful();
        $this->assertNull(Template::query()->where('slug', $id)->first());
        $this->assertNotSame('', $gone);
    }

    public function test_changing_a_templates_settings_doesnt_change_which_stay_editable(): void
    {
        $user = $this->as(User::factory()->create(['plan' => 'plus']));
        [$first, $second] = [(string) Str::uuid(), (string) Str::uuid()];
        $this->save($first, $this->template($first))->assertOk();
        $this->travel(1)->minutes();
        $this->save($second, $this->template($second))->assertOk();
        $user->forceFill(['plan' => 'free'])->save();
        $this->as($user);

        $this->patchJson('/api/templates/'.$first, ['libraryOptIn' => true])->assertOk()->assertJsonPath('data.readOnly', true);
        $this->getJson('/api/templates/mine')->assertJsonPath('data.0.id', $second)->assertJsonPath('data.0.readOnly', false);
    }

    public function test_a_persons_template_may_have_instrument_parts_with_their_own_names_up_to_six(): void
    {
        $this->as(User::factory()->create(['plan' => 'plus']));
        $id = (string) Str::uuid();
        $data = $this->template($id);
        $data['parts'][3] = [...$data['parts'][3], 'key' => 'guitar-1', 'name' => 'Guitar', 'kind' => 'instrument'];
        $data['voicings'] = ['3' => ['soprano', 'alto', 'tenor']];
        $data['order'] = ['soprano', 'alto', 'tenor', 'guitar-1'];
        $this->save($id, $data)->assertOk();
        $this->assertSame('instrument', TemplateVersion::query()->latest('id')->firstOrFail()->data['parts'][3]['kind']);

        // A key that isn't one, an unknown kind, a long name, or a seventh part: refused, said why.
        $bad = $data;
        $bad['parts'][3]['key'] = 'Guitar 1';
        $this->save($id, [...$bad, 'version' => 2])->assertStatus(422)->assertJsonValidationErrors('template');
        $bad = $data;
        $bad['parts'][3]['kind'] = 'drum';
        $this->save($id, [...$bad, 'version' => 2])->assertStatus(422);
        $bad = $data;
        $bad['parts'][3]['name'] = str_repeat('g', 41);
        $this->save($id, [...$bad, 'version' => 2])->assertStatus(422);
        $many = $data;
        foreach (range(1, 3) as $n) {
            $many['parts'][] = [...$data['parts'][3], 'key' => 'guitar-'.($n + 1)];
        }
        $refused = $this->save($id, [...$many, 'version' => 2])->assertStatus(422);
        $this->assertContains('parts: at most 6', (array) $refused->json('errors.template'));
    }

    public function test_the_song_builders_record_is_kept_with_the_template_and_checked(): void
    {
        $this->as(User::factory()->create(['plan' => 'plus']));
        $id = (string) Str::uuid();
        $builder = ['progression' => ['id' => 'four-chords', 'bars' => [['I'], ['V'], ['vi'], ['IV']], 'mode' => 'major', 'swing' => 0.5], 'sound' => 'band', 'mix' => ['voices' => 0.8, 'instrument' => 0.6, 'bass' => 0.7, 'beat' => 0.5], 'sections' => [['name' => 'Verse', 'bars' => [['I'], ['IV']]]]];
        $this->save($id, $this->template($id, 1, ['builder' => $builder]))->assertOk();
        $this->assertSame('four-chords', TemplateVersion::query()->latest('id')->firstOrFail()->data['builder']['progression']['id']);

        $long = $builder;
        $long['progression']['bars'] = array_fill(0, 65, ['I']);
        $this->save($id, $this->template($id, 2, ['builder' => $long]))->assertStatus(422)->assertJsonValidationErrors('template');
        $this->save($id, $this->template($id, 2, ['builder' => [...$builder, 'mix' => ['voices' => 2]]]))->assertStatus(422);
        $this->save($id, $this->template($id, 2, ['builder' => [...$builder, 'sound' => 'kazoo']]))->assertStatus(422);
    }
}
