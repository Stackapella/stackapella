<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Support\Site\MusicalTheatrePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Musical theatre course page (Hillel's design, 2026-11-18): its demo's notes and syllables are the course's own
 * (unit 2's Two notes, many words), its running order is the course's units with every lesson linked, and it keeps
 * every fact and citation the page had.
 */
final class MusicalTheatrePageTest extends TestCase
{
    // The page request writes rate-limit rows: in a transaction, so none outlives the test.
    use RefreshDatabase;

    public function test_the_patter_is_the_lessons_own_first_fourteen_syllables(): void
    {
        $patter = MusicalTheatrePage::patter();

        $this->assertCount(14, $patter);
        $this->assertSame(['I', 'am', 'the', 've-', 'ry', 'mod-', 'el', 'of', 'a', 'mod-', 'ern', 'Ma-', 'jor-', 'Gi-'], array_column($patter, 0));
        // B flat and C, a step apart, as the score has them (sung an octave below the treble clef).
        $this->assertSame([58, 58, 60, 58, 60, 58, 60, 58, 60, 58, 60, 58, 60, 58], array_column($patter, 1));
        foreach ($patter as [, $midi, $high]) {
            $this->assertSame($midi === 60, $high);
        }
    }

    public function test_the_running_order_is_every_unit_with_its_lessons(): void
    {
        $acts = MusicalTheatrePage::acts(['units' => [
            ['title' => 'Speak it, then sing it', 'lessons' => ['Say it, then sing it', 'The word that matters', 'Breathe where the thought ends', 'Little Buttercup']],
            ['title' => 'Words first: patter', 'lessons' => ['Two notes, many words', 'Consonant first', 'A little quicker', 'I am the very model']],
        ], 'lessons' => 8]);

        $this->assertSame(['I', 'II'], array_column($acts, 'numeral'));
        $this->assertSame([false, false, false, true], array_column($acts[0]['lessons'], 'song'));
        $this->assertSame('Two notes, many words', $acts[1]['lessons'][0]['name']);
    }

    public function test_the_page_draws_the_programme_and_keeps_its_facts(): void
    {
        $html = (string) $this->get('/courses/musical-theatre')->assertOk()->getContent();

        $this->assertSame(14, substr_count($html, 'data-mt-note="'));
        $this->assertStringContainsString('data-notes="[58,58,60,58,60,58,60,58,60,58,60,58,60,58]"', $html);
        $this->assertStringContainsString('/js/public/musical-theatre.js', $html);
        $this->assertStringContainsString('Every word to the back of the <em>gallery.</em>', $html);
        // The sources, numbered, and the story's facts.
        $this->assertSame(35, substr_count($html, 'id="src-'));
        foreach (['made Gay rich and Rich gay', 'Sing it out as loud as you can.', 'without even singeing the muslin', 'open spiral coils of iron wire', 'Give My Regards to Broadway'] as $fact) {
            $this->assertStringContainsString(e($fact), $html);
        }
        // The cast's parts are the ones the sources give, never invented.
        foreach (['The first Polly Peachum', 'The first Ko-Ko', 'Librettist and stage director', 'Vaudeville performer'] as $part) {
            $this->assertStringContainsString(e($part), $html);
        }
        $this->assertStringNotContainsString('Song-and-dance man', $html);
        // Every course is free, and the FAQ says so.
        $this->assertStringContainsString('every course is free', $html);
    }
}
