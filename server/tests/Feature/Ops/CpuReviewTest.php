<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Actions\Counts\RecordCounts;
use App\Jobs\CopyHarmonyStems;
use App\Jobs\RedrawHarmonyCard;
use App\Jobs\RedrawPersonsCards;
use App\Models\Part;
use App\Models\Project;
use App\Models\Take;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use App\Support\Flags;
use App\Support\Trust\AuthEmails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\Support\Sharing;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * The CPU and queue review (2026-11-21): what one account (or a bot) can make the server do is bounded.
 */
final class CpuReviewTest extends TestCase
{
    use RefreshDatabase;
    use Sharing;

    public function test_a_name_or_hidden_profile_change_redraws_once_and_ten_a_day(): void
    {
        Queue::fake();
        $this->owner();
        config(['limits.profile-changes.account.max' => 2]);

        $this->patchJson('/api/account', ['name' => 'Ada One'])->assertOk();
        $this->patchJson('/api/account', ['name' => 'Ada Two'])->assertOk();
        // Waiting already: one redraw for both, drawn with the last.
        Queue::assertPushed(RedrawPersonsCards::class, 1);
        // Something else changes freely.
        $this->patchJson('/api/account', ['bio' => 'Alto.'])->assertOk();
        $this->assertMatchesContract($this->patchJson('/api/account', ['name' => 'Ada Three']), 'account.update', 429);
        $this->assertSame('Ada Two', User::query()->firstOrFail()->name);
    }

    public function test_a_rename_draws_its_card_off_the_request_once_for_a_run_of_renames(): void
    {
        $this->owner();
        [$project] = $this->recordedProject();
        $harmony = $this->shareHarmony($project);
        Queue::fake();

        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['title' => 'For Gran'])->assertOk()->assertJsonPath('data.title', 'For Gran');
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['title' => 'For Grandad'])->assertOk();

        Queue::assertPushed(RedrawHarmonyCard::class, 1);
        Queue::assertPushed(RedrawHarmonyCard::class, fn (RedrawHarmonyCard $job): bool => $job->harmonyId === $harmony->id && $job->delay !== null);
    }

    public function test_a_third_take_waits_while_two_are_being_made(): void
    {
        $user = $this->owner();
        [$project, $part] = $this->projectWithPart();
        $projectId = (int) Project::query()->where('uuid', $project)->value('id');
        Take::factory()->count(2)->create(['user_id' => $user->id, 'project_id' => $projectId, 'part_id' => Part::factory()->create(['project_id' => $projectId])->id, 'status' => Take::PROCESSING]);

        $refused = $this->startTake((string) Str::uuid(), $part, Wav::tone(0.5));
        $this->assertMatchesContract($refused, 'takes.create', 429);
        $refused->assertJsonPath('retryAfter', 30);

        // One stuck for a quarter hour no longer counts.
        Take::query()->where('status', Take::PROCESSING)->limit(1)->update(['updated_at' => now()->subMinutes(20)]);
        $this->startTake((string) Str::uuid(), $part, Wav::tone(0.5))->assertCreated();
    }

    public function test_no_account_keeps_more_than_the_backstops_projects(): void
    {
        $this->owner();
        config(['usage.max_projects' => 1]);
        $this->saveProject((string) Str::uuid())->assertOk();

        $this->assertMatchesContract($this->saveProject((string) Str::uuid()), 'projects.save', 403);
    }

    public function test_the_error_tunnel_takes_ten_a_minute_from_one_network(): void
    {
        foreach (range(1, 10) as $ignored) {
            $this->call('POST', '/log/errors', [], [], [], ['CONTENT_TYPE' => 'text/plain'], '')->assertNoContent();
        }

        $this->call('POST', '/log/errors', [], [], [], ['CONTENT_TYPE' => 'text/plain'], '')->assertStatus(429);
    }

    public function test_reddit_tags_count_only_for_our_subreddits_and_new_embed_hosts_are_capped_a_day(): void
    {
        config(['counts.new_embed_hosts_a_day' => 1]);

        $this->assertSame('reddit-choir', RecordCounts::source('reddit-choir'));
        $this->assertSame('reddit-choir-dec', RecordCounts::source('reddit-choir-dec'));
        $this->assertSame('reddit-other', RecordCounts::source('reddit-somewhere'));

        $this->assertSame('embed-choir.example.com', RecordCounts::source('embed-choir.example.com'));
        $this->assertSame('embed-other', RecordCounts::source('embed-second.example.com'));
        $this->assertSame('embed-choir.example.com', RecordCounts::source('embed-choir.example.com'), 'A host kept once stays kept.');

        $this->travel(1)->days();
        $this->assertSame('embed-second.example.com', RecordCounts::source('embed-second.example.com'), 'A new day takes a new one.');
    }

    public function test_a_flood_of_sign_in_emails_turns_shield_mode_on_and_tells_the_admins_once(): void
    {
        Notification::fake();
        config(['ops.auth_emails_an_hour' => 2, 'admin.admin_google_ids' => ['g-admin']]);
        $admin = User::factory()->admin()->create(['google_id' => 'g-admin', 'email' => 'admin@example.com']);

        AuthEmails::sent();
        AuthEmails::sent();
        $this->assertFalse(app(Flags::class)->on('shield_mode'));

        AuthEmails::sent();
        AuthEmails::sent();
        $this->assertTrue(app(Flags::class)->on('shield_mode'));
        Notification::assertSentToTimes($admin, OpsAlert::class, 1);
    }

    public function test_one_copy_of_a_harmonys_stems_at_a_time(): void
    {
        Queue::fake();

        CopyHarmonyStems::dispatch(7);
        CopyHarmonyStems::dispatch(7);
        CopyHarmonyStems::dispatch(8);

        Queue::assertPushed(CopyHarmonyStems::class, 2);
    }
}
