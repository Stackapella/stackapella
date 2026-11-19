<?php

declare(strict_types=1);

namespace Tests\Unit\Pages;

use App\Support\Pages\OtherLanguagesPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * Singing in another language's page draws each song's opening line from its own template, a box a sung syllable as
 * long as its notes, and Shchedryk's figure from the template, not a guess.
 */
final class OtherLanguagesPageTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{lang: string, song: string, label: string, beats: float, items: list<array{len: float, text: string|null, hint: string|null, tie: bool, marks: list<float>}>}>
     */
    private function lines(): array
    {
        $lines = OtherLanguagesPage::firstLines((array) Lang::get('site.courses.pages.other-languages.love.lines.rows'));

        return array_column($lines, null, 'lang');
    }

    public function test_each_opening_line_is_its_templates_with_a_box_a_sung_syllable(): void
    {
        $lines = $this->lines();
        $this->assertSame(['ja', 'es', 'la', 'uk'], array_keys($lines));

        // Sakura in its kana, the template's lengths: the first ra two beats, the last one and a quarter.
        $sakura = $lines['ja']['items'];
        $this->assertSame(['さ', 'く', 'ら', 'さ', 'く', 'ら'], array_column($sakura, 'text'));
        $this->assertEquals([1, 1, 2, 1, 1, 1.25], array_column($sakura, 'len'));
        $this->assertSame(['sa', 'ku', 'ra', 'sa', 'ku', 'ra'], array_column($sakura, 'hint'));

        // La Paloma: "la Ha-" is one note, two words tied; the h's hint is the course's "la-AH".
        $paloma = $lines['es']['items'];
        $this->assertSame(['Cuan', 'do', 'sa', 'lí', 'de', 'la‿Ha', 'ba', 'na', 'Vál', 'ga', 'me', 'Dios'], array_column($paloma, 'text'));
        $this->assertTrue($paloma[5]['tie']);
        $this->assertSame('la-AH', $paloma[5]['hint']);
        $this->assertNull($paloma[0]['hint']);

        // Ave verum: two notes on a vowel make one box with a mark, and the rest after "a-ve," a gap.
        $ave = $lines['la']['items'];
        $this->assertSame([1.0], $ave[1]['marks']);
        $this->assertNull($ave[4]['text']);
        $this->assertEquals(1, $ave[4]['len']);

        // Shchedryk: long, short, short, long, twice.
        $this->assertEquals([1, 0.5, 0.5, 1, 1, 0.5, 0.5, 1], array_column($lines['uk']['items'], 'len'));
        $this->assertSame('Щед', $lines['uk']['items'][0]['text']);
    }

    public function test_a_line_whose_script_or_hints_dont_fit_its_boxes_falls_back_to_the_template(): void
    {
        $lines = array_column(OtherLanguagesPage::firstLines(['ja' => ['script' => 'さ く ら', 'hints' => 'sa']]), null, 'lang');

        $this->assertSame(['sa', 'ku', 'ra', 'sa', 'ku', 'ra'], array_column($lines['ja']['items'], 'text'));
        $this->assertSame([null, null, null, null, null, null], array_column($lines['ja']['items'], 'hint'));
        $this->assertSame([], OtherLanguagesPage::firstLines([], '/nowhere'));
    }

    public function test_the_ladder_plays_and_draws_shchedryks_own_figure_at_the_courses_tempos(): void
    {
        $this->assertSame([[70, 0.0, 1.0], [69, 1.0, 0.5], [70, 1.5, 0.5], [67, 2.0, 1.0]], OtherLanguagesPage::figureNotes());
        $this->assertSame(['Щед-', 'рик,', 'щед-', 'рик,'], OtherLanguagesPage::figureWords());

        $ladder = OtherLanguagesPage::ladder();
        $this->assertCount(10, $ladder['rounds']);
        $this->assertSame([60, 80, 96], array_column($ladder['tempos'], 'bpm'));
        // A faster round is a narrower one.
        $width = static fn (array $round): float => $round['notes'][3]['x'] - $round['notes'][0]['x'];
        $this->assertGreaterThan($width($ladder['rounds'][9]), $width($ladder['rounds'][0]));
    }

    public function test_marked_lines_and_titles_split_as_the_design_sets_them(): void
    {
        $this->assertSame([['text' => 'not', 'kind' => 'em'], ['text' => ' sa-ku-', 'kind' => 'plain'], ['text' => 'ruh', 'kind' => 'struck'], ['text' => '.', 'kind' => 'plain']], OtherLanguagesPage::marked('*not* sa-ku-[ruh].'));
        $this->assertSame(['Four songs, four languages,', 'harder each time'], OtherLanguagesPage::tail('Four songs, four languages, harder each time'));
        $this->assertSame(['Questions', ''], OtherLanguagesPage::tail('Questions'));
        $this->assertSame([['Borrowed vowels, ', false], ['1894', true], [' to ', false], ['1961', true]], OtherLanguagesPage::years('Borrowed vowels, 1894 to 1961'));
        $this->assertSame(['word' => 'lay', 'from' => 'eh', 'to' => 'ee'], OtherLanguagesPage::glide('lay: eh, ee'));
        $this->assertSame([['Do', 'DOE'], ['mi', 'MEE'], ['ne', 'NAY']], OtherLanguagesPage::pairs('Do mi ne', 'DOE MEE NAY'));
    }

    public function test_every_lesson_is_listed_by_unit_and_unlinked_until_the_course_has_lesson_pages(): void
    {
        $units = OtherLanguagesPage::units(['units' => [['title' => 'One sound, one note', 'lessons' => ['Sakura', 'Five whole vowels']]], 'lessons' => 2]);

        $this->assertSame('ja', $units[0]['lang']);
        $this->assertSame(['01', '02'], array_column($units[0]['lessons'], 'n'));
        $this->assertSame([null, null], array_column($units[0]['lessons'], 'url'));
        $this->assertFalse(OtherLanguagesPage::linksEveryLesson($units));
        $this->assertTrue(OtherLanguagesPage::linksEveryLesson([['lessons' => [['url' => '/courses/other-languages/sakura']]]]));
    }
}
