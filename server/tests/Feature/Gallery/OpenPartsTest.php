<?php

declare(strict_types=1);

namespace Tests\Feature\Gallery;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\BellItem;
use App\Models\Harmony;
use App\Models\HarmonyStem;
use App\Models\User;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\Sharing;
use Tests\Support\Wav;
use Tests\TestCase;

/**
 * Open parts (N5; contract/api.md, "Open parts (N5)"): a public harmony's parts left open, its
 * other parts kept as stems, and fills sung by others that link back.
 */
final class OpenPartsTest extends TestCase
{
    use RefreshDatabase;
    use Sharing;

    private function open(): void
    {
        app(Flags::class)->set('open_parts', 'on', null, 'Opening open parts');
        app(Flags::class)->set('gallery', 'on', null, 'Opening the gallery');
    }

    /**
     * Someone 18 or over, trusted, with a public profile, signed in.
     */
    private function singer(string $handle): User
    {
        $user = User::factory()->create(['handle' => $handle, 'name' => ucfirst($handle), 'adult_confirmed_at' => now(), 'public_profile_at' => now(), 'trust_level_override' => 3])->fresh() ?? throw new \LogicException;
        $this->actingAs($user);

        return $user;
    }

    /**
     * The signed-in person's project from Amazing Grace with the alto and tenor sung, shared and in
     * the gallery.
     */
    private function sharedWithTwoParts(): Harmony
    {
        [$project] = $this->recordedProject();
        $tenor = (string) Str::uuid();
        $this->savePart($tenor, $project, 0, ['templatePartKey' => 'tenor', 'order' => 1])->assertOk();
        $take = $this->recordTake($tenor, seconds: 2.0);
        $this->savePart($tenor, $project, 1, ['templatePartKey' => 'tenor', 'order' => 1, 'currentTakeId' => $take->uuid])->assertOk();
        $harmony = $this->shareHarmony($project);
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['inGallery' => true, 'rightsConfirmed' => true])->assertOk();

        return $harmony->refresh();
    }

    public function test_an_owner_opens_a_part_and_its_other_parts_become_stems(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $ana = $this->singer('ana');
        $harmony = $this->sharedWithTwoParts();

        $closed = $this->putJson('/api/harmonies/'.$harmony->public_id.'/open', ['parts' => ['tenor']]);
        $this->assertMatchesContract($closed, 'harmonies.open', 409);
        $closed->assertJsonPath('code', 'open_parts_closed');
        $this->open();

        $this->assertMatchesContract($this->putJson('/api/harmonies/'.$harmony->public_id.'/open', ['parts' => ['tenor', 'alto']]), 'harmonies.open', 422);
        $this->assertMatchesContract($this->putJson('/api/harmonies/'.$harmony->public_id.'/open', ['parts' => ['descant']]), 'harmonies.open', 422);
        $this->assertMatchesContract($this->putJson('/api/harmonies/abcdefghijkl/open', ['parts' => ['tenor']]), 'harmonies.open', 404);

        // Shared before its mix's takes were kept: nothing to make stems from.
        $takes = $harmony->take_ids;
        $harmony->forceFill(['take_ids' => null])->save();
        $missing = $this->putJson('/api/harmonies/'.$harmony->public_id.'/open', ['parts' => ['tenor']]);
        $this->assertMatchesContract($missing, 'harmonies.open', 409);
        $missing->assertJsonPath('code', 'stems_missing');
        $harmony->forceFill(['take_ids' => $takes])->save();

        $opened = $this->putJson('/api/harmonies/'.$harmony->public_id.'/open', ['parts' => ['tenor'], 'guide' => false]);
        $this->assertMatchesContract($opened, 'harmonies.open', 200);
        $opened->assertJsonPath('data.openParts', [['key' => 'tenor', 'name' => 'Tenor']])->assertJsonPath('data.openPartsGuide', false);
        $stem = HarmonyStem::query()->sole();
        $this->assertSame(['alto', $ana->id], [$stem->part_key, $stem->user_id]);
        Storage::disk('private')->assertExists($stem->file_key);

        // On the Open parts shelf.
        $this->getJson('/api/explore?tab=open')->assertJsonPath('data.0.id', $harmony->public_id)->assertJsonPath('data.0.openParts.0.key', 'tenor');

        // A singer hears the stems once they're copied; someone under 18 doesn't.
        $this->singer('sam');
        $ready = $harmony->refresh()->stems_ready_at;
        $harmony->forceFill(['stems_ready_at' => null])->save();
        $copying = $this->getJson('/api/harmonies/'.$harmony->public_id.'/stems');
        $this->assertMatchesContract($copying, 'harmonies.stems', 409);
        $copying->assertJsonPath('code', 'stems_not_ready');
        $harmony->forceFill(['stems_ready_at' => $ready])->save();
        $stems = $this->getJson('/api/harmonies/'.$harmony->public_id.'/stems');
        $this->assertMatchesContract($stems, 'harmonies.stems', 200);
        $stems->assertJsonPath('data.stems.0.partKey', 'alto')->assertJsonPath('data.template.id', 'amazing-grace')->assertJsonPath('data.openParts.0.key', 'tenor');
        $this->assertNotSame([], $stems->json('data.stems.0.peaks'));
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \LogicException);
        $this->assertMatchesContract($this->getJson('/api/harmonies/'.$harmony->public_id.'/stems'), 'harmonies.stems', 403);

        // Closed again: the stems go.
        $this->actingAs($ana);
        $this->putJson('/api/harmonies/'.$harmony->public_id.'/open', ['parts' => []])->assertOk()->assertJsonPath('data.openParts', []);
        $this->assertSame(0, HarmonyStem::query()->count());
        Storage::disk('private')->assertMissing($stem->file_key);
        $this->actingAs(User::query()->where('handle', 'sam')->sole());
        $this->assertMatchesContract($this->getJson('/api/harmonies/'.$harmony->public_id.'/stems'), 'harmonies.stems', 404);
    }

    public function test_a_fill_is_sung_for_an_open_part_links_back_and_can_be_featured(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $this->open();
        $ana = $this->singer('ana');
        $harmony = $this->sharedWithTwoParts();
        $this->putJson('/api/harmonies/'.$harmony->public_id.'/open', ['parts' => ['tenor']])->assertOk();

        // Sam sings the tenor in his own project from the same song.
        $sam = $this->singer('sam');
        $project = (string) Str::uuid();
        $this->saveProject($project)->assertOk();
        $tenor = (string) Str::uuid();
        $this->savePart($tenor, $project, 0, ['templatePartKey' => 'tenor'])->assertOk();
        $take = $this->recordTake($tenor, seconds: 2.0);
        $this->savePart($tenor, $project, 1, ['templatePartKey' => 'tenor', 'currentTakeId' => $take->uuid])->assertOk();

        $wrong = $this->startHarmony($project, Wav::stereo(2.0), ['parentId' => $harmony->public_id, 'fillPart' => 'alto']);
        $this->assertMatchesContract($wrong, 'harmonies.create', 409);
        $wrong->assertJsonPath('code', 'fill_mismatch');
        $this->startHarmony($project, Wav::stereo(2.0), ['parentId' => $harmony->public_id])->assertStatus(422);
        $fill = $this->shareHarmony($project, null, ['parentId' => $harmony->public_id, 'fillPart' => 'tenor']);
        $this->assertSame([$harmony->id, 'tenor'], [$fill->parent_id, $fill->fill_part_key]);

        // Shared to the gallery: Ana hears of it, once.
        $public = $this->patchJson('/api/harmonies/'.$fill->public_id, ['inGallery' => true, 'rightsConfirmed' => true]);
        $public->assertOk()->assertJsonPath('data.parent.id', $harmony->public_id)->assertJsonPath('data.parent.part', 'tenor');
        $this->patchJson('/api/harmonies/'.$fill->public_id, ['inGallery' => false])->assertOk();
        $this->patchJson('/api/harmonies/'.$fill->public_id, ['inGallery' => true, 'rightsConfirmed' => true])->assertOk();
        $this->assertSame(1, BellItem::query()->where('user_id', $ana->id)->where('kind', 'fill')->count());

        // Ana features it; it's first among the fills.
        $this->actingAs($ana);
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['featuredFillId' => 'abcdefghijkl'])->assertStatus(422)->assertJsonValidationErrors('featuredFillId');
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['featuredFillId' => $fill->public_id])->assertOk()->assertJsonPath('data.featuredFill.id', $fill->public_id);
        $fills = $this->getJson('/api/harmonies/'.$harmony->public_id.'/fills');
        $this->assertMatchesContract($fills, 'harmonies.fills.list', 200);
        $fills->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $fill->public_id);
        $this->assertMatchesContract($this->getJson('/api/harmonies/abcdefghijkl/fills'), 'harmonies.fills.list', 404);

        // The original out of the gallery (no longer public): its fills carry its voices, so they
        // go private too.
        $this->actingAs($ana);
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['inGallery' => false])->assertOk();
        $this->assertSame([Harmony::PRIVATE, null], [$fill->refresh()->visibility, $fill->gallery_opt_in_at]);
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['inGallery' => true, 'rightsConfirmed' => true])->assertOk();

        // The original deleted: its stems go, and the fill stays the singer's own (private).
        $stem = HarmonyStem::query()->sole();
        $this->deleteJson('/api/harmonies/'.$harmony->public_id)->assertNoContent();
        Storage::disk('private')->assertMissing($stem->file_key);
        $this->actingAs($sam);
        $this->getJson('/api/harmonies/'.$fill->public_id)->assertOk()->assertJsonPath('data.parent', null);
    }
}
