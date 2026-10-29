<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Jobs\DeletePrivateFiles;
use App\Models\Part;
use App\Models\Project;
use App\Models\SyncTombstone;
use App\Models\Take;
use App\Support\Accounts\SignInSession;
use App\Support\Sync\Changes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CloudSave;
use Tests\Support\Wav;
use Tests\TestCase;

final class SyncTest extends TestCase
{
    use CloudSave;
    use RefreshDatabase;

    public function test_a_pull_brings_everything_then_only_what_changed_since(): void
    {
        $this->owner();
        [$project, $part] = $this->projectWithPart();
        $take = $this->recordTake($part);

        $all = $this->pull();
        $this->assertMatchesContract($all, 'sync.pull', 200);
        $all->assertJsonCount(1, 'data.projects')->assertJsonCount(1, 'data.parts')->assertJsonCount(1, 'data.takes')->assertJsonPath('meta.more', false);
        $cursor = (string) $all->json('meta.cursor');

        $this->pull($cursor)->assertJsonCount(0, 'data.projects')->assertJsonCount(0, 'data.takes')->assertJsonPath('meta.cursor', $cursor);

        $this->savePart($part, $project, 1, ['currentTakeId' => $take->uuid, 'muted' => true])->assertOk();
        $this->deleteJson('/api/projects/'.$project.'?version=1&changeId='.Str::uuid())->assertNoContent();

        $since = $this->pull($cursor);
        $since->assertJsonCount(1, 'data.parts')->assertJsonPath('data.parts.0.muted', true)->assertJsonCount(1, 'data.projects');
        $this->assertNotNull($since->json('data.projects.0.binnedAt'));
    }

    public function test_a_deleted_part_is_removed_on_the_other_devices(): void
    {
        $this->owner();
        [, $part] = $this->projectWithPart();
        $cursor = (string) $this->pull()->json('meta.cursor');

        $this->deleteJson('/api/parts/'.$part.'?version=1&changeId='.Str::uuid())->assertNoContent();

        $this->pull($cursor)->assertJsonPath('data.removed', [['kind' => 'part', 'id' => $part]]);
    }

    public function test_a_long_pull_comes_in_pages_without_skipping(): void
    {
        $this->owner();
        $project = (string) Str::uuid();
        $this->saveProject($project)->assertOk();
        $parts = [];
        for ($i = 0; $i < Changes::PAGE + 5; $i++) {
            $parts[] = $id = (string) Str::uuid();
            $this->savePart($id, $project, 0, ['order' => $i])->assertOk();
        }

        $first = $this->pull();
        $first->assertJsonPath('meta.more', true);
        $second = $this->pull((string) $first->json('meta.cursor'));
        $second->assertJsonPath('meta.more', false);

        $seen = [...(array) $first->json('data.parts.*.id'), ...(array) $second->json('data.parts.*.id')];
        $this->assertEqualsCanonicalizing($parts, $seen);
    }

    public function test_the_takes_columns_without_peaks_are_every_other_column(): void
    {
        $this->assertSame(array_values(array_diff(Schema::getColumnListing('takes'), ['peaks'])), Take::WITHOUT_PEAKS, 'A new takes column: add it to Take::WITHOUT_PEAKS.');
    }

    public function test_takes_with_long_peaks_come_in_smaller_pages_without_skipping(): void
    {
        $user = $this->owner();
        $project = Project::factory()->create(['user_id' => $user->id, 'seq' => 1]);
        $part = Part::factory()->create(['project_id' => $project->id, 'seq' => 2]);
        // Two-minute takes: about 11,000 peaks each, so a page holds far fewer than PAGE of them.
        $peaks = array_fill(0, 11250, 0.5);
        $takes = [];
        foreach (range(1, 120) as $i) {
            $takes[] = Take::factory()->create(['part_id' => $part->id, 'user_id' => $user->id, 'peaks' => $peaks, 'frames' => 11250 * 512, 'frames_per_peak' => 512, 'seq' => 2 + $i])->uuid;
        }
        $user->forceFill(['sync_seq' => 122])->save();

        $seen = [];
        $cursor = null;
        $pages = 0;
        do {
            $page = $this->pull($cursor)->assertOk();
            $this->assertLessThanOrEqual(Changes::PEAKS, array_sum(array_map(static fn (array $take): int => strlen((string) base64_decode((string) ($take['peaks8'] ?? ''), true)), (array) $page->json('data.takes'))));
            $seen = [...$seen, ...(array) $page->json('data.takes.*.id')];
            $cursor = (string) $page->json('meta.cursor');
            $pages++;
        } while ($page->json('meta.more') === true && $pages < 20);

        $this->assertGreaterThan(1, $pages);
        $this->assertEqualsCanonicalizing($takes, $seen);
    }

    public function test_peaks_come_compact(): void
    {
        $user = $this->owner();
        $project = Project::factory()->create(['user_id' => $user->id, 'seq' => 1]);
        $part = Part::factory()->create(['project_id' => $project->id, 'seq' => 2]);
        $take = Take::factory()->create(['part_id' => $part->id, 'user_id' => $user->id, 'peaks' => [0, 0.5, 1, 0.25], 'frames' => 4 * 512, 'frames_per_peak' => 512, 'seq' => 3]);
        $part->forceFill(['current_take_id' => $take->id])->save();
        $user->forceFill(['sync_seq' => 3])->save();

        // contract/api.md's worked example, byte for byte.
        $pulled = $this->pull()->assertOk();
        $this->assertMatchesContract($pulled, 'sync.pull', 200);
        $this->assertSame('AID/QA==', $pulled->json('data.takes.0.peaks8'));
        $this->assertArrayNotHasKey('peaks', (array) $pulled->json('data.takes.0'));

        // What the clients sent while numbers were the default still works.
        $this->getJson('/api/sync?peaks=8')->assertOk()->assertJsonPath('data.takes.0.peaks8', 'AID/QA==');
        $this->getJson('/api/sync?peaks=16')->assertUnprocessable();

        $shown = $this->getJson('/api/takes/'.$take->uuid)->assertOk();
        $this->assertMatchesContract($shown, 'takes.show', 200);
        $shown->assertJsonPath('data.peaks8', 'AID/QA==');
        $bundle = $this->getJson('/api/projects/'.$project->uuid)->assertOk();
        $this->assertMatchesContract($bundle, 'projects.show', 200);
        $bundle->assertJsonPath('data.takes.0.peaks8', 'AID/QA==');
    }

    public function test_a_cursor_older_than_the_remembered_deletions_starts_again(): void
    {
        $user = $this->owner();
        [, $part] = $this->projectWithPart();
        $old = (string) $this->pull()->json('meta.cursor');
        $this->deleteJson('/api/parts/'.$part.'?version=1&changeId='.Str::uuid())->assertNoContent();

        $this->travel(91)->days();
        $this->artisan('app:bin:purge')->assertSuccessful();
        $this->actingAs($user->refresh());

        $expired = $this->pull($old);
        $this->assertMatchesContract($expired, 'sync.pull', 410);
        $this->assertMatchesContract($this->pull('not-a-cursor'), 'sync.pull', 422);
    }

    public function test_an_account_with_no_changes_yet_gets_a_cursor_it_can_send_back(): void
    {
        $this->owner();
        $first = $this->pull();
        $cursor = (string) $first->json('meta.cursor');

        $again = $this->pull($cursor);
        $this->assertMatchesContract($again, 'sync.pull', 200);
        $again->assertJsonPath('meta.cursor', $cursor);
    }

    public function test_the_purge_empties_the_bin_after_30_days_files_and_all(): void
    {
        $this->owner();
        [$project, $part] = $this->projectWithPart();
        $replaced = $this->recordTake($part);
        $this->savePart($part, $project, 1, ['currentTakeId' => $replaced->uuid])->assertOk();
        $current = $this->recordTake($part);
        $this->savePart($part, $project, 2, ['currentTakeId' => $current->uuid])->assertOk();
        // A replaced take is kept; deleted, it's in the bin for 30 days.
        $this->deleteJson('/api/takes/'.$replaced->uuid)->assertNoContent();
        $pending = (string) Str::uuid();
        $this->startTake($pending, $part, Wav::tone(0.2))->assertCreated();
        $pendingTake = Take::query()->where('uuid', $pending)->firstOrFail();
        Storage::disk('private')->put($pendingTake->upload_key, 'partial');

        $this->travel(31)->days();
        $this->artisan('app:bin:purge')->assertSuccessful();

        $this->assertModelMissing($replaced);
        Storage::disk('private')->assertMissing((string) $replaced->file_key);
        $this->assertModelExists($current);
        $this->assertSame(Take::UPLOADING, $pendingTake->refresh()->status, 'The row waits for a retry.');
        Storage::disk('private')->assertMissing($pendingTake->upload_key);
        $this->assertTrue(SyncTombstone::query()->where('kind', 'take')->where('uuid', $replaced->uuid)->exists());

        $this->deleteJson('/api/projects/'.$project.'?version=1&changeId='.Str::uuid())->assertNoContent();
        $this->travel(31)->days();
        $this->artisan('app:bin:purge')->assertSuccessful();
        $this->assertSame(0, Project::query()->count());
        Storage::disk('private')->assertMissing((string) $current->file_key);
        $this->assertTrue(SyncTombstone::query()->where('kind', 'project')->where('uuid', $project)->exists());
    }

    public function test_deleting_the_account_deletes_every_recording(): void
    {
        $user = $this->owner();
        [, $part] = $this->projectWithPart();
        $take = $this->recordTake($part);
        $this->withSession([SignInSession::SIGNED_IN_AT => now()->getTimestamp()]);

        Queue::fake();
        $this->deleteJson('/api/account')->assertNoContent();

        $this->assertModelMissing($user);
        $this->assertModelMissing($take);
        Queue::assertPushed(DeletePrivateFiles::class, fn (DeletePrivateFiles $job): bool => in_array($take->file_key, $job->keys, true));
    }

    public function test_course_progress_is_versioned_and_merged_by_the_app(): void
    {
        $this->owner();

        $none = $this->getJson('/api/account/learning');
        $this->assertMatchesContract($none, 'account.learning.show', 200);
        $none->assertJsonPath('data.version', 0);

        $change = (string) Str::uuid();
        $saved = $this->putJson('/api/account/learning', ['changeId' => $change, 'version' => 0, 'progress' => ['xp' => 40]]);
        $this->assertMatchesContract($saved, 'account.learning.update', 200);
        $saved->assertJsonPath('data.version', 1);
        $this->putJson('/api/account/learning', ['changeId' => $change, 'version' => 0, 'progress' => ['xp' => 40]])->assertOk()->assertJsonPath('data.version', 1);

        $stale = $this->putJson('/api/account/learning', ['changeId' => (string) Str::uuid(), 'version' => 0, 'progress' => ['xp' => 10]]);
        $this->assertMatchesContract($stale, 'account.learning.update', 409);
        $stale->assertJsonPath('current.progress.xp', 40);

        $this->assertMatchesContract($this->putJson('/api/account/learning', ['changeId' => (string) Str::uuid(), 'version' => 1, 'progress' => ['x' => str_repeat('a', 300 * 1024)]]), 'account.learning.update', 422);
    }
}
