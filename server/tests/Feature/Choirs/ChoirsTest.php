<?php

declare(strict_types=1);

namespace Tests\Feature\Choirs;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Actions\Users\DeleteAccount;
use App\Models\Choir;
use App\Models\ChoirVoice;
use App\Models\Notice;
use App\Models\Project;
use App\Models\Score;
use App\Models\Take;
use App\Models\User;
use App\Support\Audio\Hall;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Support\Sharing;
use Tests\TestCase;

/**
 * The virtual choir (N6; contract/api.md, "The virtual choir (N6)"): voices join from a take at
 * the song's own key and tempo, the server mixes them, and they leave or are taken out.
 */
final class ChoirsTest extends TestCase
{
    use RefreshDatabase;
    use Sharing;

    private function open(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        app(Flags::class)->set('virtual_choir', 'on', null, 'Opening the choirs');
    }

    /**
     * Someone 18 or over, verified, signed in, with a ready alto take of Amazing Grace.
     *
     * @return array{0: User, 1: Take}
     */
    private function singerWithTake(string $handle): array
    {
        $user = User::factory()->create(['handle' => $handle, 'name' => ucfirst($handle), 'adult_confirmed_at' => now(), 'public_profile_at' => now(), 'trust_level_override' => 2])->fresh() ?? throw new \LogicException;
        $this->actingAs($user);
        [$project] = $this->recordedProject();
        $take = Take::query()->whereHas('project', fn ($q) => $q->where('uuid', $project))->sole();

        return [$user, $take];
    }

    /**
     * @param  array<string, mixed>  $with
     */
    private function join(Take $take, array $with = []): TestResponse
    {
        return $this->postJson('/api/choirs/amazing-grace/voices', ['takeId' => $take->uuid, 'partKey' => 'alto', 'score' => 90, 'credit' => 'handle', 'rightsConfirmed' => true, ...$with]);
    }

    public function test_a_choir_has_a_public_page_naming_those_who_chose_a_credit(): void
    {
        $this->open();
        [, $take] = $this->singerWithTake('ana');
        $this->join($take)->assertCreated();
        [, $other] = $this->singerWithTake('zed');
        $this->join($other, ['credit' => 'anonymous'])->assertCreated();
        ChoirVoice::query()->update(['status' => ChoirVoice::READY, 'in_mix_at' => now()]);
        $this->app['auth']->forgetGuards();

        $page = $this->get('/choirs/amazing-grace')->assertOk();
        $page->assertSee('Amazing Grace, sung together')->assertSee('data-part="alto"', false)->assertSee('Ana')->assertDontSee('Zed')->assertSee('choir=amazing-grace', false);
        $this->get('/sitemap.xml')->assertSee('/choirs/amazing-grace');

        // A song nobody has joined still has its page; the flag closed, none does.
        $this->get('/choirs/nope')->assertNotFound();
        app(Flags::class)->set('virtual_choir', 'off', null, 'Closing');
        $this->get('/choirs/amazing-grace')->assertNotFound();
    }

    public function test_the_choir_is_closed_until_its_flag_opens(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        [, $take] = $this->singerWithTake('ana');

        $this->assertMatchesContract($this->getJson('/api/choirs/amazing-grace'), 'choirs.show', 404);
        $closed = $this->join($take);
        $this->assertMatchesContract($closed, 'choirs.join', 409);
        $closed->assertJsonPath('code', 'choir_closed');
    }

    public function test_voices_join_are_mixed_and_leave(): void
    {
        $this->open();
        [$ana, $take] = $this->singerWithTake('ana');

        $shown = $this->getJson('/api/choirs/amazing-grace');
        $this->assertMatchesContract($shown, 'choirs.show', 200);
        $shown->assertJsonPath('data.voices', 0)->assertJsonPath('data.mine', null)->assertJsonPath('data.minScore', 60)->assertJsonPath('data.parts.0.key', 'soprano');
        $this->assertMatchesContract($this->getJson('/api/choirs/no-such-song'), 'choirs.show', 404);

        // Not good enough yet, or not this take's score, or the wrong part.
        $this->assertMatchesContract($this->join($take, ['score' => 40]), 'choirs.join', 422);
        Score::factory()->create(['user_id' => $ana->id, 'take_uuid' => $take->uuid, 'summary' => ['pitchPercent' => 55, 'clipping' => false, 'bleed' => false]]);
        $this->join($take, ['score' => 82])->assertStatus(422)->assertJsonValidationErrors('score');
        $this->join($take, ['score' => 55])->assertStatus(422)->assertJsonValidationErrors('score');
        // A clipped take is refused; its own report back as it was, it can join.
        Score::query()->latest('id')->firstOrFail()->forceFill(['summary' => ['pitchPercent' => 90, 'clipping' => true, 'bleed' => false]])->save();
        $this->join($take)->assertStatus(422)->assertJsonValidationErrors('takeId');
        Score::query()->latest('id')->firstOrFail()->delete();
        $this->join($take, ['partKey' => 'tenor'])->assertStatus(422)->assertJsonValidationErrors('takeId');
        $this->assertMatchesContract($this->join($take, ['takeId' => (string) Str::uuid()]), 'choirs.join', 404);

        // Another key: refused.
        Project::query()->whereKey($take->project_id)->update(['music_key' => 'A']);
        $this->assertMatchesContract($this->join($take), 'choirs.join', 409);
        Project::query()->whereKey($take->project_id)->update(['music_key' => 'G']);

        $joined = $this->join($take);
        $this->assertMatchesContract($joined, 'choirs.join', 201);
        $voice = ChoirVoice::query()->sole();
        $this->assertSame(ChoirVoice::READY, $voice->status);
        Storage::disk('private')->assertExists((string) $voice->file_key);

        // Someone under 18 can't.
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \LogicException);
        $this->assertMatchesContract($this->join($take), 'choirs.join', 403);

        // A second voice, anonymously, then the hourly mix.
        [, $other] = $this->singerWithTake('sam');
        $this->join($other, ['credit' => 'anonymous'])->assertCreated();
        $this->artisan('app:choirs:mix')->expectsOutputToContain('Mixing 1')->assertSuccessful();
        $choir = Choir::query()->sole();
        $this->assertNotNull($choir->mix_key);
        Storage::disk('media')->assertExists((string) $choir->mix_key);
        $this->assertSame(2, $choir->voices_count);
        $this->artisan('app:choirs:mix')->expectsOutputToContain('Mixing 0');

        $list = $this->getJson('/api/choirs/amazing-grace/voices');
        $this->assertMatchesContract($list, 'choirs.voices.list', 200);
        $list->assertJsonCount(2, 'data')->assertJsonPath('data.0.singer.handle', null)->assertJsonPath('data.1.singer.handle', 'ana');
        $this->assertMatchesContract($this->getJson('/api/choirs/nope/voices'), 'choirs.voices.list', 404);
        $this->actingAs($ana);
        $mine = $this->getJson('/api/choirs/amazing-grace')->assertJsonPath('data.voices', 2)->assertJsonPath('data.mine.inMix', true);
        $this->assertNotNull($mine->json('data.mine.url'));

        // Ana leaves: the next mix drops her.
        $file = (string) $voice->file_key;
        $this->assertMatchesContract($this->deleteJson('/api/choirs/amazing-grace/voices/mine'), 'choirs.leave', 204);
        Storage::disk('private')->assertMissing($file);
        $this->travel(1)->hours();
        $this->artisan('app:choirs:mix')->expectsOutputToContain('Mixing 1');
        $this->assertSame(1, Choir::query()->sole()->voices_count);
    }

    public function test_a_voice_placed_hours_away_is_left_out_of_the_mix(): void
    {
        $this->open();
        [, $take] = $this->singerWithTake('ana');
        $this->join($take)->assertCreated();
        [, $other] = $this->singerWithTake('sam');
        $this->join($other)->assertCreated();

        // A take sent with an offset of 1e8 frames at 8 kHz: some 3.5 hours after the song starts.
        ChoirVoice::query()->latest('id')->firstOrFail()->forceFill(['offset_frames' => 100_000_000, 'sample_rate' => 8000])->save();
        $this->assertFalse(ChoirVoice::inSong(100_000_000 / 8000, 10));
        $this->assertFalse(ChoirVoice::inSong(-30, 10));
        $this->assertTrue(ChoirVoice::inSong(-5, 10));
        $this->assertTrue(ChoirVoice::inSong(120, 10));

        $this->artisan('app:choirs:mix')->expectsOutputToContain('Mixing 1')->assertSuccessful();
        $this->assertSame(1, Choir::query()->sole()->voices_count);
    }

    public function test_hillel_takes_a_voice_out_and_an_account_takes_its_voices(): void
    {
        $this->open();
        [$ana, $take] = $this->singerWithTake('ana');
        $this->join($take)->assertCreated();
        $voice = ChoirVoice::query()->sole();

        $admin = $this->actingAsAdmin();
        $this->assertMatchesContract($this->postJson('/api/admin/choir-voices/'.$voice->uuid.'/remove', []), 'admin.choirs.voices.remove', 422);
        $this->assertMatchesContract($this->postJson('/api/admin/choir-voices/'.Str::uuid().'/remove', ['note' => 'x']), 'admin.choirs.voices.remove', 404);
        $this->assertMatchesContract($this->postJson('/api/admin/choir-voices/'.$voice->uuid.'/remove', ['note' => 'Not singing.']), 'admin.choirs.voices.remove', 204);
        $this->assertSame(Notice::CHOIR_VOICE_REMOVED, Notice::query()->where('user_id', $ana->id)->value('kind'));
        $this->assertNotNull($admin);

        // Out: not listed, and joining again doesn't bring it back.
        $this->actingAs($ana);
        $this->getJson('/api/choirs/amazing-grace')->assertJsonPath('data.voices', 0)->assertJsonPath('data.mine', null);
        $this->join($take)->assertNotFound();

        // Her account goes: so does her voice and its file.
        app(DeleteAccount::class)->handle($ana->fresh() ?? $ana, null);
        $this->assertSame(0, ChoirVoice::query()->count());
        Storage::disk('private')->assertMissing((string) $voice->file_key);
    }

    public function test_the_hall_is_the_apps_own_room(): void
    {
        $left = Hall::channel(11, 8000);
        $this->assertCount((int) round((Hall::PREDELAY + Hall::DECAY * 1.2) * 8000), $left);
        $this->assertSame(0.0, $left[0]);
        // Unit energy, and the same every time.
        $this->assertEqualsWithDelta(1.0, array_sum(array_map(static fn (float $v): float => $v * $v, $left)), 1e-6);
        $this->assertSame($left, Hall::channel(11, 8000));
        $this->assertNotSame($left, Hall::channel(23, 8000));
        $this->assertSame(0.0, Hall::spread(3, 4));
        $this->assertSame(-0.18, Hall::spread(0, 4));
    }

    public function test_reports_hold_a_voice_for_review_and_a_held_voice_can_always_leave(): void
    {
        $this->open();
        [$ana, $take] = $this->singerWithTake('ana');
        $this->join($take)->assertCreated();
        $voice = ChoirVoice::query()->sole();
        $this->assertSame([0, 0], [$voice->trim_start_frames, $voice->trim_end_frames]);

        $this->app['auth']->forgetGuards();
        foreach (['203.0.113.1', '203.0.113.2'] as $ip) {
            $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/api/reports', ['choirVoiceId' => $voice->uuid, 'reason' => 'offensive'])->assertNoContent();
        }
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \LogicException);
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.3'])->postJson('/api/reports', ['choirVoiceId' => $voice->uuid, 'reason' => 'offensive'])->assertNoContent();
        $this->assertNotNull($voice->refresh()->held_at);
        $this->assertNull($voice->hidden_at);
        $this->getJson('/api/choirs/amazing-grace/voices')->assertJsonCount(0, 'data');

        // Hillel hears it in the queue, and puts it back.
        $this->actingAsAdmin();
        $held = $this->getJson('/api/admin/choir-voices/held');
        $this->assertMatchesContract($held, 'admin.choirs.voices.held', 200);
        $held->assertJsonPath('data.0.id', $voice->uuid)->assertJsonPath('data.0.reports.count', 3);
        $approved = $this->postJson('/api/admin/choir-voices/'.$voice->uuid.'/approve');
        $this->assertMatchesContract($approved, 'admin.choirs.voices.approve', 200);
        $approved->assertJsonCount(0, 'data');
        $this->assertMatchesContract($this->postJson('/api/admin/choir-voices/'.$voice->uuid.'/approve'), 'admin.choirs.voices.approve', 404);

        // Taken out for good: the singer is told, and can still take their own recording back.
        $this->postJson('/api/admin/choir-voices/'.$voice->uuid.'/remove', ['note' => 'Not singing.'])->assertNoContent();
        $this->actingAs($ana);
        $this->deleteJson('/api/choirs/amazing-grace/voices/mine')->assertNoContent();
        $this->assertSame(0, ChoirVoice::query()->count());
        Storage::disk('private')->assertMissing((string) $voice->file_key);
    }

    public function test_a_silent_take_never_joins_and_reading_a_choir_makes_nothing(): void
    {
        $this->open();
        [$ana, $take] = $this->singerWithTake('ana');
        Score::query()->where('take_uuid', $take->uuid)->update(['summary' => json_encode(['pitchPercent' => null, 'clipping' => false, 'bleed' => false])]);

        $this->join($take, ['score' => 100])->assertStatus(422)->assertJsonValidationErrors('score');
        $this->getJson('/api/choirs/amazing-grace')->assertOk()->assertJsonPath('data.voices', 0);
        $this->getJson('/api/choirs/amazing-grace/voices')->assertOk();
        $this->assertSame(0, Choir::query()->count());
        $this->assertNotNull($ana);
    }
}
