<?php

declare(strict_types=1);

namespace Tests\Feature\Pages;

use App\Actions\Counts\Funnel;
use App\Enums\TemplateAccess;
use App\Models\Experiment;
use App\Models\Template;
use App\Models\TemplateVersion;
use App\Support\Pages\Landing;
use App\Support\Pages\MudeoArrival;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The landing page, what it carries for growth and for search whatever its design: the links into the app carrying
 * where the visitor came from, the mudeo letter only for mudeo's singers, lesson 1's chord promise and its experiment,
 * the plans' numbers, and the link card. Its stage and its scenes are LandingStagePageTest's.
 */
final class LandingPageTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_the_chord_promise_waits_for_the_arm_while_onb1_enrols(): void
    {
        // No experiment running: the promise shows to everyone, as lesson 1 makes it.
        $this->get('/')->assertOk()
            ->assertSee(__('pages.landing.chord_promise'))
            ->assertDontSee('data-variant-except', false);

        // While onb1 enrols, the control arm gets no chord moment: the line waits, hidden, for the
        // page to learn this device's arm (js/public/funnel.js).
        config(['counts.enabled' => true]);
        Experiment::query()->where('key', 'onb1')->update(['starts_on' => now()->toDateString(), 'planned_per_arm' => 10]);
        Cache::forget(Funnel::CACHE);
        Cache::forget('funnel.current');
        $this->get('/')->assertOk()->assertSee('hidden data-variant-except="onb1-a"', false);
    }

    public function test_the_plans_numbers_come_from_config(): void
    {
        config(['usage.plans.free.storage_bytes' => 2 * 1024 ** 3, 'usage.plans.plus.storage_bytes' => 70 * 1024 ** 3, 'billing.trial_days' => 10, 'billing.prices.month' => 'price_m', 'billing.prices.year' => 'price_y']);

        $this->get('/')->assertOk()
            ->assertSee('2 GB for your recordings')->assertSee('70 GB for your recordings')
            ->assertSee('$3.33')->assertSee('$39.99')->assertSee('$0<', false);

        config(['billing.prices.month' => null]);
        $this->get('/')->assertSee(__('pages.landing.plans_paused'));
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
