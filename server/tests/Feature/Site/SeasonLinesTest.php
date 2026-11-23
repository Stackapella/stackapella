<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Actions\Templates\SyncOfficialTemplates;
use App\Support\Flags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The season's lines (2026-11-23): a Hanukkah song's page offers the choir guide in Hanukkah's window, and the Jewish
 * songs say their AI choir sings their own words while own_choir is on; the guides index lists the Hanukkah guide.
 */
final class SeasonLinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_hanukkah_and_the_jewish_songs_own_words(): void
    {
        app(SyncOfficialTemplates::class)->handle();
        Carbon::setTestNow('2026-11-24 12:00:00');

        $song = (string) $this->get('/songs/maoz-tzur')->assertOk()->getContent();
        $this->assertStringContainsString('href="/guides/hanukkah-songs-for-choir"', $song);
        $this->assertStringNotContainsString(e(__('pages.song.choir.own_words')), $song, 'only with own_choir');

        app(Flags::class)->set('own_choir', 'on', null, 'Live');
        $this->assertStringContainsString(e(__('pages.song.choir.own_words')), (string) $this->get('/songs/maoz-tzur')->getContent());
        $this->assertStringContainsString(e(__('site.collections.jewish-songs.ai_choir')), (string) $this->get('/songs/jewish-songs')->assertOk()->getContent());
        $this->assertStringNotContainsString(e(__('pages.song.choir.own_words')), (string) $this->get('/songs/amazing-grace')->getContent());

        // After Hanukkah, no guide line.
        Carbon::setTestNow('2026-12-20 12:00:00');
        $this->assertStringNotContainsString('href="/guides/hanukkah-songs-for-choir"', (string) $this->get('/songs/maoz-tzur')->getContent());

        $this->assertStringContainsString('href="/guides/hanukkah-songs-for-choir"', (string) $this->get('/guides')->getContent());
    }
}
