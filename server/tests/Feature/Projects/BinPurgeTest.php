<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Part;
use App\Models\Project;
use App\Models\SyncTombstone;
use App\Models\Take;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The storage screen's "Delete now" (bin.purge), "Clear older takes" (bin.purgeTakes) and each
 * project's bytes (usage.show): only one's own, only what's in the bin, gone for good at once with
 * their files and a tombstone for other devices.
 */
final class BinPurgeTest extends TestCase
{
    use RefreshDatabase;

    private User $ada;

    private Project $song;

    private Part $alto;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
        $this->ada = User::factory()->create()->fresh() ?? throw new \LogicException;
        $this->song = Project::factory()->create(['user_id' => $this->ada->id]);
        $this->alto = Part::factory()->create(['project_id' => $this->song->id]);
    }

    /** A take on the alto, its file in storage; the part plays it unless it's binned. */
    private function take(bool $binned, int $bytes = 600000, ?Project $project = null): Take
    {
        $part = $project === null ? $this->alto : Part::factory()->create(['project_id' => $project->id]);
        $take = Take::factory()->create(['part_id' => $part->id, 'user_id' => $this->ada->id, 'stored_bytes' => $bytes, 'binned_at' => $binned ? now()->subDay() : null]);
        Storage::disk('private')->put((string) $take->file_key, 'flac');

        if (! $binned) {
            $part->forceFill(['current_take_id' => $take->id])->save();
        }

        return $take;
    }

    public function test_delete_now_takes_one_item_out_of_the_bin_for_good(): void
    {
        $this->take(false);
        $older = $this->take(true);

        $this->assertMatchesContract($this->actingAs($this->ada)->deleteJson('/api/bin/take/'.$older->uuid), 'bin.purge', 204);
        $this->assertNull(Take::query()->find($older->id));
        Storage::disk('private')->assertMissing((string) $older->file_key);
        $this->assertTrue(SyncTombstone::query()->where('kind', 'take')->where('uuid', $older->uuid)->exists());
        $this->assertMatchesContract($this->deleteJson('/api/bin/take/'.$older->uuid), 'bin.purge', 404);

        // A project in the bin, with its parts and takes.
        $old = Project::factory()->create(['user_id' => $this->ada->id, 'binned_at' => now()->subDay()]);
        $inside = $this->take(false, project: $old);
        $this->deleteJson('/api/bin/project/'.$old->uuid)->assertNoContent();
        $this->assertNull(Project::query()->find($old->id));
        $this->assertNull(Take::query()->find($inside->id));
        Storage::disk('private')->assertMissing((string) $inside->file_key);
    }

    public function test_only_whats_in_the_bin_and_only_ones_own(): void
    {
        $current = $this->take(false);
        $this->actingAs($this->ada)->deleteJson('/api/bin/take/'.$current->uuid)->assertNotFound();
        $this->deleteJson('/api/bin/project/'.$this->song->uuid)->assertNotFound();

        $theirs = Project::factory()->create(['binned_at' => now()->subDay()]);
        $theirTake = Take::factory()->create(['binned_at' => now()->subDay()]);
        $this->deleteJson('/api/bin/project/'.$theirs->uuid)->assertNotFound();
        $this->deleteJson('/api/bin/take/'.$theirTake->uuid)->assertNotFound();
        $this->assertMatchesContract($this->deleteJson('/api/projects/'.$theirs->uuid.'/older-takes'), 'bin.purgeTakes', 404);

        $this->assertNotNull(Take::query()->find($current->id));
        $this->assertNotNull(Project::query()->find($theirs->id));
        $this->assertNotNull(Take::query()->find($theirTake->id));
    }

    public function test_clear_older_takes_and_each_projects_bytes(): void
    {
        $current = $this->take(false, 5000000);
        $this->take(true, 3000000);
        $this->take(true, 1000000);
        $other = Project::factory()->create(['user_id' => $this->ada->id]);
        $this->take(false, 2000000, $other);

        $usage = $this->actingAs($this->ada)->getJson('/api/usage');
        $this->assertMatchesContract($usage, 'usage.show', 200);
        $usage->assertJsonPath('data.storage.projects', [
            ['projectId' => $this->song->uuid, 'bytes' => 5000000, 'binBytes' => 4000000],
            ['projectId' => $other->uuid, 'bytes' => 2000000, 'binBytes' => 0],
        ]);

        $cleared = $this->deleteJson('/api/projects/'.$this->song->uuid.'/older-takes');
        $this->assertMatchesContract($cleared, 'bin.purgeTakes', 200);
        $cleared->assertJsonPath('data.freedBytes', 4000000);
        $this->assertSame([$current->id], Take::query()->where('project_id', $this->song->id)->pluck('id')->all());
        $this->getJson('/api/usage')->assertJsonPath('data.storage.projects.0.binBytes', 0)->assertJsonPath('data.storage.binBytes', 0);
        $this->deleteJson('/api/projects/'.$this->song->uuid.'/older-takes')->assertOk()->assertJsonPath('data.freedBytes', 0);
    }

    public function test_the_hourly_purge_never_takes_the_copy_a_take_is_being_made_from(): void
    {
        $processing = Take::factory()->create(['part_id' => $this->alto->id, 'user_id' => $this->ada->id, 'status' => Take::PROCESSING, 'upload_key' => 'uploads/received/processing.wav']);
        $ready = Take::factory()->create(['part_id' => $this->alto->id, 'user_id' => $this->ada->id, 'status' => Take::READY, 'upload_key' => 'uploads/received/ready.wav']);
        Storage::disk('private')->put('uploads/received/processing.wav', 'wav');
        Storage::disk('private')->put('uploads/received/ready.wav', 'wav');

        $this->artisan('app:bin:purge')->assertSuccessful();

        Storage::disk('private')->assertExists('uploads/received/processing.wav');
        Storage::disk('private')->assertMissing('uploads/received/ready.wav');
        $this->assertSame(Take::PROCESSING, $processing->refresh()->status);
        $this->assertNotNull($ready->refresh());
    }
}
