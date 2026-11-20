<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Admin\StaffDevice;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * Hear yourself as a choir (docs/ai-voices-build-plan.md, phase 3's launch page): ready but unpublished. Not found while
 * own_choir is off, except on an admin's device; once on, everyone's, in English and Spanish; its words lead with the
 * arrangement and say AI plainly.
 */
final class YourChoirPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ops.indexing' => true, 'flags.own_choir' => ['kind' => 'feature', 'values' => ['off', 'on']]]);
    }

    public function test_while_off_only_an_admins_device_sees_it_and_never_in_search(): void
    {
        $this->get('/your-choir')->assertNotFound();
        $this->get('/es/your-choir')->assertNotFound();
        config(['flags' => Arr::except((array) config('flags'), 'own_choir')]);
        $this->get('/your-choir')->assertNotFound();
        config(['flags.own_choir' => ['kind' => 'feature', 'values' => ['off', 'on']]]);

        $admin = $this->withUnencryptedCookie(StaffDevice::PREVIEW_COOKIE, StaffDevice::previewCookie(request())->getValue())
            ->get('/your-choir')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertStringContainsString('no-store', (string) $admin->headers->get('Cache-Control'));
        $this->assertStringContainsString('<meta name="robots" content="noindex">', (string) $admin->getContent());
    }

    public function test_once_on_it_leads_with_the_arrangement_says_ai_plainly_and_opens_the_app_on_silent_night(): void
    {
        app(Flags::class)->set('own_choir', 'on', null, 'Own choir live');

        $html = (string) $this->get('/your-choir')->assertOk()->assertHeaderMissing('X-Robots-Tag')->getContent();
        $this->assertStringContainsString(e(__('site.your_choir.headline')), $html);
        $this->assertStringContainsString('song=silent-night&amp;choir=1', $html);
        $this->assertStringContainsString('href="/about/ai-voices"', $html);
        $this->assertStringNotContainsString('AI', (string) __('site.your_choir.headline'));
        $this->assertStringNotContainsString('AI', (string) __('site.your_choir.lead'));
        $this->assertStringContainsString('AI', (string) __('site.your_choir.honest.body'));
        foreach (['/', '/songs', '/about', '/sitemap.xml'] as $path) {
            $this->assertStringNotContainsString('/your-choir', (string) $this->get($path)->getContent(), $path);
        }
    }

    public function test_it_answers_in_spanish(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        app(Flags::class)->set('own_choir', 'on', null, 'Own choir live');

        $this->get('/es/your-choir')->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertSee('Tú, como un coro entero')
            ->assertSee('Pruébalo con Noche de paz');
    }
}
