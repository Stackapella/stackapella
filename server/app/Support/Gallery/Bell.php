<?php

declare(strict_types=1);

namespace App\Support\Gallery;

use App\Models\BellItem;
use App\Models\Harmony;
use App\Models\Idea;
use App\Models\PartScore;
use App\Models\SongRequest;
use App\Models\User;
use App\Support\Push\AppPushes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The bell (N1; in the app only, never email): what happened that's about the person. Likes are
 * grouped, one item per harmony a day with how many others; nobody is told about their own doing.
 * Each new item is also pushed to the native apps, when push is on (AppPushes).
 */
final class Bell
{
    /**
     * Rings the bell once for each person and thing (a like a day, a follow a month): liking or
     * following over and over never rings it again.
     */
    public static function tellOnce(int $userId, string $kind, User $actor, ?Harmony $harmony = null): void
    {
        $days = $kind === 'like' ? 1 : 30;

        if (Cache::add('bell:'.$kind.':'.$userId.':'.($harmony->id ?? 0).':'.$actor->id, true, now()->addDays($days))) {
            self::tell($userId, $kind, $actor, $harmony);
        }
    }

    public static function tell(int $userId, string $kind, ?User $actor, ?Harmony $harmony = null, ?Idea $idea = null, ?SongRequest $request = null, ?PartScore $score = null): void
    {
        if ($actor !== null && $actor->id === $userId) {
            return;
        }

        // A new voice on someone's idea: grouped per idea a day, as likes are.
        if ($kind === 'idea_voice' && $idea !== null) {
            $key = 'idea:'.$idea->id.':'.now()->utc()->toDateString();

            if (BellItem::query()->where('user_id', $userId)->where('group_key', $key)->update(['others' => DB::raw('others + 1'), 'actor_id' => $actor?->id, 'read_at' => null, 'updated_at' => now()]) > 0) {
                return;
            }

            try {
                self::create(['user_id' => $userId, 'kind' => $kind, 'actor_id' => $actor?->id, 'idea_id' => $idea->id, 'group_key' => $key]);
            } catch (UniqueConstraintViolationException) {
                BellItem::query()->where('user_id', $userId)->where('group_key', $key)->increment('others');
            }

            return;
        }

        if ($kind === 'like' && $harmony !== null) {
            $key = 'like:'.$harmony->id.':'.now()->utc()->toDateString();
            // (One update, so two likes at once both count.)
            if (BellItem::query()->where('user_id', $userId)->where('group_key', $key)->update(['others' => DB::raw('others + 1'), 'actor_id' => $actor?->id, 'read_at' => null, 'updated_at' => now()]) > 0) {
                return;
            }

            try {
                self::create(['user_id' => $userId, 'kind' => $kind, 'actor_id' => $actor?->id, 'harmony_id' => $harmony->id, 'group_key' => $key]);
            } catch (UniqueConstraintViolationException) {
                BellItem::query()->where('user_id', $userId)->where('group_key', $key)->increment('others');
            }

            return;
        }

        self::create(['user_id' => $userId, 'kind' => $kind, 'actor_id' => $actor?->id, 'harmony_id' => $harmony?->id, 'idea_id' => $idea?->id, 'song_request_id' => $request?->id, 'part_score_id' => $score?->id]);
    }

    /**
     * A new item in the bell, and its push to the apps (a group's later news adds to it quietly).
     *
     * @param  array<string, mixed>  $attributes
     */
    private static function create(array $attributes): void
    {
        $item = (new BellItem)->forceFill($attributes);
        $item->save();
        AppPushes::queue($item);
    }
}
