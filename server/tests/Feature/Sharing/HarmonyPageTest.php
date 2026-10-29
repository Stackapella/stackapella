<?php

declare(strict_types=1);

namespace Tests\Feature\Sharing;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\Harmony;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * /h/<id>: the shared page (contract/api.md, "Public pages"), rendered with the frontend's landed view.
 */
final class HarmonyPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $with
     */
    private function live(array $with = []): Harmony
    {
        $harmony = Harmony::factory()->create(['project_id' => null, ...$with]);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        app(HarmonyFiles::class)->sync($harmony);

        return $harmony->refresh();
    }

    private function assertNoCookiesAndNoIndex(TestResponse $response): void
    {
        $this->assertSame([], $response->headers->getCookies());
        $response->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=300', (string) $response->headers->get('Cache-Control'));
    }

    public function test_a_link_preview_crawler_gets_the_full_card_and_search_engines_are_told_noindex(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $version = TemplateVersion::query()->whereHas('template', fn ($query) => $query->where('slug', 'silent-night'))->firstOrFail();
        $harmony = $this->live(['user_id' => User::factory()->create(['name' => 'Ana Ruiz'])->id, 'template_version_id' => $version->id]);

        foreach (['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', 'Twitterbot/1.0', 'WhatsApp/2.23.20.0', 'LinkedInBot/1.0'] as $agent) {
            $page = $this->withHeaders(['User-Agent' => $agent])->get('/h/'.$harmony->public_id);
            $page->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
            $html = (string) $page->getContent();
            foreach (['og:title', 'og:description', 'og:image', 'og:image:width'] as $tag) {
                $this->assertStringContainsString('property="'.$tag.'"', $html, "{$agent}: {$tag}");
            }
            $this->assertStringContainsString('Ana Ruiz', $html);
        }
    }

    public function test_a_live_harmony_plays_from_the_cdn_with_its_card_credits_and_links(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $version = TemplateVersion::query()->whereHas('template', fn ($query) => $query->where('slug', 'silent-night'))->firstOrFail();
        $owner = User::factory()->create(['name' => 'Ana Ruiz']);
        $harmony = $this->live(['user_id' => $owner->id, 'template_version_id' => $version->id]);
        $sessions = DB::table('sessions')->count();

        $page = $this->get('/h/'.$harmony->public_id);

        $page->assertOk()->assertViewIs('public.harmony');
        $this->assertNoCookiesAndNoIndex($page);
        $this->assertSame($sessions, DB::table('sessions')->count());

        $page->assertViewHas('harmony', fn (array $view): bool => $view['id'] === $harmony->public_id
            && $view['title'] === 'Silent Night for Mum'
            && $view['singers'] === [['name' => 'Ana Ruiz', 'avatarUrl' => null]]
            && $view['song']['slug'] === 'silent-night'
            && $view['song']['arrangedBy'] === 'Stackapella'
            && $view['audioUrl'] === Storage::disk('media')->url((string) $harmony->media_audio_key)
            && $view['cardUrl'] === Storage::disk('media')->url((string) $harmony->media_card_key)
            && $view['colors'] === ['#e4a94b', '#6fa8dc']
            && count($view['peaks']) === 600
            // One waveform per part, in order: 120 points each, from its bytes (180 of 255).
            && array_column($view['parts'], 'name') === ['Soprano', 'Alto'] && array_column($view['parts'], 'index') === [1, 2]
            && count($view['parts'][0]['peaks']) === 120 && $view['parts'][0]['peaks'][0] === round(180 / 255, 3)
            && $view['part'] === null);
        $page->assertViewHas('meta', fn (array $meta): bool => $meta['imageWidth'] === 1200 && str_contains($meta['description'], 'Ana Ruiz') && $meta['url'] === route('harmony.page', ['harmony' => $harmony->public_id]));
        $page->assertViewHas('links', ['learn' => '/?song=silent-night&ref=h-'.$harmony->public_id, 'app' => '/?ref=h-'.$harmony->public_id, 'api' => null]);
        $page->assertViewHas('report', ['harmonyId' => $harmony->public_id]);
        $page->assertSee('<meta name="robots" content="noindex">', false);
        $page->assertSee('og:image', false);
        // Drawn as a row per part: its initial in its colour beside it, the waveform two rows tall.
        $page->assertSee(__('pages.harmony_page.together'))
            ->assertSee('data-part-labels', false)
            ->assertSee('style="--c: var(--color-part-2); --ct: var(--color-part-text-2)">A</span>', false)
            ->assertSee('style="height: 60px"', false);
    }

    public function test_a_blank_or_held_title_shows_as_untitled_and_a_harmony_without_a_song_has_no_learn_link(): void
    {
        $held = $this->live(['title' => 'free money', 'title_held_at' => now()]);
        $blank = $this->live(['title' => '']);

        $this->get('/h/'.$held->public_id)->assertOk()->assertViewHas('harmony', fn (array $view): bool => $view['title'] === __('sharing.untitled') && $view['song'] === null);
        $this->get('/h/'.$blank->public_id)->assertOk()->assertViewHas('links', fn (array $links): bool => $links['learn'] === null);
    }

    public function test_anything_that_isnt_live_is_not_there_and_a_removed_one_is_gone_for_good(): void
    {
        $private = $this->live(['visibility' => Harmony::PRIVATE]);
        $processing = Harmony::factory()->uploading()->create(['project_id' => null]);
        $underReview = $this->live();
        $underReview->forceFill(['hidden_at' => now(), 'hidden_reason' => Harmony::HIDDEN_REPORTS])->save();
        $deleted = $this->live(['deleted_at' => now()]);
        $suspended = $this->live(['user_id' => User::factory()->suspended()->create()->id]);
        $removed = $this->live(['removed_at' => now(), 'hidden_at' => now(), 'hidden_reason' => Harmony::HIDDEN_DMCA]);

        foreach ([$private, $processing, $underReview, $deleted, $suspended] as $harmony) {
            $gone = $this->get('/h/'.$harmony->public_id);
            $gone->assertNotFound()->assertViewIs('public.harmony-gone')->assertViewHas('reason', 'missing');
            $this->assertNoCookiesAndNoIndex($gone);
        }

        $this->get('/h/'.$removed->public_id)->assertStatus(410)->assertViewHas('reason', 'removed');
        $this->get('/h/nothing-here')->assertNotFound()->assertViewHas('links', ['app' => '/']);
    }

    public function test_the_credits_say_played_for_instruments_and_sung_and_played_for_both(): void
    {
        $peaks = base64_encode(str_repeat(chr(100), 60));
        $played = $this->live(['tracks' => [['name' => 'Guitar', 'color' => '#e4a94b', 'peaks' => $peaks, 'kind' => 'instrument']]]);
        $page = $this->get('/h/'.$played->public_id)->assertOk();
        $page->assertViewHas('harmony', fn (array $view): bool => str_starts_with($view['creditLine'], 'Played by ') && $view['parts'][0]['kind'] === 'instrument');
        $page->assertViewHas('meta', fn (array $meta): bool => str_contains($meta['imageAlt'], 'played by') && str_contains($meta['description'], ' played '));

        $both = $this->live(['tracks' => [['name' => 'Alto', 'color' => '#e4a94b', 'peaks' => $peaks], ['name' => 'Guitar', 'color' => '#6fa8dc', 'peaks' => $peaks, 'kind' => 'instrument']]]);
        $this->get('/h/'.$both->public_id)->assertViewHas('harmony', fn (array $view): bool => str_starts_with($view['creditLine'], 'Sung and played by '));

        // A share made before instruments: all voices.
        $this->get('/h/'.$this->live()->public_id)->assertViewHas('harmony', fn (array $view): bool => str_starts_with($view['creditLine'], 'Sung by '));
    }
}
