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
        $this->get('/ai-harmony-singing')->assertNotFound();
        $this->get('/es/armonias-con-ia')->assertNotFound();
        config(['flags' => Arr::except((array) config('flags'), 'own_choir')]);
        $this->get('/ai-harmony-singing')->assertNotFound();
        config(['flags.own_choir' => ['kind' => 'feature', 'values' => ['off', 'on']]]);

        $preview = StaffDevice::previewCookie(request())->getValue();
        $this->assertNotNull($preview);
        $admin = $this->withUnencryptedCookie(StaffDevice::PREVIEW_COOKIE, $preview)
            ->get('/ai-harmony-singing')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');
        $this->assertStringContainsString('no-store', (string) $admin->headers->get('Cache-Control'));
        $this->assertStringContainsString('<meta name="robots" content="noindex">', (string) $admin->getContent());
    }

    public function test_once_on_it_leads_with_the_arrangement_says_ai_plainly_and_opens_the_app_on_silent_night(): void
    {
        app(Flags::class)->set('own_choir', 'on', null, 'Own choir live');

        $html = (string) $this->get('/ai-harmony-singing')->assertOk()->assertHeaderMissing('X-Robots-Tag')->getContent();
        $this->assertStringContainsString(e(__('site.your_choir.headline_em')), $html);
        $this->assertStringContainsString('song=happy-birthday&amp;choir=1', $html);
        // Its links into the app say it's AI the visitor came for (the app turns AI on by either), and a visitor's own ref
        // stays the source.
        $this->assertStringContainsString('choir=1&amp;ref=ai-harmony-singing&amp;for=ai', $html);
        $theirs = (string) $this->get('/ai-harmony-singing?ref=reddit-choir')->getContent();
        $this->assertStringContainsString('choir=1&amp;ref=reddit-choir&amp;for=ai', $theirs);
        $this->assertStringNotContainsString('href="/create?ref=ai-harmony-singing', $theirs);
        $this->assertStringContainsString('href="/about/ai-voices"', $html);
        $this->assertStringNotContainsString('AI', __('site.your_choir.headline_before').__('site.your_choir.headline_em'));
        // AI named in the hero's lead (AI harmony singing, Hillel and the SEO check, 2026-11-24), never in the headline, and
        // among the voice's facts.
        $this->assertStringContainsString('AI harmony', (string) __('site.your_choir.lead'));
        $this->assertStringContainsString('AI', implode(' ', (array) __('site.your_choir.voice.facts')));
        // No photos on this page (Hillel, 2026-11-20): the library's songs are lanes of their tunes' notes.
        $this->assertStringNotContainsString('<img', $html);
        // Its own link card, in the stage's look (tool/link_cards.py), and its words for a share.
        $this->assertStringContainsString('<meta property="og:image" content="'.url('/icons/og/ai-harmony-singing.png').'">', $html);
        // Its search title says AI singing; its heading stays the page's name (marketing).
        $this->assertStringContainsString('<meta property="og:title" content="'.e(__('site.your_choir.meta_title')), $html);
        $this->assertStringContainsString(e(__('site.your_choir.headline_em')), $html);
        $this->assertFileExists(dirname(base_path()).'/web/icons/og/ai-harmony-singing.png');
        // No stage without the demo's voices: the page still reads, and plays nothing.
        $this->assertStringNotContainsString('data-yc-play', $html);
        $this->assertStringNotContainsString('data-stage', $html);
        foreach (['/songs', '/about'] as $path) {
            $this->assertStringNotContainsString('/your-choir', (string) $this->get($path)->getContent(), $path);
        }
        // The AI landing is in the sitemap at its own address, never its first one (its help page, /help/your-choir, is too).
        $sitemap = (string) $this->get('/sitemap.xml')->getContent();
        $this->assertMatchesRegularExpression('#<loc>[^<]*/ai-harmony-singing</loc>#', $sitemap);
        $this->assertDoesNotMatchRegularExpression('#<loc>[^<]*(?<!/help)/your-choir</loc>#', $sitemap);
        $this->assertMatchesRegularExpression('#<link rel="canonical" href="[^"]*/ai-harmony-singing">#', $html);
        // The home says nothing of AI, so it doesn't lead here (Hillel, 2026-11-23).
        $home = (string) $this->get('/')->getContent();
        $this->assertStringNotContainsString('/your-choir', $home);
        $this->assertStringNotContainsString('/ai-harmony-singing', $home);
    }

    public function test_its_first_address_moved_for_good_in_each_language_with_the_query_kept(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');

        // One hop each, to the page in the same language: its Spanish address is its own words.
        $this->get('/your-choir?ref=reddit-choir')->assertStatus(301)->assertRedirect('/ai-harmony-singing?ref=reddit-choir');
        $this->get('/your-choir')->assertStatus(301)->assertRedirect('/ai-harmony-singing');
        $this->get('/es/your-choir?ref=choir-share')->assertStatus(301)->assertRedirect('/es/armonias-con-ia?ref=choir-share');
        $this->get('/ai-singing')->assertStatus(301)->assertRedirect('/ai-harmony-singing');
        $this->get('/es/ai-singing')->assertStatus(301)->assertRedirect('/es/armonias-con-ia');
        // The English slug under /es/ is not the Spanish page.
        $this->get('/es/ai-harmony-singing')->assertNotFound();
    }

    public function test_it_answers_in_spanish(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        app(Flags::class)->set('own_choir', 'on', null, 'Own choir live');

        $this->get('/es/armonias-con-ia')->assertOk()
            ->assertSee('<html lang="es"', false)
            ->assertSee('coro entero')
            ->assertSee('Pruébalo con tu voz')
            ->assertSee('<meta property="og:image" content="'.url('/icons/og/es-ai-harmony-singing.png').'">', false)
            ->assertSee('<link rel="canonical" href="'.url('/es/armonias-con-ia').'">', false)
            ->assertSee('hreflang="en" href="'.url('/ai-harmony-singing').'"', false);
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
        $this->assertStringNotContainsString('data-yc-play', (string) $this->get('/ai-harmony-singing')->assertOk()->getContent());

        app(Flags::class)->set('ai_voices', 'on', null, 'AI voices');
        $html = (string) $this->get('/ai-harmony-singing')->assertOk()->getContent();
        $this->assertStringContainsString('data-stage', $html);
        $this->assertStringContainsString('<script type="module" src="/js/public/your-choir.js"></script>', $html);
        // The library's four songs, each a lane of its tune's opening notes, and still no photo.
        $this->assertSame(4, substr_count($html, 'class="yc-song"'));
        $this->assertStringNotContainsString('<img', $html);
        // One real lane and two AI ones, each saying once which it is; the real one first, on its own song.
        $this->assertSame(1, substr_count($html, 'data-seg="real"'));
        $this->assertSame(2, substr_count($html, 'data-seg="ai"'));
        // The lanes under the first screen (the deck, from its lanes to the page that follows), each chipped once.
        $deck = (string) strstr((string) strstr($html, 'data-yc-lanes'), 'data-yc-tail', true);
        $this->assertSame(1, substr_count($deck, '>'.e(__('site.your_choir.stage.real_chip')).'<'));
        $this->assertSame(2, substr_count($deck, '>'.e(__('site.your_choir.stage.ai_chip')).'<'));
        // Each scene's picture (site/_yc-mini): her real lane, then the tune and harmony made from it.
        $scene = static fn (string $s, string $next): string => (string) strstr((string) strstr($html, 'data-s="'.$s.'"'), 'data-s="'.$next.'"', true);
        $this->assertSame(1, substr_count($scene('real', 'split'), '<span class="yc-chip">'.e(__('site.your_choir.stage.real_chip')).'</span>'));
        $this->assertSame(2, substr_count($scene('split', 'yours'), '<span class="yc-chip">'.e(__('site.your_choir.stage.ai_chip')).'</span>'));
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
        $html = (string) $this->get('/ai-harmony-singing')->getContent();
        $this->assertStringContainsString('data-s="four"', $html);
        $this->assertSame(4, substr_count($html, 'data-seg="ai"'));
        $joins = array_column($this->play($html)['segments'][1]['voices'], 'join', 'part');
        $this->assertSame(0.0, (float) $joins['soprano']);
        $this->assertGreaterThan(0, $joins['tenor']);
        $this->assertSame($joins['tenor'], $joins['bass']);

        // Her credit taken back, or a required part not there: no stage.
        $voice->forceFill(['credited' => false])->save();
        $this->assertStringNotContainsString('data-yc-play', (string) $this->get('/ai-harmony-singing')->getContent());
        $voice->forceFill(['credited' => true])->save();
        $alto->delete();
        $this->assertStringNotContainsString('data-yc-play', (string) $this->get('/ai-harmony-singing')->getContent());
    }

    public function test_without_the_demos_voices_the_page_is_the_picture_with_no_sound_and_no_singer(): void
    {
        // Hillel, 2026-11-21: "lets support starting track B without A, we just need to adjust the your voice page so
        // there aren't samples". No AI voice licensed: the arrangement's notes as a picture, never a recording.
        app(SyncOfficialTemplates::class)->handle();
        app(Flags::class)->set('own_choir', 'on', null, 'Own choir live');

        $html = (string) $this->get('/ai-harmony-singing')->assertOk()->getContent();
        $this->assertStringContainsString('data-picture', $html);
        foreach (['data-yc-data', 'data-yc-play', 'data-yc-go', 'yc-dock', '<audio', '/media/'] as $sound) {
            $this->assertStringNotContainsString($sound, $html, 'no player and no sound: '.$sound);
        }
        // One voice line, then the four parts, each saying AI; nothing calls a lane a real singer.
        $this->assertSame(1, substr_count($html, 'data-seg="you"'));
        $this->assertSame(4, substr_count($html, 'data-seg="ai"'));
        $deck = (string) strstr((string) strstr($html, 'data-yc-lanes'), 'data-yc-tail', true);
        $this->assertSame(4, substr_count($deck, '>'.e(__('site.your_choir.stage.ai_chip')).'<'));
        $this->assertStringNotContainsString(e(__('site.your_choir.stage.real_chip')), $html);
        // Each scene's picture (site/_yc-mini): the four AI parts; then your recording leading the AI alto, tenor and bass.
        $four = (string) strstr((string) strstr($html, 'data-s="four"'), 'data-s="lead"', true);
        $lead = (string) strstr((string) strstr($html, 'data-s="lead"'), 'data-s="yours"', true);
        $this->assertSame(4, substr_count($four, '<span class="yc-chip">'.e(__('site.your_choir.stage.ai_chip')).'</span>'));
        $this->assertSame(1, substr_count($lead, '<span class="yc-chip">'.e(__('site.your_choir.lead_scene.you_chip')).'</span>'));
        $this->assertSame(3, substr_count($lead, '<span class="yc-chip">'.e(__('site.your_choir.stage.ai_chip')).'</span>'));
        $this->assertStringContainsString(e(__('site.your_choir.picture.two.body')), $html);
        // The way in on the first screen, in the hero, before anything else.
        $hero = (string) strstr((string) strstr($html, 'data-s="hero"'), 'data-s="four"', true);
        $this->assertStringContainsString(e(__('site.your_choir.start_voice')), $hero);
        $this->assertStringContainsString('song=happy-birthday&amp;choir=1', $hero);
        // The scenes: the first screen, the four parts, now yours.
        $this->assertSame(['hero', 'four', 'lead', 'yours'], array_values(array_filter(array_map(static fn (string $s): string => $s, (array) (preg_match_all('/data-s="([a-z]+)"/', $html, $m) ? $m[1] : [])))));
        // You + choir: the voice line is named as the singer's own recording there.
        $this->assertStringContainsString(e(__('site.your_choir.lead_scene.you_chip')), $html);
        // Which song to sing is always answered; friends only once their switch is on.
        $this->assertStringContainsString(e((string) __('site.your_choir.faq_tune')[0]), $html);
        $this->assertStringNotContainsString('yc-friends', $html);
        $this->assertStringNotContainsString(e((string) __('site.your_choir.faq_friends')[0]), $html);

        config(['flags.own_choir_together' => ['kind' => 'feature', 'values' => ['off', 'on']]]);
        app(Flags::class)->set('own_choir_together', 'on', null, 'Choir with friends');
        $together = (string) $this->get('/ai-harmony-singing')->assertOk()->getContent();
        $this->assertStringContainsString('yc-friends', $together);
        $this->assertStringContainsString(e((string) __('site.your_choir.faq_friends')[0]), $together);
        // Said once: the section's heading, with no second line under the steps.
        $this->assertSame(1, substr_count($together, e(__('site.your_choir.friends.heading'))));
        // Still one try block: the friends section carries no button.
        $friends = (string) strstr((string) strstr($together, 'class="yc-sec yc-friends"'), '</section>', true);
        // Two parts each, every lane saying whose and the same small AI chip; how the AI sings them is said once.
        $this->assertSame(2, substr_count($friends, '· '.e(__('site.your_choir.friends.you')).'<'));
        $this->assertSame(2, substr_count($friends, '· '.e(__('site.your_choir.friends.friend')).'<'));
        $this->assertSame(4, substr_count($friends, '>'.e(__('site.your_choir.stage.ai_chip')).'<'));
        $this->assertSame(1, substr_count($friends, e(__('site.your_choir.friends.ai'))));
        $this->assertStringNotContainsString('data-your-choir-try', $friends);
    }

    public function test_a_song_of_your_own_is_offered_only_once_its_switch_is_on(): void
    {
        app(Flags::class)->set('own_choir', 'on', null, 'Own choir live');

        $off = (string) $this->get('/ai-harmony-singing')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-your-choir-hum', $off);
        $this->assertStringNotContainsString('/create?', $off);
        $this->assertStringContainsString(e(__('site.your_choir.lead')), $off);
        $this->assertStringNotContainsString(e((string) __('site.your_choir.faq_hum')[0]), $off);

        config(['flags.own_choir_songs' => ['kind' => 'feature', 'values' => ['off', 'on']]]);
        app(Flags::class)->set('own_choir_songs', 'on', null, 'Own songs live');
        $on = (string) $this->get('/ai-harmony-singing')->getContent();
        $this->assertStringContainsString('data-your-choir-hum', $on);
        $this->assertStringContainsString('/create?ref=ai-harmony-singing', $on);
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
