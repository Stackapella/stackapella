<?php

declare(strict_types=1);

namespace App\Support\OwnChoir;

use App\Enums\OwnChoirStatus;
use App\Models\ChoirGalleryItem;
use App\Models\OwnChoirPart;
use App\Models\OwnChoirRequest;
use App\Models\User;
use App\Support\Accounts\AiChoice;
use App\Support\Flags;

/**
 * The choir gallery's rules (docs/choir-gallery-plan.md, version 1): who may add which song, and what anyone sees of an
 * item. Worked out when read, so nothing has to follow the song: an item is out of the gallery once its song, its share
 * or its singer's account is gone, hidden while their AI features are off, and hidden after a remake until it's added
 * again (the share staff heard is no longer the song).
 */
final class ChoirGallery
{
    public static function on(?User $user = null): bool
    {
        return app(Flags::class)->onFor('choir_gallery', $user);
    }

    public static function consentVersion(): int
    {
        return (int) config('own_choir.gallery.consent_version');
    }

    /** AI features off for them (turned off, hidden, or under 18): their items are hidden. */
    public static function aiOff(User $user): bool
    {
        return ! AiChoice::aiOn($user);
    }

    /** The song's parts as they are, by their files' hashes: a remake changes it. */
    public static function partsSha(OwnChoirRequest $request): string
    {
        $hashes = $request->parts->map(static fn (OwnChoirPart $part): string => $part->part_key.':'.$part->sha256)->sort()->values()->all();

        return hash('sha256', implode(',', $hashes));
    }

    /**
     * Why the owner can't add this song now; null when they can (or when it's already in, waiting or shown).
     */
    public static function cannot(OwnChoirRequest $request, User $owner): ?string
    {
        $item = $request->galleryItem;

        return match (true) {
            $request->status !== OwnChoirStatus::Ready => 'not_ready',
            $request->own => 'not_library',
            $request->members->isNotEmpty() => 'together',
            self::aiOff($owner) => 'ai_off',
            $item !== null && in_array($item->status, ChoirGalleryItem::LIVE, true) => null,
            self::counts($owner->id)['today'] >= (int) config('own_choir.gallery.adds_a_day') => 'limit_day',
            self::counts($owner->id)['live'] >= (int) config('own_choir.gallery.live') => 'limit_live',
            default => null,
        };
    }

    /**
     * Their adds today and their live items, read once a request (a list of songs asks for each).
     *
     * @return array{today: int, live: int}
     */
    private static function counts(int $ownerId): array
    {
        return once(static fn (): array => [
            'today' => ChoirGalleryItem::query()->where('user_id', $ownerId)->where('added_at', '>=', now()->startOfDay())->count(),
            'live' => ChoirGalleryItem::query()->where('user_id', $ownerId)->whereIn('status', ChoirGalleryItem::LIVE)->count(),
        ]);
    }

    /** Its status as anyone would see it: removed once the song or its share is gone. */
    public static function status(ChoirGalleryItem $item): string
    {
        $harmony = $item->harmony;

        if ($item->status === ChoirGalleryItem::REMOVED || $item->request->status !== OwnChoirStatus::Ready || $harmony === null || ! $harmony->isLive()) {
            return ChoirGalleryItem::REMOVED;
        }

        return $item->status;
    }

    /** Why an approved item isn't shown: its singer's AI features are off, or the song changed since it was added. */
    public static function hiddenBecause(ChoirGalleryItem $item): ?string
    {
        return match (true) {
            self::aiOff($item->user) => 'ai_off',
            self::partsSha($item->request) !== $item->parts_sha => 'changed',
            default => null,
        };
    }

    public static function visible(ChoirGalleryItem $item): bool
    {
        return self::status($item) === ChoirGalleryItem::APPROVED && self::hiddenBecause($item) === null;
    }

    /**
     * The owner's view of it, on their song (ownChoir.show's `gallery`).
     *
     * @return array<string, mixed>|null
     */
    public static function entry(?ChoirGalleryItem $item): ?array
    {
        if ($item === null) {
            return null;
        }

        $status = self::status($item);

        return [
            'id' => $item->sqid(),
            'harmonyId' => (string) ($item->harmony->public_id ?? ''),
            'status' => $status,
            'reason' => $item->reason,
            'reasonText' => $item->reason === 'other' ? $item->reason_text : null,
            'hiddenBecause' => $status === ChoirGalleryItem::APPROVED ? self::hiddenBecause($item) : null,
            'addedAt' => $item->added_at->utc()->toIso8601ZuluString(),
            'decidedAt' => $item->decided_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * What anyone sees of it: no name.
     *
     * @return array<string, mixed>
     */
    public static function shown(ChoirGalleryItem $item): array
    {
        $version = $item->request->templateVersion;

        return [
            'id' => $item->sqid(),
            'harmonyId' => (string) $item->harmony?->public_id,
            'title' => (string) $version?->template?->title,
            'templateId' => (string) $version?->template?->slug,
            'parts' => self::parts($item),
            'addedAt' => $item->added_at->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * The parts' names, as its share shows them.
     *
     * @return list<string>
     */
    public static function parts(ChoirGalleryItem $item): array
    {
        return array_values(array_map(static fn (array $track): string => $track['name'], $item->harmony->tracks ?? []));
    }

    /** Whether a share is in the gallery now: its page and preview then say "A singer", never a name. */
    public static function lists(int $harmonyId): bool
    {
        // Asked several times as a page is drawn: once a request for each share.
        return once(static fn (): bool => ChoirGalleryItem::query()->where('harmony_id', $harmonyId)->whereIn('status', ChoirGalleryItem::LIVE)->exists());
    }
}
