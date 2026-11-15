<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Pages\LandingNextChatgpt;
use App\Support\Site\SitemapPaths;
use App\Support\Site\SiteVisit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /next-chatgpt, a candidate landing page under test beside /: it plays the landing's song through the song page's
 * player, and it stays apart from the real site: never indexed, in no sitemap, with no canonical and no other-language
 * links, and counted under its own kind, which no growth total reads.
 */
final class LandingNextChatgptTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_stage_carries_the_players_hooks_and_every_note_of_the_song(): void
    {
        $template = $this->amazingGrace();
        $notes = 0;
        foreach ((array) $template->currentVersion->data['parts'] as $part) {
            $notes += count($part['guide']['notes']);
        }

        $page = $this->get('/next-chatgpt')->assertOk();
        $html = (string) $page->getContent();

        $this->assertGreaterThan(0, $notes);
        $this->assertSame($notes, substr_count($html, 'class="lnc-gem"'));
        $this->assertSame(1, substr_count($html, 'data-player'));
        $this->assertSame(1, substr_count($html, 'data-play '));
        $this->assertSame(1, preg_match('#<script type="application/json" data-song>(.*?)</script>#s', $html, $match));
        $song = json_decode($match[1], true);
        $this->assertSame('amazing-grace', $song['song']);
        $this->assertSame(count((array) $template->currentVersion->data['parts']), substr_count($html, 'data-hear-alone'));
        $this->assertSame(count((array) $template->currentVersion->data['parts']), substr_count($html, 'data-pick'));
        // The first frame is in the page: the headline, and Play named, before any script.
        $page->assertSee(__('next_chatgpt.headline'))->assertSee(__('next_chatgpt.stage.play', ['song' => 'Amazing Grace']));
        // Each part's Learn link is the song page's, carrying this page as its source.
        $page->assertSee('part=alto', false)->assertSee('ref='.LandingNextChatgpt::REF, false);
    }

    public function test_it_is_never_indexed_and_names_no_other_address(): void
    {
        $this->amazingGrace();
        config(['ops.indexing' => true]);

        foreach (['/next-chatgpt', '/es/next-chatgpt'] as $path) {
            $page = $this->get($path)->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
            $html = (string) $page->getContent();

            $this->assertStringContainsString('<meta name="robots" content="noindex">', $html, $path);
            $this->assertStringNotContainsString('rel="canonical"', $html, $path);
            $this->assertStringNotContainsString('rel="alternate"', $html, $path);
            $this->assertStringNotContainsString('hreflang', $html, $path);
        }
    }

    public function test_it_is_in_no_sitemap_and_in_no_languages_list_of_pages(): void
    {
        $this->amazingGrace();

        $paths = app(SitemapPaths::class)->all();

        $this->assertNotContains('/next-chatgpt', $paths);
        $this->assertSame([], array_values(array_filter($paths, static fn (string $path): bool => str_contains($path, 'next-chatgpt'))));
        foreach ((array) config('languages.site') as $names) {
            $this->assertNotContains('next-chatgpt.page', (array) $names);
        }
    }

    public function test_its_visits_count_under_their_own_kind_which_no_growth_total_reads(): void
    {
        $this->amazingGrace();
        config(['counts.enabled' => true]);

        $this->assertContains(LandingNextChatgpt::KIND, (array) config('counts.kinds'));
        $this->assertNotContains(substr(LandingNextChatgpt::KIND, 3), SiteVisit::SECTIONS);

        $html = (string) $this->get('/next-chatgpt')->assertOk()->getContent();
        $this->assertStringContainsString('data-lnc-visit>{"kind":"'.LandingNextChatgpt::KIND.'"}', $html);
        // Never the site's own count: the page has no site footer, so no section's kind goes with it.
        $this->assertStringNotContainsString('data-site-visit', $html);

        config(['counts.enabled' => false]);
        $this->assertStringNotContainsString('data-lnc-visit', (string) $this->get('/next-chatgpt')->getContent());
    }

    public function test_the_spanish_page_reads_in_spanish_and_keeps_its_links_in_spanish(): void
    {
        $this->amazingGrace();

        $page = $this->get('/es/next-chatgpt')->assertOk()->assertHeader('Content-Language', 'es');

        $page->assertSee('hecha por ti.')->assertSee('href="/es/songs"', false)->assertSee('href="/es/next-chatgpt"', false)->assertSee('<html lang="es"', false);
        // The request's language doesn't stay on the worker for the next page.
        $this->assertSame('en', app()->getLocale());
        $this->get('/next-chatgpt')->assertOk()->assertSee('made by you.')->assertSee('href="/songs"', false);
        $this->get('/de/next-chatgpt')->assertNotFound();
    }

    public function test_it_reads_without_the_song(): void
    {
        $page = $this->get('/next-chatgpt')->assertOk();

        $page->assertSee(__('next_chatgpt.headline'))->assertSee(__('next_chatgpt.close.title'));
        $this->assertStringNotContainsString('data-player', (string) $page->getContent());
        $this->assertStringNotContainsString('/js/public/mixer.js', (string) $page->getContent());
    }

    private function amazingGrace(): Template
    {
        $template = Template::factory()->create(['slug' => 'amazing-grace', 'title' => 'Amazing Grace', 'access' => TemplateAccess::Free]);
        $version = TemplateVersion::factory()->create(['template_id' => $template->id]);
        $template->forceFill(['current_version_id' => $version->id])->save();

        return $template->fresh(['currentVersion']);
    }
}
