<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Models\Harmony;
use App\Models\Template;
use App\Models\User;
use App\Support\Flags;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * Hear yourself as a choir across the site (the marketing review, 2026-11-22): while own_choir is off, nothing below
 * shows; once on, the plans, the questions, the pricing rows, the song pages, About AI voices, the share page and the
 * self-host page say what it is, and each way in leads to /your-choir or the app with its own ref.
 */
final class OwnChoirSiteWordsTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    private function on(string ...$names): void
    {
        foreach ($names as $name) {
            app(Flags::class)->set($name, 'on', null, 'Live');
        }
    }

    public function test_off_the_home_pricing_and_song_pages_say_nothing_of_it(): void
    {
        app(SyncOfficialTemplates::class)->handle();

        foreach (['/', '/pricing', '/songs/amazing-grace'] as $page) {
            $html = (string) $this->get($page)->assertOk()->getContent();
            $this->assertStringNotContainsString(e(__('pages.landing.plans_free_choir')), $html, $page);
            $this->assertStringNotContainsString(e(strtr((string) __('pages.landing.faq_free_choir'), [':plus_songs' => '15'])), $html, $page);
            $this->assertStringNotContainsString(e(__('pages.pricing.faq_share_choir')), $html, $page);
            $this->assertStringNotContainsString('/your-choir', $html, $page);
            $this->assertStringNotContainsString('choir=1', $html, $page);
        }
    }

    public function test_on_the_plans_questions_and_rows_name_the_songs_and_both_prices(): void
    {
        $this->on('own_choir');

        $home = (string) $this->get('/')->assertOk()->getContent();
        $this->assertStringContainsString(e(__('pages.landing.plans_free_choir')), $home);
        $this->assertStringContainsString(e(__('plus.items.choir.0')), $home);
        $this->assertStringContainsString(e(strtr((string) __('pages.landing.faq_free_choir'), [':plus_songs' => '15'])), $home);
        $this->assertStringContainsString('href="/your-choir?ref=choir-home"', $home);
        $this->assertStringContainsString(e(__('landing.share.choir')), $home);

        $pricing = (string) $this->get('/pricing')->assertOk()->getContent();
        $this->assertStringContainsString('About 15 songs a month (60 minutes of AI singing)', $pricing);
        $this->assertStringContainsString(e(__('pages.pricing.faq_share_choir')), $pricing);
        $this->assertStringContainsString(e(__('pages.pricing.choir_songs_row.0')), $pricing);
        $this->assertStringContainsString(e(__('pages.pricing.choir_row.0')), $pricing);
        // The seat line waits for choir seats.
        $this->assertStringNotContainsString(e(__('pages.pricing.faq_seat_choir')), $pricing);
        $this->on('choir_seats');
        $this->assertStringContainsString(e(__('pages.pricing.faq_seat_choir')), (string) $this->get('/pricing')->getContent());

        $choir = (string) $this->get('/your-choir')->assertOk()->getContent();
        $this->assertStringContainsString('$4.99', $choir);
        $this->assertStringContainsString('$39.99', $choir);
        $this->assertStringNotContainsString(':price_', $choir);
        $this->assertStringContainsString('£4.49', (string) $this->get('/your-choir', ['CF-IPCountry' => 'GB'])->getContent());
    }

    public function test_on_a_song_page_opens_the_app_on_its_song_and_says_the_first_is_free(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $this->on('own_choir', 'own_choir_teaser');

        $html = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        $this->assertStringContainsString('song=amazing-grace&amp;choir=1&amp;ref=choir-song', $html);
        $this->assertStringContainsString(e(__('pages.song.choir.free')), $html);
        // The live link replaces the coming-soon teaser.
        $this->assertStringNotContainsString('data-choir-teaser', $html);
        $this->assertStringNotContainsString('choir-teaser.js', $html);
    }

    public function test_the_song_link_shows_only_where_the_choir_can_sing_the_song(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $this->on('own_choir');
        $link = 'song=amazing-grace&amp;choir=1';
        $this->assertStringContainsString($link, (string) $this->get('/songs/amazing-grace')->assertOk()->getContent());

        // Over the choir's own limits (what OwnChoirRequests refuses as song_too_long): no link, never a refusal.
        config(['own_choir.max_parts' => 3]);
        $this->assertStringNotContainsString($link, (string) $this->get('/songs/amazing-grace')->assertOk()->getContent());
        config(['own_choir.max_parts' => 8, 'own_choir.max_part_seconds' => 10]);
        $this->assertStringNotContainsString($link, (string) $this->get('/songs/amazing-grace')->assertOk()->getContent());
        config(['own_choir.max_part_seconds' => 300]);

        // A hidden song: no link, whether or not its page still shows.
        Template::query()->where('slug', 'amazing-grace')->update(['hidden_at' => now()]);
        $this->assertStringNotContainsString($link, (string) $this->get('/songs/amazing-grace')->getContent());
    }

    public function test_about_ai_voices_with_only_the_choir_on_explains_the_choir_alone(): void
    {
        $this->on('own_choir');

        $html = (string) $this->get('/about/ai-voices')->assertOk()->getContent();
        $this->assertStringContainsString(e(__('site.ai_voices.choir_only.headline')), $html);
        $this->assertStringContainsString(e(__('site.ai_voices.choir_only.label')), $html);
        $this->assertStringContainsString(e(__('site.ai_voices.choir_only.whose')), $html);
        $this->assertStringNotContainsString(e(__('site.ai_voices.sections.whose.body')), $html);
        $this->assertStringNotContainsString(e(__('site.ai_voices.sections.checked.heading')), $html);
        $this->assertStringNotContainsString(e(__('site.ai_voices.sections.replace.heading')), $html);
        $this->assertStringNotContainsString(e(__('site.ai_voices.sample.kick')), $html);
        $this->assertStringContainsString('href="/your-choir?ref=choir-about"', $html);

        // With AI guides on too, the whole page as before.
        $this->on('ai_voices');
        $both = (string) $this->get('/about/ai-voices')->assertOk()->getContent();
        $this->assertStringContainsString(e(__('site.ai_voices.headline')), $both);
        $this->assertStringContainsString(e(__('site.ai_voices.sections.checked.heading')), $both);
        $this->assertStringNotContainsString('/your-choir', $both);
    }

    public function test_a_choir_songs_share_page_offers_hearing_yourself_as_a_choir_once_it_is_on(): void
    {
        $template = $this->catalogue();
        $harmony = Harmony::factory()->create(['user_id' => User::factory()->create()->id, 'project_id' => null, 'template_version_id' => $template->current_version_id, 'guide_ids' => [], 'own_voice_parts' => ['Alto']]);
        Storage::disk('private')->put((string) $harmony->audio_key, 'aac');
        Storage::disk('private')->put((string) $harmony->card_key, 'jpg');
        app(HarmonyFiles::class)->sync($harmony);
        $path = '/h/'.$harmony->refresh()->public_id;

        $this->assertStringNotContainsString('/your-choir', (string) $this->get($path)->assertOk()->getContent());
        $this->on('own_choir');
        $this->assertStringContainsString('href="/your-choir?ref=choir-share"', (string) $this->get($path)->assertOk()->getContent());
    }

    public function test_the_self_host_page_says_how_the_choir_runs_once_it_is_on(): void
    {
        $this->on('open_source');
        $line = e(__('site.selfhost.missing_choir'));

        $this->assertStringNotContainsString($line, (string) $this->get('/selfhost')->assertOk()->getContent());
        $this->on('own_choir');
        $this->assertStringContainsString($line, (string) $this->get('/selfhost')->assertOk()->getContent());
    }

    public function test_your_choirs_song_cards_go_straight_into_the_choir_for_that_song(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        $this->on('own_choir');

        $html = (string) $this->get('/your-choir')->assertOk()->getContent();
        $this->assertStringContainsString('song=amazing-grace&amp;choir=1&amp;ref=your-choir', $html);
        $this->assertStringNotContainsString('href="/songs/amazing-grace"', $html);

        // Past the choir's limits: the card falls back to the song's page.
        config(['own_choir.max_parts' => 1]);
        $this->assertStringContainsString('href="/songs/amazing-grace"', (string) $this->get('/your-choir')->assertOk()->getContent());
    }
}
