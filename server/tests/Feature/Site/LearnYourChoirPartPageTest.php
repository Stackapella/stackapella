<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Flags;
use App\Support\Site\ChoirPartScore;
use App\Support\Site\SitePages;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Learn your part for rehearsal course page (Hillel's design, 2026-11-18): its score is the course's own song's real
 * notes, words and bars (O Come, All Ye Faithful, from its template), its take says it's a sample, every lesson is a link
 * to its place on its unit's page, and the facts the page had are kept.
 */
final class LearnYourChoirPartPageTest extends TestCase
{
    // The page request writes rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Open, as it is live: its lesson pages (and so its lesson links) are only there while courses are on.
        app(Flags::class)->set('plus_courses', 'on', null, 'Courses open');
    }

    public function test_the_score_is_the_songs_first_two_lines_in_four_parts(): void
    {
        $score = ChoirPartScore::hero();

        $this->assertSame('o-come-all-ye-faithful', $score['song']);
        $this->assertSame('O Come, All Ye Faithful', $score['title']);
        // A one-beat upbeat in 4/4: bar 1 starts on beat 1, and the second line ends in bar 4.
        $this->assertSame(16.0, $score['span']);
        $this->assertSame([1, 4], [$score['from'], $score['to']]);
        $this->assertSame([[1, 6.25], [2, 31.25], [3, 56.25], [4, 81.25]], array_map(static fn (array $b): array => [$b['n'], $b['left']], $score['bars']));

        $this->assertSame(ChoirPartScore::PARTS, array_keys($score['parts']));
        $this->assertSame(['O', 67], [$score['parts']['soprano'][0]['word'], $score['parts']['soprano'][0]['midi']]);
        $this->assertSame(['O', 'come', 'all', 'ye', 'faith', 'ful', 'joy', 'ful', 'and', 'tri', 'um', 'phant'], array_column($score['parts']['soprano'], 'word'));
        $this->assertSame(43, $score['parts']['bass'][0]['midi']);
        foreach ($score['parts'] as $key => $notes) {
            $this->assertCount(12, $notes, "{$key} sings twelve notes in the two lines");
            foreach ($notes as $note) {
                $this->assertLessThanOrEqual(100.0, $note['left'] + $note['width'], "{$key} at {$note['at']} stays in its lane");
                $this->assertGreaterThanOrEqual(0.0, $note['y']);
                $this->assertLessThanOrEqual(1.0, $note['y']);
            }
        }
    }

    public function test_the_sample_take_misses_two_and_amazing_grace_has_four_parts(): void
    {
        foreach (ChoirPartScore::take(ChoirPartScore::hero()['parts']) as $key => $take) {
            $this->assertSame([10, 12], [$take['hit'], $take['all']], "{$key}'s sample take");
            foreach ($take['notes'] as $note) {
                $this->assertGreaterThanOrEqual(0.0, $note['sung']);
                $this->assertLessThanOrEqual(1.0, $note['sung']);
            }
        }

        $grace = ChoirPartScore::grace();
        $this->assertSame(ChoirPartScore::PARTS, array_keys($grace['parts']));
        $this->assertSame('A', $grace['parts']['soprano'][0]['word']);
        foreach ($grace['parts'] as $notes) {
            $this->assertNotEmpty($notes);
        }
    }

    public function test_every_lesson_links_to_its_place_on_its_units_page(): void
    {
        $links = ChoirPartScore::lessons('learn-your-choir-part');
        $outline = SitePages::courseOutline('learn-your-choir-part');

        // The heading counts four rehearsal marks.
        $this->assertNotNull($outline);
        $this->assertCount(4, $outline['units']);
        $this->assertSame(['A', 'B', 'C', 'D'], array_map(ChoirPartScore::mark(...), [0, 1, 2, 3]));
        foreach ($outline['units'] as $unit) {
            foreach ($unit['lessons'] as $title) {
                $this->assertArrayHasKey($title, $links, "{$title} has a link");
            }
        }
        $this->assertSame('/courses/learn-your-choir-part/your-line-in-the-sound#lesson-1', $links['Hear your line']);
        $this->assertSame('/courses/learn-your-choir-part/fix-the-hard-bars#lesson-4', $links['The verse']);
        $this->assertCount(4, ChoirPartScore::unitPages('learn-your-choir-part'));
    }

    public function test_the_page_draws_the_score_and_keeps_its_facts(): void
    {
        $html = (string) $this->get('/courses/learn-your-choir-part')->assertOk()->getContent();

        $this->assertStringContainsString('data-lcp ', $html);
        $this->assertStringContainsString('data-part="soprano"', $html);
        $this->assertSame(4, substr_count($html, 'data-lcp-pick="'));
        $this->assertStringContainsString('A sample take', $html);
        $this->assertStringContainsString('/js/public/learn-your-choir-part.js', $html);
        $this->assertSame(15, substr_count($html, '#lesson-'));
        foreach (ChoirPartScore::unitPages('learn-your-choir-part') as $unitPage) {
            $this->assertStringContainsString('href="'.$unitPage['url'].'"', $html);
        }
        // Every fact the page had: the safety lines, word for word, and the song with its credit.
        foreach (['Sing at an easy, medium-soft volume, not a whisper.', 'If a note sits too high or low, sing it lightly, never louder.', 'Stop if anything tightens or hurts.', 'Latin hymn, 18th century; English words 1841; public domain. The four-part setting is our own.'] as $fact) {
            $this->assertStringContainsString(e($fact), $html);
        }
    }

    public function test_the_page_reads_in_spanish(): void
    {
        $html = (string) $this->get('/es/courses/learn-your-choir-part')->assertOk()->getContent();

        $this->assertStringContainsString('Una toma de ejemplo', $html);
        $this->assertStringContainsString('Contralto', $html);
        $this->assertSame(15, substr_count($html, '#lesson-'));
    }
}
