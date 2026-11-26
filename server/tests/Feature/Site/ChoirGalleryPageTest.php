<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\OwnChoirStatus;
use App\Models\ChoirGalleryItem;
use App\Models\Harmony;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\User;
use App\Support\Flags;
use App\Support\OwnChoir\ChoirGallery;
use App\Support\Site\SitemapPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The choir gallery's page (/ai-harmony-singing/gallery, /es/armonias-con-ia/galeria; docs/choir-gallery-plan.md, 2): the
 * approved choirs as "A singer", never a name, each with its song (to its share page), parts, the AI label, its player and
 * Report; empty, the first choirs are on their way and make your own, and it's noindex and out of the sitemap until a
 * choir is in it (its items never are); See the gallery
 * on the AI landing; a choir's share page in the gallery's words once it's held or gone.
 */
final class ChoirGalleryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_while_the_gallery_is_off_it_is_not_found(): void
    {
        $this->get('/ai-harmony-singing/gallery')->assertNotFound();
        $this->get('/es/armonias-con-ia/galeria')->assertNotFound();
        $this->assertNotContains('/ai-harmony-singing/gallery', app(SitemapPaths::class)->all());
    }

    public function test_empty_it_says_the_first_choirs_are_on_their_way_and_how_to_make_your_own(): void
    {
        $this->on();

        $html = (string) $this->get('/ai-harmony-singing/gallery')->assertOk()->getContent();
        $this->assertStringContainsString(e(__('site.choir_gallery.empty')), $html);
        $this->assertStringContainsString('href="/ai-harmony-singing" class="btn-primary btn-lg s-cg-go">'.e(__('site.choir_gallery.make')), $html);
        $this->assertStringNotContainsString('data-report', $html);
        $this->assertMatchesRegularExpression('#<link rel="canonical" href="[^"]*/ai-harmony-singing/gallery">#', $html);
        $this->get('/es/armonias-con-ia/galeria')->assertOk()->assertSee('<html lang="es"', false);
    }

    public function test_empty_it_is_noindex_and_out_of_the_sitemap_and_with_a_choir_it_is_indexed_and_listed_again(): void
    {
        $this->on();
        // Indexing on, and Spanish open to everyone: otherwise every page is noindex whatever the gallery holds.
        config(['ops.indexing' => true]);
        app(Flags::class)->set('lang_es', 'on', null, 'test');

        foreach (['/ai-harmony-singing/gallery', '/es/armonias-con-ia/galeria'] as $path) {
            $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        }
        $this->assertNotContains('/ai-harmony-singing/gallery', app(SitemapPaths::class)->all());

        [$item] = $this->approved();
        foreach (['/ai-harmony-singing/gallery', '/es/armonias-con-ia/galeria'] as $path) {
            $this->get($path)->assertOk()->assertHeaderMissing('X-Robots-Tag');
        }
        $this->assertContains('/ai-harmony-singing/gallery', app(SitemapPaths::class)->all());

        // Held again, the gallery is empty again: out by itself.
        $item->forceFill(['status' => ChoirGalleryItem::HELD])->save();
        $this->get('/ai-harmony-singing/gallery')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertNotContains('/ai-harmony-singing/gallery', app(SitemapPaths::class)->all());
    }

    public function test_an_approved_choir_shows_as_a_singer_with_its_song_parts_label_player_and_report(): void
    {
        $this->on();
        [$item, $share] = $this->approved();

        $html = (string) $this->get('/ai-harmony-singing/gallery')->assertOk()->getContent();
        $this->assertStringContainsString(e(__('site.choir_gallery.singer')), $html);
        $this->assertStringNotContainsString('Ada Lovelace', $html);
        $this->assertStringContainsString(e(__('site.choir_gallery.label')), $html);
        $this->assertStringContainsString('<a href="/h/'.$share->public_id.'">', $html);
        $this->assertStringContainsString('<li>Alto</li>', $html);
        // Play named for its song, its progress a slider with the song's length, and the way to make your own as the last tile.
        $this->assertStringContainsString('aria-label="'.e(__('site.choir_gallery.play', ['song' => $share->templateVersion?->template?->title ?? ''])).'"', $html);
        $this->assertMatchesRegularExpression('#role="slider" tabindex="0" aria-label="[^"]+" aria-valuemin="0" aria-valuemax="\d+"#', $html);
        $this->assertStringContainsString('<li class="s-cg-tile">', $html);
        $this->assertStringContainsString('data-gallery="'.$item->sqid().'"', $html);
        $this->assertStringContainsString('value="my_voice"', $html);
        // "It isn't the singer's own voice" is the gallery's own reason, not_own (contract/api.md), and is chosen first.
        $this->assertMatchesRegularExpression('#name="reason" value="not_own" class="radio" checked#', $html);
        $this->assertStringNotContainsString('value="not_theirs"', $html);
        $this->assertStringContainsString('<script type="module" src="/js/public/choir-gallery.js"></script>', $html);
        // No counts: a showcase, not a contest.
        $this->assertStringNotContainsString('plays', strtolower(strip_tags($html)));
        // See the gallery from the AI landing.
        app(Flags::class)->set('own_choir', 'on', null, 'test');
        $this->assertStringContainsString('href="/ai-harmony-singing/gallery" class="yc-lk" data-yc-gallery>'.e(__('site.choir_gallery.see')), (string) $this->get('/ai-harmony-singing')->getContent());
    }

    public function test_a_held_choir_is_not_available_and_a_gone_one_isnt_in_the_gallery_any_more(): void
    {
        $this->on();
        [$item, $share] = $this->approved();

        $item->forceFill(['status' => ChoirGalleryItem::HELD])->save();
        $this->get('/h/'.$share->public_id)->assertNotFound()->assertSee(e(__('site.choir_gallery.held')), false);
        $this->assertStringNotContainsString('data-gallery-item', (string) $this->get('/ai-harmony-singing/gallery')->getContent());

        $item->forceFill(['status' => ChoirGalleryItem::APPROVED])->save();
        $share->forceFill(['removed_at' => now()])->save();
        $this->get('/h/'.$share->public_id)->assertStatus(410)->assertSee(e(__('site.choir_gallery.removed')), false);
    }

    private function on(): void
    {
        app(Flags::class)->set('choir_gallery', 'on', null, 'test');
    }

    /**
     * An approved choir in the gallery, as the backend's own test makes one.
     *
     * @return array{0: ChoirGalleryItem, 1: Harmony}
     */
    private function approved(): array
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $user->forceFill(['ai_features' => true])->save();
        $song = OwnChoirRequest::factory()->create(['user_id' => $user->id, 'status' => OwnChoirStatus::Ready, 'ready_at' => now(), 'own' => false]);
        foreach (['soprano', 'alto', 'tenor', 'bass'] as $i => $part) {
            OwnChoirPart::factory()->create(['own_choir_request_id' => $song->id, 'part_key' => $part, 'sha256' => str_repeat((string) $i, 64)]);
        }
        $tracks = array_map(static fn (string $name): array => ['name' => $name, 'color' => '#336699', 'peaks' => ''], ['Soprano', 'Alto', 'Tenor', 'Bass']);
        $share = Harmony::factory()->create(['user_id' => $user->id, 'template_version_id' => $song->template_version_id, 'own_voice_parts' => ['Soprano', 'Alto', 'Tenor', 'Bass'], 'tracks' => $tracks, 'media_audio_key' => 'harmonies/gallery-test.m4a']);
        $item = ChoirGalleryItem::factory()->create(['own_choir_request_id' => $song->id, 'harmony_id' => $share->id, 'parts_sha' => ChoirGallery::partsSha($song->fresh() ?? $song), 'status' => ChoirGalleryItem::APPROVED, 'decided_at' => now()]);

        return [$item, $share];
    }
}
