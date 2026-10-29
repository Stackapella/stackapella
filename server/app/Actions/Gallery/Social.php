<?php

declare(strict_types=1);

namespace App\Actions\Gallery;

use App\Exceptions\Refusal;
use App\Models\Follow;
use App\Models\Harmony;
use App\Models\HarmonyLike;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\Gallery\Bell;
use App\Support\Gallery\Gallery;
use App\Support\Trust\TrustLevel;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Likes, follows and blocks in the gallery (N1). Each is idempotent: doing it twice is the same as
 * once (the trust and safety plan's "duplicates are no-ops"), and the counts are kept with it.
 */
final class Social
{
    public function __construct(private readonly Gallery $gallery, private readonly TrustLevel $trust) {}

    /**
     * @return array{liked: bool, likes: int}
     */
    public function like(User $user, Harmony $harmony, bool $liked): array
    {
        $this->gallery->ensureOpen();

        if (! $this->gallery->isListed($harmony) || $this->gallery->blockedBetween($user, $harmony->user_id)) {
            throw new Refusal(404, 'not_found');
        }

        if ($liked && $this->trust->of($user) < TrustLevel::VERIFIED) {
            throw new Refusal(403, 'email_unverified');
        }

        $changed = DB::transaction(function () use ($user, $harmony, $liked): bool {
            if (! $liked) {
                $gone = HarmonyLike::query()->where('harmony_id', $harmony->id)->where('user_id', $user->id)->delete() > 0;
                $gone && Harmony::query()->whereKey($harmony->id)->where('likes_count', '>', 0)->decrement('likes_count');

                return false;
            }

            try {
                (new HarmonyLike)->forceFill(['harmony_id' => $harmony->id, 'user_id' => $user->id, 'created_at' => now()])->save();
            } catch (UniqueConstraintViolationException) {
                return false;
            }

            Harmony::query()->whereKey($harmony->id)->increment('likes_count');

            return true;
        });

        if ($changed) {
            Bell::tellOnce($harmony->user_id, 'like', $user, $harmony);
        }

        return ['liked' => $liked, 'likes' => (int) Harmony::query()->whereKey($harmony->id)->value('likes_count')];
    }

    /**
     * @return array{following: bool, followers: int}
     */
    public function follow(User $user, User $profile, bool $following): array
    {
        $this->gallery->ensureOpen();

        // (Unfollowing works whatever the profile is now, so a follow never outlasts the wish.)
        if ($following && ! Gallery::hasPublicProfile($profile)) {
            throw new Refusal(404, 'not_found');
        }

        if ($following) {
            Gallery::ensureAdult($user);

            if (! $this->gallery->canFollow($user, $profile)) {
                throw new Refusal(403, 'blocked');
            }

            try {
                (new Follow)->forceFill(['follower_id' => $user->id, 'followee_id' => $profile->id, 'created_at' => now()])->save();
                Bell::tellOnce($profile->id, 'follow', $user);
            } catch (UniqueConstraintViolationException) {
                // Already following: the same answer.
            }
        } else {
            Follow::query()->where('follower_id', $user->id)->where('followee_id', $profile->id)->delete();
        }

        return ['following' => $following, 'followers' => Follow::query()->where('followee_id', $profile->id)->count()];
    }

    public function block(User $user, User $other, bool $blocked): void
    {
        if ($other->id === $user->id) {
            throw new Refusal(422, 'validation_failed');
        }

        if (! $blocked) {
            UserBlock::query()->where('blocker_id', $user->id)->where('blocked_id', $other->id)->delete();

            return;
        }

        DB::transaction(function () use ($user, $other): void {
            UserBlock::query()->firstOrCreate(['blocker_id' => $user->id, 'blocked_id' => $other->id], ['created_at' => now()]);
            // Neither follows the other any more.
            Follow::query()->where(fn ($q) => $q->where('follower_id', $user->id)->where('followee_id', $other->id))
                ->orWhere(fn ($q) => $q->where('follower_id', $other->id)->where('followee_id', $user->id))->delete();
        });
    }
}
