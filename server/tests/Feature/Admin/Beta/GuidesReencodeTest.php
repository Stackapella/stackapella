<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Beta;

use App\Models\BetaSubmission;
use App\Models\TemplateGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * Guides at another bitrate (beta.guide_kbps; the 64 and 48 kbps pairs for Hillel): app:guides:reencode encodes each
 * published guide again from its master, in place, so the apps' chosen voices (by guide id) and every guide's timing stay.
 */
final class GuidesReencodeTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private function published(): TemplateGuide
    {
        // Published at the old 128, so each test can move it to another bitrate.
        config(['beta.guide_kbps' => 128]);
        $template = $this->catalogue();
        [, $assignment] = $this->singerWithAlto();
        $wav = Wav::tone(2.0);
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
        $this->actingAsAdmin();
        $this->postJson("/api/admin/beta/submissions/{$submission->sqid()}/accept", ['makeDefault' => true])->assertOk();

        return TemplateGuide::query()->where('beta_submission_id', $submission->id)->firstOrFail();
    }

    public function test_a_guide_is_encoded_again_in_place_at_the_new_bitrate_and_only_once(): void
    {
        $guide = $this->published();
        $this->assertSame(128, $guide->kbps);
        $this->artisan('app:guides:reencode', ['--dry-run' => true])->expectsOutput('0 guides to encode again at 128 kbps.')->assertSuccessful();

        config(['beta.guide_kbps' => 64]);
        $before = (string) Storage::disk('media')->get($guide->media_key);
        $this->artisan('app:guides:reencode')->expectsOutput('1 guide queued at 64 kbps.')->assertSuccessful();

        $after = TemplateGuide::query()->findOrFail($guide->id);
        $this->assertSame([64, $guide->frames, $guide->offset_frames, $guide->is_default, $guide->template_version_id], [$after->kbps, $after->frames, $after->offset_frames, $after->is_default, $after->template_version_id]);
        $this->assertNotSame($guide->media_key, $after->media_key, 'a new file at a new immutable key');
        $this->assertFalse(Storage::disk('media')->exists($guide->media_key), 'the old file is gone');
        $this->assertLessThan(strlen($before), strlen((string) Storage::disk('media')->get($after->media_key)), 'smaller at 64 kbps');
        $this->assertSame(1, TemplateGuide::query()->count(), 'the same guide, never a second');

        $this->artisan('app:guides:reencode')->expectsOutput('0 guides queued at 64 kbps.')->assertSuccessful();
    }

    public function test_a_guide_without_its_master_is_left_as_it_is(): void
    {
        $guide = $this->published();
        Storage::disk('private')->delete((string) $guide->submission?->master_key);
        config(['beta.guide_kbps' => 64]);
        $this->artisan('app:guides:reencode')->assertSuccessful();

        $this->assertSame([$guide->media_key, 128], [TemplateGuide::query()->findOrFail($guide->id)->media_key, TemplateGuide::query()->findOrFail($guide->id)->kbps]);
    }
}
