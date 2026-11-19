<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Pages\Lessons;
use App\Support\Pages\VibratoPage;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Vibrato course page (site/courses/vibrato, Hillel's design of 2026-11-18): every lesson 01 to 17 a link to its page
 * (so the shared list isn't repeated); its numbers the app's own measure (web/js/vibrato.js) and the course file's guide
 * waves; its waves drawn to scale; its wobble always a wobble to the app; the years from the course's own facts, each
 * in the one Sources list; and the page in Spanish.
 */
final class VibratoPageTest extends TestCase
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

    public function test_every_lesson_is_numbered_and_linked_and_the_shared_list_is_not_repeated(): void
    {
        $page = (array) __('site.courses.pages.vibrato');
        $outline = SitePages::courseOutline('vibrato');
        $this->assertNotNull($outline);
        $units = VibratoPage::units($page, $outline);
        $lessons = array_merge(...array_map(static fn (array $unit): array => $unit['lessons'], $units));

        $this->assertCount($outline['lessons'], $lessons);
        $this->assertSame(range(1, $outline['lessons']), array_map(static fn (array $lesson): int => (int) $lesson['n'], $lessons));
        $this->assertTrue(VibratoPage::linksEveryLesson($units));
        foreach ($lessons as $lesson) {
            $this->assertStringStartsWith('/courses/vibrato/', (string) $lesson['url']);
            $this->assertNotSame('', $lesson['line'], $lesson['name'].' has its line');
        }

        $html = (string) $this->get(self::SITE.'/courses/vibrato')->assertOk()->getContent();
        foreach ($lessons as $lesson) {
            $this->assertStringContainsString('href="'.e((string) $lesson['url']).'"', $html);
        }
        $this->assertStringNotContainsString('data-lesson-pages', $html);
        $this->assertStringContainsString('data-course="vibrato"', $html);
    }

    public function test_the_numbers_are_the_apps_own_measure(): void
    {
        $js = (string) file_get_contents(dirname(base_path()).'/web/js/vibrato.js');
        $constant = static function (string $name) use ($js): string {
            preg_match('/export const '.$name.' = ([^;]+);/', $js, $m);

            return trim($m[1] ?? '');
        };

        $this->assertSame((string) VibratoPage::STRAIGHT, $constant('STRAIGHT_FULL'));
        $this->assertSame('['.implode(', ', VibratoPage::VIBRATO_RATE).']', $constant('VIBRATO_RATE'));
        $this->assertSame('['.implode(', ', VibratoPage::VIBRATO_WIDTH).']', $constant('VIBRATO_WIDTH'));
        $this->assertSame('100', $constant('WOBBLE_WIDE'));

        $page = (array) __('site.courses.pages.vibrato');
        $this->get(self::SITE.'/courses/vibrato')->assertOk()->assertSee(VibratoPage::measure($page));
    }

    public function test_the_wobble_is_always_a_wobble_and_the_waves_are_drawn_to_scale(): void
    {
        for ($sec = 0.0; $sec < 12; $sec += 0.05) {
            [$width] = VibratoPage::wobble($sec);
            $rate = (VibratoPage::wobble($sec + 0.001)[1] - VibratoPage::wobble($sec)[1]) / 0.001;
            $this->assertLessThan(VibratoPage::VIBRATO_RATE[0], $rate, "slower than a vibrato at {$sec} s");
            $this->assertGreaterThanOrEqual(VibratoPage::WOBBLE['rate'][0] - 0.01, $rate);
            $this->assertGreaterThanOrEqual(VibratoPage::VIBRATO_WIDTH[1] - 0.01, $width, "as wide as a vibrato ever is, or wider, at {$sec} s");
            $this->assertLessThanOrEqual(VibratoPage::WOBBLE['cents'][1] + 0.01, $width);
        }

        // A divider of ±50 swings five times as far as one of ±10.
        $height = static function (int $cents): float {
            preg_match_all('/[ML][\d.]+ ([\d.-]+)/', VibratoPage::path(['shape' => 'even', 'w' => 1440, 'h' => 48, 'secs' => 5.33, 'rate' => 6, 'cents' => $cents, 'scale' => 0.3, 'n' => 2000]), $m);
            $ys = array_map('floatval', $m[1]);

            return $ys === [] ? 0.0 : (max($ys) - min($ys)) / 2;
        };
        $this->assertEqualsWithDelta(5 * $height(10), $height(50), 0.2);
        $this->assertEqualsWithDelta(15.0, $height(50), 0.1);
        $this->assertEqualsWithDelta(0.0, $height(0), 0.01);

        // The one-second strip shows as many waves as the rate it states.
        $page = (array) __('site.courses.pages.vibrato');
        [, $cycles] = VibratoPage::at(VibratoPage::wave(['shape' => 'even', 'secs' => 1, 'rate' => VibratoPage::WAVE['rate']]), 1.0, 1.0);
        $this->assertSame((float) VibratoPage::WAVE['rate'], $cycles);
        $this->assertStringContainsString(':n', $page['big']['strip']);
    }

    public function test_each_units_guide_is_the_course_files_own_waves(): void
    {
        $guides = VibratoPage::guides();
        $this->assertCount(4, $guides);
        $this->assertSame(['rate' => [2.0, 4.0], 'cents' => [30.0, 50.0]], $guides[0]);
        $this->assertSame(['rate' => [2.0, 5.0], 'cents' => [20.0, 50.0]], $guides[1]);
        $this->assertSame(['rate' => [4.0, 4.0], 'cents' => [30.0, 30.0]], $guides[2]);
        $this->assertNull($guides[3]);

        $words = ['guide' => 'Guide :rate/s · ±:cents¢', 'range' => ':a to :b', 'own' => 'Straight, then yours'];
        $this->assertSame('Guide 2 to 4/s · ±50 to 30¢', VibratoPage::guideLine($guides[0], $words));
        $this->assertSame('Guide 4/s · ±30¢', VibratoPage::guideLine($guides[2], $words));
        $this->assertSame('Straight, then yours', VibratoPage::guideLine(null, $words));
    }

    public function test_the_years_come_from_the_courses_facts_and_each_is_in_the_sources(): void
    {
        $page = (array) __('site.courses.pages.vibrato');
        $years = VibratoPage::years($page);
        $this->assertSame(VibratoPage::YEARS, array_map(static fn (array $year): string => $year['id'], $years));
        $sources = VibratoPage::sources($page, $years);
        foreach ($years as $year) {
            $this->assertNotSame('', $year['year']);
            $this->assertNotSame('', $year['source']);
            $this->assertStringStartsWith('https://', (string) $year['url']);
            $this->assertArrayHasKey($year['id'], $sources);
        }
        $this->assertSame(['prame', 'sundberg', 'shonle', 'hsiao'], array_slice(array_keys($sources), 0, 4));

        $html = (string) $this->get(self::SITE.'/courses/vibrato')->assertOk()->getContent();
        $this->assertSame(count($sources), substr_count($html, 'id="src-'));
        $this->assertStringContainsString('href="#src-'.count($sources).'"', $html);
    }

    public function test_the_page_answers_in_spanish(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');
        $this->get(self::SITE.'/es/courses/vibrato')->assertOk()
            ->assertSee('Deja que llegue la <span class="cp-vi-wavy">onda</span>.', false)
            ->assertSee('Preguntas desde la orilla')
            ->assertSee('Para la app, una onda de menos de ±12 cents es lisa, y un vibrato va de 4,5 a 7,5 ondas por segundo');
    }
}
