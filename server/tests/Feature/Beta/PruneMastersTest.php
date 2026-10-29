<?php

declare(strict_types=1);

namespace Tests\Feature\Beta;

use App\Enums\SubmissionStatus;
use App\Models\BetaSubmission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class PruneMastersTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejected_masters_go_after_90_days_and_unfinished_uploads_after_a_day(): void
    {
        $rejected = BetaSubmission::factory()->create(['status' => SubmissionStatus::Rejected, 'updated_at' => now()->subDays(91)]);
        $recent = BetaSubmission::factory()->create(['status' => SubmissionStatus::Rejected]);
        $accepted = BetaSubmission::factory()->create(['status' => SubmissionStatus::Accepted, 'updated_at' => now()->subDays(200)]);
        $orphan = BetaSubmission::factory()->create(['status' => SubmissionStatus::Uploading, 'created_at' => now()->subDays(2)]);
        foreach ([$rejected, $recent, $accepted, $orphan] as $submission) {
            Storage::disk('private')->put($submission->master_key, 'wav');
        }

        $this->artisan('app:beta:prune-masters')->assertSuccessful();

        Storage::disk('private')->assertMissing($rejected->master_key);
        Storage::disk('private')->assertExists($recent->master_key);
        Storage::disk('private')->assertExists($accepted->master_key);
        Storage::disk('private')->assertMissing($orphan->master_key);
        $this->assertModelMissing($orphan);
        $this->assertSame('', $rejected->refresh()->master_key);
    }
}
