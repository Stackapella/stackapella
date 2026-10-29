<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Site\OpenSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Open source on the site (docs/open-source-plan.md, 5.1): nothing says so, and its pages aren't
 * there, until the open_source flag is on; then the pages answer and every mention shows.
 */
final class OpenSourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_while_the_flag_is_off_its_pages_are_not_there_and_nothing_mentions_it(): void
    {
        $this->get('/open-source')->assertNotFound();
        $this->get('/selfhost')->assertNotFound();
        $this->get('/legal/self-hosting-terms')->assertNotFound();

        foreach (['/', '/about', '/press', '/features', '/pricing'] as $path) {
            $this->get($path)->assertOk()
                ->assertDontSee('href="/open-source"', false)
                ->assertDontSee('href="/selfhost"', false)
                ->assertDontSee(__('site.press.open_source_label'))
                ->assertDontSee((string) config('site.source.repo'), false);
        }
        $this->get('/')->assertDontSee(__('pages.landing.faq_open_source.0'))->assertDontSee(__('pages.landing.faq_privacy_check'));
        $this->get('/pricing')->assertDontSee(__('pages.pricing.faq_selfhost.0'));
        $this->get('/sitemap.xml')->assertDontSee('/open-source')->assertDontSee('self-hosting-terms');
    }

    public function test_once_the_flag_is_on_the_pages_answer_and_the_site_says_so(): void
    {
        app(Flags::class)->set('open_source', 'on', null, 'The repos are public');

        $this->get('/open-source')->assertOk()
            ->assertSee(__('site.open_source.headline'))
            ->assertSee('href="'.config('site.source.repo').'"', false)
            ->assertSee('href="'.config('site.source.repo').'/blob/main/web/engine/engine.js"', false);
        // The settings the README's Running it yourself names.
        $this->get('/selfhost')->assertOk()->assertSee(__('site.selfhost.headline'))->assertSee('php artisan app:doctor')
            ->assertSee('SELF_HOSTED=true')->assertSee('app:templates:sync')->assertSee('BEHIND_CLOUDFLARE')->assertSee('SITE_CONTACT_EMAIL')->assertSee('SELF_HOSTED_LEGAL_PATH')
            ->assertSee('href="'.OpenSource::repo('THIRD-PARTY-NOTICES.md').'"', false);
        $this->get('/open-source')->assertSee('href="'.OpenSource::repo('CONTRIBUTING.md').'"', false)->assertSee('href="'.OpenSource::repo('SECURITY.md').'"', false);
        $this->get('/self-host')->assertStatus(301)->assertRedirect('/selfhost');
        $this->get('/legal/self-hosting-terms')->assertOk()->assertSee(__('site.selfhost.terms'));

        $this->get('/about')->assertSee('href="/open-source"', false)->assertSee(__('site.about.open_source_title'));
        $this->get('/press')->assertSee(__('site.press.open_source_label'))->assertSee((string) config('site.source.repo'));
        $this->get('/features')->assertSee(__('site.features_open.title'));
        $this->get('/')->assertSee('href="/open-source"', false)->assertSee(__('pages.landing.faq_open_source.0'))->assertSee(__('pages.landing.faq_privacy_check'));
        $this->get('/pricing')->assertSee(__('pages.pricing.faq_selfhost.0'));
        $this->get('/sitemap.xml')->assertSee('/open-source')->assertSee('/selfhost')->assertSee('/legal/self-hosting-terms');
    }

    public function test_the_licences_credit_shows_once_open_and_always_on_a_self_hosted_install(): void
    {
        $credit = 'href="'.OpenSource::CREDIT_URL.'"';

        $this->get('/about')->assertOk()->assertDontSee($credit, false)->assertDontSee(__('pages.nav.credit'));

        app(Flags::class)->set('open_source', 'on', null, 'The repos are public');
        $this->get('/about')->assertSee($credit, false)->assertSee(__('pages.nav.credit'));

        app(Flags::class)->set('open_source', 'off', null, 'A fresh install');
        config(['selfhost.enabled' => true, 'site.founder' => 'Someone Else']);
        foreach (['/', '/about', '/pricing', '/help'] as $path) {
            $this->get($path)->assertOk()->assertSee($credit, false)->assertSee('Hillel Coren')->assertSee('stackapella.com');
        }
    }

    public function test_every_file_the_page_points_into_is_in_the_repo(): void
    {
        foreach ([...array_column((array) __('site.open_source.code'), 0), 'CONTRIBUTING.md', 'SECURITY.md', 'TRADEMARK.md', 'THIRD-PARTY-NOTICES.md', 'CONTENT-LICENSE', 'README.md'] as $path) {
            $this->assertFileExists(dirname(base_path()).'/'.$path);
        }
    }
}
