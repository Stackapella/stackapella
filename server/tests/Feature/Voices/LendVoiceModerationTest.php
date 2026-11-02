<?php

declare(strict_types=1);

namespace Tests\Feature\Voices;

use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Models\CourseClip;
use App\Models\Report;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Lend your voice, slice 3 (docs/lend-your-voice-plan.md): reporting a published voice, removing
 * it, the abuse block, and the clip's style for its credit.
 */
final class LendVoiceModerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A volunteer with a published clip.
     *
     * @return array{0: User, 1: BetaSinger, 2: CourseClip, 3: BetaSubmission}
     */
    private function published(string $style = 'beatbox'): array
    {
        $user = User::factory()->create(['name' => 'Ana Ruiz', 'adult_confirmed_at' => now(), 'birth_year' => 1990, 'birth_month' => 5, 'created_at' => now()->subDays(30)]);
        $singer = BetaSinger::factory()->create(['user_id' => $user->id, 'volunteer' => true, 'credit_mode' => 'name']);
        $clip = CourseClip::factory()->create(['brief' => ['sing' => ['en' => 'A beat'], 'pitch' => null, 'seconds' => 3.0, 'style' => $style, 'voices' => 'any', 'used' => []]]);
        $submission = BetaSubmission::factory()->create(['beta_assignment_id' => BetaAssignment::factory()->create(['beta_singer_id' => $singer->id, 'course_clip_id' => $clip->id])->id]);
        Storage::disk('media')->put('clips/ana.m4a', 'audio');
        $clip->forceFill(['beta_submission_id' => $submission->id, 'media_key' => 'clips/ana.m4a', 'sample_rate' => 48000, 'frames' => 144000, 'published_at' => now()])->save();

        return [$user, $singer, $clip, $submission];
    }

    public function test_a_published_voice_is_reported_and_the_admins_hear_once(): void
    {
        Notification::fake();
        [, , $clip] = $this->published();
        $admin = $this->actingAsAdmin();

        $this->actingAs(User::factory()->create());
        $this->postJson('/api/reports', ['clipSlot' => $clip->slot, 'reason' => 'copyright'])->assertNoContent();
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/reports', ['clipSlot' => $clip->slot, 'reason' => 'offensive'])->assertNoContent();
        $this->postJson('/api/reports', ['clipSlot' => 'no.such.clip', 'reason' => 'offensive'])->assertNotFound();

        $this->assertSame(2, Report::query()->where('reportable_type', 'course_clip')->where('status', Report::OPEN)->count());
        Notification::assertSentTimes(OpsAlert::class, 1);
        // Never hidden by reports alone.
        $this->assertNotNull($clip->refresh()->media_key);

        $this->actingAsAdmin($admin);
        $list = $this->getJson('/api/admin/beta/clips');
        $this->assertMatchesContract($list, 'admin.beta.clips.list', 200);
        $this->assertSame(2, collect((array) $list->json('data'))->firstWhere('id', $clip->slot)['reports']);
    }

    public function test_removing_a_voice_for_abuse_blocks_the_volunteer_and_the_synth_plays_again(): void
    {
        app(Flags::class)->set('lend_voice', 'on', null, 'Open');
        [$ana, $singer, $clip] = $this->published();
        $this->postJson('/api/reports', ['clipSlot' => $clip->slot, 'reason' => 'copyright'])->assertNoContent();

        // Its credit names the style: "Beatbox by Ana Ruiz".
        $this->actingAs($ana);
        $this->assertSame(['credit' => 'Ana Ruiz', 'creditStyle' => 'beatbox'], array_intersect_key((array) $this->getJson('/api/courses/'.$clip->course_id.'/clips')->json('data.0'), ['credit' => 1, 'creditStyle' => 1]));

        $this->actingAsAdmin();
        $this->assertMatchesContract($this->deleteJson('/api/admin/beta/clips/no.such.clip', ['abuse' => true]), 'admin.beta.clips.delete', 404);
        $this->deleteJson('/api/admin/beta/clips/'.$clip->slot, ['abuse' => true])->assertNoContent();
        $this->assertNull($clip->refresh()->media_key);
        Storage::disk('media')->assertMissing('clips/ana.m4a');
        $this->assertNotNull($singer->refresh()->volunteer_blocked_at);
        $this->assertSame(0, Report::query()->where('status', Report::OPEN)->count());

        $this->actingAs($ana);
        $this->assertMatchesContract($this->postJson('/api/voices/claims', ['item' => 'clip:yodel.flip']), 'voices.claims.create', 403);
        $this->postJson('/api/voices/claims', ['item' => 'clip:yodel.flip'])->assertJsonPath('code', 'volunteer_blocked');
    }

    public function test_the_public_summary_counts_what_is_wanted_and_lent_only_while_open(): void
    {
        $this->published();
        $this->assertMatchesContract($this->getJson('/api/voices/summary'), 'voices.summary', 404);

        app(Flags::class)->set('lend_voice', 'on', null, 'Open');
        $summary = $this->getJson('/api/voices/summary');
        $this->assertMatchesContract($summary, 'voices.summary', 200);
        $summary->assertJsonPath('data.lent', 1);
        $this->assertSame(collect((array) $this->actingAs(User::factory()->create(['adult_confirmed_at' => now(), 'created_at' => now()->subDays(30)]))->getJson('/api/voices/wanted')->json('data.items'))->where('kind', 'clip')->count(), $summary->json('data.clips'));
    }

    public function test_only_a_rejection_for_abuse_blocks_a_volunteer(): void
    {
        [, $singer, , $submission] = $this->published();
        $second = BetaSubmission::factory()->create(['beta_assignment_id' => $submission->beta_assignment_id]);
        $this->actingAsAdmin();

        $this->postJson('/api/admin/beta/submissions/'.$submission->sqid().'/reject', ['note' => 'Too quiet.'])->assertOk();
        $this->assertNull($singer->refresh()->volunteer_blocked_at);

        $this->postJson('/api/admin/beta/submissions/'.$second->sqid().'/reject', ['note' => 'Someone else\'s recording.', 'abuse' => true])->assertOk();
        $this->assertNotNull($singer->refresh()->volunteer_blocked_at);
    }
}
