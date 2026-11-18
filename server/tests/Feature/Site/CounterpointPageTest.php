<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Counterpoint page's Fig. 1 (site/courses/counterpoint): the first-species exercise it draws and plays is the
 * course's own (lesson cp-4-first's listen step, web/course/plus/counterpoint/course.json), and its red figures are
 * the intervals those notes make, counted in steps of the scale.
 */
final class CounterpointPageTest extends TestCase
{
    // The page request writes rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    /** @return array{top: list<int>, cantus: list<int>} the lesson's two lines, in semitones from do */
    private function lesson(): array
    {
        $path = config('app.web_path').'/course/plus/counterpoint/course.json';
        $course = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        foreach ($course['units'] as $unit) {
            foreach ($unit['lessons'] as $lesson) {
                if ($lesson['id'] !== 'cp-4-first') {
                    continue;
                }
                $listen = $lesson['steps'][0]['listen'];

                return [
                    'top' => array_values(array_map(static fn (array $n): int => (int) $n['step'], $listen['notes'])),
                    'cantus' => array_values(array_map(static fn (array $n): int => (int) $n['step'], $listen['with'][0]['notes'])),
                ];
            }
        }
        $this->fail('The course has no cp-4-first lesson');
    }

    public function test_fig_1_is_the_courses_first_species_exercise_with_its_intervals_counted(): void
    {
        $html = (string) $this->get('/courses/counterpoint')->assertOk()->getContent();
        $lesson = $this->lesson();

        if (preg_match('#data-cp-species data-top="([^"]+)" data-low="([^"]+)"#', $html, $m) !== 1) {
            $this->fail('Fig. 1 carries no notes');
        }
        $top = json_decode(html_entity_decode($m[1]), true);
        $low = json_decode(html_entity_decode($m[2]), true);
        $this->assertSame(array_map(static fn (int $s): int => 60 + $s, $lesson['top']), $top, 'the counterpoint is the lesson\'s');
        $this->assertSame(array_map(static fn (int $s): int => 60 + $s, $lesson['cantus']), $low, 'the cantus is the lesson\'s');

        // A step of the major scale for each semitone from do, octaves counted; an interval spans both ends.
        $steps = [0 => 0, 2 => 1, 4 => 2, 5 => 3, 7 => 4, 9 => 5, 11 => 6];
        $degree = static fn (int $s): int => 7 * (int) floor($s / 12) + $steps[(($s % 12) + 12) % 12];
        $expected = array_map(static fn (int $a, int $b): string => (string) ($degree($a) - $degree($b) + 1), $lesson['top'], $lesson['cantus']);

        preg_match_all('#class="cp-cp2-figure">(\d+)<#', $html, $figures);
        $this->assertSame($expected, $figures[1]);
        $this->assertSame(['8', '6', '3', '6', '3', '6', '6', '6', '8'], $figures[1], 'begins and ends on the octave, only smooth intervals between');
    }

    public function test_the_books_are_the_courses_units(): void
    {
        $html = (string) $this->get('/courses/counterpoint')->assertOk()->getContent();
        $path = config('app.web_path').'/course/plus/counterpoint/course.json';
        $units = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR)['units'];

        $this->assertSame(count($units), substr_count($html, 'class="cp-cp2-bookn"'));
        $this->assertStringContainsString(count($units).' books', $html);
    }
}
