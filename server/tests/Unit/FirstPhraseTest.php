<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Templates\FirstPhrase;
use App\Support\Templates\TemplateRules;
use Tests\TestCase;

/**
 * A song cut to its first phrase (Hillel, 2026-11-15: "Sing the first phrase, then ask").
 */
final class FirstPhraseTest extends TestCase
{
    /** @return array<string, mixed> a round: the melody from beat 0, a second voice from beat 8, a phrase ending at 6 */
    private static function round(): array
    {
        $part = static fn (string $key, int $from, bool $melody): array => [
            'key' => $key, 'name' => ucfirst($key), 'melody' => $melody, 'range' => ['low' => 48, 'high' => 72],
            'guide' => ['type' => 'notes', 'notes' => array_map(static fn (int $i): array => ['beat' => $from + 2 * $i, 'length' => 2, 'pitch' => 60 + $i, 'lyric' => 'la'], range(0, 7))],
        ];

        return [
            'id' => 'a-round', 'version' => 1, 'title' => 'A round', 'reviewed' => true, 'tempo' => 100, 'key' => 'C',
            'timeSignature' => '4/4', 'pickupBeats' => 0, 'licence' => ['tune' => 'public domain'],
            'parts' => [$part('soprano', 0, true), $part('alto', 8, false)],
            'order' => ['soprano', 'alto'],
            'voicings' => ['1' => ['soprano'], '2' => ['soprano', 'alto']],
            'sections' => [['name' => 'Verse', 'startBeat' => 0, 'endBeat' => 24, 'phrases' => [[0, 5], [5, 16], [16, 24]]]],
            'chords' => [['beat' => 0, 'symbol' => 'C'], ['beat' => 4, 'symbol' => 'G'], ['beat' => 8, 'symbol' => 'C']],
            'meters' => [['beat' => 12, 'timeSignature' => '3/4']],
            'builder' => ['sections' => []],
        ];
    }

    public function test_it_keeps_only_the_first_phrase_and_stays_a_valid_template(): void
    {
        $cut = FirstPhrase::of(self::round());
        $this->assertNotNull($cut);

        // Notes starting before beat 5, the last clipped to it.
        $notes = $cut['parts'][0]['guide']['notes'];
        $this->assertSame([0, 2, 4], array_column($notes, 'beat'));
        $this->assertEquals(1, $notes[2]['length']);
        // The second voice enters later: left out, with the order and voicing that name it.
        $this->assertSame(['soprano'], array_column($cut['parts'], 'key'));
        $this->assertSame(['soprano'], $cut['order']);
        $this->assertSame(['1' => ['soprano']], $cut['voicings']);
        $this->assertSame([[0, 5.0]], $cut['sections'][0]['phrases']);
        $this->assertEquals(5, $cut['sections'][0]['endBeat']);
        $this->assertSame([0, 4], array_column($cut['chords'], 'beat'));
        $this->assertArrayNotHasKey('meters', $cut);
        $this->assertArrayNotHasKey('builder', $cut);
        $this->assertSame('A round', $cut['title']);

        $this->assertSame([], (new TemplateRules)->problems($cut));
    }

    public function test_the_section_ends_with_the_last_note_when_the_phrase_runs_past_it(): void
    {
        $song = self::round();
        $song['parts'] = [$song['parts'][0]];
        $song['parts'][0]['guide']['notes'] = [['beat' => 0, 'length' => 2, 'pitch' => 60]];
        $song['order'] = ['soprano'];
        unset($song['voicings']);

        $cut = FirstPhrase::of($song);
        $this->assertNotNull($cut);
        $this->assertEquals(2, $cut['sections'][0]['endBeat']);
        $this->assertSame([], (new TemplateRules)->problems($cut));
    }

    public function test_an_intro_the_melody_doesnt_sing_in_runs_on_to_the_melodys_first_phrase(): void
    {
        // The alto plays a 4-beat intro alone; the melody comes in at beat 4, in the second section.
        $song = self::round();
        $song['parts'][0]['guide']['notes'] = array_map(static fn (int $i): array => ['beat' => 4 + 2 * $i, 'length' => 2, 'pitch' => 60 + $i], range(0, 5));
        $song['parts'][1]['guide']['notes'] = [['beat' => 0, 'length' => 4, 'pitch' => 55], ['beat' => 4, 'length' => 4, 'pitch' => 57]];
        $song['sections'] = [['name' => 'Intro', 'startBeat' => 0, 'endBeat' => 4, 'phrases' => [[0, 4]]], ['name' => 'Verse', 'startBeat' => 4, 'endBeat' => 16, 'phrases' => [[4, 10], [10, 16]]]];
        unset($song['meters']);

        $cut = FirstPhrase::of($song);
        $this->assertNotNull($cut);
        $this->assertSame(['soprano', 'alto'], array_column($cut['parts'], 'key'));
        $this->assertSame([4, 6, 8], array_column($cut['parts'][0]['guide']['notes'], 'beat'));
        $this->assertCount(1, $cut['sections']);
        $this->assertSame('Intro', $cut['sections'][0]['name']);
        $this->assertEquals(10, $cut['sections'][0]['endBeat']);
        $this->assertEquals([[0, 10]], $cut['sections'][0]['phrases']);
        $this->assertSame([], (new TemplateRules)->problems($cut));

        // A melody that never starts a note inside a phrase: no cut.
        $song['sections'] = [['name' => 'Intro', 'startBeat' => 0, 'endBeat' => 4, 'phrases' => [[0, 4]]]];
        $this->assertNull(FirstPhrase::of($song));
    }

    public function test_no_phrase_means_no_cut(): void
    {
        $song = self::round();
        $song['sections'][0]['phrases'] = [];
        $this->assertNull(FirstPhrase::of($song));

        unset($song['sections']);
        $this->assertNull(FirstPhrase::of($song));
    }
}
