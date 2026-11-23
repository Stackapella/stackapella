<?php

declare(strict_types=1);

namespace Tests\Feature\Ops;

use App\Enums\OwnChoirStatus;
use App\Models\OwnChoirRequest;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/** Admin > Choir's Live panel (admin.choir.live): the queue, today's spend, failures, the latest requests, the worker. */
final class ChoirLiveTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    public function test_the_live_panel_reads_the_queue_spend_failures_and_the_worker_and_names_no_one(): void
    {
        // Midday, so "earlier today" never lands yesterday (a run just after midnight UTC counted 6 part-minutes, not 10).
        $this->travelTo(Carbon::parse('2026-11-23 12:00:00', 'UTC'));
        $this->catalogue();
        $version = (int) Template::query()->where('slug', 'silent-night')->value('current_version_id');
        $singer = User::factory()->create(['name' => 'Ann Lee', 'email' => 'ann@example.com']);
        $make = fn (array $with): OwnChoirRequest => OwnChoirRequest::factory()->create(['user_id' => $singer->id, 'template_version_id' => $version, ...$with]);
        $make(['status' => OwnChoirStatus::Queued, 'created_at' => now()->subMinutes(5)]);
        $make(['status' => OwnChoirStatus::Queued, 'starts_after' => now()->addHours(3)]);
        $make(['status' => OwnChoirStatus::Rendering, 'first_claimed_at' => now()->subMinute(), 'part_minutes' => 6.0, 'gpu_seconds' => 100]);
        $make(['status' => OwnChoirStatus::Ready, 'first_claimed_at' => now()->subMinutes(20), 'ready_at' => now()->subMinutes(10), 'part_minutes' => 4.0, 'gpu_seconds' => 300, 'priority' => 1]);
        $make(['status' => OwnChoirStatus::Failed, 'failure' => 'clip_unusable', 'failure_detail' => 'notes']);
        $make(['status' => OwnChoirStatus::Failed, 'failure' => 'clip_unusable', 'failure_detail' => 'notes']);

        $this->actingAs(User::factory()->create());
        $this->getJson('/api/admin/choir/live')->assertForbidden();

        // The worker asking for work is its sign of life.
        config(['services.ai_worker.choir_token' => str_repeat('k', 40)]);
        $this->json('POST', '/api/worker/choir/claim', [], ['Authorization' => 'Bearer '.str_repeat('k', 40)]);

        $this->actingAsAdmin();
        $live = $this->getJson('/api/admin/choir/live');
        $this->assertMatchesContract($live, 'admin.choir.live', 200);
        $live->assertJsonPath('data.queue.queued', 1)->assertJsonPath('data.queue.waitingForTomorrow', 1)->assertJsonPath('data.queue.rendering', 1)
            ->assertJsonPath('data.today.partMinutes', 10)->assertJsonPath('data.today.made', 1)->assertJsonPath('data.today.failed', 2)
            ->assertJsonPath('data.month.cost', round(400 * 0.00035, 2))
            ->assertJsonPath('data.failures.0', ['failure' => 'clip_unusable', 'failureDetail' => 'notes', 'n' => 2]);
        $this->assertGreaterThanOrEqual(300, (int) $live->json('data.queue.oldestWaitSeconds'));
        $ready = collect($live->json('data.recent'))->firstWhere('status', 'ready');
        $this->assertSame([true, 600, 'silent-night'], [$ready['paid'], $ready['seconds'], $ready['songId']]);
        $this->assertNotNull($live->json('data.worker.lastPollAt'));
        $this->assertStringNotContainsString('Ann Lee', (string) $live->getContent());
        $this->assertStringNotContainsString('ann@example.com', (string) $live->getContent());
    }
}
