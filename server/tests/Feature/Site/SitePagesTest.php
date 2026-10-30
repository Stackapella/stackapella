<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Enums\TemplateAccess;
use App\Http\Controllers\Public\DocumentPagesController;
use App\Models\Flag;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Flags;
use App\Support\Http\Hosts;
use App\Support\Pages\MudeoArrival;
use App\Support\Site\HelpGuide;
use App\Support\Site\Prose;
use App\Support\Site\SitePages;
use App\Support\Site\WhatsNew;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The feature and "for" pages (docs/public-site-plan.md): each renders with its words, one h1 and
 * the site's frame; "Coming soon" follows the flags; every link on them goes somewhere real.
 */
final class SitePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_page_shows_the_same_picture_twice(): void
    {
        // Hillel: "same image is shown twice". A page's own picture, its sections' and the cards it
        // shows (the first three) are each a different drawing.
        foreach (['features' => SitePages::FEATURES, 'for' => SitePages::FOR] as $area => $slugs) {
            foreach ($slugs as $slug) {
                $page = SitePages::page($area, $slug);
                $shown = SitePages::picturesOf($page);
                $cards = $area === 'features'
                    ? SitePages::related($slug, shown: $shown)
                    : SitePages::unshown(SitePages::cards(array_values(array_filter((array) ($page['features'] ?? []), 'is_string'))), $shown);
                $all = [...$shown, ...array_column(array_slice($cards, 0, 3), 'pic')];
                $twice = array_keys(array_filter(array_count_values($all), static fn (int $n): bool => $n > 1));
                $this->assertSame([], $twice, "/{$area}/{$slug} shows the same picture twice: ".implode(', ', $twice));
            }
        }
    }

    public function test_every_feature_and_for_page_renders_with_its_words(): void
    {
        foreach (SitePages::paths() as $path) {
            $page = $this->get($path)->assertOk();
            $html = (string) $page->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), "{$path}: one h1");
            $page->assertSee('class="s-wrap s-top"', false)->assertSee('s-footer', false);
            $page->assertSee('<link rel="canonical" href="'.rtrim((string) config('app.url'), '/').$path.'">', false);
            $this->assertMatchesRegularExpression('/<title>[^<]*Stackapella[^<]*<\/title>/', $html, "{$path}: titled with the name");
            $this->assertStringNotContainsStringIgnoringCase('mudeo', $html, "{$path}: nothing about mudeo");
        }

        foreach ([...SitePages::FEATURES] as $slug) {
            $this->assertIsArray(Lang::get('site.features.pages.'.$slug.'.sections'), "{$slug}: its words");
            // A feature with its own designed page (site/features/<slug>) leads with its hero title instead.
            $words = (array) Lang::get('site.features.pages.'.$slug);
            $this->get('/features/'.$slug)->assertSee((string) ($words['hero_title'] ?? $words['headline']));
        }
        foreach (SitePages::FOR as $who) {
            $this->get('/for/'.$who)->assertSee(__('site.for.pages.'.$who.'.headline'));
        }

        $this->get('/features/teleport')->assertNotFound();
        $this->get('/for/nobody')->assertNotFound();
    }

    public function test_coming_soon_follows_the_flags_on_the_pages_and_the_cards(): void
    {
        $label = __('pages.nav.coming_soon');

        $this->get('/features/gallery')->assertSee($label)->assertSee(__('site.features.pages.gallery.soon'));
        $this->get('/features/studio')->assertDontSee($label);

        Flag::query()->create(['name' => 'gallery', 'value' => 'on']);
        $this->app->forgetScopedInstances();

        $this->get('/features/gallery')->assertDontSee(__('site.features.pages.gallery.soon'));
        $this->assertFalse(collect($this->get('/features')->viewData('cards'))->firstWhere('slug', 'gallery')['soon']);

        // Hum a tune needs both halves open.
        Flag::query()->create(['name' => 'ideas', 'value' => 'on']);
        $this->app->forgetScopedInstances();
        $this->assertTrue(SitePages::soon('your-tune'));
        app(Flags::class)->set('your_tune', 'on', null, 'Hum a tune opens');
        $this->assertFalse(SitePages::soon('your-tune'));
    }

    public function test_the_mixer_plays_where_hearing_it_helps(): void
    {
        $template = Template::factory()->create(['slug' => 'amazing-grace', 'title' => 'Amazing Grace']);
        $template->forceFill(['current_version_id' => TemplateVersion::factory()->create(['template_id' => $template->id])->id])->save();

        $this->get('/features/learn-a-part')->assertSee('class="s-mixer"', false)->assertSee('/js/public/mixer.js', false);
        $this->get('/features/course')->assertDontSee('class="s-mixer"', false)->assertDontSee('/js/public/mixer.js', false);
    }

    public function test_every_link_on_the_site_pages_goes_somewhere_real(): void
    {
        // Hundreds of pages from one address: this checks the links, not the page limiter.
        $this->withoutMiddleware(ThrottleRequests::class);
        $checked = [];
        foreach (['/', ...SitePages::paths()] as $path) {
            preg_match_all('/href="([^"#]+)"/', (string) $this->get($path)->getContent(), $matches);
            foreach (array_unique($matches[1]) as $href) {
                $host = parse_url($href, PHP_URL_HOST);
                // Another site's link (Hillel's own) isn't ours to answer.
                if (isset($checked[$href]) || str_starts_with($href, 'mailto:') || (is_string($host) && ! in_array($host, [Hosts::appHost(), Hosts::publicHost()], true))) {
                    continue;
                }
                $checked[$href] = true;
                $target = parse_url($href, PHP_URL_PATH) ?? '/';
                // A static file (the logo, the link picture) is served by nginx, not Laravel.
                if (is_file(dirname(base_path()).'/web'.$target)) {
                    continue;
                }
                // Links into the app are the app's pages, answered by its shell.
                $this->assertContains($this->get($href)->getStatusCode(), [200], "{$path} links to {$href} ({$target})");
            }
        }

        $this->assertGreaterThan(20, count($checked));
    }

    public function test_the_footer_links_our_networks_that_are_set_and_the_email(): void
    {
        config(['site.social' => ['x' => '', 'bluesky' => 'https://bsky.app/profile/stackapella.com']]);

        $this->get('/about')->assertOk()
            ->assertSee('href="https://bsky.app/profile/stackapella.com"', false)
            ->assertSee(__('pages.nav.social.bluesky'))
            ->assertDontSee(__('pages.nav.social.x'))
            ->assertSee('href="mailto:'.config('site.contact_email').'"', false)
            ->assertSee(__('pages.nav.groups.legal'));

        config(['site.social.x' => 'https://x.com/stackapella']);
        $this->get('/about')->assertSee('href="https://x.com/stackapella"', false)->assertSee(__('pages.nav.social.x'));
    }

    public function test_the_pages_write_the_installs_own_address_and_the_coaching_commission(): void
    {
        config(['site.contact_email' => 'hello@choir.example', 'coaching.commission_percent' => 12]);

        foreach (['/help', '/help/plus-and-billing', '/help/mic', '/for/coaches', '/about'] as $path) {
            $this->get($path)->assertOk()->assertDontSee('contact@stackapella.com')->assertDontSee(':email');
        }
        $this->get('/help')->assertSee('hello@choir.example');
        $this->get('/help/plus-and-billing')->assertSee('hello@choir.example');
        $this->get('/for/coaches')->assertSee('hello@choir.example')->assertSee('12%')->assertDontSee(':commission');
    }

    public function test_about_tells_mudeos_story_only_to_mudeos_singers(): void
    {
        $everyone = (string) $this->get('/about')->assertOk()->assertSee(__('site.about.company'))->getContent();
        $this->assertStringNotContainsStringIgnoringCase('mudeo', $everyone);

        $this->get('/about?ref=mudeo')->assertOk()->assertSee(__('site.about.mudeo.heading'));
        $this->withUnencryptedCookie(MudeoArrival::COOKIE, '1')->get('/about')->assertSee(__('site.about.mudeo.heading'))
            ->assertHeader('Cache-Control', 'no-cache, private');
    }

    public function test_the_press_kit_and_whats_new(): void
    {
        $founder = '<a class="s-lk" href="https://hillel.dev">Hillel Coren</a>';
        // About is Hillel's signed note: his name linked, the address linked inside its sentence.
        $this->get('/about')->assertSee('<p class="s-sig">'.$founder.'</p>', false)->assertSee('write to me at <a class="s-lk" href="mailto:contact@stackapella.com">contact@stackapella.com</a>.', false)
            ->assertDontSee(':founder')->assertDontSee(':email');
        $this->get('/press')->assertOk()->assertSee($founder, false)->assertSee('$3.33')->assertDontSee(':founder')->assertSee('href="/icons/icon.svg"', false);
        $this->get('/press', ['CF-IPCountry' => 'GB'])->assertSee('£2.91');
        $this->assertFileExists(dirname(base_path()).'/web/icons/icon.svg');

        $entries = WhatsNew::entries();
        $this->assertNotSame([], $entries);
        $this->assertSame($entries, collect($entries)->sortByDesc('date')->values()->all());
        // Newest first, each linkable by its title, and no dates shown (Hillel: changes come in bursts).
        $this->get('/whats-new')->assertOk()->assertSee($entries[0]['title'])
            ->assertSee('id="'.Str::slug($entries[0]['title']).'"', false)
            ->assertDontSee('datetime=', false)->assertDontSee($entries[0]['day']);
    }

    public function test_guides_and_legal_pages_read_as_pages_in_the_sites_frame(): void
    {
        $this->get('/guides')->assertOk()->assertSee('href="/guides/how-to-sing-harmony"', false);

        $guide = $this->get('/guides/how-to-sing-harmony')->assertOk();
        // Its numbered headings are a path of steps, each number in a disc and not in the words.
        $guide->assertSee('class="s-prose"', false)->assertSee('class="s-wrap s-top"', false)
            ->assertSee('<h2 class="s-gstep"><span class="s-disc s-v1" aria-hidden="true">1</span><span>Hear your part alone</span></h2>', false)
            ->assertDontSee('1. Hear your part alone');
        $this->assertSame(1, substr_count((string) $guide->getContent(), '<h1'));

        foreach (DocumentPagesController::LEGAL as $page) {
            if (! is_file(resource_path('legal/en/'.$page.'.md'))) {
                continue;
            }
            $legal = $this->get('/legal/'.$page)->assertOk();
            $legal->assertSee('class="s-prose"', false)->assertDontSee('<pre>', false);
            $this->assertSame(1, substr_count((string) $legal->getContent(), '<h1'), $page);
        }
        $this->get('/legal/terms')->assertSee(__('pages.legal.version', ['version' => 1]));
    }

    public function test_every_listed_help_page_and_guide_has_its_words_and_renders(): void
    {
        foreach (HelpGuide::slugs() as $slug) {
            $this->assertTrue(Lang::has('pages.help.pages.'.$slug.'.title'), "help.pages.{$slug} has no words.");
            $this->get('/help/'.$slug)->assertOk();
        }

        foreach (DocumentPagesController::GUIDES as $slug) {
            $this->assertFileExists(resource_path('guides/en/'.$slug.'.md'));
            $page = $this->get('/guides/'.$slug)->assertOk();
            $this->assertSame(1, substr_count((string) $page->getContent(), '<h1'), $slug);
        }
    }

    public function test_a_plus_songs_page_plays_its_clip_and_never_its_parts_or_notes(): void
    {
        $template = Template::factory()->create(['slug' => 'amazing-grace', 'title' => 'Amazing Grace', 'access' => TemplateAccess::Plus]);
        $template->forceFill(['current_version_id' => TemplateVersion::factory()->create(['template_id' => $template->id])->id])->save();

        $page = $this->get('/songs/amazing-grace')->assertOk();
        $html = (string) $page->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $page->assertSee(__('pages.song.plus'))->assertSee(__('pages.song.start'))->assertSee('D4');
        // A Plus song: the preview clip, never a single part or the notes (Hillel, 2026-10-27).
        $this->assertStringNotContainsString('data-song', $html);
        $this->assertStringNotContainsString('"pitch"', $html);
        $this->assertStringNotContainsString('/js/public/mixer.js', $html);

        $this->get('/songs')->assertOk()->assertSee('href="/songs/amazing-grace"', false);
    }

    public function test_a_free_songs_page_plays_its_opening_line_and_charts_its_parts(): void
    {
        foreach (['amazing-grace' => 'Amazing Grace', 'happy-birthday' => 'Happy Birthday'] as $slug => $title) {
            $template = Template::factory()->create(['slug' => $slug, 'title' => $title, 'access' => TemplateAccess::Free]);
            $template->forceFill(['current_version_id' => TemplateVersion::factory()->create(['template_id' => $template->id])->id])->save();
        }

        $page = $this->get('/songs/amazing-grace')->assertOk();
        $html = (string) $page->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $page->assertSee(__('pages.song.free'))
            ->assertSee('data-song', false)
            ->assertSee('<script type="module" src="/js/public/mixer.js"></script>', false)
            ->assertSee('role="radiogroup"', false)
            ->assertSee(__('pages.song.range_row', ['part' => 'Soprano', 'low' => 'D4', 'high' => 'D5']))
            ->assertSee(__('pages.song.end_title', ['title' => 'Amazing Grace']))
            ->assertSee('href="/songs/happy-birthday"', false);
        $this->assertSame(4, substr_count($html, 'class="s-range-row'));
        // Its opening line ends on a phrase's end, not part way through the song.
        $line = json_decode((string) preg_replace('/^.*<script type="application\/json" data-song>(.*?)<\/script>.*$/s', '$1', $html), true);
        $this->assertSame(24, (int) $line['beats']);
        $this->assertSame('soprano', $line['you'], 'Melody first.');
    }

    public function test_text_blocks_are_escaped_with_only_bold_made_bold(): void
    {
        $this->assertSame('a <strong>b</strong> &lt;script&gt;', Prose::inline('a **b** <script>')->toHtml());
    }

    public function test_each_page_has_its_own_link_card_drawn_from_its_own_words(): void
    {
        foreach (SitePages::linkCards() as $card) {
            $this->assertFileExists(dirname(base_path()).'/web/icons/og/'.$card['key'].'.png', 'Run: cd server && python3 tool/link_cards.py');
        }

        $this->artisan('app:site:link-cards')->expectsOutputToContain('"key": "features-learn-a-part"')->assertSuccessful();

        $this->get('/features/learn-a-part')->assertSee('<meta property="og:image" content="'.url('/icons/og/features-learn-a-part.png').'">', false);
        $this->get('/')->assertSee('<meta property="og:image" content="'.url('/icons/og-landing.png').'">', false);
    }

    public function test_the_mic_help_page_says_exactly_what_the_apps_mic_dialog_says(): void
    {
        $words = json_decode((string) file_get_contents(dirname(base_path()).'/web/locales/en.json'), true)['micHelp'];
        $page = (string) $this->get('/help/mic')->assertOk()->getContent();

        foreach ($words['steps'] as $device => $steps) {
            foreach ($steps as $step) {
                // The iPhone browsers' steps name the browser; the rest are word for word, a setting's
                // name in bold as the dialog marks it.
                $shown = (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', e($step));
                foreach (explode('{browser}', $shown) as $piece) {
                    $this->assertStringContainsString(trim($piece), $page, "micHelp.steps.{$device}");
                }
            }
        }
        foreach (['blocked', 'notFound', 'inUse', 'insecure', 'unsupported'] as $cause) {
            $this->assertStringContainsString(e($words['cause'][$cause]), $page, $cause);
        }
        $this->assertStringNotContainsString('{browser}', $page);
    }

    public function test_help_pages_fill_their_numbers_from_config(): void
    {
        foreach (HelpGuide::slugs() as $slug) {
            $this->get('/help/'.$slug)->assertOk()->assertDontSee(':price')->assertDontSee(':days');
        }

        $this->get('/help/plus-and-billing')->assertSee('$3.33')->assertSee(config('billing.trial_days').' days');
        $this->get('/help/plus-and-billing', ['CF-IPCountry' => 'GB'])->assertSee('£2.91');
    }

    public function test_the_sitemap_lists_every_site_page(): void
    {
        $xml = (string) $this->get('/sitemap.xml')->getContent();

        foreach (SitePages::paths() as $path) {
            $this->assertStringContainsString('<loc>'.rtrim((string) config('app.url'), '/').$path.'</loc>', $xml);
        }
    }

    public function test_a_course_has_a_page_only_once_its_words_are_written(): void
    {
        $this->assertCount(27, SitePages::COURSES);
        $written = array_values(array_filter(SitePages::COURSES, static fn (string $slug): bool => Lang::has('site.courses.pages.'.$slug.'.headline')));
        $this->assertContains(SitePages::courses(), [[], $written]);

        if (SitePages::courses() === []) {
            $this->get('/courses')->assertNotFound();
            $this->get('/courses/vibrato')->assertNotFound();
            $this->assertNotContains('/courses', SitePages::paths());
        }

        // Words for one: once the views are there too, it's in the sitemap and the link cards.
        app('translator')->addLines(['site.courses.pages.vibrato.headline' => 'Vibrato, freely'], 'en');
        if (view()->exists('site.course') && view()->exists('site.courses')) {
            $this->assertContains('vibrato', SitePages::courses());
            $this->assertContains('/courses/vibrato', SitePages::paths());
            $this->assertContains('courses-vibrato', array_column(SitePages::linkCards(), 'key'));
        } else {
            $this->assertSame([], SitePages::courses());
        }
        $this->get('/courses/not-a-course')->assertNotFound();
    }

    public function test_a_course_is_open_once_the_plus_courses_are_and_its_list_says_live(): void
    {
        $this->assertFalse(SitePages::courseOpen('vibrato'));

        app(Flags::class)->set('plus_courses', 'on', null, 'Opening the Plus courses');
        $live = collect((array) json_decode((string) file_get_contents(dirname(base_path()).'/web/course/plus/courses.json'), true)['courses'])->where('status', 'live')->map(fn (array $c): string => $c['slug'] ?? $c['id'])->values()->all();
        foreach (SitePages::COURSES as $slug) {
            $this->assertSame(in_array($slug, $live, true), SitePages::courseOpen($slug), $slug);
        }
    }

    public function test_how_it_works_is_its_own_page_once_its_view_is_there(): void
    {
        if (! SitePages::hasHowItWorks()) {
            // The landing agent's view and words come in their own landing.
            $this->get('/how-it-works')->assertNotFound();
            $this->assertNotContains('/how-it-works', SitePages::paths());

            return;
        }

        $page = $this->get('/how-it-works');
        $page->assertOk()->assertViewIs('site.how-it-works')->assertViewHas('hero');
        $this->assertSame(1, substr_count((string) $page->getContent(), '<h1'));
        $this->assertContains('/how-it-works', SitePages::paths());
    }

    public function test_a_course_outline_comes_from_the_courses_own_file(): void
    {
        $catalogue = collect((array) json_decode((string) file_get_contents(dirname(base_path()).'/web/course/plus/courses.json'), true)['courses']);
        $vibrato = $catalogue->first(fn (array $c): bool => ($c['slug'] ?? $c['id']) === 'vibrato');
        $file = (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/course/plus/'.$vibrato['file']), true);

        $outline = SitePages::courseOutline('vibrato');
        $this->assertNotNull($outline);
        $this->assertCount(count($file['units']), $outline['units']);
        $this->assertSame($file['units'][0]['title']['en'], $outline['units'][0]['title']);
        $this->assertSame($file['units'][0]['lessons'][0]['title']['en'], $outline['units'][0]['lessons'][0]);
        $this->assertSame(array_sum(array_map(fn (array $u): int => count($u['lessons']), $file['units'])), $outline['lessons']);
        $this->assertSame($vibrato['minutes'] ?? null, $outline['minutes']);

        // No file yet, or no such course: nothing.
        $this->assertNull(SitePages::courseOutline('no-such-course'));
        $withoutFile = $catalogue->first(fn (array $c): bool => ! isset($c['file']));
        if ($withoutFile !== null) {
            $this->assertNull(SitePages::courseOutline($withoutFile['slug'] ?? $withoutFile['id']));
        }
    }

    public function test_the_free_courses_outline_comes_from_its_own_file(): void
    {
        $file = (array) json_decode((string) file_get_contents(dirname(base_path()).'/web/course/course.json'), true);

        $outline = SitePages::freeCourseOutline();
        $this->assertNotNull($outline);
        $this->assertCount(count($file['units']), $outline['units']);
        $this->assertSame(array_sum(array_map(fn (array $u): int => count($u['lessons']), $file['units'])), $outline['lessons']);
        $this->assertSame($file['units'][0]['lessons'][0]['title']['en'], $outline['units'][0]['lessons'][0]);
    }

    public function test_the_free_course_has_its_own_page_with_its_units_and_is_listed_first(): void
    {
        $outline = SitePages::freeCourseOutline();
        $this->assertNotNull($outline);
        $page = (array) Lang::get('site.features.pages.course');

        $response = $this->get('/features/course')->assertOk()
            ->assertSee((string) $page['hero_title'])
            ->assertSee((string) $page['plus_unit'])
            ->assertSee('"@type":"FAQPage"', false);
        foreach ($outline['units'] as $unit) {
            $response->assertSee($unit['title'])->assertSee($unit['lessons'][0]);
        }

        $html = (string) $this->get('/courses')->assertOk()->getContent();
        $free = strpos($html, 'href="/features/course"');
        $this->assertNotFalse($free, 'the free course is on /courses');
        $this->assertLessThan(strpos($html, 'href="/courses/'), $free, 'and listed before the Plus courses');
        $this->assertStringContainsString(e(__('site.courses.index.free_name')), $html);
    }

    public function test_a_course_page_shows_its_units_from_its_file_how_it_listens_and_its_songs(): void
    {
        $outline = SitePages::courseOutline('vibrato');
        $this->assertNotNull($outline);
        $page = (array) Lang::get('site.courses.pages.vibrato');

        // Vibrato has its own designed page (site/courses/vibrato), with the same facts from the course.
        $response = $this->get('/courses/vibrato')->assertOk()
            ->assertSee((string) $page['titles']['units'])
            ->assertSee(__('pages.site.course_free'))
            ->assertSee((string) $page['measures'])
            ->assertSee((string) $page['scoring']['serious'])
            ->assertSee((string) $page['safety'][0])
            ->assertSee((string) $page['songs'][0][0]);
        foreach ($outline['units'] as $unit) {
            $response->assertSee($unit['title'])->assertSee($unit['lessons'][0]);
        }

        // Barbershop says what's built: its own chorus and original tags, never "classic tags".
        $this->get('/courses/barbershop')->assertOk()
            ->assertSee((string) Lang::get('site.courses.pages.barbershop.titles.inside'))
            ->assertSee('Down by the Old Mill Stream')
            ->assertDontSee('Sweet Adeline')
            ->assertDontSee('classic tags');
    }

    public function test_the_course_groups_follow_the_apps_catalogue_and_keep_every_course(): void
    {
        $catalogue = collect((array) json_decode((string) file_get_contents(dirname(base_path()).'/web/course/plus/courses.json'), true)['courses'])
            ->filter(fn (array $c): bool => isset($c['group']))->sortBy('order');
        $groups = SitePages::courseGroups();

        $this->assertSame(array_keys((array) Lang::get('site.courses.groups')), array_keys($groups));

        foreach ($groups as $key => $group) {
            $this->assertNotSame('', $group['name']);
            $listed = $catalogue->where('group', $key)->map(fn (array $c): string => $c['slug'] ?? $c['id'])->values()->all();
            $this->assertSame($listed, array_slice($group['courses'], 0, count($listed)), $key);
        }

        // Every course with a page is in exactly one group.
        $all = array_merge(...array_values(array_column($groups, 'courses')));
        $this->assertSame(count($all), count(array_unique($all)));
        foreach (SitePages::COURSES as $slug) {
            $this->assertContains($slug, $all, $slug);
            $this->assertNotNull(SitePages::courseGroup($slug));
        }
    }

    public function test_an_open_feature_reads_in_the_present_and_hum_a_tune_needs_no_ideas(): void
    {
        app('translator')->addLines([
            'site.features.pages.your-tune.now.summary' => 'Hum it, and see it as notes.',
            'site.features.pages.your-tune.now.lead' => 'Here now.',
        ], 'en');
        $sections = (array) Lang::get('site.features.pages.your-tune.sections');
        $first = (string) ($sections[0]['heading'] ?? '');

        // Coming: the coming words, whatever `now` says.
        app(Flags::class)->set('your_tune', 'off', null, 'Not yet');
        $this->assertNotSame('Here now.', SitePages::page('features', 'your-tune')['lead'] ?? null);

        // Open, with Ideas still off: the present words, and a section hidden by its heading.
        app(Flags::class)->set('your_tune', 'on', null, 'Hum a tune opens');
        $this->assertFalse(SitePages::soon('your-tune'));
        app('translator')->addLines(['site.features.pages.your-tune.now.sections.'.$first.'.hide' => true], 'en');
        $page = SitePages::page('features', 'your-tune');
        $this->assertSame('Here now.', $page['lead']);
        $this->assertNotContains($first, array_column($page['sections'], 'heading'));
        $this->assertSame('Hum it, and see it as notes.', SitePages::cards(['your-tune'])[0]['summary']);
    }
}
