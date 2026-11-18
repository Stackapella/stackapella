<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\EarTrainingLeaps;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Ear training course page (Hillel's design, 2026-11-18): its leaps are real (each song's opening interval from its
 * template or lesson 1's own notes, under its real title), every lesson is a link to its page with the course's own
 * first line, and its board says it's a sample.
 */
final class EarTrainingPageTest extends TestCase
{
    // The page request writes rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    public function test_each_opening_is_its_songs_real_leap(): void
    {
        $songs = EarTrainingLeaps::songs();

        $this->assertSame(['three', 'four', 'five'], array_column($songs, 'key'));
        $this->assertSame(['Oh When the Saints', 'Amazing Grace', 'Twinkle, Twinkle'], array_column($songs, 'title'));

        $words = [];
        foreach ($songs as $song) {
            $a = $song['notes'][$song['from']];
            $b = $song['notes'][$song['from'] + 1];
            $this->assertSame($song['semis'], $b['midi'] - $a['midi'], "{$song['title']} opens with its leap");
            $this->assertTrue($a['leap'] && $b['leap']);
            $words[$song['key']] = [$a['word'], $b['word']];
            // A higher note sits higher on the card.
            $this->assertLessThan($a['y'], $b['y']);
        }

        // Saints: "Oh" up to "when", do to mi. Amazing Grace (its template, in G): "A-" (D4) up to "maz-" (G4), so below
        // to do. Twinkle: "kle" up to "twin-", do to so.
        $this->assertSame(['three' => ['Oh', 'when'], 'four' => ['A-', 'maz-'], 'five' => ['kle', 'twin-']], $words);
        $grace = $songs[1]['notes'];
        $this->assertSame([62, 67], [$grace[0]['midi'], $grace[1]['midi']]);
        $this->assertSame([-5, 0], [$grace[0]['rel'], $grace[1]['rel']]);
    }

    public function test_the_ladder_draws_each_leap_where_it_sits(): void
    {
        $ladder = EarTrainingLeaps::ladder();

        $this->assertCount(8, $ladder['rungs']);
        $leaps = array_column($ladder['leaps'], null, 'key');
        // Saints do to mi, Twinkle do to so, Amazing Grace so to high do.
        $this->assertSame([1, 3], [$leaps['three']['from'], $leaps['three']['to']]);
        $this->assertSame([1, 5], [$leaps['five']['from'], $leaps['five']['to']]);
        $this->assertSame([5, 8], [$leaps['four']['from'], $leaps['four']['to']]);
        foreach ($leaps as $key => $leap) {
            $this->assertSame(EarTrainingLeaps::LEAPS[$key], $leap['toMidi'] - $leap['fromMidi']);
        }
    }

    public function test_every_lesson_is_listed_with_its_first_line_and_its_page(): void
    {
        $outline = SitePages::courseOutline('ear-training');
        $this->assertNotNull($outline);
        $units = EarTrainingLeaps::lessons($outline);

        $this->assertSame($outline['lessons'], array_sum(array_map(static fn (array $u): int => count($u['lessons']), $units)));
        $first = $units[0]['lessons'];
        $this->assertSame('You already carry these leaps in songs you know.', $first[0]['line']);
        // The designer's three new lines, as the course file says them.
        $this->assertSame('Three, four and five, mixed.', $first[3]['line']);
        $this->assertSame('Any leap, up or down.', $units[1]['lessons'][5]['line']);
        $this->assertSame('A bass sings the roots under a tune.', $units[3]['lessons'][2]['line']);

        $html = (string) $this->get('/courses/ear-training')->assertOk()->getContent();
        if (SitePages::courseOpen('ear-training')) {
            foreach ($units as $unit) {
                foreach ($unit['lessons'] as $lesson) {
                    $this->assertNotNull($lesson['url'], "{$lesson['name']} has a page");
                    $this->assertStringContainsString('href="'.$lesson['url'].'">'.e($lesson['name']).'</a>', $html);
                }
            }
        }
    }

    public function test_the_page_plays_its_leaps_and_marks_its_board_a_sample(): void
    {
        $html = (string) $this->get('/courses/ear-training')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'data-et-leap="'));
        $this->assertSame(3, substr_count($html, 'data-et-card '));
        $this->assertStringContainsString('data-ear-demo', $html);
        $this->assertStringContainsString('>Sample</b>', $html);
        $this->assertStringContainsString('/js/public/ear-training-page.js', $html);
        // Every fact the page had, with its source, and the course is never said to cost money.
        foreach (['Twinkle&#039;s tune was first printed in France in 1761.', 'https://en.wikipedia.org/wiki/Missing_fundamental', 'Public domain hymn (words 1779, tune 1829)', 'Stop if anything tightens or hurts.', 'Nothing: every course is free.'] as $fact) {
            $this->assertStringContainsString($fact, $html);
        }
    }
}
