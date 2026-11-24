<?php

declare(strict_types=1);

namespace App\Actions\SingingTest;

use App\Actions\Counts\RecordCounts;
use App\Models\SingingTest;
use App\Models\User;
use App\Support\SingingTest\SingingTestHolds;
use App\Support\SingingTest\SingingTests;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * singingTests.create: a finished test kept as numbers and scored by the server (App\Support\SingingTest\Scoring); the
 * device's own score is kept beside it, and a difference is counted (`st_mismatch`), never trusted. The same id again
 * answers what was kept.
 */
final class SaveSingingTest
{
    /**
     * @param  array<string, mixed>  $data  SaveSingingTestRequest's validated fields
     * @return array{test: SingingTest, created: bool}
     */
    public function handle(User $user, array $data): array
    {
        SingingTests::ensureOn($user);
        $items = ['match' => (array) $data['match'], 'back' => (array) $data['back'], 'hold' => (array) $data['hold']];
        $existing = SingingTest::query()->where('uuid', (string) $data['id'])->first();

        if ($existing !== null) {
            return ['test' => $this->same($existing, $user, $items), 'created' => false];
        }

        $test = new SingingTest;
        $test->forceFill([
            'uuid' => (string) $data['id'],
            'user_id' => $user->id,
            'test_version' => (int) $data['testVersion'],
            'device_scoring_version' => (int) $data['scoringVersion'],
            'taken_at' => Carbon::parse((string) $data['takenAt'])->utc(),
            'anchor' => round((float) $data['anchor'], 1),
            'device' => (string) $data['device'],
            'warm_up' => (bool) $data['warmUp'],
            'items' => $items,
            'device_score' => isset($data['score']) && is_array($data['score']) ? $data['score'] : null,
            'items_hash' => SingingTest::hashItems($items),
        ])->rescore();
        $reasons = $test->status === SingingTest::PRACTICE ? [] : app(SingingTestHolds::class)->reasons($test);

        if ($reasons !== []) {
            $test->forceFill(['flagged_at' => now(), 'flag_reasons' => $reasons]);
        }

        try {
            $test->save();
        } catch (UniqueConstraintViolationException) {
            // The same test sent twice at once: the first one kept it.
            return ['test' => $this->same(SingingTest::query()->where('uuid', $test->uuid)->firstOrFail(), $user, $items), 'created' => false];
        }

        if ($reasons !== []) {
            app(RecordCounts::class)->serverAmount('st_held', 1);
        }

        $device = $test->device_score;

        if ($device !== null && (($device['total'] ?? null) !== $test->total || ($device['band'] ?? null) !== $test->band)) {
            app(RecordCounts::class)->serverAmount('st_mismatch', 1);
        }

        return ['test' => $test, 'created' => true];
    }

    /**
     * @param  array<string, mixed>  $items
     */
    private function same(SingingTest $test, User $user, array $items): SingingTest
    {
        if ($test->user_id !== $user->id || $test->items != $items) {
            throw ValidationException::withMessages(['id' => __('validation.score_id')]);
        }

        return $test;
    }
}
