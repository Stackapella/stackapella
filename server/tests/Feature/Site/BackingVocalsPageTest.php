<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\BackingVocalsLanes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Backing vocals course page (Hillel's design, 2026-11-18): its lanes are real notes (Shenandoah's tune from its
 * template, the course's own pad, a doo-wop and an answer on the template's chords; Amazing Grace's four parts from the
 * beginner course's template), and its result card says it's a sample.
 */
final class BackingVocalsPageTest extends TestCase
{
    // The page request writes rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    public function test_the_hero_lanes_are_shenandoahs_real_notes(): void
    {
        $hero = BackingVocalsLanes::hero();
        $lanes = $hero['lanes'];

        $this->assertSame(63, $hero['tempo']);
        $this->assertSame(['Oh', 60], [$lanes['lead'][0]['word'], $lanes['lead'][0]['midi']]);
        $this->assertSame('Shen', $lanes['lead'][1]['word']);
        // The pad is the lesson's: low so (C4 in F) from beat 1, la, then so.
        $this->assertSame([[1.0, 6.25, 60], [9.0, 3.0, 62], [12.0, 3.25, 60]], array_map(static fn (array $n): array => [$n['at'], $n['len'], $n['midi']], $lanes['pad']));
        $this->assertCount(14, $lanes['doo']);
        $this->assertCount(4, $lanes['answer']);

        foreach ($lanes as $kind => $notes) {
            foreach ($notes as $note) {
                $this->assertLessThanOrEqual(100.0, $note['left'] + $note['width'], "{$kind} at {$note['at']} stays in its lane");
            }
        }

        // Each doo-wah and answer note is a tone of the chord sounding under it (F: F A C, B flat: B flat D F).
        foreach ([...$lanes['doo'], ...$lanes['answer']] as $note) {
            $chord = $note['at'] >= 9 && $note['at'] < 12 ? [10, 2, 5] : [5, 9, 0];
            $this->assertContains($note['midi'] % 12, $chord, "the note at beat {$note['at']} is in its chord");
        }

        // The answers sit under the tune's last long note of each line.
        foreach ($lanes['answer'] as $note) {
            $this->assertLessThan(72, $note['midi']);
        }
    }

    public function test_amazing_grace_has_its_four_parts(): void
    {
        $grace = BackingVocalsLanes::grace();

        $this->assertCount(4, $grace['parts']);
        $this->assertSame('A', $grace['parts'][0][0]['word']);
        foreach ($grace['parts'] as $notes) {
            $this->assertNotEmpty($notes);
        }
    }

    public function test_the_page_draws_the_lanes_and_marks_the_result_a_sample(): void
    {
        $html = (string) $this->get('/courses/backing-vocals')->assertOk()->getContent();

        $this->assertStringContainsString('data-bv ', $html);
        $this->assertSame(38, substr_count($html, 'data-midi="'));
        $this->assertStringContainsString('A sample result', $html);
        $this->assertStringContainsString('/js/public/backing-vocals.js', $html);
        // Every fact the page had: the songs with their credits and the safety lines.
        foreach (['Public domain hymn (words 1779, tune 1829)', 'Written for the course', 'Easy volume, softer than the tune.', 'Stop if anything tightens or hurts.'] as $fact) {
            $this->assertStringContainsString(e($fact), $html);
        }
    }
}
