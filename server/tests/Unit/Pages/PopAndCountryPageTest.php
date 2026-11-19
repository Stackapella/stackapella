<?php

declare(strict_types=1);

namespace Tests\Unit\Pages;

use App\Support\Pages\PopAndCountryPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Pop and country styling's page sings and draws lesson 1's own line from the course file, lists every lesson as a track
 * on its side, and keeps every safety line, fact and source of the page it replaced.
 */
final class PopAndCountryPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_now_playing_is_lesson_1s_own_line_and_chords(): void
    {
        $line = PopAndCountryPage::line();

        // Down in the Valley's first line as pop-1-style has it: its words, beats and steps from the comfortable note.
        $this->assertSame(['Down', 'in', 'the', 'val-', 'ley,', 'val-', 'ley', 'so', 'low,'], array_column($line['notes'], 3));
        $this->assertSame([-5, 0, 2, 4, 0, 4, 2, 0, 2], array_column($line['notes'], 2));
        $this->assertSame(96, $line['tempo']);
        // Only the chords under the line: I, then V7 on the last note.
        $this->assertSame([[0, 0, 'maj'], [12, 7, 'dom7']], $line['chords']);

        $roll = PopAndCountryPage::roll($line);
        $this->assertCount(9, $roll);
        $this->assertSame(0.0, (float) $roll[0]['left']);
        // A word close after the one before it takes the other row, so short notes' words never touch.
        $this->assertSame([0, 1, 0, 1, 0, 0, 1, 0, 1], array_column($roll, 'row'));
        $this->assertSame(['tempo' => 96, 'notes' => [], 'chords' => []], PopAndCountryPage::line('/nowhere/course.json'));
    }

    public function test_every_lesson_is_a_track_on_its_side_linked_to_its_page(): void
    {
        $outline = ['units' => [['title' => 'One', 'lessons' => ['a', 'b', 'c', 'd']], ['title' => 'Two', 'lessons' => ['e', 'f', 'g', 'h']]]];
        $sides = PopAndCountryPage::sides($outline, 'nowhere', '/nowhere/course.json');

        $this->assertSame(['A', 'B'], array_column($sides, 'side'));
        $this->assertSame(['A1', 'A2', 'A3', 'A4'], array_column($sides[0]['tracks'], 'code'));
        $this->assertTrue($sides[0]['tracks'][0]['first']);
        $this->assertFalse($sides[1]['tracks'][0]['first']);
        $this->assertSame('D', PopAndCountryPage::side(4));
        $this->assertSame(['Easy', 'does it'], PopAndCountryPage::twoLines('Easy does it'));
    }

    public function test_the_page_keeps_every_safety_line_fact_and_source(): void
    {
        $page = (array) Lang::get('site.courses.pages.pop-and-country');

        // The cut "Singing it safely" list said nothing the Small and light band doesn't.
        $this->assertCount(4, $page['light']['points']);
        $this->assertCount(count($page['light']['points']), $page['light']['tags']);
        $this->assertStringContainsString('Stop if anything tightens, tickles or hurts.', $page['light']['lead']);
        // Every fact keeps its source and its link, and leads with its own year.
        $this->assertCount(3, $page['facts']);
        foreach ($page['facts'] as $i => [$text, $source, $url]) {
            $this->assertNotSame('', $source);
            $this->assertStringStartsWith('https://', $url);
            $this->assertStringContainsString(substr($page['facts_years'][$i], 0, 4), $text);
        }
        $this->assertCount(4, PopAndCountryPage::moves($page['moves']['kinds']));
    }
}
