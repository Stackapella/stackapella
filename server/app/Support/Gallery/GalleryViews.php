<?php

declare(strict_types=1);

namespace App\Support\Gallery;

use App\Actions\Gallery\OpenParts;
use App\Models\Comment;
use App\Models\Follow;
use App\Models\Harmony;
use App\Models\HarmonyLike;
use App\Models\User;
use App\Support\Http\Hosts;
use App\Support\Moderation\TextFilter;
use App\Support\Sharing\HarmonyFacts;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * The gallery's shapes (contract/schemas/galleryHarmony.json, profile.json, comment.json), each
 * list loaded in a few queries whatever its length.
 */
final class GalleryViews
{
    /** What a gallery harmony needs loaded. */
    public const WITH = ['user', 'templateVersion.template'];

    public function __construct(private readonly Gallery $gallery, private readonly HarmonyFacts $facts) {}

    /**
     * @param  Collection<int, Harmony>  $harmonies
     * @return list<array<string, mixed>>
     */
    public function harmonies(Collection $harmonies, ?User $viewer): array
    {
        $liked = $viewer !== null && $harmonies->isNotEmpty()
            ? array_flip(HarmonyLike::query()->where('user_id', $viewer->id)->whereIn('harmony_id', $harmonies->pluck('id')->all())->pluck('harmony_id')->all())
            : [];
        $friends = User::query()->whereKey($harmonies->flatMap(fn (Harmony $h): array => $h->singer_ids ?? [])->unique()->values()->all())->get(['id', 'name', 'handle', 'public_profile_at', 'hide_profile', 'adult_confirmed_at', 'suspended_at'])->keyBy('id');

        return array_values($harmonies->map(fn (Harmony $h): array => $this->harmony($h, $viewer, isset($liked[$h->id]), $friends))->all());
    }

    /**
     * @param  Collection<int, User>|null  $friends
     * @return array<string, mixed>
     */
    public function harmony(Harmony $harmony, ?User $viewer, bool $liked, ?Collection $friends = null): array
    {
        $friends ??= User::query()->whereKey($harmony->singer_ids ?? [])->get(['id', 'name', 'handle', 'public_profile_at', 'hide_profile', 'adult_confirmed_at', 'suspended_at'])->keyBy('id');
        $song = $harmony->templateVersion?->template;
        $credit = static fn (User $u): array => ['name' => self::name($u), 'handle' => Gallery::hasPublicProfile($u) ? $u->handle : null];

        return [
            'id' => $harmony->public_id,
            'title' => $this->facts->title($harmony),
            'pageUrl' => Hosts::public('h/'.$harmony->public_id),
            'audioUrl' => $harmony->media_audio_key !== null ? Storage::disk('media')->url($harmony->media_audio_key) : null,
            'cardUrl' => $harmony->media_card_key !== null ? Storage::disk('media')->url($harmony->media_card_key) : null,
            'durationSeconds' => $harmony->durationSeconds(),
            'song' => $song !== null && $song->isOfficial() ? ['id' => $song->slug, 'title' => $song->title] : null,
            'owner' => self::person($harmony->user),
            'singers' => [$credit($harmony->user), ...array_values(array_map($credit, array_values(array_filter(array_map(static fn (int $id): ?User => $friends->get($id), $harmony->singer_ids ?? [])))))],
            'likes' => $harmony->likes_count,
            'comments' => $harmony->comments_count,
            'liked' => $liked,
            'commentsOff' => $harmony->comments_off_at !== null,
            'canComment' => $this->gallery->canComment($viewer, $harmony),
            'featured' => $harmony->featured_at !== null,
            'openParts' => app(OpenParts::class)->partNames($harmony),
            'createdAt' => $harmony->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function profile(User $user, ?User $viewer): array
    {
        return [
            ...self::person($user),
            'bio' => $user->bio !== null && ! TextFilter::holds($user->bio) ? $user->bio : null,
            'followers' => Follow::query()->where('followee_id', $user->id)->count(),
            'following' => Follow::query()->where('follower_id', $user->id)->count(),
            'harmonies' => $this->gallery->listed()->where('user_id', $user->id)->count(),
            'isMe' => $viewer?->id === $user->id,
            'isFollowing' => $viewer !== null && Follow::query()->where('follower_id', $viewer->id)->where('followee_id', $user->id)->exists(),
            'blocked' => $viewer !== null && $this->gallery->blockedBetween($viewer, $user->id),
            'canFollow' => $this->gallery->canFollow($viewer, $user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function comment(Comment $comment, ?User $viewer, Harmony $harmony): array
    {
        return [
            'id' => $comment->sqid(),
            'body' => $comment->body,
            'author' => self::person($comment->user),
            'mine' => $viewer?->id === $comment->user_id,
            'canDelete' => $viewer !== null && in_array($viewer->id, [$comment->user_id, $harmony->user_id], true),
            'held' => $comment->held_at !== null,
            'createdAt' => $comment->created_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array{handle: string|null, name: string, avatarUrl: string|null}
     */
    public static function person(?User $user): array
    {
        if ($user === null) {
            return ['handle' => null, 'name' => __('coaching.deleted_account'), 'avatarUrl' => null];
        }

        if (Gallery::hidden($user)) {
            return ['handle' => null, 'name' => __('sharing.anonymous'), 'avatarUrl' => null];
        }

        return [
            'handle' => Gallery::hasPublicProfile($user) ? $user->handle : null,
            'name' => self::name($user),
            'avatarUrl' => $user->avatar_key !== null ? Storage::disk('media')->url($user->avatar_key) : null,
        ];
    }

    /**
     * A name shown in public: "Anonymous" for a hidden profile, and one with a link or a word-list
     * match is never drawn there.
     */
    public static function name(User $user): string
    {
        if (Gallery::hidden($user)) {
            return __('sharing.anonymous');
        }

        return TextFilter::hasLink($user->name) || TextFilter::holds($user->name) ? __('sharing.a_singer') : $user->name;
    }
}
