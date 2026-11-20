<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\AiVoice;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\User;
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

        $preview = StaffDevice::previewCookie(request())->getValue();
        $this->assertNotNull($preview);
        $admin = $this->withUnencryptedCookie(StaffDevice::PREVIEW_COOKIE, $preview)
            ->get('/your-choir')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertStringContainsString('no-store', (string) $admin->headers->get('Cache-Control'));
        $this->assertStringContainsString('<meta name="robots" content="noindex">', (string) $admin->getContent());
    }

    public function test_once_on_it_leads_with_the_arrangement_says_ai_plainly_and_opens_the_app_on_silent_night(): void
    {
        app(Flags::class)->set('own_choir', 'on', null, 'Own choir live');

        $html = (string) $this->get('/your-choir')->assertOk()->assertHeaderMissing('X-Robots-Tag')->getContent();
        $this->assertStringContainsString(e(__('site.your_choir.headline_em')), $html);
        $this->assertStringContainsString('song=silent-night&amp;choir=1', $html);
        $this->assertStringContainsString('href="/about/ai-voices"', $html);
        $this->assertStringNotContainsString('AI', __('site.your_choir.headline_before').__('site.your_choir.headline_em'));
        // AI named in the hero once (design rules), never in the headline, and once among the voice's facts.
        $this->assertSame(1, substr_count((string) __('site.your_choir.lead'), 'AI'));
        $this->assertStringContainsString('AI', implode(' ', (array) __('site.your_choir.voice.facts')));
        // No photos on this page (Hillel, 2026-11-20): the library's songs are lanes of their tunes' notes.
        $this->assertStringNotContainsString('<img', $html);
        // Its own link card, in the stage's look (tool/link_cards.py), and its words for a share.
        $this->assertStringContainsString('<meta property="og:image" content="'.url('/icons/og/your-choir.png').'">', $html);
        $this->assertStringContainsString('<meta property="og:title" content="'.e(__('site.your_choir.title')), $html);
        $this->assertFileExists(dirname(base_path()).'/web/icons/og/your-choir.png');
        // No stage without the demo's voices: the page still reads, and plays nothing.
        $this->assertStringNotContainsString('data-yc-play', $html);
        $this->assertStringNotContainsString('data-stage', $html);
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
            ->assertSee('coro entero')
            ->assertSee('Pruébalo con Noche de paz')
            ->assertSee('<meta property="og:image" content="'.url('/icons/og/es-your-choir.png').'">', false);
    }

    public function test_the_stage_plays_her_real_guide_then_her_ai_voice_only_when_all_is_there_and_she_chose_a_credit(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        app(Flags::class)->set('own_choir', 'on', null, 'Own choir live');
        $grace = Template::query()->where('slug', 'amazing-grace')->firstOrFail();
        $night = Template::query()->where('slug', 'silent-night')->firstOrFail();
        $singer = User::factory()->create(['name' => 'Daniella Goldfine']);
        TemplateGuide::factory()->create(['template_version_id' => $grace->current_version_id, 'part_key' => 'soprano', 'credit_user_id' => $singer->id]);
        $voice = AiVoice::factory()->consented()->credited()->create(['based_on' => 'Daniella']);
        TemplateGuide::factory()->ai($voice)->create(['template_version_id' => $night->current_version_id, 'part_key' => 'soprano']);
        $alto = TemplateGuide::factory()->ai($voice)->create(['template_version_id' => $night->current_version_id, 'part_key' => 'alto']);
        // The demo's voice, and the song's default real guide (whatever a local .env names for screenshots).
        config(['site.voice_demo.ai_voice' => $voice->id, 'site.voice_demo.real.guide' => 0]);

        // AI voices off for everyone: no stage, no player.
        $this->assertStringNotContainsString('data-yc-play', (string) $this->get('/your-choir')->assertOk()->getContent());

        app(Flags::class)->set('ai_voices', 'on', null, 'AI voices');
        $html = (string) $this->get('/your-choir')->assertOk()->getContent();
        $this->assertStringContainsString('data-stage', $html);
        $this->assertStringContainsString('<script type="module" src="/js/public/your-choir.js"></script>', $html);
        // The library's four songs, each a lane of its tune's opening notes, and still no photo.
        $this->assertSame(4, substr_count($html, 'class="yc-song"'));
        $this->assertStringNotContainsString('<img', $html);
        // One real lane and two AI ones, each saying once which it is; the real one first, on its own song.
        $this->assertSame(1, substr_count($html, 'data-seg="real"'));
        $this->assertSame(2, substr_count($html, 'data-seg="ai"'));
        $this->assertSame(1, substr_count($html, '>'.e(__('site.your_choir.stage.real_chip')).'<'));
        $this->assertSame(2, substr_count($html, '>'.e(__('site.your_choir.stage.ai_chip')).'<'));
        $this->assertLessThan(strpos($html, 'data-seg="ai"'), strpos($html, 'data-seg="real"'));
        $this->assertStringContainsString(e(__('site.your_choir.s2.body', ['name' => 'Daniella', 'song' => 'Silent Night'])), $html);
        $this->assertStringContainsString('Amazing Grace', $html);
        // Two parts: no "whole choir" scene, and nothing joins.
        $this->assertStringNotContainsString('data-s="four"', $html);
        $this->assertStringNotContainsString('data-stage data-four', $html);

        // The player's timeline: her song from the start, the AI one after it, as the app serves the voices.
        $play = $this->play($html);
        $this->assertSame(['real', 'ai'], array_column($play['segments'], 'key'));
        $this->assertGreaterThan($play['segments'][0]['start'], $play['segments'][1]['start']);
        $this->assertSame(['soprano', 'alto'], array_column($play['segments'][1]['voices'], 'part'));
        $this->assertSame([0, 0], array_map('intval', array_column($play['segments'][1]['voices'], 'join')));

        // Tenor and bass once they're published: the whole choir's scene, and they join at a phrase in the middle.
        TemplateGuide::factory()->ai($voice)->create(['template_version_id' => $night->current_version_id, 'part_key' => 'tenor']);
        TemplateGuide::factory()->ai($voice)->create(['template_version_id' => $night->current_version_id, 'part_key' => 'bass']);
        $html = (string) $this->get('/your-choir')->getContent();
        $this->assertStringContainsString('data-s="four"', $html);
        $this->assertSame(4, substr_count($html, 'data-seg="ai"'));
        $joins = array_column($this->play($html)['segments'][1]['voices'], 'join', 'part');
        $this->assertSame(0.0, (float) $joins['soprano']);
        $this->assertGreaterThan(0, $joins['tenor']);
        $this->assertSame($joins['tenor'], $joins['bass']);

        // Her credit taken back, or a required part not there: no stage.
        $voice->forceFill(['credited' => false])->save();
        $this->assertStringNotContainsString('data-yc-play', (string) $this->get('/your-choir')->getContent());
        $voice->forceFill(['credited' => true])->save();
        $alto->delete();
        $this->assertStringNotContainsString('data-yc-play', (string) $this->get('/your-choir')->getContent());
    }

    public function test_a_song_of_your_own_is_offered_only_once_its_switch_is_on(): void
    {
        app(Flags::class)->set('own_choir', 'on', null, 'Own choir live');

        $off = (string) $this->get('/your-choir')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-your-choir-hum', $off);
        $this->assertStringNotContainsString('/create?', $off);
        $this->assertStringContainsString(e(__('site.your_choir.lead')), $off);
        $this->assertStringNotContainsString(e((string) __('site.your_choir.faq_hum')[0]), $off);

        config(['flags.own_choir_songs' => ['kind' => 'feature', 'values' => ['off', 'on']]]);
        app(Flags::class)->set('own_choir_songs', 'on', null, 'Own songs live');
        $on = (string) $this->get('/your-choir')->getContent();
        $this->assertStringContainsString('data-your-choir-hum', $on);
        $this->assertStringContainsString('/create?ref=your-choir', $on);
        $this->assertStringContainsString(e(__('site.your_choir.lead_hum')), $on);
        $this->assertStringContainsString(e((string) __('site.your_choir.faq_hum')[0]), $on);
        $this->assertStringContainsString(e(__('site.your_choir.own_songs_hum')), $on);
    }

    /**
     * The player's data, as the page gives it to its script.
     *
     * @return array<string, mixed>
     */
    private function play(string $html): array
    {
        $found = preg_match('#<script type="application/json" data-yc-data>(.*?)</script>#s', $html, $m) === 1 ? $m[1] : '';
        $this->assertNotSame('', $found, 'the page gives its player its data');

        return (array) json_decode($found, true, 512, JSON_THROW_ON_ERROR);
    }
}
