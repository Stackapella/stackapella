<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Exceptions\Refusal;
use App\Models\User;
use App\Models\UserLearning;
use Illuminate\Support\Facades\DB;

/**
 * account.learning.update: the course's progress, versioned like a project. The app merges a
 * conflict itself (the best stars, the most XP), so the server never has to. "Start the course
 * over" (reset) moves the progress to a new epoch: a device's save from an older one is refused,
 * never merged back in, and the reset can be undone for 30 days from its snapshot.
 */
final class SaveLearning
{
    /**
     * @param  array<string, mixed>  $progress
     */
    public function handle(User $user, int $version, string $changeId, array $progress, int $epoch = 0): UserLearning
    {
        return DB::transaction(function () use ($user, $version, $changeId, $progress, $epoch): UserLearning {
            User::query()->whereKey($user->id)->lockForUpdate()->first(['id']);
            $learning = UserLearning::query()->where('user_id', $user->id)->lockForUpdate()->first();

            if ($learning !== null && $learning->last_change_id === $changeId) {
                return $learning;
            }

            // Progress from before a reset (or its undo): the device takes the server's, not merges.
            if (($learning->epoch ?? 0) > $epoch) {
                throw new Refusal(409, 'learning_reset', ['current' => self::fields($learning)]);
            }

            if (($learning->version ?? 0) !== $version) {
                throw new Refusal(409, 'version_conflict', ['current' => self::fields($learning)]);
            }

            $learning ??= (new UserLearning)->forceFill(['user_id' => $user->id, 'version' => 0]);
            $learning->fill(['progress' => $progress]);
            $learning->forceFill(['version' => $learning->version + 1, 'last_change_id' => $changeId])->save();

            return $learning;
        });
    }

    /** What "Start the course over" clears: the lessons and their stars, and units opened early.
     * The comfortable note, the range, XP, practice, the goal and badges stay. */
    public const RESET_CLEARS = ['lessons' => [], 'testedOut' => []];

    public const UNDO_DAYS = 30;

    /**
     * account.learning.reset: the lessons and stars start again, on every device.
     */
    public function reset(User $user, string $changeId): UserLearning
    {
        return DB::transaction(function () use ($user, $changeId): UserLearning {
            User::query()->whereKey($user->id)->lockForUpdate()->first(['id']);
            $learning = UserLearning::query()->where('user_id', $user->id)->lockForUpdate()->first()
                ?? (new UserLearning)->forceFill(['user_id' => $user->id, 'version' => 0, 'progress' => []]);

            if ($learning->last_change_id === $changeId) {
                return $learning;
            }

            $progress = (array) ($learning->progress ?? []);
            $kept = [...$progress, ...array_intersect_key(self::RESET_CLEARS, $progress)];
            // Reset again while the last one can still be undone (a second device, a second tap):
            // the undo still brings back what came before the first.
            $open = $learning->reset_snapshot !== null && $learning->undo_until?->isFuture() === true;
            $learning->forceFill([
                'reset_snapshot' => $open ? $learning->reset_snapshot : $progress,
                'progress' => $kept,
                'epoch' => $learning->epoch + 1,
                'version' => $learning->version + 1,
                'reset_at' => now(),
                'undo_until' => now()->addDays(self::UNDO_DAYS),
                'last_change_id' => $changeId,
            ])->save();

            return $learning;
        });
    }

    /**
     * account.learning.undoReset: the progress from before the reset, back, within 30 days.
     */
    public function undoReset(User $user, string $changeId): UserLearning
    {
        return DB::transaction(function () use ($user, $changeId): UserLearning {
            User::query()->whereKey($user->id)->lockForUpdate()->first(['id']);
            $learning = UserLearning::query()->where('user_id', $user->id)->lockForUpdate()->first();

            if ($learning !== null && $learning->last_change_id === $changeId) {
                return $learning;
            }

            if ($learning === null || $learning->reset_snapshot === null) {
                throw new Refusal(409, 'nothing_to_undo');
            }

            if ($learning->undo_until === null || $learning->undo_until->isPast()) {
                throw new Refusal(410, 'undo_expired');
            }

            // Only what the reset cleared comes back; XP, practice and badges earned since stay.
            $current = (array) ($learning->progress ?? []);
            $before = array_intersect_key((array) $learning->reset_snapshot, self::RESET_CLEARS);
            $learning->forceFill([
                'progress' => [...$current, ...array_intersect_key(self::RESET_CLEARS, $current), ...$before],
                'reset_snapshot' => null,
                // A new epoch again: a device holding the reset's empty copy takes this one.
                'epoch' => $learning->epoch + 1,
                'version' => $learning->version + 1,
                'reset_at' => null,
                'undo_until' => null,
                'last_change_id' => $changeId,
            ])->save();

            return $learning;
        });
    }

    /**
     * @return array{progress: object, version: int, epoch: int, resetAt: string|null, undoUntil: string|null, updatedAt: string|null}
     */
    public static function fields(?UserLearning $learning): array
    {
        $undo = $learning !== null && $learning->reset_snapshot !== null && $learning->undo_until?->isFuture() === true;

        return [
            'progress' => (object) ($learning->progress ?? []),
            'version' => $learning->version ?? 0,
            'epoch' => $learning->epoch ?? 0,
            'resetAt' => $learning?->reset_at?->utc()->toIso8601ZuluString(),
            'undoUntil' => $undo ? $learning->undo_until->utc()->toIso8601ZuluString() : null,
            'updatedAt' => $learning?->updated_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
