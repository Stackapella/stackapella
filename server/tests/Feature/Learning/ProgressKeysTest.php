<?php

declare(strict_types=1);

namespace Tests\Feature\Learning;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The progress keys the app merges itself: a part's plan for a rehearsal date (progress.rehearsals) and the first
 * week's note inside the chord (progress.chordAgain). The server keeps them as sent and refuses them malformed.
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
            'days' => [['date' => '2026-12-01', 'phrases' => [0, 3], 'doneAt' => 1_795_100_000_000], ['date' => '2026-12-02', 'phrases' => [4, 7], 'whole' => true]],
            ...$with,
        ];
    }

    public function test_a_rehearsal_plan_and_the_chord_numbers_are_kept_as_sent(): void
    {
        $this->actingAs(User::factory()->create());
        $chord = [['date' => '2026-11-23', 'inTuneSeconds' => 2.6, 'centsOff' => 31.5], ['date' => '2026-11-24', 'inTuneSeconds' => 4.1, 'centsOff' => 12]];

        $saved = $this->save(['lessons' => [], 'rehearsals' => [$this->plan()], 'chordAgain' => $chord]);
        $this->assertMatchesContract($saved, 'account.learning.update', 200);
        $saved->assertJsonPath('data.progress.rehearsals.0.days.1.whole', true)->assertJsonPath('data.progress.chordAgain.1.inTuneSeconds', 4.1);

        // Without either key, as an older app saves, nothing changes.
        $this->save(['lessons' => []])->assertOk();
    }

    public function test_either_key_malformed_is_refused(): void
    {
        $this->actingAs(User::factory()->create());
        $chord = static fn (array $with = []): array => [['date' => '2026-11-23', 'inTuneSeconds' => 2.6, 'centsOff' => 31.5, ...$with]];

        foreach ([
            'chord seconds past 5' => ['chordAgain' => $chord(['inTuneSeconds' => 5.5])],
            'chord cents negative' => ['chordAgain' => $chord(['centsOff' => -1])],
            'chord a recording kept' => ['chordAgain' => $chord(['audio' => 'UklGRg=='])],
            'chord the same day twice' => ['chordAgain' => [...$chord(), ...$chord()]],
            'chord more than 14' => ['chordAgain' => array_map(static fn (int $d): array => ['date' => sprintf('2026-11-%02d', $d), 'inTuneSeconds' => 1, 'centsOff' => 1], range(1, 15))],
            'chord a map' => ['chordAgain' => ['2026-11-23' => 2.6]],
            'plan a bad date' => ['rehearsals' => [$this->plan(['date' => '3 December'])]],
            'plan a day of three phrases' => ['rehearsals' => [$this->plan(['days' => [['date' => '2026-12-01', 'phrases' => [0, 1, 2]]]])]],
            'plan an unknown field' => ['rehearsals' => [$this->plan(['note' => 'Thursday, the church hall'])]],
            'plan no madeAt' => ['rehearsals' => [array_diff_key($this->plan(), ['madeAt' => true])]],
            'plan more than 20' => ['rehearsals' => array_fill(0, 21, $this->plan())],
        ] as $why => $progress) {
            $this->save(['lessons' => [], ...$progress])->assertStatus(422, $why);
        }
    }
}
