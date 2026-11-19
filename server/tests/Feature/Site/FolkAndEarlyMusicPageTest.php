<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\FolkShapeNotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Folk and early music course page (Hillel's design, 2026-11-18): its notes are Drunken Sailor's from the song's
 * template, drawn as four-shape notes (fa a triangle, sol an oval, la a square, mi a diamond) read in each version's key,
 * so the bright sixth is mi and the dark one fa; the old scales rise in the right shapes; and the page keeps every fact
 * and source it had.
 */
final class FolkAndEarlyMusicPageTest extends TestCase
{
    // The page request writes rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    public function test_now_playing_is_drunken_sailors_third_line_from_its_template(): void
    {
        $line = FolkShapeNotes::line();
        $notes = $line['notes'];

        $this->assertSame(88, $line['tempo']);
        // E Dorian: the home note E4, the drone an octave under it.
        $this->assertSame(64, $line['home']);
        $this->assertSame(['What', 'shall', 'we', 'do', 'with', 'a', 'drunk-', 'en', 'sai-', 'lor'], array_column($notes, 'word'));
        $this->assertSame([71, 71, 71, 71, 71, 71, 71, 73, 74, 76], array_column($notes, 'midi'));
        // Only "-en", the sixth, changes for plain minor: a half step lower.
        $this->assertSame([71, 71, 71, 71, 71, 71, 71, 72, 74, 76], array_column($notes, 'dark'));
        $this->assertSame([false, false, false, false, false, false, false, true, false, false], array_column($notes, 'sixth'));
        // The last note is held to the end of the line (the print's full beat), so the line lasts its 8 beats.
        $this->assertSame(8.0, $notes[9]['at'] + $notes[9]['len']);
    }

    public function test_the_shapes_are_the_four_shape_syllables_in_each_versions_key(): void
    {
        $notes = FolkShapeNotes::line()['notes'];

        // As it goes, in two sharps: B la, C sharp mi (the bright note), D fa, E sol.
        $this->assertSame(['la', 'la', 'la', 'la', 'la', 'la', 'la', 'mi', 'fa', 'sol'], array_column($notes, 'brightShape'));
        // As plain minor, in one sharp: B la, C fa (the dark note), D sol, E la.
        $this->assertSame(['la', 'la', 'la', 'la', 'la', 'la', 'la', 'fa', 'sol', 'la'], array_column($notes, 'darkShape'));

        // The broadside's head: the first line, B seven times, then E, G, B.
        $this->assertSame(['la', 'la', 'la', 'la', 'la', 'la', 'la', 'sol', 'fa', 'la'], array_column(FolkShapeNotes::opening(), 'shape'));

        // The major scale in the four shapes: fa sol la fa sol la mi fa.
        $this->assertSame(['fa', 'sol', 'la', 'fa', 'sol', 'la', 'mi', 'fa'], array_map(static fn (int $s): string => FolkShapeNotes::shape($s, 'major'), [0, 2, 4, 5, 7, 9, 11, 12]));
    }

    public function test_the_old_scales_rise_in_their_shapes_with_the_changed_note_marked(): void
    {
        $ladders = FolkShapeNotes::ladders();

        $this->assertSame(['sol', 'la', 'fa', 'sol', 'la', 'mi', 'fa', 'sol'], array_column($ladders['bright']['steps'], 'shape'));
        $this->assertSame([9], array_column(array_filter($ladders['bright']['steps'], static fn (array $s): bool => $s['changed']), 'semi'));
        // Where plain minor would sing it: the minor sixth, fa.
        $this->assertSame(['at' => 5, 'semi' => 8, 'shape' => 'fa'], $ladders['bright']['ghost']);

        $this->assertSame(['sol', 'la', 'mi', 'fa', 'sol', 'la', 'fa', 'sol'], array_column($ladders['soft']['steps'], 'shape'));
        $this->assertSame([10], array_column(array_filter($ladders['soft']['steps'], static fn (array $s): bool => $s['changed']), 'semi'));
        // Where major would sing it: the leading note, mi.
        $this->assertSame(['at' => 6, 'semi' => 11, 'shape' => 'mi'], $ladders['soft']['ghost']);
    }

    public function test_the_page_draws_the_line_and_keeps_its_facts(): void
    {
        $html = (string) $this->get('/courses/folk-and-early-music')->assertOk()->getContent();

        $this->assertStringContainsString('data-fo-demo', $html);
        $this->assertSame(10, substr_count($html, 'data-fo-note="'));
        $this->assertSame(1, substr_count($html, 'data-fo-sixth'));
        $this->assertStringContainsString('/js/public/folk-and-early-music.js', $html);
        // Every fact and source the page had, the songs' credits and the safety lines.
        foreach ([
            'On 2 January 1666, Samuel Pepys wrote in his diary of hearing the actress Elizabeth Knipp sing Barbara Allen.',
            'https://www.pepysdiary.com/diary/1666/01/02/',
            'https://en.wikipedia.org/wiki/Ison_(music)',
            'https://en.wikipedia.org/wiki/Great_Highland_bagpipe',
            'its tune from an 1891 printing',
            'Ornaments come from the breath, light and small, with the jaw still.',
            'Nothing: every course is free.',
        ] as $fact) {
            $this->assertStringContainsString(e($fact), $html);
        }
        // The tunes oldest first.
        $this->assertLessThan(strpos($html, 'Barbara Allen</b>'), strpos($html, 'Ut queant laxis</b>'));
        $this->assertLessThan(strpos($html, 'Drunken Sailor</b>'), strpos($html, 'Barbara Allen</b>'));
        $this->assertLessThan(strpos($html, 'Wondrous Love</b>'), strpos($html, 'Drunken Sailor</b>'));
        // A real screen of the app, never a placeholder.
        $this->assertStringContainsString('course-folk-and-early-music--1', $html);
    }
}
