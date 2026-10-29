<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Enums\TemplateAccess;
use App\Models\Flag;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Flags;
use App\Support\Pages\Landing;
use App\Support\Pages\MudeoArrival;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The landing page: the hero that plays a song's first line, the links into the app carrying
 * where the visitor came from, the mudeo letter only for mudeo's singers, and the link card.
 */
final class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_hero_plays_the_first_line_of_amazing_grace_in_four_voices(): void
    {
        $this->amazingGrace();

        $page = $this->get('/')->assertOk();
        $hero = $page->viewData('hero');

        $this->assertSame('amazing-grace', $hero['song']);
        $this->assertSame(['soprano', 'alto', 'tenor', 'bass'], array_column($hero['parts'], 'key'));
        $this->assertSame([1, 2, 3, 4], array_column($hero['parts'], 'color'));
        foreach ($hero['parts'] as $part) {
            $this->assertNotSame([], $part['notes']);
            foreach ($part['notes'] as $note) {
                $this->assertLessThan(Landing::HERO_END_BEAT, $note['beat']);
                $this->assertLessThanOrEqual(Landing::HERO_END_BEAT, $note['beat'] + $note['length']);
            }
        }
        $this->assertSame('Amazing grace, how sweet the sound that saved a wretch like me.', Landing::sentence($hero['words']));

        // The player, its data, and the words for anyone who can't hear it.
        $page->assertSee('data-song', false)->assertSee('/js/public/mixer.js', false);
        $page->assertSee('data-voice="alto"', false)->assertSee('data-play', false);
        $this->assertSame('soprano', $hero['you'], 'Melody first.');
        $page->assertSee('"Amazing grace, how sweet the sound that saved a wretch like me."');
    }

    public function test_without_the_song_the_landing_has_no_player(): void
    {
        $page = $this->get('/')->assertOk();

        $this->assertNull($page->viewData('hero'));
        $page->assertDontSee('class="s-mixer"', false)->assertSee('href="/learn/1-comfortable"', false);
    }

    public function test_where_the_visitor_came_from_goes_on_into_the_app(): void
    {
        $this->get('/?ref=hn')->assertOk()
            ->assertSee('href="/learn/1-comfortable?ref=hn"', false)
            ->assertSee('href="/?ref=hn"', false);

        // Anything that isn't one of ours in shape is dropped.
        $this->get('/?ref=%3Cscript%3E')->assertSee('href="/learn/1-comfortable"', false)->assertDontSee('ref=%3C', false);
        $this->get('/?ref[]=x')->assertOk()->assertSee('href="/learn/1-comfortable"', false);
        $this->assertNull(Landing::ref(str_repeat('a', 33)));

        // With the hosts apart, the links go to the app's host.
        config(['app.url' => 'https://app.stackapella.test', 'hosts.public_url' => 'https://beta.stackapella.test']);
        $this->assertSame('https://app.stackapella.test/learn/1-comfortable?ref=hn', Landing::links('hn')['start']);
    }

    public function test_only_people_who_came_from_mudeo_see_anything_about_mudeo(): void
    {
        config(['hosts.pass_domain' => '.stackapella.test']);

        $everyone = $this->get('/')->assertOk();
        $this->assertStringNotContainsStringIgnoringCase('mudeo', (string) $everyone->getContent());
        $everyone->assertCookieMissing(MudeoArrival::COOKIE);
        $this->assertStringNotContainsStringIgnoringCase('mudeo', (string) $this->get('/?ref=hn')->getContent());

        // A mudeo link: the banner and the letter, remembered for the visit across both hosts.
        $arrival = $this->get('/?ref=mudeo')->assertOk()
            ->assertSee(__('pages.landing.mudeo.banner'))
            ->assertSee(__('pages.landing.mudeo.title'))
            ->assertSee('href="/learn/1-comfortable?ref=mudeo"', false);
        $cookie = collect($arrival->headers->getCookies())->first(fn ($c) => $c->getName() === MudeoArrival::COOKIE);
        $this->assertNotNull($cookie);
        $this->assertSame('1', $cookie->getValue());
        $this->assertSame(0, $cookie->getExpiresTime());
        $this->assertSame('.stackapella.test', $cookie->getDomain());
        $this->assertTrue($cookie->isHttpOnly());

        // Back later in the visit (from another page, or from the early-access link): still them,
        // and their links still say so; the cookie isn't set again.
        $back = $this->withUnencryptedCookie(MudeoArrival::COOKIE, '1')->get('/')->assertOk()
            ->assertSee(__('pages.landing.mudeo.title'))
            ->assertSee('href="/learn/1-comfortable?ref=mudeo"', false);
        $this->assertSame([], $back->headers->getCookies());
    }

    public function test_no_list_of_songs_and_no_songs_link_start_singing_leads_into_the_app(): void
    {
        $this->amazingGrace();

        $page = $this->get('/')->assertOk();

        // Hillel: a short catalogue looks thin; the song pages stay, for search and shared links.
        $page->assertDontSee('href="/songs', false);
        $this->get('/pricing')->assertOk()->assertDontSee('href="/songs', false);
        $this->get('/songs')->assertOk();
    }

    public function test_whats_on_the_way_follows_each_features_flag(): void
    {
        $page = $this->get('/')->assertOk();
        $page->assertSee(__('pages.landing.way_title'))->assertSee(__('pages.landing.way.gallery.0'));
        $this->assertTrue(collect($page->viewData('way'))->firstWhere('key', 'gallery')['soon']);
        // Each chip goes to its feature's page, which says "Coming soon" until it opens.
        $page->assertSee('href="/features/gallery"', false)->assertDontSee(__('pages.landing.way.gallery.2'));

        Flag::query()->create(['name' => 'gallery', 'value' => 'on']);
        $this->app->forgetScopedInstances();

        $open = $this->get('/')->assertOk();
        $this->assertFalse(collect($open->viewData('way'))->firstWhere('key', 'gallery')['soon']);
        $open->assertSee('href="/features/gallery"', false)->assertSee(__('pages.landing.way.gallery.2'));

        // Everything open: the section goes.
        foreach (array_keys(Landing::ON_THE_WAY) as $feature) {
            $flag = config('site.features.'.$feature.'.flag');
            is_string($flag) ? Flag::query()->updateOrCreate(['name' => $flag], ['value' => 'on']) : config(['site.features.'.$feature.'.live' => true]);
        }
        $this->app->forgetScopedInstances();
        $this->get('/')->assertOk()->assertDontSee(__('pages.landing.way_title'));
    }

    public function test_the_chord_promise_waits_for_the_arm_while_onb1_enrols(): void
    {
        // No experiment running: the promise shows to everyone, as lesson 1 makes it.
        $this->get('/')->assertOk()
            ->assertSee(__('pages.landing.chord_promise'))
            ->assertDontSee('data-variant-except', false);

        // While onb1 enrols, the control arm gets no chord moment: the line waits, hidden, for the
        // page to learn this device's arm (js/public/funnel.js).
        config(['counts.enabled' => true, 'counts.experiments' => [['id' => 'onb1', 'what' => 'The chord moment', 'arms' => ['a', 'b'], 'start' => now()->toDateString(), 'end' => null, 'plannedPerArm' => 10, 'primary' => 'f_lesson3', 'guardrail' => 'f_lesson2']]]);
        Cache::forget('funnel.current');
        $this->get('/')->assertOk()->assertSee('hidden data-variant-except="onb1-a"', false);
    }

    public function test_the_plans_numbers_come_from_config(): void
    {
        config(['usage.plans.free.projects' => 5, 'billing.trial_days' => 10, 'billing.prices.month' => 'price_m', 'billing.prices.year' => 'price_y']);

        $this->get('/')->assertOk()
            ->assertSee('5 saved songs')
            ->assertSee(__('pages.landing.plans_get'))->assertSee(__('pages.landing.plans_trial', ['days' => 10]))
            ->assertSee('$3.33')->assertSee('$39.99')->assertSee('$0<', false);

        config(['billing.prices.month' => null]);
        $this->get('/')->assertSee(__('pages.landing.plans_paused'))->assertDontSee(__('pages.landing.plans_get'));
    }

    public function test_hum_a_tune_is_on_the_way_until_it_opens_and_your_own_song_leads_into_the_creator(): void
    {
        $page = $this->get('/?ref=hn')->assertOk();
        $page->assertSee(__('pages.landing.way.your-tune.0'))->assertSee('href="/features/your-tune"', false)->assertDontSee(__('pages.landing.way.your-tune.2'));
        // Today's way to make your own: Sing it in the creator, from the card after the steps (the hero and the last word keep one CTA).
        $this->assertSame(1, substr_count((string) $page->getContent(), 'href="/create?ref=hn"'));

        app(Flags::class)->set('your_tune', 'on', null, 'Hum a tune opens');
        $this->app->forgetScopedInstances();

        $this->get('/')->assertSee(__('pages.landing.way.your-tune.2'));
    }

    public function test_the_landing_is_never_cached_as_one_visitors_page(): void
    {
        $page = $this->get('/')->assertOk();

        $this->assertStringContainsString('no-cache', (string) $page->headers->get('Cache-Control'));
        $this->assertStringContainsString('Cookie', (string) $page->headers->get('Vary'));
        // Only the visit's mark says mudeo: any other value is no one's.
        $this->assertStringNotContainsStringIgnoringCase('mudeo', (string) $this->withUnencryptedCookie(MudeoArrival::COOKIE, 'yes')->get('/')->getContent());
    }

    public function test_the_link_card_and_search_settings(): void
    {
        config(['ops.indexing' => false]);
        $this->assertFileExists(dirname(base_path()).'/web/icons/og-landing.png');

        $page = $this->get('/')->assertOk();
        $page->assertSee('<meta property="og:image" content="'.url('/icons/og-landing.png').'">', false);
        $page->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
        $page->assertSee('<link rel="canonical" href="'.rtrim((string) config('app.url'), '/').'/">', false);
        $page->assertHeader('X-Robots-Tag', 'noindex');
    }

    private function amazingGrace(): void
    {
        $template = Template::factory()->create(['slug' => 'amazing-grace', 'title' => 'Amazing Grace', 'access' => TemplateAccess::Free]);
        $version = TemplateVersion::factory()->create(['template_id' => $template->id]);
        $template->forceFill(['current_version_id' => $version->id])->save();
    }
}
