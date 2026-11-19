<?php

declare(strict_types=1);

namespace Tests\Unit\Pages;

use App\Support\Pages\ClassicalCoursePage;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Classical singing basics' page reads lesson 1's phrase from the course itself, and sets out its songs as a programme
 * without losing a word of their credits.
 */
final class ClassicalCoursePageTest extends TestCase
{
    public function test_the_hero_shows_lesson_1s_own_phrase_in_e_flat_with_the_line_the_app_gives(): void
    {
        $first = ClassicalCoursePage::firstPhrase();

        // Caro mio ben's first phrase, as its template has it: E flat 5 down to G 4.
        $this->assertSame([75, 74, 72, 70, 72, 70, 68, 67], array_column($first['notes'], 2));
        $this->assertSame([0, 1], array_slice($first['notes'][0], 0, 2));
        // Lesson 1 scores where each note lands, not the joins: the page says so in the course's own words.
        $this->assertStringContainsString('joins aren\'t scored yet', $first['measures']);

        app()->setLocale('es');
        $this->assertStringContainsString('El ligado aún no se puntúa', ClassicalCoursePage::firstPhrase()['measures']);
    }

    public function test_a_missing_course_file_shows_no_score(): void
    {
        $this->assertSame(['notes' => [], 'measures' => ''], ClassicalCoursePage::firstPhrase('/nowhere/course.json'));
        $this->assertSame(['tempo' => 0, 'rounds' => []], ClassicalCoursePage::firstRounds('/nowhere/course.json'));
    }

    public function test_the_try_is_lesson_1s_own_first_round_one_take_joined_and_one_with_gaps(): void
    {
        $try = ClassicalCoursePage::firstRounds();

        $this->assertSame(72, $try['tempo']);
        $this->assertCount(3, $try['rounds']);
        // The answer's take has no gap between its notes; the other one has.
        $gapless = static function (array $notes): bool {
            foreach ($notes as $k => $note) {
                if ($k > 0 && abs($notes[$k - 1][0] + $notes[$k - 1][1] - $note[0]) > 1e-6) {
                    return false;
                }
            }

            return true;
        };
        foreach ($try['rounds'] as $round) {
            $this->assertSame('Which one is joined?', $round['prompt']);
            $this->assertCount(2, $round['clips']);
            $this->assertTrue($gapless($round['clips'][$round['answer']]));
            $this->assertFalse($gapless($round['clips'][1 - $round['answer']]));
            $this->assertNotSame('', $round['why']);
        }

        app()->setLocale('es');
        $this->assertSame('¿Cuál va ligada?', ClassicalCoursePage::firstRounds()['rounds'][0]['prompt']);
    }

    public function test_every_note_on_the_page_points_at_a_source_it_lists_and_every_source_is_cited(): void
    {
        $love = (array) Lang::get('site.courses.pages.classical-singing-basics.love');
        $sources = (array) $love['sources'];
        $cited = [];
        foreach ([...(array) $love['practice'], ...(array) $love['notes']] as $entry) {
            foreach ((array) ($entry[3] ?? []) as $key) {
                $this->assertArrayHasKey($key, $sources, "A note cites \"{$key}\", which the sources don't list.");
                $cited[$key] = true;
            }
        }
        $this->assertSame([], array_values(array_diff(array_keys($sources), array_keys($cited))));
    }

    public function test_the_programme_keeps_every_credit_and_says_a_shared_status_once(): void
    {
        $songs = (array) Lang::get('site.courses.pages.classical-singing-basics.songs');
        $programme = ClassicalCoursePage::programme($songs);

        $this->assertSame('Public domain', $programme['status']);
        foreach ($programme['pieces'] as $i => $piece) {
            $this->assertSame($songs[$i][0], $piece['title'].($piece['aside'] === null ? '' : ' '.$piece['aside']));
            $this->assertSame($songs[$i][1], $piece['credit'].'; '.$piece['status']);
        }
        $this->assertSame(['Wiegenlied', '(Brahms\'s lullaby)'], [$programme['pieces'][1]['title'], $programme['pieces'][1]['aside']]);

        // Credits that differ keep their own status.
        $mixed = ClassicalCoursePage::programme([['A', 'One; public domain'], ['B', 'Two; CC0'], ['C', 'Three']]);
        $this->assertNull($mixed['status']);
        $this->assertSame([null, 'CC0', null], [$mixed['pieces'][2]['status'], $mixed['pieces'][1]['status'], $mixed['pieces'][2]['aside']]);
    }

    public function test_a_units_line_drops_its_name_and_the_movements_are_numbered(): void
    {
        $this->assertSame('Notes with no gap', ClassicalCoursePage::unitLine('Joined: notes with no gap'));
        $this->assertSame('', ClassicalCoursePage::unitLine(null));
        $this->assertSame(['I', 'IV', '9'], [ClassicalCoursePage::numeral(1), ClassicalCoursePage::numeral(4), ClassicalCoursePage::numeral(9)]);
    }
}
