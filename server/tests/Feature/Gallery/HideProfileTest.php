<?php

declare(strict_types=1);

namespace Tests\Feature\Gallery;

use App\Jobs\RedrawPersonsCards;
use App\Models\BellItem;
use App\Models\Harmony;
use App\Models\User;
use App\Support\Flags;
use App\Support\Gallery\GalleryViews;
use App\Support\Push\AppPushes;
use App\Support\Sharing\HarmonyFacts;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Hide profile (docs/hide-profile-plan.md; Hillel: "We wouldn't show names, we'd show anonymous"):
 * others see "Anonymous" with no handle, picture or profile page, and it never stops sharing.
 */
final class HideProfileTest extends TestCase
{
    use RefreshDatabase;

    private function singer(string $handle, bool $public = true): User
    {
        return User::factory()->create([
            'handle' => $handle,
            'name' => ucfirst($handle).' Reyes',
            'avatar_key' => 'avatars/'.$handle.'.jpg',
            'adult_confirmed_at' => now(),
            'public_profile_at' => $public ? now() : null,
            'trust_level_override' => 3,
        ])->fresh() ?? throw new \LogicException;
    }

    private function harmony(User $owner): Harmony
    {
        $harmony = Harmony::factory()->create(['project_id' => null, 'user_id' => $owner->id]);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        app(HarmonyFiles::class)->sync($harmony);

        return $harmony->refresh();
    }

    public function test_hiding_turns_the_profile_into_anonymous_everywhere_others_look(): void
    {
        app(Flags::class)->set('gallery', 'on', null, 'Opening the gallery');
        $ana = $this->singer('ana');
        $harmony = $this->harmony($ana);
        $this->actingAs($ana);
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['inGallery' => true, 'rightsConfirmed' => true])->assertOk();

        $shown = $this->getJson('/api/account');
        $this->assertMatchesContract($shown, 'account.show', 200);
        $shown->assertJsonPath('data.hideProfile', false);

        $hidden = $this->patchJson('/api/account', ['hideProfile' => true]);
        $this->assertMatchesContract($hidden, 'account.update', 200);
        $hidden->assertJsonPath('data.hideProfile', true)->assertJsonPath('data.publicProfile', true);

        // No profile page, and the song stays in the gallery as Anonymous.
        $this->assertMatchesContract($this->getJson('/api/profiles/ana'), 'profiles.show', 404);
        $this->get('/@ana')->assertNotFound();
        $explore = $this->getJson('/api/explore');
        $this->assertMatchesContract($explore, 'explore.list', 200);
        $explore->assertJsonPath('data.0.id', $harmony->public_id)->assertJsonPath('data.0.owner', ['handle' => null, 'name' => 'Anonymous', 'avatarUrl' => null]);
        $this->assertStringNotContainsString('Ana Reyes', (string) $explore->getContent());

        // The shared song's page and its link preview name no one.
        $fresh = $ana->fresh() ?? $ana;
        $this->assertSame([['name' => 'Anonymous', 'avatarUrl' => null]], app(HarmonyFacts::class)->singers($harmony->refresh()->setRelation('user', $fresh)));
        $page = $this->get('/h/'.$harmony->public_id);
        $page->assertOk();
        $this->assertStringNotContainsString('Ana Reyes', (string) $page->getContent());

        // Shown again: the profile is back as it was.
        $this->patchJson('/api/account', ['hideProfile' => false])->assertOk();
        $this->getJson('/api/profiles/ana')->assertOk();
        $this->getJson('/api/explore')->assertJsonPath('data.0.owner.handle', 'ana');
    }

    public function test_a_hidden_adult_can_put_songs_in_the_gallery_without_a_public_profile(): void
    {
        app(Flags::class)->set('gallery', 'on', null, 'Opening the gallery');
        $kim = $this->singer('kim', public: false);
        $harmony = $this->harmony($kim);
        $this->actingAs($kim);

        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['inGallery' => true, 'rightsConfirmed' => true])->assertStatus(409);
        $this->patchJson('/api/account', ['hideProfile' => true])->assertOk();
        $this->patchJson('/api/harmonies/'.$harmony->public_id, ['inGallery' => true, 'rightsConfirmed' => true])->assertOk();
        $this->getJson('/api/explore')->assertJsonPath('data.0.owner.name', 'Anonymous');

        // Showing the profile again without a public one takes the song back out of the gallery.
        $this->patchJson('/api/account', ['hideProfile' => false])->assertOk();
        $this->getJson('/api/explore')->assertJsonCount(0, 'data');
    }

    public function test_pushes_name_a_hidden_person_anonymous_and_hiding_redraws_their_cards(): void
    {
        $ana = $this->singer('ana');
        $sam = $this->singer('sam');
        $harmony = $this->harmony($ana);
        $sam->forceFill(['hide_profile' => true])->save();

        $like = BellItem::factory()->create(['user_id' => $ana->id, 'kind' => 'like', 'actor_id' => $sam->id, 'harmony_id' => $harmony->id]);
        $follow = BellItem::factory()->create(['user_id' => $ana->id, 'kind' => 'follow', 'actor_id' => $sam->id, 'harmony_id' => null]);
        $words = app(AppPushes::class)->words($like->fresh() ?? $like, $ana);
        $this->assertNotNull($words);
        $this->assertStringContainsString('Anonymous', $words['title'].' '.$words['body']);
        $this->assertStringNotContainsString('Sam Reyes', $words['title'].' '.$words['body']);
        $this->assertSame('/', app(AppPushes::class)->words($follow->fresh() ?? $follow, $ana)['url'] ?? null);

        Bus::fake([RedrawPersonsCards::class]);
        $this->actingAs($ana)->patchJson('/api/account', ['hideProfile' => true])->assertOk();
        Bus::assertDispatched(RedrawPersonsCards::class, fn (RedrawPersonsCards $job): bool => $job->userId === $ana->id);
    }

    public function test_redrawing_draws_a_new_card_and_drops_the_old_videos(): void
    {
        $ana = $this->singer('ana');
        $harmony = $this->harmony($ana);
        $old = $harmony->card_key;
        $ana->forceFill(['hide_profile' => true])->save();

        (new RedrawPersonsCards($ana->id))->handle(app(HarmonyFiles::class));

        $this->assertNotSame($old, $harmony->refresh()->card_key);
        $this->assertSame(['Anonymous'], array_column(app(HarmonyFacts::class)->singers($harmony->load('user')), 'name'));
    }

    public function test_the_person_others_see(): void
    {
        $ana = $this->singer('ana');
        $this->assertSame('ana', GalleryViews::person($ana)['handle']);

        $ana->forceFill(['hide_profile' => true])->save();
        $this->assertSame(['handle' => null, 'name' => 'Anonymous', 'avatarUrl' => null], GalleryViews::person($ana->fresh()));
        $this->assertSame('Anonymous', GalleryViews::name($ana->fresh() ?? $ana));
    }
}
