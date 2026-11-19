<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Pages\Lessons;
use App\Support\Pages\SightSingingPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Sight-singing page (site/courses/sight-singing): Find do plays the course's own line, the music is drawn right (the
 * key signatures where treble clef puts them, the ladder's half steps), every lesson is a numbered link to its page, and
 * the hymn board's numbers are the units the course file sings each song in.
 */
final class SightSingingPageTest extends TestCase
{
    // The page request writes rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    private const SITE = 'https://site.stackapella.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://app.stackapella.test', 'hosts.public_url' => self::SITE, 'app.is_nexus' => true, 'app.coming_soon' => false]);
        app(Flags::class)->set('plus_courses', 'on', null, 'The courses are open');
        Lessons::forget();
    }

    /** @return array<string, mixed> */
    private function course(): array
    {
        $path = config('app.web_path').'/course/plus/sight-singing/course.json';

        return json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_find_do_plays_the_lessons_own_line(): void
    {
        $line = null;
        foreach ($this->course()['units'] as $unit) {
            foreach ($unit['lessons'] as $lesson) {
                if ($lesson['id'] !== SightSingingPage::DEMO_LESSON) {
                    continue;
                }
                foreach ($lesson['steps'] as $step) {
                    if (($step['kind'] ?? null) === 'play' && ($step['exercise']['notes'] ?? []) !== []) {
                        $line = array_map(static fn (array $n): int => (int) $n['step'], $step['exercise']['notes']);
                        break 3;
                    }
                }
            }
        }
        $this->assertNotNull($line, 'the lesson has a line to read');

        $html = (string) $this->get(self::SITE.'/courses/sight-singing')->assertOk()->getContent();
        preg_match_all('#data-ss-note="\d+" data-degree="\d" data-semis="(-?\d+)"#', $html, $m);
        $this->assertSame(array_map('strval', $line), $m[1]);
        $this->assertSame(SightSingingPage::demo()['notes'], $line);
        $this->assertContains(0, $line, 'the line has a do to find');
        $this->assertStringContainsString('href="/courses/sight-singing/lines-that-turn#lesson-2"', $html);
    }

    public function test_the_key_signatures_sit_where_the_treble_clef_puts_them(): void
    {
        $signs = SightSingingPage::staves([0])['signatures'];
        $this->assertSame([], $signs['c']);
        // D: F sharp on the top line, C sharp in the third space; F: B flat on the middle line; G: F sharp.
        $this->assertSame([['accidentalSharp', SightSingingPage::y(8)], ['accidentalSharp', SightSingingPage::y(5)]], array_map(static fn (array $s): array => [$s['glyph'], $s['y']], $signs['d']));
        $this->assertSame([['accidentalFlat', SightSingingPage::y(4)]], array_map(static fn (array $s): array => [$s['glyph'], $s['y']], $signs['f']));
        $this->assertSame([['accidentalSharp', SightSingingPage::y(8)]], array_map(static fn (array $s): array => [$s['glyph'], $s['y']], $signs['g']));

        // Do: middle C on its ledger line in C, D under the stave, F in the bottom space, G on the second line.
        $do = SightSingingPage::staves([0])['notes'][0]['keys'];
        $this->assertSame(SightSingingPage::y(-2), $do['c']['y']);
        $this->assertTrue($do['c']['ledger']);
        $this->assertSame([SightSingingPage::y(-1), SightSingingPage::y(1), SightSingingPage::y(2)], [$do['d']['y'], $do['f']['y'], $do['g']['y']]);
        $this->assertFalse($do['d']['ledger']);
    }

    public function test_the_ladder_keeps_real_semitones_with_the_half_steps_closer(): void
    {
        $this->assertSame([0, 2, 4, 5, 7, 9, 11, 12], SightSingingPage::RUNGS);
        $html = (string) $this->get(self::SITE.'/courses/sight-singing')->assertOk()->getContent();
        preg_match_all('#data-ss-rung="(\d+)" style="--at: ([\d.]+)"#', $html, $m);
        $this->assertSame(['0', '2', '4', '5', '7', '9', '11', '12'], $m[1]);
        foreach ($m[1] as $i => $semis) {
            $this->assertEqualsWithDelta((12 - (int) $semis) / 12, (float) $m[2][$i], 0.0001);
        }
    }

    public function test_every_lesson_is_a_numbered_bar_linked_to_its_page(): void
    {
        $html = (string) $this->get(self::SITE.'/courses/sight-singing')->assertOk()->getContent();
        $lessons = array_sum(array_map(static fn (array $u): int => count($u['lessons']), $this->course()['units']));

        preg_match_all('#<a href="(/courses/sight-singing/[a-z0-9-]+(?:\#lesson-\d)?)" class="cp-ss-lesson">\s*<span class="cp-ss-lesson-top"><span class="cp-ss-lesson-n">(\d\d)</span>#', $html, $m);
        $this->assertCount($lessons, $m[1]);
        $this->assertSame(array_map(static fn (int $n): string => str_pad((string) $n, 2, '0', STR_PAD_LEFT), range(1, $lessons)), $m[2]);
    }

    public function test_the_hymn_board_numbers_the_unit_each_song_is_sung_in(): void
    {
        $units = array_map(static fn (array $u): string => (string) json_encode($u), $this->course()['units']);
        $first = static function (string $mark) use ($units): ?int {
            foreach ($units as $i => $json) {
                if (str_contains($json, $mark)) {
                    return $i + 1;
                }
            }

            return null;
        };

        $html = (string) $this->get(self::SITE.'/courses/sight-singing')->assertOk()->getContent();
        preg_match_all('#<span class="cp-ss-board-n">(\d*)</span><div><b translate="no">([^<]+)</b>#', $html, $m);
        $this->assertSame([html_entity_decode('Ode to Joy'), 'Amazing Grace', 'Michael, Row the Boat Ashore'], array_map('html_entity_decode', $m[2]));
        $this->assertSame([(string) $first('Ode to Joy'), (string) $first('amazing-grace'), (string) $first('michael-row')], $m[1]);
        $this->assertNotContains('', $m[1]);
    }
}
