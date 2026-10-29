<?php

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * "Start the course over" across two devices: the reset reaches both, an older device's progress
 * is never merged back in, and the reset can be undone for 30 days.
 */
final class CourseResetTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $progress
     */
    private function save(int $version, array $progress, ?int $epoch = null): TestResponse
    {
        return $this->putJson('/api/account/learning', ['changeId' => (string) Str::uuid(), 'version' => $version, 'progress' => $progress, ...($epoch !== null ? ['epoch' => $epoch] : [])]);
    }

    private function progress(int $stars): array
    {
        return ['version' => 1, 'home' => 57, 'range' => ['low' => 53, 'high' => 69], 'lessons' => ['u1-l1' => ['stars' => $stars, 'bestPercent' => 90, 'at' => 1]], 'testedOut' => ['u2'], 'xp' => ['total' => 120, 'days' => []], 'goal' => 3];
    }

    public function test_a_reset_reaches_every_device_and_an_older_ones_progress_is_never_merged_back(): void
    {
        $this->actingAs(User::factory()->create());
        $this->save(0, $this->progress(3))->assertOk()->assertJsonPath('data.epoch', 0);

        // Device A starts the course over: the lessons go, the note, range and XP stay.
        $reset = $this->postJson('/api/account/learning/reset', ['changeId' => $change = (string) Str::uuid()]);
        $this->assertMatchesContract($reset, 'account.learning.reset', 200);
        $reset->assertJsonPath('data.epoch', 1)->assertJsonPath('data.progress.lessons', [])->assertJsonPath('data.progress.testedOut', [])->assertJsonPath('data.progress.home', 57)->assertJsonPath('data.progress.xp.total', 120);
        $this->assertNotNull($reset->json('data.undoUntil'));
        // Sent again (a lost reply): the same, not a second reset.
        $this->postJson('/api/account/learning/reset', ['changeId' => $change])->assertJsonPath('data.epoch', 1);

        // Device B still has its stars, from before: refused, with the server's copy to take.
        $stale = $this->save(1, $this->progress(3), epoch: 0);
        $this->assertMatchesContract($stale, 'account.learning.update', 409);
        $stale->assertJsonPath('code', 'learning_reset')->assertJsonPath('current.epoch', 1);
        // An older app, sending no epoch at all: refused the same way.
        $this->save(2, $this->progress(3))->assertStatus(409)->assertJsonPath('code', 'learning_reset');

        // B takes it and carries on, at the new epoch.
        $this->save(2, [...$this->progress(1)], epoch: 1)->assertOk()->assertJsonPath('data.version', 3);

        // A undoes it: the stars from before are back, at another new epoch.
        $undone = $this->postJson('/api/account/learning/reset/undo', ['changeId' => (string) Str::uuid()]);
        $this->assertMatchesContract($undone, 'account.learning.undoReset', 200);
        $undone->assertJsonPath('data.epoch', 2)->assertJsonPath('data.progress.lessons.u1-l1.stars', 3)->assertJsonPath('data.undoUntil', null);
        $this->save(4, $this->progress(1), epoch: 1)->assertStatus(409)->assertJsonPath('code', 'learning_reset');
        $this->assertMatchesContract($this->postJson('/api/account/learning/reset/undo', ['changeId' => (string) Str::uuid()]), 'account.learning.undoReset', 409);
    }

    public function test_an_undo_keeps_what_was_earned_since_and_a_second_reset_keeps_the_first_snapshot(): void
    {
        $this->actingAs(User::factory()->create());
        $this->save(0, $this->progress(3))->assertOk();
        $this->postJson('/api/account/learning/reset', ['changeId' => (string) Str::uuid()])->assertOk();
        // Pressed again on another device: nothing more to clear, and the undo still has the stars.
        $this->postJson('/api/account/learning/reset', ['changeId' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.epoch', 2);

        // A week of practice after the reset: more XP and a badge.
        $this->save(3, [...$this->progress(1), 'xp' => ['total' => 300, 'days' => []], 'badges' => ['first-week']], epoch: 2)->assertOk();

        $undone = $this->postJson('/api/account/learning/reset/undo', ['changeId' => (string) Str::uuid()])->assertOk();
        $undone->assertJsonPath('data.progress.lessons.u1-l1.stars', 3)->assertJsonPath('data.progress.testedOut', ['u2'])
            ->assertJsonPath('data.progress.xp.total', 300)->assertJsonPath('data.progress.badges', ['first-week']);
    }

    public function test_a_reset_can_be_undone_for_30_days_only(): void
    {
        $this->actingAs(User::factory()->create());
        $this->save(0, $this->progress(2))->assertOk();
        $this->postJson('/api/account/learning/reset', ['changeId' => (string) Str::uuid()])->assertOk();

        $this->travel(31)->days();
        $this->getJson('/api/account/learning')->assertJsonPath('data.undoUntil', null);
        $this->assertMatchesContract($this->postJson('/api/account/learning/reset/undo', ['changeId' => (string) Str::uuid()]), 'account.learning.undoReset', 410);
    }

    public function test_plus_courses_progress_is_never_touched_by_a_reset_or_its_undo(): void
    {
        $this->actingAs(User::factory()->create());
        $vibrato = fn (int $stars): array => ['vibrato' => ['lessons' => ['vib-1-swing' => ['stars' => $stars, 'bestPercent' => 80, 'at' => 1]]]];
        $this->save(0, [...$this->progress(3), 'courses' => $vibrato(1)])->assertOk();

        // Starting the free course over leaves the Plus courses as they are.
        $reset = $this->postJson('/api/account/learning/reset', ['changeId' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.progress.lessons', []);
        $this->assertEquals($vibrato(1), $reset->json('data.progress.courses'));

        // A Plus course sung after the reset survives the undo: the undo brings back only what the reset cleared.
        $this->save(2, [...$this->progress(1), 'lessons' => [], 'courses' => $vibrato(3)], epoch: 1)->assertOk();
        $undone = $this->postJson('/api/account/learning/reset/undo', ['changeId' => (string) Str::uuid()])->assertOk()->assertJsonPath('data.progress.lessons.u1-l1.stars', 3);
        $this->assertEquals($vibrato(3), $undone->json('data.progress.courses'));
    }
}
