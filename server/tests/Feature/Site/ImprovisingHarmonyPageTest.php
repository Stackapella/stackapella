<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Pages\Lessons;
use App\Support\Site\ImprovisingHarmonyPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Improvising a harmony course page (Hillel's "tunebook" design, 2026-11-18): its player is real notes (Michael, Row
 * the Boat Ashore's tune from its template, lesson 1's part above from the course), each row has the right shape-note
 * head, every lesson is a link to its page, and the take is marked as a sample.
 */
final class ImprovisingHarmonyPageTest extends TestCase
{
    // The page request writes rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    public function test_the_player_is_the_songs_tune_and_lesson_ones_part(): void
    {
        $lane = ImprovisingHarmonyPage::lane();

        $this->assertSame(92, $lane['tempo']);
        // The template's tune (F A C A C D C A C D C), in the octave lesson 1 sings it.
        $this->assertSame([53, 57, 60, 57, 60, 62, 60, 57, 60, 62, 60], array_column($lane['tune'], 'midi'));
        $this->assertSame(['Mi-', 'chael,', 'row', 'the', 'boat', 'a-', 'shore,', 'Hal-', 'le-', 'lu-', 'jah!'], array_column($lane['tune'], 'word'));
        // Lesson 1's part above: a third up, a fourth over so (C to F), a third over la (D to F).
        $this->assertSame([57, 60, 65, 60, 65, 65, 65, 60, 65, 65, 65], array_column($lane['part'], 'midi'));
        $this->assertSame(array_column($lane['tune'], 'at'), array_column($lane['part'], 'at'));

        // Every part note is a tone of its chord (F: F A C, B flat: B flat D F), and every choice too, never the tune's.
        $chordAt = static fn (float $beat): array => $beat >= 10 && $beat < 14 ? [10, 2, 5] : [5, 9, 0];
        foreach ($lane['part'] as $note) {
            $this->assertContains($note['midi'] % 12, $chordAt($note['at']));
        }
        foreach ($lane['choices'] as $note) {
            $this->assertContains($note['midi'] % 12, $chordAt($note['at']));
        }

        foreach ([...$lane['tune'], ...$lane['part'], ...$lane['choices']] as $note) {
            $this->assertLessThanOrEqual(100.0, $note['left'] + $note['width']);
            $this->assertGreaterThanOrEqual(0, $note['row']);
            $this->assertLessThanOrEqual(7, $note['row']);
        }
    }

    public function test_each_row_has_its_four_shape_head(): void
    {
        // High do to low do: fa a triangle, sol an oval, la a square, mi a diamond (do and fa are fa, re and so sol, mi
        // and la la, ti mi).
        $this->assertSame(
            [['do', 'fa'], ['ti', 'mi'], ['la', 'la'], ['so', 'sol'], ['fa', 'fa'], ['mi', 'la'], ['re', 'sol'], ['do', 'fa']],
            array_map(static fn (array $row): array => [$row['name'], $row['shape']], ImprovisingHarmonyPage::lane()['rows']),
        );
    }

    public function test_the_strip_holds_the_part_above_on_the_long_notes(): void
    {
        $strip = ImprovisingHarmonyPage::strip();

        $this->assertSame([['row', 2.0, 0], ['lu-', 10.0, 0]], array_map(static fn (array $n): array => [$n['word'], $n['at'], $n['row']], $strip['part']));
        $this->assertCount(11, $strip['tune']);
    }

    public function test_every_lesson_is_numbered_and_links_to_its_page(): void
    {
        app(Flags::class)->set('plus_courses', 'on', null, 'Courses open');
        $index = ImprovisingHarmonyPage::index();
        $urls = array_column(Lessons::pages(ImprovisingHarmonyPage::COURSE), 'url');

        $this->assertCount(12, $urls);
        $this->assertCount(4, $index);
        $lessons = array_merge(...array_column($index, 'lessons'));
        $this->assertSame(array_map(static fn (int $n): string => sprintf('%02d', $n), range(1, 16)), array_column($lessons, 'n'));
        foreach ($lessons as $lesson) {
            $this->assertContains($lesson['url'], $urls, "{$lesson['title']} links to a lesson page");
        }
    }

    public function test_the_page_draws_the_player_and_marks_the_take_a_sample(): void
    {
        app(Flags::class)->set('plus_courses', 'on', null, 'Courses open');
        $html = (string) $this->get('/courses/improvising-harmony')->assertOk()->getContent();

        $this->assertStringContainsString('data-im-demo', $html);
        $this->assertSame(22, substr_count($html, 'data-midi="'));
        $this->assertSame(4, substr_count($html, 'data-im-stage="'));
        $this->assertStringContainsString('A sample take', $html);
        $this->assertStringContainsString('/js/public/improvising-harmony.js', $html);
        // The index links every lesson page (16 lessons on 12 pages), beside the shared list below the page.
        foreach (Lessons::pages(ImprovisingHarmonyPage::COURSE) as $page) {
            $this->assertStringContainsString('<a href="'.$page['url'].'">', $html);
        }
        $this->assertSame(16, substr_count($html, 'class="cp-imp-lesson"'));
        // Every fact and citation the page had, the songs' credits and the safety lines.
        foreach (['https://www.britannica.com/art/gymel', 'https://www.georgiaencyclopedia.org/articles/arts-culture/sacred-harp/', 'https://en.wikipedia.org/wiki/Bill_Monroe', 'Written for the course', 'Above a loud tune, go lighter rather than strain to match it.'] as $fact) {
            $this->assertStringContainsString(e($fact), $html);
        }
    }
}
