<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Pages\Lessons;
use App\Support\Pages\ScatPage;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Scat singing course page (site/courses/scat, Hillel's design of 2026-11-18): the demo plays lesson 2's own line,
 * swung as the course writes it (the "and" two thirds through the beat), straight and as a soloist; the close's trade is
 * lesson 1's call a bar at a time; and the four record sleeves link every lesson to its page, in Spanish too.
 */
final class ScatPageTest extends TestCase
{
    use RefreshDatabase;

    private const SITE = 'https://site.stackapella.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://app.stackapella.test', 'hosts.public_url' => self::SITE, 'app.is_nexus' => true, 'app.coming_soon' => false]);
        app(Flags::class)->set('plus_courses', 'on', null, 'The courses are open');
        Lessons::forget();
    }

    /**
     * A lesson's sung lines, each its notes as [beat, length, step, lyric].
     *
     * @return list<list<array{0: float, 1: float, 2: int, 3: string}>>
     */
    private function lines(string $lessonId): array
    {
        $course = json_decode((string) file_get_contents(config('app.web_path').'/course/plus/scat/course.json'), true);
        $lines = [];
        foreach ($course['units'] as $unit) {
            foreach ($unit['lessons'] as $lesson) {
                if ($lesson['id'] !== $lessonId) {
                    continue;
                }
                foreach ($lesson['steps'] as $step) {
                    if (isset($step['exercise']['notes'])) {
                        $lines[] = array_values(array_map(static fn (array $n): array => [(float) $n['beat'], (float) $n['length'], (int) $n['step'], (string) ($n['lyric'] ?? '')], $step['exercise']['notes']));
                    }
                }
            }
        }

        return $lines;
    }

    public function test_the_demo_plays_lesson_twos_own_line_on_its_syllables(): void
    {
        $lines = $this->lines('sc-1-swing');
        $this->assertNotSame([], $lines);
        $lesson = $lines[count($lines) - 1];
        $this->assertSame(
            array_map(static fn (array $n): array => [$n[0], $n[1], $n[2]], $lesson),
            array_map(static fn (array $n): array => [(float) $n[0], (float) $n[1], (int) $n[2]], ScatPage::LINE),
        );

        $demo = (array) __('site.courses.pages.scat.love.demo');
        $this->assertSame(array_map(static fn (array $n): string => $n[3], array_slice($lesson, 0, 8)), $demo['syllables']);
        $names = [0 => 'C', 2 => 'D'];
        $this->assertSame(array_map(static fn (array $n): string => $names[$n[2]], array_slice($lesson, 0, 8)), $demo['notes']);
    }

    public function test_swing_puts_the_and_two_thirds_through_the_beat_straight_half_way_and_the_soloist_leans_back(): void
    {
        $feels = ScatPage::feels();
        $beatMs = 60000 / ScatPage::TEMPO;

        $this->assertSame(333, (int) round($feels['swung'][1][0] * $beatMs));
        $this->assertSame([0.0, 0.6667], $feels['swung'][0]);
        $this->assertSame([0.6667, 0.3333], $feels['swung'][1]);
        $this->assertSame([0.0, 0.5], $feels['straight'][0]);
        $this->assertSame([0.5, 0.5], $feels['straight'][1]);
        // The soloist: 33 ms late on the beat, 300 + 200 ms, and with the band on the "and".
        $this->assertSame(33, (int) round($feels['soloist'][0][0] * $beatMs));
        $this->assertSame(300, (int) round($feels['soloist'][0][1] * $beatMs));
        $this->assertSame($feels['swung'][1][0], $feels['soloist'][1][0]);
        $this->assertSame(200, (int) round($feels['soloist'][1][1] * $beatMs));
        // The landing on doo, two beats from the third bar's first beat, in every feel.
        foreach ($feels as $notes) {
            $this->assertEqualsWithDelta(2.0, $notes[count($notes) - 1][1], 0.001);
        }
    }

    public function test_the_closes_trade_is_lesson_ones_call_a_bar_at_a_time(): void
    {
        $call = $this->lines('sc-1-trade')[0];
        $bars = [];
        foreach ($call as [$beat, , , $lyric]) {
            $bars[(int) floor($beat / 4)][] = $lyric;
        }

        $this->assertSame(array_map(static fn (array $bar): string => implode(' ', $bar), array_values($bars)), __('site.courses.pages.scat.love.trade.call'));
    }

    public function test_the_sleeves_link_every_lesson_to_its_page(): void
    {
        $page = (array) __('site.courses.pages.scat');
        $outline = SitePages::courseOutline('scat');
        $this->assertNotNull($outline);
        $units = ScatPage::units($page, $outline);

        $lessons = array_merge(...array_map(static fn (array $unit): array => $unit['lessons'], $units));
        $this->assertCount($outline['lessons'], $lessons);
        $html = (string) $this->get(self::SITE.'/courses/scat')->assertOk()
            ->assertSee($page['love']['notes_by'])
            ->assertSee('data-feels=', false)
            ->getContent();
        foreach ($lessons as $lesson) {
            $this->assertNotNull($lesson['url'], $lesson['name'].' has its page');
            $this->assertStringStartsWith('/courses/scat/', (string) $lesson['url']);
            $this->assertStringContainsString('href="'.e((string) $lesson['url']).'"', $html);
        }
    }

    public function test_the_page_answers_in_spanish(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        $this->get(self::SITE.'/es/courses/scat')->assertOk()
            ->assertSee('La historia · Cara A')
            ->assertSee('Notas del equipo del curso')
            ->assertSee('Banda');
    }
}
