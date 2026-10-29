<?php

declare(strict_types=1);

namespace App\Support\Gallery;

use App\Actions\Gallery\Consents;
use App\Exceptions\Refusal;
use App\Models\Harmony;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\Flags;
use App\Support\Trust\TrustLevel;
use Illuminate\Database\Eloquent\Builder;

/**
 * The gallery's rules in one place (N1; contract/api.md, "The gallery (N1)"). It's open only when
 * Hillel switches the `gallery` flag on. Anything public about a person (a profile, a harmony in
 * the gallery, comments, follows) needs them to be 18 or over (the UK Children's Code row of the
 * trust and safety plan); commenting and posting need trust level 2, and a level-2 singer's first
 * two harmonies in the gallery wait for review. Blocks work both ways: neither sees the other.
 */
final class Gallery
{
    /** A level-2 singer's first this many harmonies in the gallery are reviewed first. */
    public const HELD_FIRST = 2;

    /** @var array<int, list<int>> */
    private array $blocked = [];

    /** @var array<int, bool> */
    private array $commenters = [];

    public function __construct(private readonly Flags $flags, private readonly TrustLevel $trust) {}

    public function isOpen(): bool
    {
        return $this->flags->on('gallery');
    }

    public function ensureOpen(): void
    {
        if (! $this->isOpen()) {
            throw new Refusal(409, 'gallery_closed');
        }
    }

    public static function adult(User $user): bool
    {
        // (Read from the row as loaded: a user made in memory may not carry the column yet.)
        return ($user->getAttributes()['adult_confirmed_at'] ?? null) !== null;
    }

    public static function ensureAdult(User $user): void
    {
        if (! self::adult($user)) {
            throw new Refusal(403, 'adult_required');
        }
    }

    public static function hasPublicProfile(User $user): bool
    {
        return ($user->getAttributes()['public_profile_at'] ?? null) !== null && self::adult($user) && $user->handle !== null && ! $user->isSuspended() && ! self::hidden($user);
    }

    /**
     * Hide profile (docs/hide-profile-plan.md): shown to others as "Anonymous", with no handle,
     * picture or profile page. A user loaded without the column counts as not hidden, so every
     * query that loads people to show others selects it (PublicPerson::COLUMNS).
     */
    public static function hidden(User $user): bool
    {
        return (bool) ($user->getAttributes()['hide_profile'] ?? false);
    }

    /**
     * Whether their work can be in the gallery and the library: an adult in good standing with a
     * public profile, or one who hid theirs (shown as Anonymous: Hillel's "keep songs without your
     * info being attached to a public profile").
     */
    public static function mayShowWork(User $user): bool
    {
        return self::adult($user) && ! $user->isSuspended() && (self::hidden($user) || self::hasPublicProfile($user));
    }

    /**
     * mayShowWork() as a query on the owner.
     *
     * @param  Builder<User>  $owner
     * @return Builder<User>
     */
    public static function whereMayShowWork(Builder $owner): Builder
    {
        return $owner->whereNotNull('adult_confirmed_at')->whereNull('suspended_at')
            ->where(fn ($q) => $q->where('hide_profile', true)->orWhere(fn ($q) => $q->whereNotNull('public_profile_at')->whereNotNull('handle')));
    }

    /**
     * Harmonies anyone can find in the gallery: live, listed by an owner who may show work
     * (mayShowWork) who said they have the right to share it, not waiting for review.
     *
     * @return Builder<Harmony>
     */
    public function listed(?User $viewer = null): Builder
    {
        $blocked = $viewer !== null ? $this->blockedIds($viewer) : [];

        return Harmony::query()
            ->whereNull('deleted_at')->where('status', Harmony::READY)->where('visibility', Harmony::UNLISTED)
            ->whereNotNull('gallery_opt_in_at')->whereNotNull('rights_confirmed_at')->whereNull('gallery_held_at')->whereNull('hidden_at')->whereNull('removed_at')
            ->whereHas('user', fn ($owner) => self::whereMayShowWork($owner))
            ->when($blocked !== [], fn ($query) => $query->whereNotIn('user_id', $blocked));
    }

    public function isListed(Harmony $harmony): bool
    {
        return $this->listed()->whereKey($harmony->id)->exists();
    }

    /**
     * Public in the sense of the guide-voice licence (Hillel's call, with the frontend's share
     * sheet): featured (its owner said yes to our accounts featuring it), or in the gallery; live,
     * its owner 18 or over, and every friend on it agreed (only adults can). An unlisted link
     * alone is never public.
     */
    public function isPublic(Harmony $harmony): bool
    {
        $harmony->loadMissing('user');

        return $harmony->isLive() && $harmony->user !== null && self::adult($harmony->user) && ! $harmony->user->isSuspended()
            && ($harmony->may_feature_at !== null || $this->isListed($harmony)) && Consents::allAgreed($harmony);
    }

    /**
     * Everyone the person blocked, or who blocked them.
     *
     * @return list<int>
     */
    public function blockedIds(User $user): array
    {
        return $this->blocked[$user->id] ??= array_values(array_unique(array_map('intval', [
            ...UserBlock::query()->where('blocker_id', $user->id)->pluck('blocked_id')->all(),
            ...UserBlock::query()->where('blocked_id', $user->id)->pluck('blocker_id')->all(),
        ])));
    }

    public function blockedBetween(User $a, int $b): bool
    {
        return in_array($b, $this->blockedIds($a), true);
    }

    /**
     * Whether this person may comment on this harmony (the app shows the box only when they may).
     */
    public function canComment(?User $viewer, Harmony $harmony): bool
    {
        return $viewer !== null && $this->mayComment($viewer)
            && $harmony->comments_off_at === null && ! $this->blockedBetween($viewer, $harmony->user_id);
    }

    /**
     * Whether they may comment at all: worked out once per person (a list asks for every card).
     */
    private function mayComment(User $viewer): bool
    {
        return $this->commenters[$viewer->id] ??= $this->isOpen() && self::adult($viewer) && $this->trust->of($viewer) >= TrustLevel::SINGER && ! $viewer->isSuspended();
    }

    public function canFollow(?User $viewer, User $profile): bool
    {
        return $viewer !== null && $this->isOpen() && $viewer->id !== $profile->id && self::adult($viewer) && $this->trust->of($viewer) >= TrustLevel::VERIFIED && ! $this->blockedBetween($viewer, $profile->id);
    }

    public function canPost(User $user): bool
    {
        return self::adult($user) && $this->trust->of($user) >= TrustLevel::SINGER && ! $user->isSuspended();
    }

    /**
     * Whether their next harmony in the gallery waits for review.
     */
    public function postsHeld(User $user): bool
    {
        return $this->trust->of($user) < TrustLevel::TRUSTED
            && Harmony::query()->where('user_id', $user->id)->whereNotNull('gallery_opt_in_at')->whereNull('gallery_held_at')->whereNotNull('rights_confirmed_at')->count() < self::HELD_FIRST;
    }

    /**
     * For account.show: what the gallery will let them do, in words the app can show (never
     * their trust level, which isn't shown to anyone but Hillel).
     *
     * @return array{canPost: bool, canComment: bool, postsHeld: bool}
     */
    public function abilities(User $user): array
    {
        $open = $this->isOpen();

        return [
            'canPost' => $open && $this->canPost($user),
            'canComment' => $open && self::adult($user) && $this->trust->of($user) >= TrustLevel::SINGER && ! $user->isSuspended(),
            'postsHeld' => $open && $this->postsHeld($user),
        ];
    }
}
