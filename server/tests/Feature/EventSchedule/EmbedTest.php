<?php

declare(strict_types=1);

namespace Tests\Feature\EventSchedule;

use App\Models\Harmony;
use App\Models\Report;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A shared harmony played on Event Schedule's event pages: oEmbed and /embed/h/<id>
 * (contract/api.md, "/embed/h/<id>").
 */
final class EmbedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $with
     */
    private function live(array $with = []): Harmony
    {
        $harmony = Harmony::factory()->create(['project_id' => null, 'title' => 'Silent Night for Mum', ...$with]);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        app(HarmonyFiles::class)->sync($harmony);

        return $harmony->refresh();
    }

    public function test_oembed_answers_for_our_live_harmony_links_only(): void
    {
        $harmony = $this->live();
        $link = 'https://stackapella.test/h/'.$harmony->public_id;

        $oembed = $this->get('/oembed?url='.urlencode($link).'&format=json&maxwidth=400');
        $oembed->assertOk()->assertJsonPath('type', 'rich')->assertJsonPath('provider_name', 'Stackapella')->assertJsonPath('title', 'Silent Night for Mum')->assertJsonPath('width', 400);
        $this->assertStringContainsString('src="https://stackapella.test/embed/h/'.$harmony->public_id.'"', (string) $oembed->json('html'));
        $this->assertSame([], $oembed->headers->getCookies());

        $this->get('/oembed?url='.urlencode($link).'&format=xml')->assertStatus(501);
        $this->get('/oembed?url='.urlencode('https://elsewhere.example.com/h/'.$harmony->public_id))->assertNotFound();
        $this->get('/oembed?url='.urlencode('https://stackapella.test/h/nothere123'))->assertNotFound();
        $this->get('/oembed')->assertNotFound();

        $private = $this->live(['visibility' => Harmony::PRIVATE]);
        $this->get('/oembed?url='.urlencode('https://stackapella.test/h/'.$private->public_id))->assertNotFound();

        // The page itself points to it.
        $this->get('/h/'.$harmony->public_id)->assertOk()->assertViewHas('meta', fn (array $meta): bool => $meta['oembed'] === route('oembed', ['url' => $link, 'format' => 'json']));
    }

    public function test_the_embedded_player_may_be_framed_by_any_https_site_and_nothing_else_may(): void
    {
        $harmony = $this->live();

        $embed = $this->get('/embed/h/'.$harmony->public_id);
        $embed->assertOk()->assertSee('Silent Night for Mum')->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertFalse($embed->headers->has('X-Frame-Options'));
        $this->assertStringContainsString("frame-ancestors 'self' https:", (string) $embed->headers->get('Content-Security-Policy'));
        $this->assertSame([], $embed->headers->getCookies());

        $page = $this->get('/h/'.$harmony->public_id);
        $page->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString("frame-ancestors 'self'", (string) $page->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('https:;', (string) $page->headers->get('Content-Security-Policy'));
        // The page's Report form can show Turnstile in shield mode; the embed has no form.
        $this->assertStringContainsString('frame-src https://challenges.cloudflare.com', (string) $page->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-src 'none'", (string) $embed->headers->get('Content-Security-Policy'));
        $this->assertSame(Report::REASONS, array_keys((array) __('sharing.page.reasons')));

        $this->get('/embed/h/nothere123')->assertNotFound();
        $removed = $this->live(['removed_at' => now()]);
        $this->get('/embed/h/'.$removed->public_id)->assertStatus(410);
    }
}
