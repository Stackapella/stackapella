<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Support\Site\ArcadeHandoff;
use App\Support\Site\SitemapPaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The home in game mode, the arcade (/arcade; Hillel, 2026-11-24: "on the main home page when you enable game mode switch
 * to the view we used to show with the in-app game experience"): the 2026-11-16 home's cabinet and sections, out of
 * search with the home as its canonical, handed over by ArcadeHandoff on a stored choice of game mode only. The home itself
 * is never redirected by the server, so a crawler and a visitor without a script always have the home.
 */
final class ArcadeHomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_the_cabinet_home_out_of_search_with_the_home_as_its_canonical(): void
    {
        app(SyncOfficialTemplates::class)->handle();

        $page = $this->get('/arcade')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $html = (string) $page->getContent();
        $this->assertStringContainsString('<meta name="robots" content="noindex">', $html);
        $this->assertMatchesRegularExpression('#<link rel="canonical" href="[^"]*/">#', $html);
        $this->assertStringContainsString('data-cabinet', $html);
        $this->assertStringContainsString('<script type="module" src="/js/public/cabinet.js"></script>', $html);
        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="/css/arcade\.css(\?v=[0-9a-f]+)?">#', $html);
        // No AI pitch, as on the home: its plans leave the songs sung by AI out.
        $this->assertDoesNotMatchRegularExpression('/\bAI\b/', strip_tags((string) preg_replace('#<script\b.*?</script>#s', '', $html)));
        $this->assertStringNotContainsString(e(__('plus.items.choir.0')), $html);
        $this->assertStringNotContainsString(e(__('pages.landing.plans_free_choir')), $html);
        $this->assertNotContains('/arcade', app(SitemapPaths::class)->all());

        $es = (string) $this->get('/es/arcade')->assertOk()->getContent();
        $this->assertStringContainsString('<html lang="es"', $es);
        $this->assertMatchesRegularExpression('#<link rel="canonical" href="[^"]*/es">#', $es);
    }

    public function test_the_home_and_the_arcade_hand_over_in_the_browser_only_and_the_csp_allows_it(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $tag = (string) ArcadeHandoff::tag();

        $home = $this->get('/')->assertOk();
        $this->assertStringContainsString($tag, (string) $home->getContent());
        $this->assertStringContainsString('id="lnf"', (string) $home->getContent(), 'the home is the home, for a crawler too');
        $arcade = $this->get('/arcade')->assertOk();
        $this->assertStringContainsString($tag, (string) $arcade->getContent());
        foreach ([$home, $arcade] as $response) {
            $this->assertStringContainsString(ArcadeHandoff::hash(), (string) $response->headers->get('Content-Security-Policy'));
        }
        // Only a stored choice of game mode hands over (the default game look is not a choice, 2026-11-18).
        $this->assertStringContainsString("chosen().skin === 'game'", ArcadeHandoff::SCRIPT);
        $this->assertStringContainsString('location.replace', ArcadeHandoff::SCRIPT);
    }
}
