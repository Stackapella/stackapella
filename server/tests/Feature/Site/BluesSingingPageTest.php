<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Pages\BluesSingingPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * The Blues singing course page after its length pass (2026-11-19): the demo sings lesson 1's own line, the page sets
 * out lesson 1's real steps, and every archive row, card, question and source is on it, in Spanish too.
 */
final class BluesSingingPageTest extends TestCase
{
    // The page request writes rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(Flags::class)->set('plus_courses', 'on', null, 'The courses are open');
    }

    public function test_the_demo_sings_lesson_ones_line_as_the_course_file_has_it(): void
    {
        $course = json_decode((string) file_get_contents(rtrim((string) config('app.web_path'), '/').'/course/plus/blues-singing/course.json'), true);
        $this->assertIsArray($course);
        $steps = (array) $course['units'][0]['lessons'][0]['steps'];
        $sung = (array) end($steps)['exercise']['notes'];

        $demo = BluesSingingPage::demo();
        $this->assertSame(
            array_map(static fn (array $n): array => [(float) $n['beat'], (float) $n['length'], (int) $n['step']], $sung),
            array_map(static fn (array $n): array => [(float) $n[0], (float) $n[1], (int) $n[2]], $demo['line']),
        );
        // Four blue notes, each leaning just under the bright note that follows it.
        $blue = array_keys(array_filter($demo['notes'], static fn (array $n): bool => $n['blue']));
        $this->assertCount(4, $blue);
        foreach ($blue as $i) {
            $this->assertSame(BluesSingingPage::BRIGHT, $demo['line'][$i + 1][2]);
        }
        $this->assertSame(['I', 'wrote', 'a', 'let-', 'ter', 'and', 'I', 'mailed', 'it', 'right', 'in', 'the', 'air'], $demo['words']);
    }

    public function test_the_page_draws_the_demo_lesson_one_and_every_fact_and_source(): void
    {
        $love = (array) Lang::get('site.courses.pages.blues-singing.love');
        $html = (string) $this->get('/courses/blues-singing')->assertOk()->getContent();

        $this->assertStringContainsString('data-bl-play="printed"', $html);
        $this->assertStringContainsString('data-bl-play="plain"', $html);
        $this->assertSame(4, substr_count($html, 'data-bl-v="plain"'));
        $this->assertStringContainsString('/js/public/blues-singing.js', $html);

        // Lesson 1's steps, from the course file.
        $this->assertSame(['learn', 'try', 'choose', 'play'], array_column(BluesSingingPage::firstLesson(), 'kind'));
        $this->assertStringContainsString(e('Now hear the line, then sing it back.'), $html);
        $this->assertStringContainsString(e('Scored on the rounds you got right first time.'), $html);

        foreach ((array) $love['works']['cards'] as [$title, $text]) {
            $this->assertStringContainsString(e($text), $html);
        }
        foreach ((array) $love['archive']['rows'] as [$year, , $title, $text]) {
            $this->assertStringContainsString(e($title), $html);
            $this->assertStringContainsString(e($text), $html);
        }
        $this->assertCount(8, (array) $love['sources']);
        foreach ((array) $love['sources'] as [, $url]) {
            $this->assertStringContainsString(e($url), $html);
        }
        foreach ((array) Lang::get('site.courses.pages.blues-singing.faq') as [$question]) {
            $this->assertStringContainsString(e($question), $html);
        }
        // The beginner course's band stays, before the questions, and the close comes after them.
        $this->assertStringContainsString('id="today-h"', $html);
        $this->assertLessThan(strpos($html, 'id="faq-h"'), strpos($html, 'id="today-h"'));
        $this->assertLessThan(strpos($html, 'id="join-h"'), strpos($html, 'id="faq-h"'));
    }

    public function test_the_spanish_page_says_the_new_sections_in_spanish(): void
    {
        app(Flags::class)->set('lang_es', 'on', null, 'Spanish checked');

        $this->get('/es/courses/blues-singing')->assertOk()
            ->assertSee('Apóyate y aterriza.')
            ->assertSee('Cómo funciona')
            ->assertSee('Un cuchillo sobre las cuerdas')
            ->assertSee('Escucha y elige')
            ->assertSee('Dos melodías cortas')
            ->assertDontSee('Lean, then land.');
    }
}
