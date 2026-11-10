<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\SitePages;
use App\Support\Tools\KnownSongs;
use App\Support\Tools\NoteChart;
use App\Support\Tools\ToolPageData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BetaFixtures;
use Tests\TestCase;

/**
 * What a tool page hands its tool besides its words, and the note chart rendered in the page.
 */
final class ToolPageDataTest extends TestCase
{
    use BetaFixtures;
    use RefreshDatabase;

    public function test_the_key_finder_gets_each_known_song_in_its_own_shape_with_a_link_to_learn_it(): void
    {
        $this->catalogue();
        $songs = (array) app(ToolPageData::class)->for('vocal-key-finder')['songs'];

        $this->assertCount(count(app(KnownSongs::class)->all()), $songs);
        $grace = collect($songs)->firstWhere('name', 'Amazing Grace');
        $this->assertSame(['name' => 'Amazing Grace', 'key' => 'G', 'low' => 62, 'high' => 74, 'href' => '/songs/amazing-grace'], $grace);
        // Every link is a song page that's there.
        foreach ($songs as $song) {
            $this->get((string) $song['href'])->assertOk();
        }
    }

    public function test_the_mic_test_leads_on_only_to_live_tools(): void
    {
        $next = (array) app(ToolPageData::class)->for('mic-test')['next'];

        $this->assertNotSame([], $next);
        foreach ($next as $step) {
            $this->assertContains(substr((string) $step['href'], strlen('/tools/')), SitePages::TOOLS);
            $this->assertNotSame('', $step['label']);
        }
    }

    public function test_other_tools_get_nothing_extra(): void
    {
        $this->assertSame([], app(ToolPageData::class)->for('tuner'));
    }

    public function test_the_note_chart_page_carries_the_whole_table(): void
    {
        if (! in_array('note-frequency-chart', SitePages::TOOLS, true)) {
            $this->markTestSkipped('the note chart is not listed yet');
        }

        $html = (string) $this->get('/tools/note-frequency-chart')->assertOk()->getContent();
        $this->assertStringContainsString('<th scope="row">C0</th><td>16.35</td><td>12</td>', $html);
        $this->assertStringContainsString('<th scope="row">C4</th><td>261.63</td><td>60</td>', $html);
        $this->assertStringContainsString('<th scope="row">B8</th><td>7902.13</td><td>119</td>', $html);
        $this->assertStringContainsString('<caption>', $html);
    }

    public function test_the_note_chart_runs_c0_to_b8_at_a440_as_the_tool_shows_it(): void
    {
        $rows = NoteChart::rows();

        $this->assertCount(108, $rows);
        $this->assertSame(['midi' => 12, 'name' => 'C0', 'hz' => '16.35'], $rows[0]);
        $this->assertSame(['midi' => 119, 'name' => 'B8', 'hz' => '7902.13'], $rows[107]);
        $byMidi = array_column($rows, null, 'midi');
        $this->assertSame(['midi' => 60, 'name' => 'C4', 'hz' => '261.63'], $byMidi[60]);
        $this->assertSame(['midi' => 69, 'name' => 'A4', 'hz' => '440.00'], $byMidi[69]);
        $this->assertSame(['midi' => 61, 'name' => 'C♯4 / D♭4', 'hz' => '277.18'], $byMidi[61]);
        $this->assertSame('432.00', array_column(NoteChart::rows(432.0), null, 'midi')[69]['hz']);
    }
}
