<?php

declare(strict_types=1);

namespace Tests\Feature\Beta;

use App\Enums\SubmissionStatus;
use App\Jobs\ProcessSubmission;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * A take sent from My parts: its details, the presigned PUT, the check, and processing with ffmpeg.
 */
final class SubmissionsTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function take(string $assignmentId, string $wav, int $version = 2): array
    {
        $frames = intdiv(strlen($wav) - 44, 2);

        return ['assignmentId' => $assignmentId, 'templateVersion' => $version, 'key' => 'G', 'sampleRate' => 48000, 'frames' => $frames, 'latencyFrames' => 960, 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav), 'device' => 'Mac, Chrome', 'mic' => 'Wired', 'scores' => ['pitchPercent' => 92], 'note' => 'Phrase 3 felt flat.'];
    }

    public function test_my_parts_lists_the_assigned_parts_with_status_and_real_voices(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'bass']);

        $response = $this->asSinger($singer)->getJson('/api/beta/parts');

        $this->assertMatchesContract($response, 'beta.parts.list', 200);
        $response->assertJsonPath('data.0.id', $assignment->sqid())
            ->assertJsonPath('data.0.status', 'to_record')
            ->assertJsonPath('data.0.recordable', true)
            ->assertJsonPath('data.0.guides.0.partKey', 'bass')
            ->assertJsonPath('data.0.template.version', $template->currentVersion?->version);
    }

    public function test_a_retake_card_dates_hillels_note_and_lets_the_singer_hear_their_take(): void
    {
        $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        $take = BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'status' => SubmissionStatus::Retake, 'admin_note' => 'A little early on "sweet".', 'reviewed_at' => now()->subDays(2), 'master_key' => 'beta/masters/take.wav']);

        $card = $this->asSinger($singer)->getJson('/api/beta/parts');
        $this->assertMatchesContract($card, 'beta.parts.list', 200);
        $card->assertJsonPath('data.0.latestSubmission.adminNoteAt', $take->reviewed_at?->utc()->toIso8601ZuluString());
        $this->assertStringContainsString('beta/masters/take.wav', (string) $card->json('data.0.latestSubmission.url'));

        // Still being processed: nothing to hear yet, and no note.
        $take->forceFill(['status' => SubmissionStatus::Processing, 'admin_note' => null])->save();
        $this->asSinger($singer)->getJson('/api/beta/parts')->assertJsonPath('data.0.latestSubmission.url', null)->assertJsonPath('data.0.latestSubmission.adminNoteAt', null);
    }

    public function test_my_parts_tells_the_singers_own_voice_from_anothers(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        $mine = BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'template_version_id' => $template->current_version_id, 'status' => SubmissionStatus::Accepted]);
        TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto', 'beta_submission_id' => $mine->id]);
        $theirs = BetaSubmission::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'bass', 'status' => SubmissionStatus::Accepted]);
        TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'bass', 'beta_submission_id' => $theirs->id]);

        $response = $this->asSinger($singer)->getJson('/api/beta/parts');

        $this->assertMatchesContract($response, 'beta.parts.list', 200);
        $guides = collect((array) $response->json('data.0.guides'))->pluck('mine', 'partKey')->all();
        $this->assertSame(['alto' => true, 'bass' => false], $guides);
    }

    public function test_a_take_goes_up_by_presigned_put_and_is_processed(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        $wav = Wav::tone(1.5);

        $created = $this->asSinger($singer)->postJson('/api/beta/submissions', $this->take($assignment->sqid(), $wav, (int) $template->currentVersion?->version));
        $this->assertMatchesContract($created, 'beta.submissions.create', 201);
        $created->assertJsonPath('data.status', 'uploading')->assertJsonPath('upload.method', 'PUT');

        $id = (string) $created->json('data.id');
        $this->assertMatchesContract($this->asSinger($singer)->postJson("/api/beta/submissions/{$id}/complete"), 'beta.submissions.complete', 409);

        $this->call('PUT', (string) $created->json('upload.url'), [], [], [], ['CONTENT_TYPE' => 'audio/wav'], $wav)->assertOk();

        $completed = $this->asSinger($singer)->postJson("/api/beta/submissions/{$id}/complete");
        $this->assertMatchesContract($completed, 'beta.submissions.complete', 200);

        $submission = BetaSubmission::query()->firstOrFail();
        $this->assertSame(SubmissionStatus::Submitted, $submission->status, (string) $submission->failure);
        $this->assertCount(150, (array) $submission->peaks);
        $this->assertEqualsWithDelta(0.3, max((array) $submission->peaks), 0.01);
        $this->assertEqualsWithDelta(-13.5, (float) $submission->loudness_dbfs, 1.0);

        // Again: nothing changes (the outbox may retry).
        $this->assertMatchesContract($this->asSinger($singer)->postJson("/api/beta/submissions/{$id}/complete"), 'beta.submissions.complete', 200);
        $this->assertMatchesContract($this->asSinger($singer)->getJson('/api/beta/parts'), 'beta.parts.list', 200);
        $this->asSinger($singer)->getJson('/api/beta/parts')->assertJsonPath('data.0.status', 'submitted');
    }

    public function test_a_different_file_is_refused_and_can_be_sent_again(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        $wav = Wav::tone(0.5);
        $created = $this->asSinger($singer)->postJson('/api/beta/submissions', $this->take($assignment->sqid(), $wav, (int) $template->currentVersion?->version));
        $key = BetaSubmission::query()->firstOrFail()->master_key;

        Storage::disk('private')->put($key, Wav::tone(0.5, 48000, 440.0));
        $mismatch = $this->asSinger($singer)->postJson('/api/beta/submissions/'.$created->json('data.id').'/complete');
        $this->assertMatchesContract($mismatch, 'beta.submissions.complete', 409);
        $mismatch->assertJsonPath('code', 'upload_mismatch');

        Storage::disk('private')->put($key, $wav);
        $this->asSinger($singer)->postJson('/api/beta/submissions/'.$created->json('data.id').'/complete')->assertOk();
    }

    public function test_a_wrong_size_file_is_deleted_at_once(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        $created = $this->asSinger($singer)->postJson('/api/beta/submissions', $this->take($assignment->sqid(), Wav::tone(0.5), (int) $template->currentVersion?->version));
        $key = BetaSubmission::query()->firstOrFail()->master_key;
        Storage::disk('private')->put($key, str_repeat('x', 5_000_000));

        $this->asSinger($singer)->postJson('/api/beta/submissions/'.$created->json('data.id').'/complete')->assertStatus(409)->assertJsonPath('code', 'upload_incomplete');

        Storage::disk('private')->assertMissing($key);
    }

    public function test_an_abandoned_newer_take_doesnt_hide_the_one_in_review(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'template_version_id' => $template->current_version_id, 'status' => SubmissionStatus::InReview]);
        BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'template_version_id' => $template->current_version_id, 'status' => SubmissionStatus::Uploading]);

        $this->asSinger($singer)->getJson('/api/beta/parts')->assertJsonPath('data.0.status', 'in_review');
    }

    public function test_processing_that_keeps_failing_says_so(): void
    {
        $submission = BetaSubmission::factory()->create(['status' => SubmissionStatus::Processing]);

        (new ProcessSubmission($submission->id))->failed();

        $this->assertSame(SubmissionStatus::Failed, $submission->refresh()->status);
        $this->assertSame('processing_error', $submission->failure);
    }

    public function test_a_file_that_isnt_the_audio_it_claims_fails_processing(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        $fake = str_repeat('x', 44 + 2 * 4800);
        $created = $this->asSinger($singer)->postJson('/api/beta/submissions', $this->take($assignment->sqid(), $fake, (int) $template->currentVersion?->version));
        Storage::disk('private')->put(BetaSubmission::query()->firstOrFail()->master_key, $fake);

        $this->asSinger($singer)->postJson('/api/beta/submissions/'.$created->json('data.id').'/complete')->assertOk();

        $submission = BetaSubmission::query()->firstOrFail();
        $this->assertSame(SubmissionStatus::Failed, $submission->status);
        $this->assertSame('not_audio', $submission->failure);
    }

    public function test_a_take_needs_consent_a_reviewed_template_the_current_version_and_the_singers_own_part(): void
    {
        $template = $this->catalogue(reviewed: false);
        [$singer, $assignment] = $this->singerWithAlto(consented: false);
        [$other] = $this->singerWithAlto();
        $take = $this->take($assignment->sqid(), Wav::tone(0.2), (int) $template->currentVersion?->version);

        $this->assertMatchesContract($this->asSinger($singer)->postJson('/api/beta/submissions', $take), 'beta.submissions.create', 403);
        $this->asSinger($other)->postJson('/api/beta/submissions', $take)->assertJsonPath('code', 'not_yours');
        $this->assertMatchesContract($this->asSinger($other)->postJson('/api/beta/submissions', $take), 'beta.submissions.create', 403);

        $singer->forceFill(['consent_version' => 1, 'consented_at' => now()])->save();
        $this->asSinger($singer)->postJson('/api/beta/submissions', $take)->assertJsonPath('code', 'not_recordable');

        $this->catalogue();
        $this->assertMatchesContract($this->asSinger($singer)->postJson('/api/beta/submissions', [...$take, 'templateVersion' => 99]), 'beta.submissions.create', 409);
        $this->assertSame(0, BetaSubmission::query()->count());
    }

    public function test_the_numbers_must_add_up(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        $take = $this->take($assignment->sqid(), Wav::tone(0.2), (int) $template->currentVersion?->version);

        $this->assertMatchesContract($this->asSinger($singer)->postJson('/api/beta/submissions', [...$take, 'bytes' => $take['bytes'] + 2]), 'beta.submissions.create', 422);
        $this->asSinger($singer)->postJson('/api/beta/submissions', [...$take, 'frames' => 48000 * 601, 'bytes' => 44 + 2 * 48000 * 601])->assertJsonPath('errors.frames.0', __('validation.take_too_long'));
        $this->asSinger($singer)->postJson('/api/beta/submissions', [...$take, 'sampleRate' => 12345])->assertUnprocessable();
        $this->asSinger($singer)->postJson('/api/beta/submissions', [...$take, 'key' => 'A'])->assertJsonPath('errors.key.0', __('validation.take_key', ['key' => 'G']));
    }

    public function test_uploads_can_be_paused(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        app(Flags::class)->set('pause_uploads', 'on', null, 'Storage attack');

        $response = $this->asSinger($singer)->postJson('/api/beta/submissions', $this->take($assignment->sqid(), Wav::tone(0.2), (int) $template->currentVersion?->version));
        $this->assertMatchesContract($response, 'beta.submissions.create', 423);
    }

    public function test_tell_hillel(): void
    {
        [$singer] = [BetaSinger::factory()->create()];

        $response = $this->asSinger($singer)->postJson('/api/beta/feedback', ['text' => 'This note feels wrong in bar 12', 'templateId' => 'amazing-grace', 'partKey' => 'alto', 'beat' => 33.5]);
        $this->assertMatchesContract($response, 'beta.feedback.create', 201);

        $this->assertMatchesContract($this->asSinger($singer)->postJson('/api/beta/feedback', ['text' => '']), 'beta.feedback.create', 422);
        // Signed out, and signed in as someone who isn't a singer.
        $this->app['auth']->forgetGuards();
        $this->assertMatchesContract($this->postJson('/api/beta/feedback', ['text' => 'x']), 'beta.feedback.create', 401);
        $this->actingAs(User::factory()->create());
        $this->assertMatchesContract($this->postJson('/api/beta/feedback', ['text' => 'x']), 'beta.feedback.create', 403);
    }

    public function test_a_take_below_the_bar_is_never_sent_and_hillel_sets_the_bar(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        $wav = Wav::tone(1.5);
        $take = fn (array $scores): array => [...$this->take($assignment->sqid(), $wav, (int) $template->currentVersion?->version), 'scores' => $scores];

        // The bar, for the welcome, My parts and the studio.
        $this->asSinger($singer)->getJson('/api/beta/me')->assertJsonPath('data.bar', ['pitchPercent' => 90, 'timingPercent' => 80]);

        foreach ([[], ['pitchPercent' => null], ['pitchPercent' => 85], ['pitchPercent' => 95, 'timingPercent' => 70]] as $scores) {
            $refused = $this->asSinger($singer)->postJson('/api/beta/submissions', $take($scores));
            $this->assertMatchesContract($refused, 'beta.submissions.create', 422);
            $refused->assertJsonPath('code', 'below_bar')->assertJsonPath('bar.pitchPercent', 90);
        }

        // Timing too few moments to judge (null) doesn't hold it back.
        $this->asSinger($singer)->postJson('/api/beta/submissions', $take(['pitchPercent' => 95, 'timingPercent' => null, 'verdict' => 'ready', 'tries' => 3]))->assertCreated();
        $this->asSinger($singer)->postJson('/api/beta/submissions', $take(['pitchPercent' => 95, 'verdict' => 'maybe']))->assertStatus(422)->assertJsonValidationErrors('scores.verdict');

        // Hillel raises it.
        $this->actingAsAdmin();
        $shown = $this->getJson('/api/admin/beta/bar');
        $this->assertMatchesContract($shown, 'admin.beta.bar.show', 200);
        $raised = $this->patchJson('/api/admin/beta/bar', ['pitchPercent' => 96]);
        $this->assertMatchesContract($raised, 'admin.beta.bar.update', 200);
        $raised->assertJsonPath('data', ['pitchPercent' => 96, 'timingPercent' => 80]);
        $this->assertMatchesContract($this->patchJson('/api/admin/beta/bar', ['pitchPercent' => 30]), 'admin.beta.bar.update', 422);
        $this->app['auth']->forgetGuards();
        $this->asSinger($singer)->postJson('/api/beta/submissions', $take(['pitchPercent' => 95, 'timingPercent' => 90]))->assertStatus(422)->assertJsonPath('code', 'below_bar');
    }
}
