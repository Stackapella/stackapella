<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Actions\OwnChoir\ChoirJobs;
use App\Enums\OwnChoirStatus;
use App\Jobs\CheckChoirClip;
use App\Models\OwnChoirConsent;
use App\Models\OwnChoirFreeSong;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Services\Audio\Ffmpeg;
use App\Support\Flags;
use App\Support\OwnChoir\OwnChoir;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\BetaFixtures;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * The deep reviews' fixes to Hear yourself as a choir: limits that no forced failure gets round, jobs re-checked under
 * their locks, failures that never undo a made or deleted song, deleting your data whatever the switch, a consent race,
 * the bin keeping quota rows and no orphaned files, and a cap on what one song costs.
 */
final class ReviewFixesTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private const TOKEN = 'test-worker-token-0123456789abcdef0123';

    private function singer(string $plan = 'free'): User
    {
        $this->catalogue();
        $user = User::factory()->create(['plan' => $plan]);
        app(Flags::class)->set('own_choir', 'on', null, 'Testing');
        config(['services.ai_worker.choir_token' => self::TOKEN]);
        OwnChoirConsent::factory()->create(['user_id' => $user->id]);
        $this->actingAs($user);

        return $user;
    }

    private function version(): int
    {
        return (int) Template::query()->where('slug', 'amazing-grace')->value('current_version_id');
    }

    /**
     * @param  array<string, mixed>  $with
     */
    private function song(User $user, array $with): OwnChoirRequest
    {
        return OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => $this->version(), ...$with]);
    }

    public function test_a_failure_the_singer_can_cause_still_counts_and_only_ours_gives_the_song_back(): void
    {
        $user = $this->singer();
        config(['own_choir.free_songs' => 2]);

        // Never reached the renderer: none of these count.
        $this->song($user, ['status' => OwnChoirStatus::Uploading]);
        $this->song($user, ['status' => OwnChoirStatus::Failed, 'failure' => 'bad_clip', 'tries' => 0]);
        $this->song($user, ['status' => OwnChoirStatus::Deleted, 'tries' => 0]);
        // Ours: given back.
        $this->song($user, ['status' => OwnChoirStatus::Failed, 'failure' => 'render_failed', 'tries' => 3]);
        $this->assertSame(2, OwnChoir::quota($user)['freeLeft']);

        // Forced by the singer after the renderer ran and spent GPU time: counted.
        $this->song($user, ['status' => OwnChoirStatus::Failed, 'failure' => 'reference_gone', 'tries' => 1, 'gpu_seconds' => 40]);
        $this->song($user, ['status' => OwnChoirStatus::Failed, 'failure' => 'clip_unusable', 'tries' => 1, 'gpu_seconds' => 12]);
        $this->assertSame(0, OwnChoir::quota($user)['freeLeft']);
        $this->assertSame('free', OwnChoir::limitReached($user));
    }

    public function test_the_day_and_month_limits_hold_with_plus(): void
    {
        $user = $this->singer('plus');
        config(['own_choir.daily' => 2, 'own_choir.plus_month' => 3]);

        $this->song($user, ['status' => OwnChoirStatus::Ready, 'tries' => 1]);
        $this->song($user, ['status' => OwnChoirStatus::Deleted, 'tries' => 1]);
        $this->assertSame('day', OwnChoir::limitReached($user), 'Deleting a made song never gives it back.');

        OwnChoirRequest::query()->update(['created_at' => now()->subDays(2)]);
        $this->assertNull(OwnChoir::limitReached($user));
        $this->song($user, ['status' => OwnChoirStatus::Ready, 'tries' => 1]);
        $this->assertSame('month', OwnChoir::limitReached($user));
        $this->assertTrue(OwnChoir::quota($user)['plus']);
    }

    public function test_a_late_worker_never_resurrects_a_deleted_song_or_leaves_a_file(): void
    {
        $user = $this->singer();
        $song = $this->song($user, ['status' => OwnChoirStatus::Rendering, 'claimed_at' => now(), 'claim_token' => str_repeat('t', 32), 'tries' => 1, 'clip_key' => 'choir/clips/late.wav']);
        $part = OwnChoirPart::factory()->create(['own_choir_request_id' => $song->id, 'status' => 'uploading', 'media_key' => null]);
        $this->deleteJson("/api/own-choir/requests/{$song->sqid()}")->assertNoContent();
        $this->assertNull(OwnChoirPart::query()->find($part->id), 'Deleting the song took its part.');
        $headers = ['Authorization' => 'Bearer '.self::TOKEN];

        $this->json('POST', "/api/worker/choir/jobs/{$song->sqid()}/finish", ['failed' => null, 'claim' => str_repeat('t', 32)], $headers)->assertStatus(409)->assertJsonPath('code', 'job_not_claimed');
        $this->assertSame(OwnChoirStatus::Deleted, $song->refresh()->status);

        $wav = Wav::tone(0.5);
        $master = ['claim' => str_repeat('t', 32), 'part' => 'alto', 'sampleRate' => 48000, 'frames' => intdiv(strlen($wav) - 44, 2), 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav),
            'checks' => ['pitchMaxCents' => 1.0, 'pitchMedianCents' => 1.0, 'notesMeasured' => 1, 'notesTotal' => 1, 'notesOver15' => 0, 'notesOver30' => 0, 'dropouts' => 0, 'heldSeconds' => 0.1, 'durationSeconds' => 0.5],
            'renderer' => ['model' => 'soulx-singer', 'codeRevision' => str_repeat('a', 40), 'weightsRevision' => str_repeat('b', 40), 'steps' => 32, 'control' => 'melody', 'pitchShift' => 0, 'reference' => 'own-clip']];
        $this->json('POST', "/api/worker/choir/jobs/{$song->sqid()}/parts", $master, $headers)->assertStatus(409);
        $this->assertSame(0, OwnChoirPart::query()->count());

        // A part still uploading when its song went: its complete keeps nothing.
        $other = $this->song($user, ['status' => OwnChoirStatus::Rendering, 'claimed_at' => now()->subHour(), 'claim_token' => str_repeat('u', 32), 'tries' => 1]);
        $lapsed = OwnChoirPart::factory()->create(['own_choir_request_id' => $other->id, 'status' => 'uploading', 'media_key' => null, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)]);
        Storage::disk('private')->put($lapsed->upload_key, $wav);
        $this->json('POST', "/api/worker/choir/parts/{$lapsed->sqid()}/complete", [], $headers)->assertStatus(409)->assertJsonPath('code', 'job_not_claimed');
        $this->assertSame([], Storage::disk('private')->allFiles('choir/masters'));
        $this->assertSame('uploading', $lapsed->refresh()->status, 'A lapsed claim\'s part is never taken in.');
        $this->assertNull($lapsed->master_key);
    }

    public function test_a_failure_never_undoes_a_made_or_deleted_song(): void
    {
        $user = $this->singer();
        $made = $this->song($user, ['status' => OwnChoirStatus::Ready, 'tries' => 1]);
        $deleted = $this->song($user, ['status' => OwnChoirStatus::Deleted, 'tries' => 0, 'clip_key' => 'choir/clips/x.wav']);

        ChoirJobs::failAndClean($made, 'reference_gone');
        $this->assertSame(OwnChoirStatus::Ready, $made->refresh()->status);

        (new CheckChoirClip($deleted->id))->failed();
        $this->assertSame(OwnChoirStatus::Deleted, $deleted->refresh()->status);
    }

    public function test_with_the_switch_off_a_singer_still_sees_and_deletes_what_is_theirs(): void
    {
        $user = $this->singer();
        $song = $this->song($user, ['status' => OwnChoirStatus::Ready, 'tries' => 1]);
        $voice = OwnChoirVoice::factory()->create(['user_id' => $user->id]);
        Storage::disk('private')->put((string) $voice->reference_key, 'wav');
        app(Flags::class)->set('own_choir', 'off', null, 'Testing');

        $this->getJson('/api/own-choir')->assertOk()->assertJsonPath('data.requests.0.id', $song->sqid());
        $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace'])->assertNotFound();
        $this->deleteJson('/api/own-choir/voice')->assertNoContent();
        Storage::disk('private')->assertMissing((string) $voice->reference_key);
        $this->deleteJson("/api/own-choir/requests/{$song->sqid()}")->assertNoContent();
        $this->deleteJson('/api/own-choir')->assertNoContent();
        // Nothing kept and the switch off: nothing, never a 404 (Settings asks for everyone signed in).
        $this->assertMatchesContract($this->getJson('/api/own-choir'), 'ownChoir.show', 200);
        $this->getJson('/api/own-choir')->assertExactJson(['data' => null]);
    }

    public function test_consenting_twice_keeps_one_row(): void
    {
        $user = $this->singer();
        OwnChoirConsent::query()->delete();

        $this->postJson('/api/own-choir/consent', ['version' => OwnChoir::termsVersion(), 'adult' => true])->assertOk();
        $this->postJson('/api/own-choir/consent', ['version' => OwnChoir::termsVersion(), 'adult' => true])->assertOk();
        $this->assertSame(1, OwnChoirConsent::query()->where('user_id', $user->id)->count());
    }

    public function test_the_bin_takes_an_own_songs_choir_files_and_keeps_its_place_counted(): void
    {
        $user = $this->singer();
        $mine = Template::factory()->create(['owner_id' => $user->id, 'slug' => (string) Str::uuid(), 'deleted_at' => now()->subDays(60)]);
        $version = TemplateVersion::factory()->create(['template_id' => $mine->id]);
        $song = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'template_version_id' => $version->id, 'status' => OwnChoirStatus::Ready, 'tries' => 1]);
        $part = OwnChoirPart::factory()->create(['own_choir_request_id' => $song->id]);
        Storage::disk('private')->put((string) $part->media_key, 'aac');

        $this->artisan('app:bin:purge')->assertSuccessful();

        $this->assertNull(Template::query()->find($mine->id));
        Storage::disk('private')->assertMissing((string) $part->media_key);
        $this->assertSame(OwnChoirStatus::Deleted, $song->refresh()->status);
        $this->assertNull($song->template_version_id);
        $this->assertSame(0, OwnChoir::quota($user)['freeLeft'], 'Its place still counts.');
    }

    public function test_an_own_song_longer_than_one_song_may_cost_is_refused(): void
    {
        $user = $this->singer();
        app(Flags::class)->set('own_choir_songs', 'on', null, 'Testing');
        config(['own_choir.max_part_seconds' => 10]);
        $data = (array) json_decode((string) DB::table('template_versions')->where('id', $this->version())->value('data'), true);
        $mine = Template::factory()->create(['owner_id' => $user->id, 'slug' => (string) Str::uuid()]);
        $version = TemplateVersion::factory()->create(['template_id' => $mine->id, 'data' => $data]);
        $mine->forceFill(['current_version_id' => $version->id])->save();
        OwnChoirVoice::factory()->create(['user_id' => $user->id]);

        $this->postJson('/api/own-choir/requests', ['templateId' => $mine->slug])->assertStatus(409)->assertJsonPath('code', 'song_too_long');
        $this->assertSame(0, OwnChoirRequest::query()->where('user_id', $user->id)->count(), 'Nothing is queued for the renderer.');
    }

    public function test_the_daily_cap_counts_every_song_that_reached_the_renderer_even_ours(): void
    {
        $user = $this->singer();
        config(['own_choir.daily' => 2, 'own_choir.free_songs' => 5]);

        // Ours (the renderer's failure, its gates, a song at the limit expiring after its tries): given back to the free
        // allowance, never to the day.
        $this->song($user, ['status' => OwnChoirStatus::Failed, 'failure' => 'render_failed', 'tries' => 3, 'gpu_seconds' => 300]);
        $this->song($user, ['status' => OwnChoirStatus::Failed, 'failure' => 'gates_failed', 'tries' => 1, 'gpu_seconds' => 90]);
        // Never reached it: neither.
        $this->song($user, ['status' => OwnChoirStatus::Failed, 'failure' => 'bad_clip', 'tries' => 0]);

        $quota = OwnChoir::quota($user);
        $this->assertSame(0, $quota['todayLeft']);
        $this->assertSame(5, $quota['freeLeft']);
        $this->assertSame('day', OwnChoir::limitReached($user));
        $this->postJson('/api/own-choir/requests', ['templateId' => 'amazing-grace'])->assertForbidden()->assertJsonPath('which', 'day');

        OwnChoirRequest::query()->update(['created_at' => now()->subDays(2)]);
        $this->assertNull(OwnChoir::limitReached($user));
    }

    public function test_a_workers_failure_needs_its_claim_still_held(): void
    {
        $user = $this->singer();
        // Claimed again by another worker after this one's claim lapsed.
        $song = $this->song($user, ['status' => OwnChoirStatus::Rendering, 'claimed_at' => now(), 'claim_token' => str_repeat('n', 32), 'tries' => 2, 'clip_key' => 'choir/clips/again.wav']);
        $part = OwnChoirPart::factory()->create(['own_choir_request_id' => $song->id]);
        Storage::disk('private')->put('choir/clips/again.wav', 'wav');

        $this->json('POST', "/api/worker/choir/jobs/{$song->sqid()}/finish", ['failed' => 'render_failed', 'claim' => str_repeat('o', 32)], ['Authorization' => 'Bearer '.self::TOKEN])
            ->assertStatus(409)->assertJsonPath('code', 'job_not_claimed');

        $song->refresh();
        $this->assertSame(OwnChoirStatus::Rendering, $song->status);
        $this->assertSame(str_repeat('n', 32), $song->claim_token, 'The new claim is untouched.');
        $this->assertNotNull(OwnChoirPart::query()->find($part->id));
        Storage::disk('private')->assertExists('choir/clips/again.wav');

        // The worker holding the claim fails it.
        $this->json('POST', "/api/worker/choir/jobs/{$song->sqid()}/finish", ['failed' => 'render_failed', 'claim' => str_repeat('n', 32)], ['Authorization' => 'Bearer '.self::TOKEN])->assertOk()->assertJsonPath('data.status', 'failed');
        Storage::disk('private')->assertMissing('choir/clips/again.wav');
    }

    /**
     * The lock fixes, checked by the queries they send (a second connection can't see this test's rows, which live in
     * RefreshDatabase's open transaction, so a true two-connection race can't run here): a worker's failure checks its
     * claim under the same lock, in one transaction; a reference locks the song before the voice, as purging does.
     */
    public function test_a_failure_takes_one_lock_and_references_lock_the_song_first(): void
    {
        $user = $this->singer();
        $song = $this->song($user, ['status' => OwnChoirStatus::Rendering, 'claimed_at' => now(), 'claim_token' => str_repeat('t', 32), 'tries' => 1]);
        $locks = [];
        DB::listen(static function ($query) use (&$locks): void {
            if (str_contains(strtolower($query->sql), 'for update')) {
                $locks[] = [preg_match('/from `([a-z_]+)`/', $query->sql, $m) === 1 ? $m[1] : '?', DB::transactionLevel()];
            }
        });

        app(ChoirJobs::class)->finish($song, str_repeat('t', 32), 'render_failed');
        $base = DB::transactionLevel();
        $this->assertSame(['own_choir_requests', $base + 1], $locks[0], 'Locked inside a transaction of its own.');
        $this->assertSame(1, count(array_filter($locks, static fn (array $l): bool => $l[0] === 'own_choir_requests')), 'One lock on the song: the claim and the failure in one transaction.');

        $voice = OwnChoirVoice::factory()->create(['user_id' => $user->id, 'status' => 'uploading']);
        $wav = Wav::tone(0.5);
        $voice->forceFill(['bytes' => strlen($wav), 'sha256' => hash('sha256', $wav)])->save();
        Storage::disk('private')->put($voice->upload_key, $wav);
        $this->song($user, ['status' => OwnChoirStatus::Rendering, 'claimed_at' => now(), 'claim_token' => str_repeat('r', 32), 'tries' => 1, 'own_choir_voice_id' => $voice->id]);
        $locks = [];

        app(ChoirJobs::class)->completeReference($voice, app(Ffmpeg::class));
        $tables = array_column($locks, 0);
        $this->assertSame(['own_choir_requests', 'own_choir_voices'], array_slice($tables, 0, 2), 'The song is locked before the voice.');
    }

    public function test_the_same_token_for_both_worker_apis_opens_neither(): void
    {
        $this->singer();
        config(['services.ai_worker.token' => self::TOKEN]);
        $headers = ['Authorization' => 'Bearer '.self::TOKEN];

        $this->json('POST', '/api/worker/choir/claim', [], $headers)->assertNotFound();
        $this->json('GET', '/api/worker/ai/needs?template=amazing-grace', [], $headers)->assertNotFound();

        config(['services.ai_worker.token' => str_repeat('k', 40)]);
        $this->json('POST', '/api/worker/choir/claim', [], $headers)->assertNoContent();
    }

    public function test_the_nullable_migration_is_a_no_op_once_done(): void
    {
        $ddl = [];
        DB::listen(static function ($query) use (&$ddl): void {
            if (preg_match('/^\s*(alter|create|drop)\b/i', $query->sql) === 1) {
                $ddl[] = $query->sql;
            }
        });

        (require base_path('database/migrations/2026_11_20_180000_keep_own_choir_requests_past_their_song.php'))->up();

        $this->assertSame([], $ddl, 'Run again, it changes nothing.');
    }

    public function test_a_failure_before_any_gpu_work_never_counts_against_anything(): void
    {
        // Hillel's first song, 2026-11-23: his clip was unusable, nothing was rendered.
        $user = $this->singer();
        config(['own_choir.free_songs' => 1, 'own_choir.daily' => 1]);
        $this->song($user, ['status' => OwnChoirStatus::Failed, 'failure' => 'clip_unusable', 'tries' => 1, 'gpu_seconds' => 0]);

        $quota = OwnChoir::quota($user);
        $this->assertSame([1, 1], [$quota['freeLeft'], $quota['todayLeft']]);
        $this->assertNull(OwnChoir::limitReached($user));

        // Paid Plus: no minutes spent either.
        $user->forceFill(['store_plus_until' => now()->addDays(10)])->save();
        $this->assertSame(60.0, OwnChoir::quota($user->fresh() ?? $user)['minutesLeft']);

        // Its free-song record, if it took one, is given back.
        $song = OwnChoirRequest::query()->sole();
        OwnChoirFreeSong::query()->create(['key_hash' => str_repeat('a', 64)])->forceFill(['own_choir_request_id' => $song->id])->save();
        OwnChoir::releaseFree($song);
        $this->assertSame(0, OwnChoirFreeSong::query()->count());
    }
}
