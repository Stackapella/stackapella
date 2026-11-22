<?php

declare(strict_types=1);

namespace Tests\Unit\OwnChoir;

use App\Support\OwnChoir\VoicingTransform;
use PHPUnit\Framework\TestCase;

final class VoicingTransformTest extends TestCase
{
    /** @return array<string, mixed> */
    private function song(): array
    {
        $part = static fn (string $key, array $pitches): array => ['key' => $key, 'guide' => ['notes' => array_map(static fn (int $p, int $i): array => ['beat' => $i, 'length' => 1, 'pitch' => $p, 'lyric' => 'la'], $pitches, array_keys($pitches))]];

        return ['parts' => [
            $part('soprano', [72, 74, 76]),
            $part('alto', [64, 60, 57]),
            $part('tenor', [55, 55, 55]),
            $part('bass', [48, 50, 52]),
        ], 'title' => 'Test'];
    }

    public function test_the_treble_parts_go_down_an_octave_and_tenor_and_bass_stay(): void
    {
        $low = VoicingTransform::low($this->song());

        $this->assertSame(['soprano' => -12, 'alto' => -12, 'tenor' => 0, 'bass' => 0], $low['shifts']);
        $pitches = static fn (string $key): array => array_column(collect($low['data']['parts'])->firstWhere('key', $key)['guide']['notes'], 'pitch');
        $this->assertSame([60, 62, 64], $pitches('soprano'));
        $this->assertSame([55, 55, 55], $pitches('tenor'));
        $this->assertSame([48, 50, 52], $pitches('bass'));
        $this->assertSame('Test', $low['data']['title'], 'Everything else as written.');
        $this->assertSame('la', collect($low['data']['parts'])->firstWhere('key', 'soprano')['guide']['notes'][0]['lyric']);
    }

    public function test_a_moved_note_never_falls_below_the_bass_and_keeps_its_chord_tone(): void
    {
        $low = VoicingTransform::low($this->song());
        $alto = array_column(collect($low['data']['parts'])->firstWhere('key', 'alto')['guide']['notes'], 'pitch');

        // 64 -> 52 (above the bass's 48), 60 -> 48 (below the bass's 50: kept at 60), 57 -> 45 (below 52: kept at 57).
        $this->assertSame([52, 60, 57], $alto);

        foreach ($alto as $i => $pitch) {
            $this->assertSame($this->song()['parts'][1]['guide']['notes'][$i]['pitch'] % 12, $pitch % 12, 'Octaves only: the same chord tone.');
        }
    }

    public function test_a_low_voice_is_one_the_tune_an_octave_down_fits_better(): void
    {
        $tune = $this->song()['parts'][0]['guide']['notes'];

        $this->assertTrue(VoicingTransform::isLow($tune, [50, 66]));
        $this->assertFalse(VoicingTransform::isLow($tune, [60, 79]));
        $this->assertFalse(VoicingTransform::isLow($tune, [48, 79]), 'Fits both: as written.');
    }
}
