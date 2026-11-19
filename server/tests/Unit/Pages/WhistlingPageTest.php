<?php

declare(strict_types=1);

namespace Tests\Unit\Pages;

use App\Support\Pages\WhistlingPage;
use App\Support\Site\SitePages;
use Illuminate\Support\Facades\Lang;
use Tests\TestCase;

/**
 * The Whistling course page draws what the course really asks (App\Support\Pages\WhistlingPage): Hot Cross Buns as
 * lesson 4 lays it in the hero, coloured by a sample take whose count adds up, each unit's own exercise, the songs in the
 * order you meet them, and the lessons' own words in the page's language.
 */
final class WhistlingPageTest extends TestCase
{
    public function test_the_hero_is_hot_cross_buns_as_the_lesson_lays_it_with_a_sample_take_that_adds_up(): void
    {
        $hero = WhistlingPage::hero();

        // Your note and the two just above it: down, down, then the quick ones, sixteen beats at 72.
        $this->assertSame([2, 1, 0, 2, 1, 0], array_slice(array_column($hero['notes'], 'step'), 0, 6));
        $this->assertSame([2, 1, 0], $hero['rows']);
        $this->assertSame(16.0, $hero['beats']);
        $this->assertSame(72, $hero['tempo']);
        $this->assertCount(17, $hero['notes']);
        $this->assertCount(17, WhistlingPage::SAMPLE_BUNS);

        // The still frame: five whistled (four on pitch, one a little flat), the sixth now, the rest to come.
        $this->assertSame(['on', 'on', 'on', 'on', 'flat', 'now', 'todo'], array_slice(array_column($hero['notes'], 'state'), 0, 7));
        $this->assertSame([4, 5], [$hero['so_far'], $hero['done']]);
        $this->assertSame([15, 17], [$hero['on'], $hero['total']]);
        $this->assertSame('4 of 5 notes on pitch so far', WhistlingPage::fill((string) Lang::get('site.courses.pages.whistling.love.hero.so_far'), $hero['so_far'], $hero['done']));
    }

    public function test_the_result_card_is_the_real_verse_and_its_count_adds_up(): void
    {
        $verse = WhistlingPage::verse();

        $this->assertSame(39, $verse['total']);
        $this->assertSame(36, $verse['on']);
        $this->assertSame(3, count(array_filter($verse['notes'], static fn (array $n): bool => $n['state'] === 'flat')));
    }

    public function test_each_unit_draws_one_of_its_own_exercises(): void
    {
        $drawings = WhistlingPage::drawings();

        $this->assertCount(4, $drawings);
        $this->assertCount(1, $drawings[0]['bars']); // one note, held
        $this->assertCount(5, $drawings[1]['bars']); // five notes up
        $this->assertNotNull($drawings[1]['path']); // then the slide
        $this->assertSame([false, true, false, true, false], array_column($drawings[2]['bars'], 'mark')); // the landed tops
        $this->assertNotNull($drawings[3]['breath']);
        $this->assertTrue($drawings[3]['bars'][count($drawings[3]['bars']) - 1]['mark']); // the line held under
    }

    public function test_the_songs_go_in_the_order_you_meet_them(): void
    {
        $songs = WhistlingPage::songsByUnit((array) Lang::get('site.courses.pages.whistling.songs'), SitePages::courseOutline('whistling'));

        $this->assertSame(['Hot Cross Buns', 'Ode to Joy', 'Simple Gifts', 'The Water Is Wide'], array_column($songs, 'title'));
        $this->assertSame([1, 2, 3, 4], array_column($songs, 'unit'));
    }

    public function test_the_lessons_own_words_come_in_the_pages_language(): void
    {
        $card = WhistlingPage::card('wh-1-find');
        $this->assertStringStartsWith('Wet your lips', $card['headline']);
        // The safety point is left out: the page says it once.
        $this->assertSame(['A hiss with a faint note is the start', 'Breathing in can find it'], array_column($card['points'], 0));
        $this->assertCount(4, WhistlingPage::tips());

        app()->setLocale('es');
        $this->assertStringStartsWith('Sopla suave', WhistlingPage::stepText('wh-1-find', 1));
    }

    public function test_a_missing_course_file_draws_nothing(): void
    {
        $this->assertSame([], WhistlingPage::hero('/nowhere/course.json')['notes']);
        $this->assertSame([], WhistlingPage::verse('/nowhere/course.json')['notes']);
        $this->assertSame(['tune' => [], 'under' => []], WhistlingPage::finale('/nowhere/course.json'));
    }
}
