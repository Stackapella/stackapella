<?php

declare(strict_types=1);

namespace Tests\Feature\Gallery;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Actions\Users\DeleteAccount;
use App\Models\Part;
use App\Models\Take;
use App\Models\TemplateGuide;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Notifications\Admin\OpsAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Sharing;
use Tests\TestCase;

/**
 * Guide voices from public songs (Hillel's call; contract/api.md, "admin.gallery.guides.create"):
 * a featured (or gallery) song's part becomes a voice of its template part, credited to its
 * singer's profile name, and goes when the song stops being public.
 */
final class GuidesFromPublicSongsTest extends TestCase
{
    use RefreshDatabase;
    use Sharing;

    public function test_a_featured_songs_part_becomes_a_credited_guide_and_goes_when_it_stops_being_public(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        Notification::fake();
        $owner = $this->owner();
        $owner->forceFill(['name' => 'Ana Ruiz', 'adult_confirmed_at' => now()])->save();
        [$project, $part] = $this->recordedProject();
        $harmony = $this->shareHarmony($project);
        $this->assertNotNull($harmony->template_version_id);
        // The mix's takes are kept: only these may become guides.
        $this->assertSame([Part::query()->where('uuid', $part)->sole()->current_take_id], $harmony->take_ids);

        // Unlisted only: never a guide.
        $admin = $this->actingAsAdmin();
        $refused = $this->postJson('/api/admin/gallery/harmony/'.$harmony->public_id.'/guides', ['partId' => $part]);
        $this->assertMatchesContract($refused, 'admin.gallery.guides.create', 409);
        $refused->assertJsonPath('code', 'not_public');

        // Featured with its owner's yes: public.
        $harmony->forceFill(['may_feature_at' => now()])->save();
        $this->assertMatchesContract($this->postJson('/api/admin/gallery/harmony/'.$harmony->public_id.'/guides', ['partId' => 'not-a-uuid']), 'admin.gallery.guides.create', 422);
        $this->assertMatchesContract($this->postJson('/api/admin/gallery/harmony/nope/guides', ['partId' => $part]), 'admin.gallery.guides.create', 404);
        $other = Part::factory()->create();
        $this->assertMatchesContract($this->postJson('/api/admin/gallery/harmony/'.$harmony->public_id.'/guides', ['partId' => $other->uuid]), 'admin.gallery.guides.create', 404);
        $queued = $this->postJson('/api/admin/gallery/harmony/'.$harmony->public_id.'/guides', ['partId' => $part, 'makeDefault' => true]);
        $this->assertMatchesContract($queued, 'admin.gallery.guides.create', 202);

        $guide = TemplateGuide::query()->where('harmony_id', $harmony->id)->sole();
        $this->assertSame($owner->id, $guide->credit_user_id);
        $this->assertTrue($guide->is_default);
        Storage::disk('media')->assertExists($guide->media_key);

        // Played to anyone learning the song, credited by name.
        $this->actingAs(User::factory()->create()->fresh() ?? throw new \LogicException);
        $voices = collect((array) $this->getJson('/api/templates/amazing-grace')->assertOk()->json('data.voices'));
        $this->assertSame('Ana Ruiz', $voices->firstWhere('id', $guide->sqid())['credit'] ?? null);

        // Its owner says no to featuring: the voice goes, off the CDN too.
        $this->actingAs($owner);
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['mayFeature' => false])->assertOk();
        $this->assertNull($guide->fresh());
        Storage::disk('media')->assertMissing($guide->media_key);

        // Hillel hears at once: which part, what plays now, why, and where to choose another.
        Notification::assertSentTo($admin, OpsAlert::class, fn (OpsAlert $alert): bool => $alert->subject === __('ops.voice_lost.subject', ['song' => 'Amazing Grace', 'part' => 'Alto'])
            && $alert->lines[1] === __('ops.voice_lost.now_synth') && $alert->lines[2] === __('ops.voice_lost.why.not_public') && str_contains($alert->lines[3], 'https://stackapella.test/admin/guides')
            && ! str_contains(implode(' ', $alert->lines), 'Ana'));
    }

    public function test_removing_a_voice_in_admin_tells_hillel_what_plays_instead(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        Notification::fake();
        $admin = $this->actingAsAdmin();
        $version = TemplateVersion::query()->whereHas('template', fn ($q) => $q->where('slug', 'amazing-grace'))->latest('id')->firstOrFail();
        $first = TemplateGuide::factory()->create(['template_version_id' => $version->id, 'part_key' => 'alto', 'is_default' => true]);
        TemplateGuide::factory()->create(['template_version_id' => $version->id, 'part_key' => 'alto', 'is_default' => false]);

        $this->deleteJson('/api/admin/beta/guides/'.$first->sqid())->assertSuccessful();

        Notification::assertSentTo($admin, OpsAlert::class, fn (OpsAlert $alert): bool => $alert->lines[1] === __('ops.voice_lost.now_other') && $alert->lines[2] === __('ops.voice_lost.why.removed'));
    }

    public function test_a_template_of_someones_own_never_gives_guides(): void
    {
        $owner = $this->owner();
        $owner->forceFill(['adult_confirmed_at' => now()])->save();
        [$project, $part] = $this->recordedProject();
        $harmony = $this->shareHarmony($project);
        $harmony->forceFill(['may_feature_at' => now(), 'template_version_id' => null])->save();

        $this->actingAsAdmin();
        $refused = $this->postJson('/api/admin/gallery/harmony/'.$harmony->public_id.'/guides', ['partId' => $part]);
        $this->assertMatchesContract($refused, 'admin.gallery.guides.create', 409);
        $refused->assertJsonPath('code', 'not_official');
        $this->assertNotNull($owner);

        // A part with no ready take.
        $empty = Part::factory()->create(['project_id' => $harmony->project_id, 'template_part_key' => 'tenor']);
        $harmony->forceFill(['template_version_id' => TemplateVersion::factory()->create()->id])->save();
        $noTake = $this->postJson('/api/admin/gallery/harmony/'.$harmony->public_id.'/guides', ['partId' => $empty->uuid]);
        $this->assertSame(409, $noTake->status());
    }

    public function test_only_a_take_in_the_shared_mix_by_an_adult_becomes_a_guide(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $owner = $this->owner();
        [$project, $part] = $this->recordedProject();
        $harmony = $this->shareHarmony($project);
        $harmony->forceFill(['may_feature_at' => now()])->save();
        $url = '/api/admin/gallery/harmony/'.$harmony->public_id.'/guides';

        // An owner not confirmed 18 or over: nothing of theirs is public.
        $admin = $this->actingAsAdmin();
        $this->postJson($url, ['partId' => $part])->assertStatus(409)->assertJsonPath('code', 'not_public');
        $owner->forceFill(['adult_confirmed_at' => now()])->save();

        // Re-recorded since it was shared: the new take was never public.
        $this->actingAs($owner);
        $this->travel(5)->minutes();
        $since = $this->recordTake($part, seconds: 2.0);
        $this->savePart($part, $project, 2, ['currentTakeId' => $since->uuid])->assertOk();
        $this->actingAsAdmin($admin);
        $refused = $this->postJson($url, ['partId' => $part]);
        $this->assertMatchesContract($refused, 'admin.gallery.guides.create', 409);
        $refused->assertJsonPath('code', 'no_take');
        $this->assertSame(0, TemplateGuide::query()->count());

        // Shared before the mix's takes were kept: a take older than it, by its owner, may.
        $harmony->forceFill(['take_ids' => null])->save();
        $this->postJson($url, ['partId' => $part])->assertStatus(409);
        Take::query()->whereKey($since->id)->update(['created_at' => $harmony->created_at?->copy()->subMinute()]);
        $this->postJson($url, ['partId' => $part])->assertStatus(202);
        $guide = TemplateGuide::query()->sole();

        // Its singer's account goes: so does the voice, off the CDN too.
        $this->actingAs($owner);
        app(DeleteAccount::class)->handle($owner->fresh() ?? throw new \LogicException, null);
        $this->assertNull($guide->fresh());
        Storage::disk('media')->assertMissing($guide->media_key);
    }
}
