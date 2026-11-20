<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Admin\StaffDevice;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Tests\TestCase;

/**
 * About AI voices (docs/ai-voices-build-plan.md, marketing item 2): unlisted; while the ai_voices switch is off, not found
 * except on an admin's device, which sees it out of search and kept by nobody; in Spanish too.
 */
final class AiVoicesPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ops.indexing' => true, 'flags.ai_voices' => ['kind' => 'feature', 'values' => ['off', 'on']]]);
    }

    public function test_it_says_what_they_are_shows_the_label_and_how_to_have_one_removed(): void
    {
        app(Flags::class)->set('ai_voices', 'on', null, 'AI voices live');

        $this->get('/about/ai-voices')->assertOk()
            ->assertSee(__('site.ai_voices.headline'))
            ->assertSee(__('pages.song.ai.credit'))
            ->assertSee(__('pages.song.ai.wanted'))
            ->assertSee(__('site.ai_voices.sections.yours.body'))
            ->assertDontSee(__('site.ai_voices.sections.yours.choir'))
            ->assertSee('href="mailto:'.config('site.contact_email').'"', false);
    }

    public function test_your_voice_says_what_own_voice_songs_keep_once_they_are_on(): void
    {
        config(['flags.own_choir' => ['kind' => 'feature', 'values' => ['off', 'on']]]);
        app(Flags::class)->set('ai_voices', 'on', null, 'AI voices live');
        app(Flags::class)->set('own_choir', 'on', null, 'Own choir live');

        $this->get('/about/ai-voices')->assertOk()
            ->assertSee(__('site.ai_voices.sections.yours.choir'))
            ->assertDontSee(__('site.ai_voices.sections.yours.body'));
    }

    public function test_while_the_switch_is_off_only_an_admins_device_sees_it_and_never_in_search(): void
    {
        $this->get('/about/ai-voices')->assertNotFound();
        $this->get('/es/about/ai-voices')->assertNotFound();
        // Before the switch is even defined, the same.
        config(['flags' => Arr::except((array) config('flags'), 'ai_voices')]);
        $this->get('/about/ai-voices')->assertNotFound();
        config(['flags.ai_voices' => ['kind' => 'feature', 'values' => ['off', 'on']]]);
        // The staff cookie's plain "1" (anyone can set it) is no key.
        $this->withUnencryptedCookie(StaffDevice::PREVIEW_COOKIE, '1')->get('/about/ai-voices')->assertNotFound();

        $preview = StaffDevice::previewCookie(request())->getValue();
        $this->assertNotNull($preview);
        $admin = $this->withUnencryptedCookie(StaffDevice::PREVIEW_COOKIE, $preview)
            ->get('/about/ai-voices')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertStringContainsString('no-store', (string) $admin->headers->get('Cache-Control'));
        $this->assertStringContainsString('<meta name="robots" content="noindex">', (string) $admin->getContent());
    }

    public function test_once_on_it_is_everyones_and_still_unlinked(): void
    {
        app(Flags::class)->set('ai_voices', 'on', null, 'AI voices live');

        $on = $this->get('/about/ai-voices')->assertOk()->assertHeaderMissing('X-Robots-Tag');
        $this->assertStringNotContainsString('<meta name="robots" content="noindex">', (string) $on->getContent());

        foreach (['/', '/about', '/songs', '/features/real-voices', '/sitemap.xml'] as $path) {
            $this->assertStringNotContainsString('/about/ai-voices', (string) $this->get($path)->getContent(), $path);
        }
    }

    public function test_it_answers_in_spanish(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        app(Flags::class)->set('ai_voices', 'on', null, 'AI voices live');

        $this->get('/es/about/ai-voices')->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertSee('Sobre las voces de IA')
            ->assertSee('Voz de IA, a la espera de una voz real')
            ->assertSee('Se busca una voz real: sé la voz')
            ->assertSee('Nada de lo que cantas en la app se convierte en una voz de IA, salvo que aceptes nuestra licencia de voz de IA.');
    }

    public function test_own_voice_songs_alone_make_it_everyones_too(): void
    {
        config(['flags.own_choir' => ['kind' => 'feature', 'values' => ['off', 'on']]]);
        app(Flags::class)->set('own_choir', 'on', null, 'Own choir live');

        $this->get('/about/ai-voices')->assertOk()->assertHeaderMissing('X-Robots-Tag')
            ->assertSee(__('site.ai_voices.sections.yours.choir'));
    }
}
