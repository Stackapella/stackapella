<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Http\Hosts;
use App\Support\Pages\Reference;
use App\Support\Pages\SongParts;
use App\Support\Site\SitemapPaths;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * The reference pages (docs/seo-growth-plan.md item 4): /intervals, /chords and /voice-types list every page of their
 * family, and each page has its H1, its card with its words and data in the page, its guide, its questions and the
 * family's ?ref= tag on its way into the app. The slug lists match the JavaScript the cards read (just.js, theory.js),
 * so a page can't name an interval, chord or range its card doesn't play. Only on the public site.
 */
final class ReferencePagesTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private const APP = 'https://app.stackapella.test';

    private const SITE = 'https://site.stackapella.test';

    private function split(): void
    {
        config(['app.url' => self::APP, 'hosts.public_url' => self::SITE, 'app.is_nexus' => true, 'app.coming_soon' => false]);
    }

    private function js(string $file): string
    {
        return (string) file_get_contents(dirname(base_path()).'/web/js/'.$file);
    }

    public function test_every_page_is_written_and_matches_what_its_card_plays(): void
    {
        foreach (array_keys(Reference::FAMILIES) as $family) {
            $this->assertNotSame([], Reference::slugs($family), $family);
        }
        $this->assertSame(array_keys(Reference::INTERVALS), Reference::slugs('intervals'));
        $this->assertSame(array_keys(Reference::CHORDS), Reference::slugs('chords'));
        $this->assertSame(array_keys(Reference::VOICE_TYPES), Reference::slugs('voice-types'));

        // Each interval's pure ratio is just.js's (the octave is 2:1 on the card itself).
        preg_match_all('/\{ semis: (\d+), ratio: \[(\d+), (\d+)\], name: \'(\w+)\' \}/', $this->js('just.js'), $intervals, PREG_SET_ORDER);
        $ratios = [];
        foreach ($intervals as [, $semis, $over, $under, $name]) {
            if ($name !== 'harmonicSeventh') {
                $ratios[(int) $semis] = $over.':'.$under;
            }
        }
        foreach (Reference::INTERVALS as $slug => [$semis, $ratio]) {
            $this->assertSame($semis === 12 ? '2:1' : ($ratios[$semis] ?? null), $ratio, $slug);
        }

        // Each chord's quality is one just.js tunes.
        preg_match('/export const RATIOS = \{(.*?)\};/s', $this->js('just.js'), $table);
        preg_match_all('/(\w+): \{/', $table[1] ?? '', $qualities);
        $this->assertSame([], array_values(array_diff(Reference::CHORDS, $qualities[1])));

        // Each voice type's range and choir part are theory.js's, and its page says that range.
        $theory = $this->js('theory.js');
        preg_match_all('/(\w+): \{ low: (\d+), high: (\d+) \}/', $theory, $types, PREG_SET_ORDER);
        $ranges = [];
        foreach ($types as [, $type, $low, $high]) {
            $ranges[$type] = [(int) $low, (int) $high];
        }
        $name = static fn (int $midi): string => ['C', 'C♯', 'D', 'E♭', 'E', 'F', 'F♯', 'G', 'A♭', 'A', 'B♭', 'B'][$midi % 12].(intdiv($midi, 12) - 1);
        preg_match('/export const CHOIR_PARTS = \{(.*?)\};/', $theory, $partsTable);
        foreach (Reference::VOICE_TYPES as $slug => [$type, $part, $low, $high]) {
            $this->assertArrayHasKey($type, $ranges, $slug);
            $this->assertSame($ranges[$type], [$low, $high], $slug);
            $this->assertSame($name($ranges[$type][0]).' to '.$name($ranges[$type][1]), (string) Reference::page('voice-types', $slug)['range'], $slug);
            $this->assertStringContainsString($type.": '".$part."'", $partsTable[1] ?? '', $slug);
        }
    }

    public function test_each_family_lists_and_links_its_pages(): void
    {
        $this->split();

        foreach (array_keys(Reference::FAMILIES) as $family) {
            $words = Reference::index($family);
            $this->assertSame((string) $words['title'].' | '.__('meta.brand'), SitePages::title((string) $words['title']), "{$family}'s title is too long");
            $this->assertLessThanOrEqual(155, mb_strlen((string) $words['description']), $family);
            $html = (string) $this->get(self::SITE.'/'.$family)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), "{$family} has one h1");
            $this->assertStringContainsString('<link rel="canonical" href="'.e(self::SITE.'/'.$family).'">', $html);
            $this->assertStringContainsString('href="/?ref='.$family.'"', $html, "{$family}'s logo carries its tag");
            foreach (Reference::slugs($family) as $slug) {
                $this->assertStringContainsString('href="/'.$family.'/'.$slug.'"', $html, "{$family} links {$slug}");
            }
        }
    }

    public function test_each_page_has_its_heading_card_guide_questions_and_way_into_the_app(): void
    {
        $this->split();

        foreach (array_keys(Reference::FAMILIES) as $family) {
            foreach (Reference::slugs($family) as $slug) {
                $page = Reference::page($family, $slug);
                $this->assertSame((string) $page['title'].' | '.__('meta.brand'), SitePages::title((string) $page['title']), "{$slug}'s title is too long");
                $this->assertLessThanOrEqual(155, mb_strlen((string) $page['description']), "{$slug}'s description");
                $html = (string) $this->get(self::SITE.'/'.$family.'/'.$slug)->assertOk()->getContent();

                $this->assertSame(1, substr_count($html, '<h1'), "{$slug} has one h1");
                $this->assertStringContainsString('>'.e((string) $page['headline']).'</h1>', $html);
                $this->assertStringContainsString('<link rel="canonical" href="'.e(self::SITE.'/'.$family.'/'.$slug).'">', $html);
                foreach ((array) $page['guide'] as $section) {
                    $this->assertStringContainsString(e((string) $section['heading']), $html, $slug);
                }
                foreach ((array) $page['faq'] as [$question, $answer]) {
                    $this->assertStringContainsString(e((string) $question), $html, $slug);
                    $this->assertStringContainsString(e((string) $answer), $html, $slug);
                }
                $this->assertStringContainsString('data-mount="'.Reference::FAMILIES[$family]['mount'].'"', $html);
                $to = (string) Lang::get('site.tools.index.try_to');
                $this->assertStringContainsString('href="'.e(Hosts::app($to.(str_contains($to, '?') ? '&' : '?').'ref='.$family)).'"', $html, "{$slug}'s way into the app carries its family's tag");

                preg_match('/<script type="application\/json" data-tool-data>(.*?)<\/script>/s', $html, $data);
                $this->assertSame(Reference::data($family, $slug), json_decode(html_entity_decode($data[1] ?? ''), true), $slug);
            }
        }
    }

    public function test_the_cards_words_are_the_ones_they_read(): void
    {
        $file = (string) file_get_contents(dirname(base_path()).'/web/js/public/reference-cards.js');
        $mounts = ['intervals' => 'mountIntervalCard', 'chords' => 'mountChordCard', 'voices' => 'mountVoiceRangeCard'];

        foreach ($mounts as $words => $mount) {
            // The words a card's own code reads, and the frame's (tuning, lower, higher) where it has a root or a tuning.
            preg_match('/export function '.$mount.'\(.*?\n\}\n/s', $file, $body);
            preg_match_all('/words\??\.(\w+)/', $body[0] ?? '', $read);
            $wanted = array_values(array_unique([...$read[1], ...($words === 'voices' ? [] : ['tuning', 'equal', 'pure', 'lower', 'higher'])]));
            $card = array_keys((array) Lang::get('site.reference.'.$words.'.card'));
            $missing = array_values(array_diff($wanted, [...$card, 'name']));
            $this->assertSame([], $missing, "site.reference.{$words}.card lacks words its card reads");
        }
    }

    public function test_a_voice_types_page_lists_song_parts_inside_its_range_its_own_part_first(): void
    {
        $this->catalogue();
        $parts = app(SongParts::class);
        [, , $low, $high] = Reference::VOICE_TYPES['bass'];

        // Amazing Grace's tenor and bass both sit inside a bass's range: the bass part comes first, once per song.
        $urls = array_column($parts->within($low, $high, Reference::VOICE_PARTS['bass']), 'url');
        $this->assertContains('/songs/amazing-grace/bass', $urls);
        $this->assertNotContains('/songs/amazing-grace/tenor', $urls);
        $this->assertLessThanOrEqual(6, count($urls));
        $this->assertSame([], $parts->within(84, 96), 'no part sits that high');

        if (SongParts::open()) {
            $this->split();
            $this->get(self::SITE.'/voice-types/bass')->assertOk()->assertSee('href="/songs/amazing-grace/bass"', false);
            $this->get(self::SITE.'/intervals/octave')->assertOk()->assertDontSee('href="/songs/amazing-grace/', false);
        }
    }

    public function test_each_familys_pages_feature_its_own_tool(): void
    {
        $this->split();

        foreach (['intervals/major-third' => 'interval-ear-training', 'chords/major' => 'chord-ear-training', 'voice-types/tenor' => 'vocal-range-test'] as $path => $tool) {
            $this->get(self::SITE.'/'.$path)->assertOk()->assertSee('href="/tools/'.$tool.'" class="s-tfeat', false);
        }
    }

    public function test_an_unknown_page_is_a_404(): void
    {
        $this->split();

        foreach (['/intervals/nonsense', '/chords/nonsense', '/voice-types/nonsense'] as $path) {
            $this->get(self::SITE.$path)->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex');
        }
    }

    public function test_the_sitemap_lists_every_page(): void
    {
        $paths = app(SitemapPaths::class)->all();

        foreach (array_keys(Reference::FAMILIES) as $family) {
            $this->assertContains('/'.$family, $paths);
            foreach (Reference::slugs($family) as $slug) {
                $this->assertContains('/'.$family.'/'.$slug, $paths);
            }
        }
    }

    public function test_the_app_host_sends_them_to_the_site(): void
    {
        $this->split();

        $this->get(self::APP.'/intervals')->assertRedirect(self::SITE.'/intervals');
        $this->get(self::APP.'/chords/major')->assertRedirect(self::SITE.'/chords/major');
        $this->get(self::APP.'/voice-types/tenor')->assertRedirect(self::SITE.'/voice-types/tenor');
    }
}
