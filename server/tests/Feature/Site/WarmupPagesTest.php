<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Http\Hosts;
use App\Support\Pages\Warmups;
use App\Support\Site\SitemapPaths;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * The free warm-ups (docs/seo-growth-plan.md item 8): /warmups lists every warm-up in the courses lead's list
 * (course/warmups.json) that can play, and each page has its H1, how to sing it, its questions, the player with
 * its warm-up from the course (no request) and one way into the app tagged ?ref=warmups. Only
 * on the public site.
 */
final class WarmupPagesTest extends TestCase
{
    use RefreshDatabase;

    private const APP = 'https://app.stackapella.test';

    private const SITE = 'https://site.stackapella.test';

    private function split(): void
    {
        config(['app.url' => self::APP, 'hosts.public_url' => self::SITE, 'app.is_nexus' => true, 'app.coming_soon' => false]);
    }

    public function test_every_warm_up_in_the_list_has_a_page(): void
    {
        $list = json_decode((string) file_get_contents(dirname(base_path()).'/web/course/warmups.json'), true);
        $ids = array_column((array) $list['warmups'], 'id');

        $this->assertNotSame([], $ids);
        $this->assertSame($ids, Warmups::ids(), 'every warm-up in course/warmups.json has its words and its course step');
    }

    public function test_the_list_links_every_warm_up(): void
    {
        $this->split();
        $page = $this->get(self::SITE.'/warmups')->assertOk()->assertSee(__('site.warmups.index.headline'));
        $this->assertSame((string) __('site.warmups.index.title').' | '.__('meta.brand'), SitePages::title((string) __('site.warmups.index.title')));

        foreach (Warmups::ids() as $id) {
            $page->assertSee('href="/warmups/'.$id.'"', false)->assertSee(__('site.warmups.pages.'.$id.'.name'));
        }
    }

    public function test_each_warm_up_has_its_headline_how_questions_player_and_way_into_the_app(): void
    {
        $this->split();

        foreach (Warmups::ids() as $id) {
            $words = (array) Lang::get('site.warmups.pages.'.$id);
            $this->assertSame((string) $words['title'].' | '.__('meta.brand'), SitePages::title((string) $words['title']), "{$id}'s title is too long to carry the name in search");
            $this->assertLessThanOrEqual(155, mb_strlen((string) $words['description']), "{$id}'s description");
            $html = (string) $this->get(self::SITE.'/warmups/'.$id)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), "{$id} has one h1");
            $this->assertStringContainsString('>'.e((string) $words['headline']).'</h1>', $html);
            foreach ([...(array) $words['how'], ...array_merge(...array_map(static fn (array $qa): array => $qa, (array) $words['faq']))] as $line) {
                $this->assertStringContainsString(e((string) $line), $html, $id);
            }
            $to = (string) __('site.warmups.index.try_to');
            $try = Hosts::app($to.(str_contains($to, '?') ? '&' : '?').'ref=warmups');
            $this->assertStringContainsString('href="'.e($try).'"', $html, "{$id}'s way into the app carries its ref");
            $this->assertStringContainsString('href="/?ref=warmups"', $html, "{$id}'s logo is counted as the warm-ups'");
            $this->assertStringContainsString('data-mount="mountWarmupPlayer"', $html);
            $this->assertStringContainsString('<link rel="canonical" href="'.e(self::SITE.'/warmups/'.$id).'">', $html, "{$id}'s canonical is its sitemap address");

            // The player's warm-up is the list's entry and its course step, in the page.
            preg_match('/<script type="application\/json" data-tool-data>(.*?)<\/script>/s', $html, $data);
            $warmup = json_decode(html_entity_decode($data[1] ?? ''), true);
            $this->assertSame($id, $warmup['warmup']['entry']['id'] ?? null);
            $this->assertSame(Warmups::find($id), $warmup['warmup'] ?? null);
        }
    }

    public function test_an_unknown_warm_up_is_a_404(): void
    {
        $this->split();

        $this->get(self::SITE.'/warmups/nonsense')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex');
    }

    public function test_the_sitemap_lists_the_warm_ups(): void
    {
        $paths = app(SitemapPaths::class)->all();

        $this->assertContains('/warmups', $paths);
        foreach (Warmups::ids() as $id) {
            $this->assertContains('/warmups/'.$id, $paths);
        }
    }

    public function test_the_app_host_sends_the_warm_ups_to_the_site(): void
    {
        $this->split();

        $this->get(self::APP.'/warmups')->assertRedirect(self::SITE.'/warmups');
        $this->get(self::APP.'/warmups/lip-trills')->assertRedirect(self::SITE.'/warmups/lip-trills');
    }

    public function test_the_players_words_are_exactly_the_ones_its_module_shows(): void
    {
        $file = (string) file_get_contents(dirname(base_path()).'/web/js/public/tools/warmup-player.js');
        preg_match('/export const WORDS = \[(.*?)\];/s', $file, $match);
        preg_match_all("/'(\w+)'/", $match[1] ?? '', $keys);
        $wanted = $keys[1];
        $card = array_keys((array) Lang::get('site.warmups.card'));
        sort($wanted);
        sort($card);

        $this->assertSame($wanted, $card);
    }
}
