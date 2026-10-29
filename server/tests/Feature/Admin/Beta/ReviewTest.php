<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Beta;

use App\Enums\SubmissionStatus;
use App\Jobs\PublishGuide;
use App\Models\BetaFeedback;
use App\Models\BetaSubmission;
use App\Models\TemplateGuide;
use App\Services\Audio\Ffmpeg;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * The review queue and Hillel's decisions, through to a published guide (real ffmpeg).
 */
final class ReviewTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private function submitted(float $seconds = 2.0): BetaSubmission
    {
        $template = $this->catalogue();
        [, $assignment] = $this->singerWithAlto();
        $wav = Wav::tone($seconds);
        $submission = BetaSubmission::factory()->create([
            'beta_assignment_id' => $assignment->id,
            'template_version_id' => $template->current_version_id,
            'frames' => intdiv(strlen($wav) - 44, 2),
            'bytes' => strlen($wav),
            'sha256' => hash('sha256', $wav),
            'loudness_dbfs' => -13.5,
            'peaks' => [0.3, 0.3],
        ]);
        Storage::disk('private')->put($submission->master_key, $wav);

        return $submission;
    }

    public function test_the_queue_lists_waiting_takes_and_shows_one_with_its_master(): void
    {
        $submission = $this->submitted();
        $this->actingAsEditor();

        $list = $this->getJson('/api/admin/beta/submissions?status=waiting');
        $this->assertMatchesContract($list, 'admin.beta.submissions.list', 200);
        $list->assertJsonPath('data.0.id', $submission->sqid())->assertJsonPath('data.0.singer.credit', 'Sarah K.');
        $this->getJson('/api/admin/beta/submissions?status=accepted')->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/beta/submissions?template=silent-night')->assertJsonCount(0, 'data');

        $show = $this->getJson("/api/admin/beta/submissions/{$submission->sqid()}");
        $this->assertMatchesContract($show, 'admin.beta.submissions.show', 200);
        $this->assertNotNull($show->json('master.url'));
        $this->assertSame([0.3, 0.3], $show->json('peaks'));

        $opened = $this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/open");
        $this->assertMatchesContract($opened, 'admin.beta.submissions.open', 200);
        $opened->assertJsonPath('data.status', 'in_review');
    }

    public function test_asking_for_another_take_pins_a_note_to_a_beat(): void
    {
        $submission = $this->submitted();
        $this->actingAsEditor();

        $this->assertMatchesContract($this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/retake", []), 'admin.beta.submissions.retake', 422);

        $response = $this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/retake", ['note' => 'A little rushed in phrase 3', 'beat' => 24]);
        $this->assertMatchesContract($response, 'admin.beta.submissions.retake', 200);
        $response->assertJsonPath('data.status', 'retake')->assertJsonPath('data.adminNoteBeat', 24);

        $this->assertMatchesContract($this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/retake", ['note' => 'again']), 'admin.beta.submissions.retake', 409);
    }

    public function test_rejecting_and_the_admins_decisions(): void
    {
        $submission = $this->submitted();
        $this->actingAsEditor();
        $this->assertMatchesContract($this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/reject"), 'admin.beta.submissions.reject', 403);

        $this->actingAsAdmin();
        $response = $this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/reject", ['note' => 'Thank you!']);
        $this->assertMatchesContract($response, 'admin.beta.submissions.reject', 200);
        $this->assertMatchesContract($this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/accept"), 'admin.beta.submissions.accept', 409);
        $again = $this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/reject", ['note' => 'Twice.']);
        $this->assertMatchesContract($again, 'admin.beta.submissions.reject', 409);
        $again->assertJsonPath('code', 'not_reviewable');
    }

    public function test_accepting_publishes_a_levelled_trimmed_guide_that_lines_up(): void
    {
        $submission = $this->submitted(2.0);
        $this->actingAsAdmin();

        $adjusted = $this->patchJson("/api/admin/beta/submissions/{$submission->sqid()}", ['offsetMs' => 10, 'trimStartMs' => 100, 'trimEndMs' => 400, 'gainDb' => 1]);
        $this->assertMatchesContract($adjusted, 'admin.beta.submissions.adjust', 200);
        $this->assertMatchesContract($this->patchJson("/api/admin/beta/submissions/{$submission->sqid()}", ['offsetMs' => 900]), 'admin.beta.submissions.adjust', 422);

        $accepted = $this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/accept", ['makeDefault' => true]);
        $this->assertMatchesContract($accepted, 'admin.beta.submissions.accept', 200);
        $accepted->assertJsonPath('data.status', 'accepted');

        $guide = TemplateGuide::query()->firstOrFail();
        $this->assertTrue($guide->is_default);
        // From the trimmed start (100 ms) less the nudge (10 ms): 90 ms of the take, i.e. 4320 frames in;
        // the guide's frame 0 is at song time 100 ms (4800 frames after beat 0).
        $this->assertSame(4800, $guide->offset_frames);
        $this->assertSame(96000 - 19200 - 4320, $guide->frames);
        Storage::disk('media')->assertExists($guide->media_key);

        $probe = app(Ffmpeg::class)->probe(Storage::disk('media')->path($guide->media_key), upload: false);
        $this->assertSame('aac', $probe['codec'] ?? null);
        $this->assertSame(48000, $probe['sampleRate'] ?? null);

        $show = $this->getJson("/api/admin/beta/submissions/{$submission->sqid()}");
        $this->assertMatchesContract($show, 'admin.beta.submissions.show', 200);
        $show->assertJsonPath('data.guide.offsetFrames', 4800);

        // The catalogue now plays this voice for Amazing Grace's alto, with the singer's credit.
        $this->getJson('/api/templates')->assertJsonFragment(['credit' => 'Sarah K.', 'partKey' => 'alto']);
    }

    public function test_guides_have_one_default_per_part_and_removing_one_falls_back(): void
    {
        $template = $this->catalogue();
        $this->actingAsAdmin();
        $first = TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto', 'is_default' => true, 'published_at' => now()->subDay()]);
        $second = TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto', 'is_default' => false]);
        Storage::disk('media')->put($second->media_key, 'aac');

        $list = $this->getJson('/api/admin/beta/guides?template=amazing-grace');
        $this->assertMatchesContract($list, 'admin.beta.guides.list', 200);
        $list->assertJsonCount(2, 'data.0.parts.1.guides')->assertJsonPath('data.0.parts.1.guides.0.isDefault', true);

        $default = $this->postJson("/api/admin/beta/guides/{$second->sqid()}/default");
        $this->assertMatchesContract($default, 'admin.beta.guides.default', 200);
        $this->assertFalse($first->refresh()->is_default);

        $this->assertMatchesContract($this->deleteJson("/api/admin/beta/guides/{$second->sqid()}"), 'admin.beta.guides.delete', 204);
        Storage::disk('media')->assertMissing($second->media_key);
        $this->assertTrue($first->refresh()->is_default, 'The alternate becomes the default.');
    }

    public function test_making_the_current_default_the_default_again_keeps_it(): void
    {
        $template = $this->catalogue();
        $this->actingAsAdmin();
        $guide = TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto', 'is_default' => true]);

        $this->postJson("/api/admin/beta/guides/{$guide->sqid()}/default")->assertOk();
        $this->postJson("/api/admin/beta/guides/{$guide->sqid()}/default")->assertOk()->assertJsonPath('data.isDefault', true);

        $this->assertTrue($guide->refresh()->is_default);
    }

    public function test_trims_must_leave_a_second_and_only_open_or_accepted_takes_can_be_adjusted(): void
    {
        $submission = $this->submitted(2.0);
        $this->actingAsAdmin();

        $tooMuch = $this->patchJson("/api/admin/beta/submissions/{$submission->sqid()}", ['trimStartMs' => 600, 'trimEndMs' => 600]);
        $this->assertMatchesContract($tooMuch, 'admin.beta.submissions.adjust', 422);
        $tooMuch->assertJsonPath('errors.trimEndMs.0', __('validation.trims_too_long'));

        $this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/reject")->assertOk();
        $this->assertMatchesContract($this->patchJson("/api/admin/beta/submissions/{$submission->sqid()}", ['gainDb' => 1]), 'admin.beta.submissions.adjust', 409);
    }

    public function test_an_accepted_guide_can_be_published_again_with_new_trims(): void
    {
        $submission = $this->submitted(2.0);
        $this->actingAsAdmin();
        $this->assertMatchesContract($this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/publish"), 'admin.beta.submissions.publish', 409);

        $this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/accept")->assertOk()->assertJsonPath('data.publish', 'published');
        $first = TemplateGuide::query()->firstOrFail();

        $this->patchJson("/api/admin/beta/submissions/{$submission->sqid()}", ['trimStartMs' => 200])->assertOk();
        $again = $this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/publish");
        $this->assertMatchesContract($again, 'admin.beta.submissions.publish', 200);

        $second = TemplateGuide::query()->firstOrFail();
        $this->assertNotSame($first->media_key, $second->media_key);
        $this->assertTrue($second->is_default, 'It keeps its place as the default.');
        $this->assertSame(9600, $second->offset_frames);
        Storage::disk('media')->assertMissing($first->media_key);
        $this->assertSame(1, TemplateGuide::query()->count());
    }

    public function test_a_publish_that_keeps_failing_says_so(): void
    {
        $submission = $this->submitted();
        $submission->forceFill(['status' => SubmissionStatus::Accepted, 'publish_status' => 'pending'])->save();

        (new PublishGuide($submission->id))->failed();

        $this->assertSame('failed', $submission->refresh()->publish_status);
        $this->actingAsAdmin();
        $this->getJson("/api/admin/beta/submissions/{$submission->sqid()}")->assertJsonPath('data.publish', 'failed');
    }

    public function test_a_quiet_take_is_levelled_without_clipping(): void
    {
        $submission = $this->submitted(2.0);
        // Loud peaks in a quiet mean (a sparse take): the gain stops at -1 dBFS.
        $submission->forceFill(['loudness_dbfs' => -40, 'peak_dbfs' => -10.5])->save();
        $this->actingAsAdmin();

        $this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/accept")->assertOk();

        // The decoded guide's loudest sample, as a level from 0 to 1 (volumedetect only counts whole dB).
        $guide = TemplateGuide::query()->firstOrFail();
        $stats = Process::run(['ffmpeg', '-hide_banner', '-nostats', '-i', Storage::disk('media')->path($guide->media_key), '-af', 'astats', '-f', 'null', '-'])->errorOutput();
        $this->assertSame(1, preg_match('/Max level:\s*([\d.]+)/', $stats, $max));
        $this->assertLessThan(0.95, (float) $max[1]);
        $this->assertGreaterThan(0.6, (float) $max[1], 'Still levelled up from a quiet take.');
    }

    public function test_a_pruned_take_shows_no_master(): void
    {
        $submission = $this->submitted();
        $submission->forceFill(['master_key' => ''])->save();
        $this->actingAsEditor();

        $show = $this->getJson("/api/admin/beta/submissions/{$submission->sqid()}");
        $this->assertMatchesContract($show, 'admin.beta.submissions.show', 200);
        $show->assertJsonPath('master', null);
    }

    public function test_the_inbox_and_the_overview(): void
    {
        $this->catalogue();
        $this->actingAsEditor();
        $feedback = BetaFeedback::factory()->create();
        BetaFeedback::factory()->create(['read_at' => now()]);

        $list = $this->getJson('/api/admin/beta/feedback?unread=1');
        $this->assertMatchesContract($list, 'admin.beta.feedback.list', 200);
        $list->assertJsonCount(1, 'data');

        $read = $this->postJson("/api/admin/beta/feedback/{$feedback->sqid()}/read");
        $this->assertMatchesContract($read, 'admin.beta.feedback.read', 200);
        $this->assertNotNull($read->json('data.readAt'));

        $overview = $this->getJson('/api/admin/overview');
        $this->assertMatchesContract($overview, 'admin.overview', 200);
        $overview->assertJsonPath('data.beta.unreadFeedback', 0)->assertJsonPath('data.harmonies.finished', 0);
        $this->assertGreaterThan(30, $overview->json('data.beta.parts'));
    }

    public function test_a_status_change_is_reflected_in_my_parts(): void
    {
        $submission = $this->submitted();
        $this->actingAsAdmin();
        $this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/accept")->assertOk();

        $this->assertSame(SubmissionStatus::Accepted, $submission->refresh()->status);
        $singer = $submission->assignment->singer;
        $this->flushSession();
        $this->asSinger($singer)->getJson('/api/beta/parts')->assertJsonPath('data.0.status', 'guide');
        $this->asSinger($singer)->getJson('/api/beta/me')->assertJsonPath('data.voiceOfParts', 1);
    }
}
