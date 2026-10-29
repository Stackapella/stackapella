<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Beta;

use App\Enums\SubmissionStatus;
use App\Models\BetaSubmission;
use App\Models\TemplateGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * The first beta: about 10 singers, the best hired for more songs. The admin sees who did well.
 */
final class WhoDidWellTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    public function test_the_singers_screen_shows_each_singers_takes_and_the_queue_opens_one_singers(): void
    {
        $template = $this->catalogue();
        [$singer, $assignment] = $this->singerWithAlto();
        $accepted = BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'template_version_id' => $template->current_version_id, 'status' => SubmissionStatus::Accepted, 'scores' => ['pitchPercent' => 94]]);
        BetaSubmission::factory()->create(['beta_assignment_id' => $assignment->id, 'template_version_id' => $template->current_version_id, 'status' => SubmissionStatus::Retake, 'scores' => ['pitchPercent' => 80]]);
        TemplateGuide::factory()->create(['template_version_id' => $template->current_version_id, 'part_key' => 'alto', 'beta_submission_id' => $accepted->id, 'is_default' => true]);
        [$other] = $this->singerWithAlto();

        $this->actingAsAdmin();
        $singers = $this->getJson('/api/admin/beta/singers');
        $this->assertMatchesContract($singers, 'admin.beta.singers.list', 200);
        $stats = collect((array) $singers->json('data'))->firstWhere('id', $singer->sqid())['stats'] ?? null;
        $this->assertSame(['takes' => 2, 'accepted' => 1, 'retakes' => 1, 'published' => 1, 'defaults' => 1, 'pitchPercent' => 87], $stats);

        $guides = $this->getJson('/api/admin/beta/guides');
        $versions = collect((array) $guides->json('data'))->flatMap(fn (array $song): array => $song['parts'])->flatMap(fn (array $part): array => $part['guides']);
        $this->assertSame([94], $versions->pluck('pitchPercent')->all(), 'Each version shows its own take score.');

        $queue = $this->getJson('/api/admin/beta/submissions?singer='.$singer->sqid());
        $this->assertMatchesContract($queue, 'admin.beta.submissions.list', 200);
        $queue->assertJsonCount(2, 'data');
        $this->getJson('/api/admin/beta/submissions?singer='.$other->sqid())->assertJsonCount(0, 'data');
        $this->getJson('/api/admin/beta/submissions?singer=nope')->assertJsonCount(0, 'data');
    }
}
