<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\GospelRunsArt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * The Gospel and soul runs course page (Hillel's design, 2026-11-18): its stairs are the major pentatonic with F and B
 * left out, the hero's run is lesson 1's own (la, so, mi, re, do over its Amen chords), lesson 1 is set out as lining
 * out from its real steps, and every fact and source the page had is still there.
 */
final class GospelRunsPageTest extends TestCase
{
    // The page request writes rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    public function test_the_stairs_are_the_major_pentatonic_with_f_and_b_left_out(): void
    {
        $stairs = GospelRunsArt::stairs();

        $this->assertSame([60, 62, 64, 65, 67, 69, 71, 72], array_column($stairs, 'midi'));
        $this->assertSame([65, 71], array_values(array_column(array_filter($stairs, static fn (array $s): bool => $s['gap']), 'midi')));
    }

    public function test_the_hero_run_is_lesson_ones_run_from_the_course(): void
    {
        $run = GospelRunsArt::run();

        // la, so, mi, re, do (A4, G4, E4, D4, C4 with do on C4), two notes a second, do held two seconds.
        $this->assertSame([9, 7, 4, 2, 0], $run['steps']);
        $this->assertSame([69, 67, 64, 62, 60], array_column($run['notes'], 0));
        $this->assertEqualsWithDelta([0.0, 0.5, 1.0, 1.5, 2.0], array_column($run['notes'], 1), 0.001);
        $this->assertEqualsWithDelta(2.0, $run['notes'][4][2], 0.001);
        // The Amen cadence under it: IV (F major) for the run, I (C major) as it lands.
        $this->assertSame([[53, 57, 60], [48, 52, 55]], array_column($run['chords'], 2));
        // Each note's stair, in stairs()'s order: la, so, mi, re, do.
        $sol = (array) Lang::get('site.courses.pages.gospel-runs.love.stairs.sol');
        $this->assertSame(['la', 'so', 'mi', 're', 'do'], array_map(static fn (int $i): string => (string) $sol[$i], $run['stairs']));
    }

    public function test_the_page_draws_the_stairs_the_demos_and_lesson_one(): void
    {
        $html = (string) $this->get('/courses/gospel-runs')->assertOk()->getContent();

        $this->assertSame(6, substr_count($html, 'data-gr-stair="'));
        $this->assertSame(2, substr_count($html, 'class="cp-gr-gap"'));
        foreach (['stairs', 'run', 'smear', 'bend'] as $demo) {
            $this->assertStringContainsString('data-gr-play="'.$demo.'"', $html);
        }
        $this->assertSame(18, substr_count($html, 'data-gr-step="'));
        $this->assertSame(12, substr_count($html, 'data-gr-seg="'));
        $this->assertStringContainsString('/js/public/gospel-runs.js', $html);

        // Lesson 1 as lining out: what the app leads and what you sing, from the lesson's own steps.
        $this->assertStringContainsString(e('Hum the run slowly, five notes stepping down to do'), $html);
        $this->assertStringContainsString(e('Sing "A-men": the run on "A-", landing "men" on do'), $html);
    }

    public function test_every_fact_and_source_stays(): void
    {
        $love = (array) Lang::get('site.courses.pages.gospel-runs.love');
        $html = (string) $this->get('/courses/gospel-runs')->assertOk()->getContent();

        foreach ((array) $love['archive']['rows'] as [$year, , $title, $text]) {
            $this->assertStringContainsString(e($year), $html);
            $this->assertStringContainsString(e($title), $html);
            $this->assertStringContainsString(e($text), $html);
        }
        foreach ((array) $love['facts'] as [$text]) {
            $this->assertStringContainsString(e($text), $html);
        }
        $this->assertCount(23, (array) $love['sources']);
        foreach ((array) $love['sources'] as [, $url]) {
            $this->assertStringContainsString(e($url), $html);
        }
        $this->assertSame(23, substr_count($html, 'id="src-'));
    }

    public function test_the_page_has_its_spanish_words(): void
    {
        $this->get('/es/courses/gospel-runs')->assertOk()
            ->assertSee('Melismas limpios,')
            ->assertSee('Oye el melisma')
            ->assertSee('De dónde vienen')
            ->assertSee('los melismas');
    }
}
