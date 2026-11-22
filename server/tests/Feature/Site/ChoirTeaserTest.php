<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Hear yourself as a choir, coming soon" on a song's page (docs/ai-voices-build-plan.md, phase 2): only behind
 * own_choir_teaser, one quiet button whose line leads with the arrangement and the 30 seconds and names AI once; off, the
 * page is exactly as it was.
 */
final class ChoirTeaserTest extends TestCase
{
    use RefreshDatabase;

    public function test_off_the_song_page_has_no_teaser(): void
    {
        app(SyncOfficialTemplates::class)->handle();

        $html = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-choir-teaser', $html);
        $this->assertStringNotContainsString('choir-teaser.js', $html);
        $this->assertStringNotContainsString(e(__('pages.song.choir.button')), $html);
    }

    public function test_on_the_song_page_offers_it_as_coming_soon_and_says_what_it_will_be(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        app(Flags::class)->set('own_choir_teaser', 'on', null, 'Finding out');

        $html = (string) $this->get('/songs/amazing-grace')->assertOk()->getContent();
        $this->assertStringContainsString('aria-controls="choir-soon">'.e(__('pages.song.choir.button')).'</button>', $html);
        $this->assertStringContainsString('<p class="s-sg-note" id="choir-soon" hidden>'.e(__('pages.song.choir.soon')).'</p>', $html);
        $this->assertStringContainsString('<script type="module" src="/js/public/choir-teaser.js"></script>', $html);
        // Its words lead with the arrangement and the 30 seconds, and say AI once.
        $soon = (string) __('pages.song.choir.soon');
        $this->assertStringStartsWith('Sing a song you know for 30 seconds', $soon);
        $this->assertSame(1, substr_count($soon, 'AI'));
        $this->assertSame(0, substr_count((string) __('pages.song.choir.button'), 'AI'));
        // Only on the song's own page, never its part pages.
        $this->assertStringNotContainsString('data-choir-teaser', (string) $this->get('/songs/amazing-grace/alto')->assertOk()->getContent());
    }

    public function test_its_taps_are_a_count_the_server_takes(): void
    {
        $this->assertContains('choir_teaser', (array) config('counts.kinds'));
    }
}
