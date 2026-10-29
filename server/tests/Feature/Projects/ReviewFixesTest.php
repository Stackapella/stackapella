<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Take;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\CloudSave;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * What M4's independent review found, each kept fixed.
 */
final class ReviewFixesTest extends TestCase
{
    use CloudSave;
    use RefreshDatabase;

    public function test_restoring_a_project_counts_its_takes_against_the_storage(): void
    {
        $this->owner();
        [$project, $part] = $this->projectWithPart();
        $take = $this->recordTake($part);
        $this->savePart($part, $project, 1, ['currentTakeId' => $take->uuid])->assertOk();
        $this->deleteJson('/api/projects/'.$project.'?version=1&changeId='.Str::uuid())->assertNoContent();

        config(['usage.plans.free.storage_bytes' => (int) $take->stored_bytes - 1]);
        $full = $this->postJson('/api/projects/'.$project.'/restore');
        $this->assertMatchesContract($full, 'projects.restore', 403);
        $full->assertJsonPath('code', 'storage_limit');
    }

    public function test_a_project_beyond_the_limit_after_a_downgrade_takes_no_new_takes(): void
    {
        $this->owner();
        // With no choice settled, the newest made stay editable.
        [, $over] = $this->projectWithPart();
        [, $kept] = $this->projectWithPart();
        config(['usage.plans.free.projects' => 1]);

        $this->assertMatchesContract($this->startTake((string) Str::uuid(), $over, Wav::tone(0.2)), 'takes.create', 403);
        $this->startTake((string) Str::uuid(), $kept, Wav::tone(0.2))->assertCreated();
    }

    public function test_an_overtaken_undo_keeps_every_take(): void
    {
        $this->owner();
        [$project, $part] = $this->projectWithPart();
        $a = $this->recordTake($part);
        $this->savePart($part, $project, 1, ['currentTakeId' => $a->uuid])->assertOk();

        // Offline: B, then C, then Undo back to B, all before either upload finishes.
        [$b, $c] = [(string) Str::uuid(), (string) Str::uuid()];
        $wavB = Wav::tone(0.5);
        $this->startTake($b, $part, $wavB)->assertCreated();
        $this->startTake($c, $part, Wav::tone(0.6))->assertCreated();
        $this->savePart($part, $project, 2, ['currentTakeId' => $b])->assertOk();
        $this->savePart($part, $project, 3, ['currentTakeId' => $c])->assertOk();
        $this->savePart($part, $project, 4, ['currentTakeId' => $b])->assertOk();

        $takeB = Take::query()->where('uuid', $b)->firstOrFail();
        Storage::disk('private')->put($takeB->upload_key, $wavB);
        $this->postJson('/api/takes/'.$b.'/complete')->assertOk();

        // Every take is kept (parts.takes.list); none is binned on the way.
        $this->assertNull($a->refresh()->binned_at);
        $this->assertNull(Take::query()->where('uuid', $c)->firstOrFail()->binned_at);
        $this->assertNull($takeB->refresh()->binned_at);
        $this->assertSame(Take::query()->where('uuid', $b)->firstOrFail()->id, $takeB->part?->refresh()->current_take_id);
    }

    public function test_a_take_no_part_plays_is_kept_and_listed(): void
    {
        $this->owner();
        [, $part] = $this->projectWithPart();
        $stray = $this->recordTake($part);

        $this->travel(2)->days();
        $this->artisan('app:bin:purge')->assertSuccessful();

        // Kept, listed and deletable, so never binned behind anyone's back; it counts toward storage.
        $this->assertNull($stray->refresh()->binned_at);
        $this->getJson('/api/parts/'.$part.'/takes')->assertJsonPath('data.0.take.id', $stray->uuid)->assertJsonPath('data.0.current', false);
        $this->assertSame($stray->bytes(), $this->getJson('/api/usage')->json('data.storage.usedBytes'));
    }

    public function test_a_second_put_after_completion_is_thrown_away(): void
    {
        $this->owner();
        [$project, $part] = $this->projectWithPart();
        $take = $this->recordTake($part);
        $this->savePart($part, $project, 1, ['currentTakeId' => $take->uuid])->assertOk();
        Storage::disk('private')->put($take->upload_key, str_repeat('x', 1000));

        $this->artisan('app:bin:purge')->assertSuccessful();

        Storage::disk('private')->assertMissing($take->upload_key);
        Storage::disk('private')->assertExists((string) $take->file_key);
    }

    public function test_the_purge_spares_a_project_restored_before_it_runs(): void
    {
        $this->owner();
        [$project] = $this->projectWithPart();
        $this->deleteJson('/api/projects/'.$project.'?version=1&changeId='.Str::uuid())->assertNoContent();
        $this->travel(31)->days();
        $this->postJson('/api/projects/'.$project.'/restore')->assertOk();

        $this->artisan('app:bin:purge')->assertSuccessful();

        $this->getJson('/api/projects/'.$project)->assertOk();
    }
}
