<?php

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The progress keys the app merges itself: a part's plan for a rehearsal date (progress.rehearsals), the first week's
 * note inside the chord (progress.chordAgain) and the range each time it changes (progress.rangeHistory). The server
 * keeps them as sent and refuses them malformed.
 */
final class ProgressKeysTest extends TestCase
{
    use RefreshDatabase;

    private int $version = 0;

    /**
     * @param  array<string, mixed>  $progress
     */
    private function save(array $progress): TestResponse
    {
        $saved = $this->putJson('/api/account/learning', ['changeId' => (string) Str::uuid(), 'version' => $this->version, 'progress' => $progress]);

        if ($saved->status() === 200) {
            $this->version++;
        }

        return $saved;
    }

    /**
     * @return array<string, mixed>
     */
    private function plan(array $with = []): array
    {
        return [
            'songId' => 'silent-night', 'partKey' => 'alto', 'date' => '2026-12-03', 'madeAt' => 1_795_000_000_000, 'groupId' => 'gr_1',
            'readyAt' => 1_795_200_000_000, 'nearlyAt' => 1_795_300_000_000,
            'days' => [['date' => '2026-12-01', 'phrases' => [0, 3], 'doneAt' => 1_795_100_000_000], ['date' => '2026-12-02', 'phrases' => [4, 7], 'whole' => true]],
            ...$with,
        ];
    }

    public function test_a_rehearsal_plan_and_the_chord_numbers_are_kept_as_sent(): void
    {
        $this->actingAs(User::factory()->create());
        $chord = [['date' => '2026-11-23', 'inTuneSeconds' => 2.6, 'centsOff' => 31.5], ['date' => '2026-11-24', 'inTuneSeconds' => 4.1, 'centsOff' => 12]];

        $ranges = [['date' => '2026-11-20', 'low' => 55, 'high' => 72, 'how' => 'measured', 'at' => 1_795_000_000_000], ['date' => '2026-11-24', 'low' => 53, 'high' => 74, 'how' => 'chosen', 'at' => 1_795_400_000_000]];

        $saved = $this->save(['lessons' => [], 'rehearsals' => [$this->plan()], 'chordAgain' => $chord, 'rangeHistory' => $ranges]);
        $this->assertMatchesContract($saved, 'account.learning.update', 200);
        $saved->assertJsonPath('data.progress.rehearsals.0.nearlyAt', 1_795_300_000_000)->assertJsonPath('data.progress.rehearsals.0.days.1.whole', true)->assertJsonPath('data.progress.chordAgain.1.inTuneSeconds', 4.1);
        $saved->assertJsonPath('data.progress.rangeHistory.1.low', 53);

        // Without either key, as an older app saves, nothing changes.
        $this->save(['lessons' => []])->assertOk();
    }

    public function test_either_key_malformed_is_refused(): void
    {
        $this->actingAs(User::factory()->create());
        $chord = static fn (array $with = []): array => [['date' => '2026-11-23', 'inTuneSeconds' => 2.6, 'centsOff' => 31.5, ...$with]];
        $range = static fn (array $with = []): array => [['date' => '2026-11-23', 'low' => 55, 'high' => 72, 'how' => 'measured', 'at' => 1_795_000_000_000, ...$with]];

        foreach ([
            'chord seconds past 5' => ['chordAgain' => $chord(['inTuneSeconds' => 5.5])],
            'chord cents negative' => ['chordAgain' => $chord(['centsOff' => -1])],
            'chord a recording kept' => ['chordAgain' => $chord(['audio' => 'UklGRg=='])],
            'chord the same day twice' => ['chordAgain' => [...$chord(), ...$chord()]],
            'chord more than 14' => ['chordAgain' => array_map(static fn (int $d): array => ['date' => sprintf('2026-11-%02d', $d), 'inTuneSeconds' => 1, 'centsOff' => 1], range(1, 15))],
            'chord a map' => ['chordAgain' => ['2026-11-23' => 2.6]],
            'range low above high' => ['rangeHistory' => $range(['low' => 74, 'high' => 60])],
            'range past the top note' => ['rangeHistory' => $range(['high' => 128])],
            'range a part picked' => ['rangeHistory' => $range(['how' => 'picked'])],
            'range the same day twice' => ['rangeHistory' => [...$range(), ...$range()]],
            'range no time' => ['rangeHistory' => [array_diff_key($range()[0], ['at' => true])]],
            'range more than 120' => ['rangeHistory' => array_map(static fn (int $d): array => ['date' => date('Y-m-d', 1_700_000_000 + $d * 86_400), 'low' => 55, 'high' => 70, 'how' => 'measured', 'at' => $d], range(1, 121))],
            'plan a bad date' => ['rehearsals' => [$this->plan(['date' => '3 December'])]],
            'plan a day of three phrases' => ['rehearsals' => [$this->plan(['days' => [['date' => '2026-12-01', 'phrases' => [0, 1, 2]]]])]],
            'plan an unknown field' => ['rehearsals' => [$this->plan(['note' => 'Thursday, the church hall'])]],
            'plan a nearlyAt that isn\'t a time' => ['rehearsals' => [$this->plan(['nearlyAt' => 'Thursday'])]],
            'plan no madeAt' => ['rehearsals' => [array_diff_key($this->plan(), ['madeAt' => true])]],
            'plan more than 20' => ['rehearsals' => array_fill(0, 21, $this->plan())],
        ] as $why => $progress) {
            $this->save(['lessons' => [], ...$progress])->assertStatus(422, $why);
        }
    }
}
