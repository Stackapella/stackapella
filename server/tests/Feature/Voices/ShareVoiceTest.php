<?php

declare(strict_types=1);

namespace Tests\Feature\Voices;

use App\Actions\Voices\ShareVoice;
use App\Enums\SubmissionStatus;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\Part;
use App\Models\Project;
use App\Models\Take;
use App\Models\Template;
use App\Models\User;
use App\Services\Audio\Ffmpeg;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * Be the voice (docs/open-guide-voices-plan.md, "The recording ladder"): a take the singer already kept, offered as a
 * part's guide voice, with the volunteers' agreement accepted only then. Behind lend_voice.
 */
final class ShareVoiceTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private Template $song;

    protected function setUp(): void
    {
        parent::setUp();
        $this->song = $this->catalogue();
    }

    /** A ready take of the alto, kept as FLAC, in the song's own key unless `$key` says otherwise. */
    private function take(User $user, ?string $key = null): Take
    {
        $data = (array) $this->song->currentVersion?->data;
        $project = Project::factory()->create(['user_id' => $user->id, 'template_slug' => $this->song->slug, 'template_version' => $this->song->currentVersion?->version, 'music_key' => $key ?? (string) $data['key']]);
        $part = Part::factory()->create(['project_id' => $project->id, 'template_part_key' => 'alto', 'nudge_frames' => 480]);
        $wav = (string) tempnam(sys_get_temp_dir(), 'tone');
        file_put_contents($wav, Wav::tone(1.0));
        $flac = $wav.'.flac';
        app(Ffmpeg::class)->encodeFlac($wav, $flac);
        $take = Take::factory()->create(['part_id' => $part->id, 'user_id' => $user->id, 'recorded_by' => $user->id, 'offset_frames' => -960, 'frames' => 48000]);
        Storage::disk('private')->put((string) $take->file_key, (string) file_get_contents($flac));
        @unlink($wav);
        @unlink($flac);

        return $take;
    }

    /** @return array<string, mixed> */
    private function body(Take $take, array $with = []): array
    {
        return ['takeId' => $take->uuid, 'templateId' => $this->song->slug, 'templateVersion' => $this->song->currentVersion?->version, 'partKey' => 'alto',
            'credit' => 'name', 'linksProfile' => false, 'agreementVersion' => (int) config('beta.volunteer_agreement_version'), 'adult' => true, ...$with];
    }

    /** @param  array<string, mixed>  $attributes */
    private function user(array $attributes = []): User
    {
        // As a request loads it: every column (a new factory model has only what it set).
        return User::factory()->create($attributes)->fresh() ?? throw new \LogicException('No user.');
    }

    private function on(): void
    {
        app(Flags::class)->set('lend_voice', 'on', null, 'test');
    }

    public function test_off_it_is_not_there(): void
    {
        $user = $this->user();
        $this->actingAs($user)->postJson('/api/voices/share', $this->body($this->take($user)))->assertNotFound();
        $this->assertSame(0, BetaSubmission::query()->count());
    }

    public function test_a_kept_take_goes_to_hillels_queue_as_its_master_with_the_agreement_recorded(): void
    {
        $this->on();
        config(['counts.enabled' => true]);
        $user = $this->user(['name' => 'Rosa Diaz', 'email_verified_at' => now()]);
        $take = $this->take($user);

        $shared = $this->actingAs($user)->postJson('/api/voices/share', $this->body($take));
        $this->assertMatchesContract($shared, 'voices.share', 200);
        $shared->assertJsonPath('data.status', 'sent')->assertJsonPath('data.partKey', 'alto');

        $submission = BetaSubmission::query()->firstOrFail();
        $this->assertSame([SubmissionStatus::Submitted, $take->id, 48000, 'alto'], [$submission->status, $submission->take_id, $submission->frames, $submission->part_key], 'processed into the queue');
        $this->assertSame((int) round((-960 + 480) * 1000 / 48000), $submission->offset_ms, "the take's place on the timeline");
        $this->assertTrue(Storage::disk('private')->exists($submission->master_key));
        $this->assertStringStartsWith('RIFF', (string) Storage::disk('private')->get($submission->master_key));
        $singer = BetaSinger::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame([true, (int) config('beta.volunteer_agreement_version')], [$singer->volunteer, $singer->consent_version]);
        $this->assertSame(1, (int) DB::table('usage_counts')->where('kind', 'take_sent')->sum('count'));

        // The same take never twice; a second singer may offer the same part.
        $again = $this->actingAs($user)->postJson('/api/voices/share', $this->body($take));
        $this->assertMatchesContract($again, 'voices.share', 409);
        $again->assertJsonPath('code', 'already_sent');
        $other = $this->user(['email_verified_at' => now()]);
        $this->actingAs($other)->postJson('/api/voices/share', $this->body($this->take($other)))->assertOk();
    }

    public function test_an_unconfirmed_email_holds_the_take_until_it_is_confirmed(): void
    {
        $this->on();
        $user = $this->user(['email_verified_at' => null]);
        $this->actingAs($user)->postJson('/api/voices/share', $this->body($this->take($user)))->assertOk()->assertJsonPath('data.status', 'held');
        $this->assertSame(0, ShareVoice::releaseHeld(), 'still unconfirmed');

        $user->forceFill(['email_verified_at' => now()])->save();
        $this->artisan('app:voices:release-held')->expectsOutput('1 held takes sent on.')->assertSuccessful();
        $this->assertSame(SubmissionStatus::Submitted, BetaSubmission::query()->firstOrFail()->status);
    }

    public function test_it_refuses_what_cant_be_a_guide(): void
    {
        $this->on();
        $user = $this->user(['email_verified_at' => now()]);

        // Sung transposed, or someone else's, or another part: not found.
        $this->assertMatchesContract($this->actingAs($user)->postJson('/api/voices/share', $this->body($this->take($user, key: 'A'))), 'voices.share', 404);
        $this->actingAs($user)->postJson('/api/voices/share', $this->body($this->take($this->user())))->assertNotFound();
        $this->actingAs($user)->postJson('/api/voices/share', $this->body($this->take($user), ['partKey' => 'bass']))->assertNotFound();

        $take = $this->take($user);
        $this->assertMatchesContract($this->actingAs($user)->postJson('/api/voices/share', $this->body($take, ['agreementVersion' => 1])), 'voices.share', 409);
        $young = $this->user(['email_verified_at' => now(), 'adult_confirmed_at' => null]);
        $this->assertMatchesContract($this->actingAs($young)->postJson('/api/voices/share', $this->body($this->take($young), ['adult' => false])), 'voices.share', 403);

        BetaSinger::factory()->create(['user_id' => $user->id, 'volunteer' => true, 'volunteer_blocked_at' => now()]);
        $blocked = $this->actingAs($user)->postJson('/api/voices/share', $this->body($take));
        $this->assertMatchesContract($blocked, 'voices.share', 403);
        $blocked->assertJsonPath('code', 'volunteer_blocked');
        $this->assertSame(0, BetaSubmission::query()->count());
    }

    public function test_ten_shared_takes_a_day(): void
    {
        $this->on();
        $user = $this->user(['email_verified_at' => now()]);
        for ($i = 0; $i < ShareVoice::PER_DAY; $i++) {
            $this->actingAs($user)->postJson('/api/voices/share', $this->body($this->take($user)))->assertOk();
        }
        $this->assertMatchesContract($this->actingAs($user)->postJson('/api/voices/share', $this->body($this->take($user))), 'voices.share', 429);
    }
}
