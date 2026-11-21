<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Harmony;
use App\Models\Part;
use App\Models\Project;
use App\Models\Take;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The abuse review (2026-11-21): the bin never counts toward storage, so it's capped at the plan's allowance (oldest
 * first); the storage alert sums everything stored, not takes alone; and a take's private links are capped a day.
 */
final class BinCapTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bin_over_the_allowance_loses_its_oldest_first_and_one_under_keeps_everything(): void
    {
        Storage::fake('private');
        config(['usage.plans.free.storage_bytes' => 1000]);
        $ada = User::factory()->create();
        $song = Project::factory()->create(['user_id' => $ada->id]);
        $part = Part::factory()->create(['project_id' => $song->id]);
        $binned = fn (int $bytes, int $daysAgo, ?Project $in = null): Take => tap(Take::factory()->create(['part_id' => $in === null ? $part->id : Part::factory()->create(['project_id' => $in->id])->id, 'user_id' => $ada->id, 'stored_bytes' => $bytes, 'binned_at' => $in === null ? now()->subDays($daysAgo) : null]), fn (Take $t) => Storage::disk('private')->put((string) $t->file_key, 'flac'));

        $oldest = $binned(600, 5);
        $oldProject = Project::factory()->create(['user_id' => $ada->id, 'binned_at' => now()->subDays(4)]);
        $inProject = $binned(500, 0, $oldProject);
        $newest = $binned(700, 1);
        // Someone under their allowance keeps their whole bin.
        $bo = User::factory()->create();
        $boTake = Take::factory()->create(['part_id' => Part::factory()->create(['project_id' => Project::factory()->create(['user_id' => $bo->id])->id])->id, 'user_id' => $bo->id, 'stored_bytes' => 900, 'binned_at' => now()->subDays(9)]);

        $this->artisan('app:bin:purge')->assertSuccessful();

        // 1800 binned, 1000 allowed: the oldest take (600) and then the project (500) go; the newest stays.
        $this->assertNull(Take::query()->find($oldest->id));
        $this->assertNull(Project::query()->find($oldProject->id));
        $this->assertNull(Take::query()->find($inProject->id));
        Storage::disk('private')->assertMissing((string) $oldest->file_key);
        Storage::disk('private')->assertMissing((string) $inProject->file_key);
        $this->assertNotNull(Take::query()->find($newest->id));
        Storage::disk('private')->assertExists((string) $newest->file_key);
        $this->assertNotNull(Take::query()->find($boTake->id));
    }

    public function test_the_storage_alert_counts_shares_and_guides_not_takes_alone(): void
    {
        Notification::fake();
        config(['admin.admin_google_ids' => ['g-admin']]);
        $admin = User::factory()->admin()->create(['google_id' => 'g-admin', 'email' => 'admin@example.com']);
        config(['ops.alerts.storage_gb_a_day' => 1]);
        Harmony::factory()->create(['stored_bytes' => 2_000_000_000]);

        $this->artisan('app:alerts')->assertSuccessful();

        Notification::assertSentTo($admin, OpsAlert::class);
    }

    public function test_a_takes_private_links_are_capped_a_day(): void
    {
        config(['limits.take-links.account.max' => 2]);
        $ada = User::factory()->create();
        $take = Take::factory()->create(['part_id' => Part::factory()->create(['project_id' => Project::factory()->create(['user_id' => $ada->id])->id])->id, 'user_id' => $ada->id]);
        $this->actingAs($ada);

        $this->getJson("/api/takes/{$take->uuid}")->assertOk();
        $this->getJson("/api/takes/{$take->uuid}")->assertOk();
        $this->assertMatchesContract($this->getJson("/api/takes/{$take->uuid}"), 'takes.show', 429);
    }
}
