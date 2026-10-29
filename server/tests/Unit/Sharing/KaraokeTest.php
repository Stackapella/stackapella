<?php

declare(strict_types=1);

namespace Tests\Unit\Sharing;

use App\Support\Sharing\Karaoke;
use PHPUnit\Framework\TestCase;

final class KaraokeTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function template(): array
    {
        return ['parts' => [['key' => 'soprano', 'guide' => ['type' => 'notes', 'notes' => [
            ['beat' => 0, 'length' => 1, 'pitch' => 62, 'lyric' => 'A-', 'br' => true],
            ['beat' => 1, 'length' => 2, 'pitch' => 67, 'lyric' => 'maz-'],
            ['beat' => 3, 'length' => 0.5, 'pitch' => 71, 'lyric' => 'ing'],
            ['beat' => 3.5, 'length' => 0.5, 'pitch' => 67],
            ['beat' => 4, 'length' => 2, 'pitch' => 71, 'lyric' => 'grace,'],
            ['beat' => 12, 'length' => 1, 'pitch' => 62, 'lyric' => 'that', 'br' => true],
            ['beat' => 13, 'length' => 2, 'pitch' => 67, 'lyric' => 'saved'],
        ]]]]];
    }

    public function test_each_syllable_is_timed_to_its_beat_at_the_projects_tempo(): void
    {
        // 80 bpm: 0.75 s a beat; beat 0 falls 2 s into the mix.
        $lines = Karaoke::lines($this->template(), 'soprano', 80, 2.0);

        $this->assertCount(2, $lines);
        $this->assertSame(['text' => 'A', 'start' => 2.0, 'end' => 2.75, 'joins' => true], $lines[0][0]);
        $this->assertSame(['text' => 'maz', 'start' => 2.75, 'end' => 4.25, 'joins' => true], $lines[0][1]);
        // The melisma after "ing" carries it to the end of that note.
        $this->assertSame(['text' => 'ing', 'start' => 4.25, 'end' => 5.0, 'joins' => false], $lines[0][2]);
        $this->assertSame(5.0, $lines[0][3]['start']);
        $this->assertSame(11.0, $lines[1][0]['start']);
        $this->assertSame('Amazing grace,', Karaoke::text($lines[0]));
        $this->assertSame('Amaz', Karaoke::text($lines[0], 1));

        // At 120 bpm the same notes come faster.
        $this->assertSame(2.5, Karaoke::lines($this->template(), 'soprano', 120, 2.0)[0][1]['start']);
    }

    public function test_a_section_keeps_the_lines_sung_in_it_timed_from_its_start(): void
    {
        $lines = Karaoke::section(Karaoke::lines($this->template(), 'soprano', 80, 2.0), 4.0, 12.0);

        $this->assertCount(2, $lines);
        $this->assertSame(['ing', 'grace,'], array_column($lines[0], 'text'));
        $this->assertSame(0.25, $lines[0][0]['start']);
        $this->assertSame(['that', 'saved'], array_column($lines[1], 'text'));
        $this->assertSame([], Karaoke::lines($this->template(), 'alto', 80, 0));
    }
}
