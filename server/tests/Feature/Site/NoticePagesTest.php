<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * The short pages in the site's look: the error pages (words only, a way on, nothing from inside),
 * the gate before launch, and the unsubscribe pages.
 */
final class NoticePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_error_page_is_words_and_a_way_on(): void
    {
        foreach (['403' => 'forbidden', '404' => 'not_found', '419' => 'expired', '500' => 'server', '503' => 'maintenance'] as $status => $key) {
            $code = (string) $status;
            $html = view('errors.'.$code, ['exception' => new RuntimeException('secret detail /var/www')])->render();

            $this->assertStringContainsString(e(__('errors.'.$key.'.title')), $html, $code);
            $this->assertStringContainsString(e(__('errors.home')), $html, $code);
            $this->assertStringNotContainsString('secret detail', $html, $code);
            $this->assertSame(1, substr_count($html, '<h1'), $code);
            $this->assertStringContainsString('class="s-wrap s-top"', $html, $code);
        }

        $this->assertStringContainsString('5 minutes', view('errors.429', ['minutes' => 5])->render());
    }

    public function test_a_page_that_isnt_there_says_so_in_the_sites_look(): void
    {
        $this->get('/features/teleport')->assertNotFound()->assertSee(__('errors.not_found.title'))->assertSee('href="/features"', false);
        $this->get('/coach/nobody_here')->assertNotFound()->assertSee(__('pages.coach.gone'));
        $this->get('/h/nothing')->assertNotFound()->assertSee(__('sharing.page.missing'))->assertSee('class="s-wrap s-top"', false);
        $this->get('/unsubscribe/1')->assertForbidden()->assertSee(__('errors.forbidden.title'));
    }

    public function test_a_missing_page_says_tell_us_into_the_apps_contact_form_on_the_apps_host(): void
    {
        // As production, two hosts: the site's 404 opens Contact us on the app's host, with where it came from and the
        // missing page's path as text (never its query), and that address is the app's own page.
        config(['app.url' => 'https://app.stackapella.test', 'hosts.public_url' => 'https://stackapella.test', 'app.is_nexus' => true]);
        $tell = 'href="https://app.stackapella.test/contact?from=site-404&amp;page=%2Ffeatures%2Fteleport" data-tell-us>'.e(__('errors.tell.link')).'</a>';

        $gone = (string) $this->get('https://stackapella.test/features/teleport?token=secret')->assertNotFound()->getContent();
        $this->assertStringContainsString(e(__('errors.tell.lead')).' <a '.$tell, $gone);
        $this->assertStringNotContainsString('secret', $gone);

        $html = view('errors.404')->render();
        $this->assertStringContainsString('data-tell-us>', $html);
        $this->assertStringContainsString('https://app.stackapella.test/contact?from=site-404&amp;page=', $html);

        $this->get('https://app.stackapella.test/contact?from=site-404&page=%2Ffeatures%2Fteleport')->assertOk()->assertSee('<div id="app"', false);

        // In Spanish, the same link in its words.
        $this->get('https://stackapella.test/es/features/teleport')->assertNotFound()->assertSee('¿Esperabas algo aquí? <a', false)->assertSee('>Avísanos</a>.', false);
    }

    public function test_the_500_page_offers_an_email_since_the_app_may_be_down_too(): void
    {
        config(['site.contact_email' => 'contact@example.com']);

        $html = view('errors.500', ['exception' => new RuntimeException('secret detail')])->render();
        $this->assertStringContainsString('Email us at <a href="mailto:contact@example.com" data-email-us>contact@example.com</a>', $html);
    }

    public function test_the_gate_says_what_stackapella_is_and_how_invited_singers_get_in(): void
    {
        $html = view('coming-soon')->render();

        $this->assertStringContainsString(e(__('meta.coming_soon.body')), $html);
        $this->assertStringContainsString('/signin', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }
}
