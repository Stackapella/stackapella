<?php

declare(strict_types=1);

namespace Tests\Feature\Admin\Beta;

use App\Enums\SubmissionStatus;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\CourseClip;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * What needs Hillel now in the guide workflow: per part, takes to review, a voice that didn't carry
 * to the current version, no singer who can still record it; and asking a singer again.
 */
final class NeedsTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    /**
     * Amazing Grace with: a soprano recorded (and the voice) only on the version before, an alto
     * take waiting for review, no tenor singer, and a bass voice on the current version.
     *
     * @return array{0: Template, 1: BetaSinger, 2: BetaAssignment, 3: BetaSubmission}
     */
    private function song(): array
    {
        $template = $this->catalogue();
        $current = $template->currentVersion;
        $older = TemplateVersion::factory()->create(['template_id' => $template->id, 'version' => $current->version - 1, 'reviewed' => true]);

        $ana = BetaSinger::factory()->accepted()->consented()->create(['name' => 'Ana Ruiz']);
        $soprano = BetaAssignment::factory()->create(['beta_singer_id' => $ana->id, 'template_id' => $template->id, 'part_key' => 'soprano']);
        $old = BetaSubmission::factory()->create(['beta_assignment_id' => $soprano->id, 'template_version_id' => $older->id, 'part_key' => 'soprano', 'status' => SubmissionStatus::Accepted, 'created_at' => now()->subDays(3)]);
        TemplateGuide::factory()->create(['template_version_id' => $older->id, 'part_key' => 'soprano', 'beta_submission_id' => $old->id, 'is_default' => true]);

        [, $alto] = $this->singerWithAlto();
        $waiting = BetaSubmission::factory()->create(['beta_assignment_id' => $alto->id, 'template_version_id' => $current->id, 'part_key' => 'alto', 'status' => SubmissionStatus::Submitted]);

        TemplateGuide::factory()->create(['template_version_id' => $current->id, 'part_key' => 'bass', 'is_default' => true]);

        return [$template, $ana, $soprano, $waiting];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function needs(): array
    {
        $list = $this->getJson('/api/admin/beta/guides?template=amazing-grace');
        $this->assertMatchesContract($list, 'admin.beta.guides.list', 200);

        return collect((array) $list->json('data.0.parts'))->mapWithKeys(fn (array $part): array => [$part['key'] => $part['needs']])->all();
    }

    public function test_each_part_says_what_needs_hillel_and_the_summary_lists_only_those(): void
    {
        [, $ana, $soprano, $waiting] = $this->song();
        $this->actingAsEditor();
        $needs = $this->needs();

        // The soprano's voice was for the version before: changed, and Ana (who sang it) isn't asked yet.
        $this->assertSame([false, true, true], [$needs['soprano']['voiced'], $needs['soprano']['changed'], $needs['soprano']['unassigned']]);
        $this->assertSame([['assignment' => $soprano->sqid(), 'singer' => ['id' => $ana->sqid(), 'name' => 'Ana Ruiz'], 'status' => 'accepted', 'open' => false, 'previous' => true, 'askedAgainAt' => null]], $needs['soprano']['singers']);
        // A take waits for review: its singer can still give the part a voice.
        $this->assertSame([$waiting->sqid()], array_column($needs['alto']['review'], 'submission'));
        $this->assertSame([false, false], [$needs['alto']['changed'], $needs['alto']['unassigned']]);
        // Nobody asked for the tenor, nothing recorded before.
        $this->assertSame([false, true, []], [$needs['tenor']['changed'], $needs['tenor']['unassigned'], $needs['tenor']['singers']]);
        // The bass has its voice.
        $this->assertSame([true, false, false], [$needs['bass']['voiced'], $needs['bass']['changed'], $needs['bass']['unassigned']]);

        $summary = $this->getJson('/api/admin/beta/needs');
        $this->assertMatchesContract($summary, 'admin.beta.needs.show', 200);
        $summary->assertJsonPath('data.review', 1);
        $song = collect((array) $summary->json('data.templates'))->firstWhere('id', 'amazing-grace');
        $this->assertSame(['soprano', 'alto', 'tenor'], array_column($song['parts'], 'key'));
    }

    public function test_hillel_asks_a_singer_again_and_their_part_is_a_retake_with_his_note_until_a_take_comes(): void
    {
        [$template, $ana, $soprano] = $this->song();

        $this->actingAsEditor();
        $this->postJson('/api/admin/beta/assignments/'.$soprano->sqid().'/again')->assertForbidden();

        $admin = $this->actingAsAdmin();
        $asked = $this->postJson('/api/admin/beta/assignments/'.$soprano->sqid().'/again');
        $this->assertMatchesContract($asked, 'admin.beta.assignments.again', 200);
        $asked->assertJsonPath('data.status', 'retake');
        $needs = $this->needs()['soprano'];
        $this->assertSame([true, false, true], [$needs['changed'], $needs['unassigned'], $needs['singers'][0]['open']]);
        $this->assertNotNull($needs['singers'][0]['askedAgainAt']);

        $part = fn (): array => collect((array) $this->asSinger($ana)->getJson('/api/beta/parts')->assertOk()->json('data'))->firstWhere('partKey', 'soprano');
        $this->assertSame('retake', $part()['status']);
        $this->assertSame(__('admin.beta.asked_again'), $part()['askedAgain']['note']);

        // A note of his own.
        $this->actingAsAdmin($admin);
        $this->postJson('/api/admin/beta/assignments/'.$soprano->sqid().'/again', ['note' => 'The tune moved in bar 3.'])->assertOk();
        $this->assertSame('The tune moved in bar 3.', $part()['askedAgain']['note']);

        // Ana sends a take for the current version: it's in review, and no longer asked.
        $this->travel(1)->minutes();
        BetaSubmission::factory()->create(['beta_assignment_id' => $soprano->id, 'template_version_id' => $template->current_version_id, 'part_key' => 'soprano', 'status' => SubmissionStatus::Submitted]);
        $this->assertSame(['submitted', null], [$part()['status'], $part()['askedAgain']]);
        $this->actingAsAdmin($admin);
        $needs = $this->needs()['soprano'];
        $this->assertCount(1, $needs['review']);
        $this->assertNull($needs['singers'][0]['askedAgainAt']);
    }

    public function test_only_a_songs_part_and_a_singer_still_with_us_can_be_asked_again(): void
    {
        [, $ana, $soprano] = $this->song();
        $clip = BetaAssignment::factory()->create(['beta_singer_id' => $ana->id, 'template_id' => null, 'part_key' => null, 'course_clip_id' => CourseClip::factory()->create()->id]);
        $this->actingAsAdmin();

        $this->assertMatchesContract($this->postJson('/api/admin/beta/assignments/'.$clip->sqid().'/again'), 'admin.beta.assignments.again', 409);
        $this->postJson('/api/admin/beta/assignments/'.$clip->sqid().'/again')->assertJsonPath('code', 'not_a_part');
        $this->postJson('/api/admin/beta/assignments/'.$soprano->sqid().'/again', ['note' => str_repeat('a', 501)])->assertJsonValidationErrors('note');

        $ana->forceFill(['revoked_at' => now()])->save();
        $gone = $this->postJson('/api/admin/beta/assignments/'.$soprano->sqid().'/again');
        $this->assertMatchesContract($gone, 'admin.beta.assignments.again', 409);
        $gone->assertJsonPath('code', 'singer_gone');
        $this->assertNull($soprano->refresh()->asked_again_at);
    }
}
