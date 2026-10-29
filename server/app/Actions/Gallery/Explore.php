<?php

declare(strict_types=1);

namespace App\Actions\Gallery;

use App\Models\Follow;
use App\Models\Harmony;
use App\Models\User;
use App\Support\Gallery\Gallery;
use App\Support\Gallery\GalleryViews;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * explore.list and profiles.harmonies (N1): the gallery's harmonies, newest first; Featured, the
 * ones Hillel featured; Following, from the profiles the person follows. Filtered by song, or by
 * words in the title or the song's title. Never anything from someone they're blocked with.
 */
final class Explore
{
    public const PAGE = 24;

    public function __construct(private readonly Gallery $gallery, private readonly GalleryViews $views) {}

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{nextCursor: string|null}}
     */
    public function list(?User $viewer, string $tab, ?string $song, ?string $q, ?int $ownerId = null): array
    {
        $query = $this->gallery->listed($viewer)->with(GalleryViews::WITH)
            ->when($ownerId !== null, fn ($query) => $query->where('user_id', $ownerId))
            ->when($tab === 'featured', fn ($query) => $query->whereNotNull('featured_at'))
            // The Open parts shelf (N5): harmonies with a part left for others.
            ->when($tab === 'open', fn ($query) => app(OpenParts::class)->isOpen() ? $query->whereNotNull('open_parts')->whereJsonLength('open_parts', '>', 0) : $query->whereRaw('1 = 0'))
            ->when($tab === 'following', fn ($query) => $query->whereIn('user_id', $viewer !== null ? Follow::query()->where('follower_id', $viewer->id)->select('followee_id') : [0]))
            ->when($song !== null && $song !== '', fn ($query) => $query->whereHas('templateVersion.template', fn ($t) => $t->where('slug', $song)))
            ->when($q !== null && trim($q) !== '', function ($query) use ($q) {
                $like = '%'.addcslashes(trim((string) $q), '%_\\').'%';

                $query->where(fn ($w) => $w->where(fn ($t) => $t->where('title', 'like', $like)->whereNull('title_held_at'))->orWhereHas('templateVersion.template', fn ($t) => $t->where('title', 'like', $like)));
            });

        // The plain newest-first list walks the gallery index in id order and stops at a page;
        // left to itself, MySQL sorts every ready harmony first (docs/performance.md). A tab, a
        // song, a search or a profile has a better index of its own.
        $plain = $ownerId === null && ! in_array($tab, ['featured', 'open', 'following'], true) && ($song === null || $song === '') && ($q === null || trim($q) === '');
        $query->when($plain, fn ($query) => $query->forceIndex('harmonies_gallery_index'));

        /** @var CursorPaginator<int, Harmony> $page */
        $page = ($tab === 'featured' ? $query->orderByDesc('featured_at') : $query)->orderByDesc('id')->cursorPaginate(self::PAGE);

        return ['data' => $this->views->harmonies(collect($page->items()), $viewer), 'meta' => ['nextCursor' => $page->nextCursor()?->encode()]];
    }
}
