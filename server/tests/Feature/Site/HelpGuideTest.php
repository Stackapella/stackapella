<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Http\Hosts;
use App\Support\Site\HelpGuide;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

final class HelpGuideTest extends TestCase
{
    use RefreshDatabase;

    private const SHOT = 'test--a-step';

    protected function tearDown(): void
    {
        foreach (glob($this->web('/icons/help/'.self::SHOT).'*') ?: [] as $file) {
            unlink($file);
        }
        parent::tearDown();
    }

    public function test_the_index_lists_every_written_page_by_group_and_starts_with_the_path(): void
    {
        $index = $this->get('/help')->assertOk()->assertSee('data-help-search="/help/search-index.json"', false);
        foreach (HelpGuide::slugs() as $slug) {
            $index->assertSee('href="/help/'.$slug.'"', false);
        }
        $index->assertSee(__('pages.help.path'))->assertSee(__('pages.help.groups.fixes'));
    }

    public function test_a_page_has_its_contents_its_anchors_and_its_neighbours(): void
    {
        $slugs = HelpGuide::slugs();
        $page = $this->get('/help/'.$slugs[1])->assertOk();
        $html = (string) $page->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $page->assertSee('aria-current="page"', false)
            ->assertSee('href="/help/'.$slugs[0].'" class="s-card s-pager-prev" rel="prev"', false)
            ->assertSee('href="/help/'.$slugs[2].'" class="s-card s-pager-next" rel="next"', false);
        foreach (HelpGuide::sections($slugs[1]) as $section) {
            $page->assertSee('<h2 id="'.$section['id'].'">', false)->assertSee('href="#'.$section['id'].'"', false);
        }

        $this->get('/help/'.$slugs[0])->assertDontSee('rel="prev"', false);
        $this->get('/help/'.end($slugs))->assertDontSee('rel="next"', false);
    }

    public function test_a_shot_has_its_serious_pair_once_all_four_files_are_there(): void
    {
        $help = dirname(base_path()).'/web/icons/help/';
        $made = [];
        try {
            foreach (['', '-dark'] as $suffix) {
                foreach (['png', 'webp'] as $ext) {
                    copy($help.'course-barbershop--1'.$suffix.'.'.$ext, $made[] = $help.'zz-test-shot'.$suffix.'.'.$ext);
                }
            }
            $this->assertSame(['light', 'dark'], array_keys((array) HelpGuide::shot('zz-test-shot')));

            // Three of the serious pair's four files: not yet.
            copy($help.'course-barbershop--1.png', $made[] = $help.'zz-test-shot-serious.png');
            copy($help.'course-barbershop--1.webp', $made[] = $help.'zz-test-shot-serious.webp');
            copy($help.'course-barbershop--1-dark.png', $made[] = $help.'zz-test-shot-serious-dark.png');
            $this->assertSame(['light', 'dark'], array_keys((array) HelpGuide::shot('zz-test-shot')));

            copy($help.'course-barbershop--1-dark.webp', $made[] = $help.'zz-test-shot-serious-dark.webp');
            $shot = (array) HelpGuide::shot('zz-test-shot');
            $this->assertSame(['light', 'dark', 'serious', 'serious-dark'], array_keys($shot));
            $this->assertSame('/icons/help/zz-test-shot-serious-dark.webp', $shot['serious-dark']['webp']);
        } finally {
            array_map('unlink', array_filter($made, 'is_file'));
        }
    }

    public function test_old_addresses_move_on_and_unwritten_pages_are_not_there(): void
    {
        $this->get('/help/first-harmony')->assertStatus(301)->assertRedirect('/help/getting-started');
        foreach (array_merge(...array_values(HelpGuide::GROUPS)) as $slug) {
            if (! HelpGuide::written($slug)) {
                $this->get('/help/'.$slug)->assertNotFound();
            }
        }
    }

    public function test_the_search_index_has_each_page_and_section_with_its_numbers_filled(): void
    {
        $rows = $this->getJson('/help/search-index.json')->assertOk()->json();

        $urls = array_column($rows, 'url');
        foreach (HelpGuide::slugs() as $slug) {
            $this->assertContains('/help/'.$slug, $urls);
        }
        $this->assertContains('/help/timing#run-a-timing-check', $urls);
        $words = json_encode($rows, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString(':price', $words);
        $this->assertStringNotContainsString('**', $words);
    }

    public function test_a_screenshot_shows_once_both_themes_are_shot_and_matches_the_theme(): void
    {
        __('pages.help.title');
        $sections = Lang::get('pages.help.pages.headphones.sections');
        $sections[0][1][] = ['shot' => self::SHOT, 'alt' => 'The headphones question'];
        $sections[0][1][] = ['tip' => 'Wired is **best**.'];
        app('translator')->addLines(['pages.help.pages.headphones.sections' => $sections], 'en');

        // Not shot yet: the words show, the picture is left out.
        $this->get('/help/headphones')->assertOk()->assertDontSee('s-shot', false)->assertSee('Wired is <strong>best</strong>.', false);
        $this->assertContains(self::SHOT, HelpGuide::namedShots());

        foreach (['', '-dark'] as $suffix) {
            imagepng(imagecreatetruecolor(39, 84), $this->web('/icons/help/'.self::SHOT.$suffix.'.png'));
            imagewebp(imagecreatetruecolor(39, 84), $this->web('/icons/help/'.self::SHOT.$suffix.'.webp'));
        }

        $this->get('/help/headphones')
            ->assertSee('class="s-shot s-shot-tall"', false)
            ->assertSee('class="s-shot-frame s-shot-light"', false)
            ->assertSee('class="s-shot-frame s-shot-dark"', false)
            ->assertSee('srcset="/icons/help/'.self::SHOT.'-dark.webp"', false)
            ->assertSee('alt="The headphones question" width="39" height="84" loading="lazy"', false);
    }

    public function test_every_label_the_guide_names_is_the_apps_own(): void
    {
        // The app's words, each a pattern: a {placeholder} stands for any words ("Record {part}" is "Record Tenor").
        $words = [];
        $json = json_decode((string) file_get_contents($this->web('/locales/en.json')), true, flags: JSON_THROW_ON_ERROR);
        array_walk_recursive($json, function (mixed $value) use (&$words): void {
            if (is_string($value)) {
                $marked = (string) preg_replace('/\{[^{}]*(\{[^{}]*\}[^{}]*)*\}/', "\u{E000}", str_replace('**', '', $value));
                $words[] = '/'.str_replace("\u{E000}", '.+', preg_quote($marked, '/')).'/u';
            }
        });
        // Words of the site's own (its header) that the guide also bolds.
        $site = [(string) __('pages.nav.open_app')];

        foreach (HelpGuide::slugs() as $slug) {
            preg_match_all('/\*\*(.+?)\*\*/u', (string) json_encode(Lang::get('pages.help.pages.'.$slug), JSON_UNESCAPED_UNICODE), $found);
            foreach (array_unique($found[1]) as $label) {
                $label = stripslashes($label);
                $known = in_array($label, $site, true) || array_filter($words, static fn (string $pattern): bool => preg_match($pattern, $label) === 1) !== [];
                $this->assertTrue($known, "/help/{$slug} names **{$label}**, which the app doesn't say");
            }
        }
    }

    public function test_troubleshooting_sends_each_answer_on_to_the_page_or_screen_that_fixes_it(): void
    {
        // Every page's links resolve (an unknown page or section throws as the page is built).
        foreach (HelpGuide::slugs() as $slug) {
            $this->assertIsArray(HelpGuide::sections($slug));
        }

        $this->get('/help/troubleshooting')->assertOk()
            ->assertSee(__('pages.help.pages.troubleshooting.title'))
            ->assertSee('href="/help/mic"', false)
            ->assertSee('href="/help/timing#run-a-timing-check"', false)
            ->assertSee(__('pages.help.pages.timing.title').': Run a timing check')
            ->assertSee('href="'.Hosts::app('/learn/1-comfortable').'"', false)
            ->assertSee('href="'.Hosts::app('/signin').'"', false);
        $this->get('/help')->assertSee('href="/help/troubleshooting"', false);
        $this->get('/sitemap.xml')->assertSee('/help/troubleshooting');
    }

    private function web(string $path): string
    {
        if (! is_dir(dirname(base_path()).'/web/icons/help')) {
            mkdir(dirname(base_path()).'/web/icons/help', 0755, true);
        }

        return dirname(base_path()).'/web'.$path;
    }
}
