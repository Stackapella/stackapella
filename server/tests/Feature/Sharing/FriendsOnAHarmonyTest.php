<?php

declare(strict_types=1);

namespace Tests\Feature\Sharing;

use App\Actions\Users\DeleteAccount;
use App\Jobs\ProcessTake;
use App\Models\Harmony;
use App\Models\Take;
use App\Models\User;
use App\Support\Sharing\HarmonyFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Sharing;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * A harmony a friend sang on (contract/api.md, "Sharing"): they're credited, featuring it or the
 * gallery waits for their yes, and it comes down when their voice leaves.
 */
final class FriendsOnAHarmonyTest extends TestCase
{
    use RefreshDatabase;
    use Sharing;

    /**
     * The owner's project with their own recorded part, and Sam's recorded part.
     *
     * @return array{0: User, 1: User, 2: string, 3: string}
     */
    private function sungTogether(): array
    {
        $owner = $this->owner();
        // An adult: featuring, like the gallery, is 18 and over.
        $owner->forceFill(['name' => 'Ana Ruiz', 'adult_confirmed_at' => now()])->save();
        [$project, $part] = $this->recordedProject();
        $url = (string) $this->postJson('/api/projects/'.$project.'/invites', ['partKey' => 'tenor', 'partName' => 'Tenor'])->assertCreated()->json('data.url');

        $sam = User::factory()->create(['name' => 'Sam Lee'])->fresh() ?? throw new \LogicException('No user.');
        $this->actingAs($sam);
        $this->postJson('/api/join/'.Str::afterLast($url, '/join/'))->assertOk();
        $tenor = (string) Str::uuid();
        $this->savePart($tenor, $project, 0, ['name' => 'Tenor', 'templatePartKey' => 'tenor'])->assertOk();
        $take = (string) Str::uuid();
        $wav = Wav::tone(1.0);
        $this->startTake($take, $tenor, $wav)->assertCreated();
        $row = Take::query()->where('uuid', $take)->firstOrFail();
        Storage::disk('private')->put($row->upload_key, $wav);
        $this->postJson('/api/takes/'.$take.'/complete')->assertOk();
        app()->call([new ProcessTake($row->id), 'handle']);
        $this->savePart($tenor, $project, 1, ['name' => 'Tenor', 'templatePartKey' => 'tenor', 'currentTakeId' => $take])->assertOk();

        $this->actingAs($owner->fresh() ?? $owner);

        return [$owner, $sam, $project, $part];
    }

    public function test_a_friend_is_credited_and_featuring_waits_for_them(): void
    {
        [, $sam, $project, $part] = $this->sungTogether();
        $both = $this->shareHarmony($project);
        $mine = $this->shareHarmony($project, null, ['partId' => $part]);

        $this->assertSame([$sam->id], $both->singer_ids);
        $this->assertSame([], $mine->singer_ids);
        $this->assertSame('Sung by Ana Ruiz and Sam Lee', app(HarmonyFacts::class)->singersLine($both));

        foreach (['mayFeature', 'inGallery'] as $yes) {
            $refused = $this->patchJson('/api/harmonies/'.$both->public_id, [$yes => true]);
            $this->assertMatchesContract($refused, 'harmonies.update', 409);
            $refused->assertJsonPath('code', 'singers_must_agree');
            $this->patchJson('/api/harmonies/'.$mine->public_id, [$yes => true])->assertOk();
        }

        $this->patchJson('/api/harmonies/'.$both->public_id, ['mayFeature' => false, 'title' => 'Together'])->assertOk();
        $this->assertNull($both->refresh()->may_feature_at);
    }

    public function test_it_comes_down_when_the_friends_voice_leaves(): void
    {
        [, $sam, $project, $part] = $this->sungTogether();
        $both = $this->shareHarmony($project);
        $mine = $this->shareHarmony($project, null, ['partId' => $part]);

        // Leaving with their parts kept (as the owner's): the harmony stays.
        $this->deleteJson('/api/projects/'.$project.'/members/'.$sam->sqid().'?keepParts=1')->assertNoContent();
        $this->assertNull($both->refresh()->deleted_at);

        // Shared again with a friend who then deletes their account: it comes down, mine stays.
        $again = Harmony::factory()->create(['user_id' => $both->user_id, 'project_id' => $both->project_id, 'singer_ids' => [$sam->id]]);
        app(DeleteAccount::class)->handle($sam->fresh() ?? $sam, '203.0.113.9');
        $this->assertNotNull($again->refresh()->deleted_at);
        $this->assertNotNull($both->refresh()->deleted_at);
        $this->assertNull($mine->refresh()->deleted_at);
    }

    public function test_it_comes_down_when_the_friend_leaves_taking_their_parts(): void
    {
        [, $sam, $project] = $this->sungTogether();
        $both = $this->shareHarmony($project);

        $this->actingAs($sam);
        $this->deleteJson('/api/projects/'.$project.'/members/'.$sam->sqid().'?keepParts=0')->assertNoContent();
        $this->assertNotNull($both->refresh()->deleted_at);
    }
}
