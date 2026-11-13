<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Beta;

use App\Enums\SubmissionStatus;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\Template;
use App\Support\Beta\PartStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * Choosing among takes (docs/open-guide-voices-plan.md, open takes): Hillel sees a part's takes together beside its
 * guide now, publishes one, and the others still waiting are kept, marked not chosen, with his note for them.
 */
final class ChoosingTakesTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private Template $song;

    protected function setUp(): void
    {
        parent::setUp();
        $this->song = $this->catalogue();
        config(['counts.enabled' => true]);
    }

    private function take(string $part = 'alto', ?string $at = null): BetaSubmission
    {
        $singer = BetaSinger::factory()->accepted()->consented()->create(['volunteer' => true]);
        $assignment = BetaAssignment::factory()->create(['beta_singer_id' => $singer->id, 'template_id' => $this->song->id, 'part_key' => $part]);
        $wav = Wav::tone(1.0);
        $take = BetaSubmission::factory()->create([
            'beta_assignment_id' => $assignment->id, 'template_version_id' => $this->song->current_version_id, 'part_key' => $part,
            'frames' => intdiv(strlen($wav) - 44, 2), 'bytes' => strlen($wav), 'sha256' => hash('sha256', $wav), 'loudness_dbfs' => -14.0, 'peaks' => [0.3],
            'status' => SubmissionStatus::Submitted, 'created_at' => $at ?? now(),
        ]);
        Storage::disk('private')->put($take->master_key, $wav);

        return $take;
    }

    public function test_the_queue_groups_a_pages_takes_by_part_with_the_oldest_waiting_first(): void
    {
        $tenor = $this->take('tenor', now()->subDays(3)->toDateTimeString());
        $altos = [$this->take('alto', now()->subDay()->toDateTimeString()), $this->take('alto')];
        $this->actingAsEditor();

        $list = $this->getJson('/api/admin/beta/submissions?status=waiting&groupBy=part');
        $this->assertMatchesContract($list, 'admin.beta.submissions.list', 200);
        $groups = (array) $list->json('meta.groups');
        $this->assertSame(['tenor', 'alto'], array_column($groups, 'partKey'), "the tenor's take waited longest");
        $this->assertSame([$tenor->sqid()], $groups[0]['takes']);
        $this->assertEqualsCanonicalizing([$altos[0]->sqid(), $altos[1]->sqid()], $groups[1]['takes']);
        $this->assertSame([$this->song->slug, (int) $this->song->currentVersion?->version, null], [$groups[1]['templateId'], $groups[1]['version'], $groups[1]['guide']]);
        $this->assertEmpty(array_diff(array_merge(...array_column($groups, 'takes')), array_column((array) $list->json('data'), 'id')), "every grouped take is in this page's data");

        // Without groupBy, nothing changes.
        $this->assertArrayNotHasKey('groups', (array) $this->getJson('/api/admin/beta/submissions?status=waiting')->json('meta'));
    }

    public function test_publishing_one_take_marks_the_parts_other_waiting_takes_not_chosen_with_the_note(): void
    {
        [$chosen, $other, $third] = [$this->take(), $this->take(), $this->take()];
        $bass = $this->take('bass');
        $this->actingAsAdmin();

        $accepted = $this->postJson("/api/admin/beta/submissions/{$chosen->sqid()}/accept", ['makeDefault' => true, 'notChosenNote' => 'Thank you for singing it.']);
        $this->assertMatchesContract($accepted, 'admin.beta.submissions.accept', 200);

        foreach ([$other, $third] as $take) {
            $take->refresh();
            $this->assertSame([SubmissionStatus::NotChosen, 'Thank you for singing it.'], [$take->status, $take->admin_note]);
        }
        $this->assertSame(SubmissionStatus::Submitted, $bass->refresh()->status, "another part's takes wait as they were");
        $this->assertSame(2, (int) DB::table('usage_counts')->where('kind', 'guide_not_chosen')->sum('count'));
        $this->assertSame(1, (int) DB::table('usage_counts')->where('kind', 'guide_published')->sum('count'));

        // My parts says so, with the note.
        $this->assertSame('not_chosen', PartStatus::of($other->refresh(), false));
        $this->postJson("/api/admin/beta/submissions/{$bass->sqid()}/accept", ['notChosenNote' => str_repeat('x', 501)])->assertJsonValidationErrors(['notChosenNote']);
    }

    public function test_an_alternate_voice_marks_nobody(): void
    {
        [$chosen, $other] = [$this->take(), $this->take()];
        $this->actingAsAdmin();
        $this->postJson("/api/admin/beta/submissions/{$chosen->sqid()}/accept", ['makeDefault' => false])->assertOk();
        $this->assertSame(SubmissionStatus::Submitted, $other->refresh()->status);
    }
}
