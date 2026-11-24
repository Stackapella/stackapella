<?php

declare(strict_types=1);

namespace Tests\Feature\AiVoices;

use App\Actions\OwnChoir\OwnChoirRequests;
use App\Enums\OwnChoirStatus;
use App\Models\ChoirGalleryItem;
use App\Models\Harmony;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\User;
use App\Support\Flags;
use App\Support\OwnChoir\ChoirGallery;
use App\Support\OwnChoir\OwnChoir;
use App\Support\Sharing\HarmonyFacts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Once;
use Tests\TestCase;

/**
 * The choir gallery, version 1 (docs/choir-gallery-plan.md): a singer adds their choir song's own share, staff hear it
 * first, anyone lists it by "A singer"; AI off and a remake hide it, deleting takes it out, a report holds it.
 */
final class ChoirGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 12:00:00', 'UTC'));
        app(Flags::class)->set('choir_gallery', 'on', null, 'test');
    }

    /**
     * A ready choir song of a library song, and its owner's own share of it.
     *
     * @return array{0: User, 1: OwnChoirRequest, 2: Harmony}
     */
    private function song(): array
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $user->forceFill(['ai_features' => true])->save();
        $song = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'status' => OwnChoirStatus::Ready, 'ready_at' => now(), 'own' => false]);

        foreach (['soprano', 'alto', 'tenor', 'bass'] as $i => $part) {
            OwnChoirPart::factory()->create(['own_choir_request_id' => $song->id, 'part_key' => $part, 'sha256' => str_repeat((string) $i, 64)]);
        }

        $tracks = array_map(static fn (string $name): array => ['name' => $name, 'color' => '#336699', 'peaks' => ''], ['Soprano', 'Alto', 'Tenor', 'Bass']);
        $share = Harmony::factory()->create(['user_id' => $user->id, 'template_version_id' => $song->template_version_id, 'own_voice_parts' => ['Soprano', 'Alto', 'Tenor', 'Bass'], 'tracks' => $tracks]);

        return [$user->fresh() ?? $user, $song, $share];
    }

    /**
     * @return array<string, mixed>
     */
    private function sheet(Harmony $share, array $with = []): array
    {
        return ['harmonyId' => $share->public_id, 'consentVersion' => 1, 'wordsHash' => hash('sha256', 'Add my own voice to the gallery'), 'locale' => 'en', 'ownVoice' => true, ...$with];
    }

    private ?User $admin = null;

    /** Staff, the same one each time. */
    private function asStaff(): void
    {
        $this->admin === null ? $this->admin = $this->actingAsAdmin() : $this->actingAs($this->admin);
    }

    private function approve(ChoirGalleryItem $item): void
    {
        $this->asStaff();
        $this->assertMatchesContract($this->postJson('/api/admin/choir-gallery/'.$item->sqid(), ['decision' => 'approve']), 'admin.choirGallery.decide', 200);
    }

    public function test_a_song_goes_in_after_staff_hear_it_and_shows_with_no_name(): void
    {
        [$user, $song, $share] = $this->song();
        $this->actingAs($user);
        $this->assertNull(OwnChoir::request($song->fresh(OwnChoir::SHOWN) ?? $song, null, $user)['galleryCan']);

        $added = $this->postJson('/api/own-choir/requests/'.$song->sqid().'/gallery', $this->sheet($share));
        $this->assertMatchesContract($added, 'ownChoir.gallery.add', 201);
        $added->assertJsonPath('data.status', 'pending')->assertJsonPath('data.harmonyId', $share->public_id);
        $item = ChoirGalleryItem::query()->sole();
        // Nothing shows before staff listen.
        $this->assertMatchesContract($this->getJson('/api/own-choir/gallery'), 'ownChoir.gallery.list', 200);
        $this->assertSame([], $this->getJson('/api/own-choir/gallery')->json('data'));

        $this->asStaff();
        $queue = $this->getJson('/api/admin/choir-gallery');
        $this->assertMatchesContract($queue, 'admin.choirGallery.list', 200);
        $this->assertSame([$item->sqid(), false], [$queue->json('data.0.id'), $queue->json('data.0.changed')]);
        $this->approve($item);

        $list = $this->getJson('/api/own-choir/gallery');
        $this->assertSame([[$item->sqid(), $share->public_id, ['Soprano', 'Alto', 'Tenor', 'Bass']]], collect($list->json('data'))->map(fn (array $i): array => [$i['id'], $i['harmonyId'], $i['parts']])->all());
        $this->assertStringNotContainsString('Ada', (string) $list->getContent());
        $this->assertMatchesContract($this->getJson('/api/own-choir/gallery/'.$item->sqid()), 'ownChoir.gallery.show', 200);
        // Its share's page and preview name "A singer", never the singer.
        $this->assertSame(__('sharing.a_singer'), app(HarmonyFacts::class)->names($share->fresh() ?? $share));
    }

    public function test_who_can_add_and_the_limits(): void
    {
        [$user, $song, $share] = $this->song();
        $this->actingAs($user);
        $url = '/api/own-choir/requests/'.$song->sqid().'/gallery';

        $this->assertMatchesContract($this->postJson($url, $this->sheet($share, ['consentVersion' => 2])), 'ownChoir.gallery.add', 409);
        $this->postJson($url, $this->sheet($share, ['consentVersion' => 2]))->assertJsonPath('code', 'agreement_changed');
        $this->assertMatchesContract($this->postJson($url, $this->sheet($share, ['ownVoice' => false])), 'ownChoir.gallery.add', 422);
        // Someone else's share, or one with a friend's voice: not this song's own.
        $other = Harmony::factory()->create(['template_version_id' => $song->template_version_id, 'own_voice_parts' => ['Alto']]);
        $this->postJson($url, $this->sheet($other))->assertStatus(409)->assertJsonPath('reason', 'share');

        $song->forceFill(['own' => true])->save();
        $this->postJson($url, $this->sheet($share))->assertStatus(409)->assertJsonPath('code', 'not_eligible')->assertJsonPath('reason', 'not_library');
        $song->forceFill(['own' => false])->save();

        $user->forceFill(['ai_features' => false])->save();
        $this->postJson($url, $this->sheet($share))->assertStatus(409)->assertJsonPath('reason', 'ai_off');
        $user->forceFill(['ai_features' => true])->save();

        // Three adds a day.
        ChoirGalleryItem::factory()->count(3)->create(['user_id' => $user->id, 'status' => ChoirGalleryItem::REMOVED]);
        // Each request reads the counts afresh (once() lasts a request; a test's requests share one process).
        Once::flush();
        $this->assertMatchesContract($this->postJson($url, $this->sheet($share)), 'ownChoir.gallery.add', 429);

        app(Flags::class)->set('choir_gallery', 'off', null, 'test');
        $this->assertMatchesContract($this->postJson($url, $this->sheet($share)), 'ownChoir.gallery.add', 404);
        $this->assertSame([], $this->getJson('/api/own-choir/gallery')->json('data'));
    }

    public function test_ai_off_and_a_remake_hide_it_and_removing_takes_it_out(): void
    {
        [$user, $song, $share] = $this->song();
        $this->actingAs($user);
        $this->postJson('/api/own-choir/requests/'.$song->sqid().'/gallery', $this->sheet($share))->assertCreated();
        $item = ChoirGalleryItem::query()->sole();
        $this->approve($item);

        $user->forceFill(['ai_features' => false])->save();
        $this->assertSame([], $this->getJson('/api/own-choir/gallery')->json('data'));
        $this->assertSame('ai_off', OwnChoir::request($song->fresh(OwnChoir::SHOWN) ?? $song, null, $user->fresh())['gallery']['hiddenBecause']);
        $user->forceFill(['ai_features' => true])->save();
        $this->assertCount(1, $this->getJson('/api/own-choir/gallery')->json('data'));

        // A remake: new parts, so the share staff heard isn't the song any more.
        OwnChoirPart::query()->where('own_choir_request_id', $song->id)->where('part_key', 'alto')->update(['sha256' => str_repeat('f', 64)]);
        $this->assertSame([], $this->getJson('/api/own-choir/gallery')->json('data'));
        $this->assertSame('changed', OwnChoir::request($song->fresh(OwnChoir::SHOWN) ?? $song, null, $user->fresh())['gallery']['hiddenBecause']);

        $this->actingAs($user);
        $this->deleteJson('/api/own-choir/requests/'.$song->sqid().'/gallery')->assertNoContent();
        $this->assertMatchesContract($this->deleteJson('/api/own-choir/requests/'.$song->sqid().'/gallery'), 'ownChoir.gallery.remove', 404);
        $this->assertMatchesContract($this->getJson('/api/own-choir/gallery/'.$item->sqid()), 'ownChoir.gallery.show', 410);
    }

    public function test_a_my_voice_report_holds_it_at_once_and_staff_decide(): void
    {
        [$user, $song, $share] = $this->song();
        $this->actingAs($user);
        $this->postJson('/api/own-choir/requests/'.$song->sqid().'/gallery', $this->sheet($share))->assertCreated();
        $item = ChoirGalleryItem::query()->sole();
        $this->approve($item);

        $reporter = User::factory()->create();
        $this->actingAs($reporter);
        // "This is my voice" is a gallery item's reason alone.
        $this->postJson('/api/reports', ['harmonyId' => $share->public_id, 'reason' => 'my_voice'])->assertStatus(422);
        $this->postJson('/api/reports', ['choirGalleryId' => $item->sqid(), 'reason' => 'my_voice'])->assertNoContent();
        $this->assertSame(ChoirGalleryItem::HELD, $item->refresh()->status);
        $this->assertMatchesContract($this->getJson('/api/own-choir/gallery/'.$item->sqid()), 'ownChoir.gallery.show', 404);
        $this->getJson('/api/own-choir/gallery/'.$item->sqid())->assertJsonPath('code', 'held');

        $this->asStaff();
        $held = $this->getJson('/api/admin/choir-gallery?status=held');
        $this->assertSame('my_voice', $held->json('data.0.reports.0.reason'));
        $this->assertMatchesContract($this->postJson('/api/admin/choir-gallery/'.$item->sqid(), ['decision' => 'decline', 'reason' => 'other']), 'admin.choirGallery.decide', 422);
        $this->postJson('/api/admin/choir-gallery/'.$item->sqid(), ['decision' => 'takedown', 'reason' => 'not_own'])->assertOk()->assertJsonPath('data.status', 'removed');
        $this->assertMatchesContract($this->postJson('/api/admin/choir-gallery/nope', ['decision' => 'approve']), 'admin.choirGallery.decide', 404);
        $this->assertMatchesContract($this->getJson('/api/own-choir/gallery/nope'), 'ownChoir.gallery.show', 404);

        // The singer sees the reason on their song, in their own words.
        $this->assertSame(['removed', 'not_own', null], [OwnChoir::request($song->fresh(OwnChoir::SHOWN) ?? $song, null, $user)['gallery']['status'], OwnChoir::request($song->fresh(OwnChoir::SHOWN) ?? $song, null, $user)['gallery']['reason'], OwnChoir::request($song->fresh(OwnChoir::SHOWN) ?? $song, null, $user)['gallery']['reasonText']]);
    }

    public function test_deleting_the_voice_or_the_song_takes_it_out(): void
    {
        [$user, $song, $share] = $this->song();
        $this->actingAs($user);
        $this->postJson('/api/own-choir/requests/'.$song->sqid().'/gallery', $this->sheet($share))->assertCreated();
        $item = ChoirGalleryItem::query()->sole();
        $this->approve($item);

        $song->forceFill(['status' => OwnChoirStatus::Deleted])->save();
        $this->assertSame(ChoirGalleryItem::REMOVED, ChoirGallery::status($item->fresh(['harmony', 'request']) ?? $item));
        $song->forceFill(['status' => OwnChoirStatus::Ready])->save();

        OwnChoirRequests::dropVoices($user);
        $this->assertSame(ChoirGalleryItem::REMOVED, $item->refresh()->status);
        $this->getJson('/api/auth/config')->assertJsonPath('data.choirGallery', ['on' => true, 'consentVersion' => 1]);
    }
}
