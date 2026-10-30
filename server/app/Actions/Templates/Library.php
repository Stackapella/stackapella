<?php

declare(strict_types=1);

namespace App\Actions\Templates;

use App\Exceptions\Refusal;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\Template;
use App\Models\TemplatePartRange;
use App\Models\TemplateRating;
use App\Models\TemplateVersion;
use App\Models\User;
use App\Support\Flags;
use App\Support\Gallery\Gallery;
use App\Support\Gallery\GalleryViews;
use App\Support\Templates\Imported;
use App\Support\Trust\TrustLevel;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The template library (N3; docs/templates-plan.md, "The public library"; contract/api.md, "The
 * template library (N3)"): templates people publish to everyone, behind the `template_library`
 * flag. Publishing needs 18 or over, trust level 2, a public profile and the licensing
 * declaration; a creator's first two wait for review unless they're level 3. Public templates are
 * free and never count toward the free limit.
 */
final class Library
{
    /** A creator's first this many public templates are reviewed first. */
    public const HELD_FIRST = 2;

    /** Ratings below this many show no average. */
    public const RATINGS_TO_SHOW = 3;

    /** For "top": the average every template starts from, and how many ratings it's worth. */
    private const PRIOR_STARS = 3.5;

    private const PRIOR_WEIGHT = 3;

    public const PER_PAGE = 24;

    public function __construct(private readonly Flags $flags, private readonly TrustLevel $trust, private readonly Gallery $gallery) {}

    public function isOpen(): bool
    {
        return $this->flags->on('template_library');
    }

    /**
     * What the library filters on, read from the version just saved: its difficulty, genres, key,
     * tempo, voicings, words for search, and each part's range.
     */
    public static function facets(Template $template, TemplateVersion $version): void
    {
        $data = $version->data;
        $credits = array_filter(array_map(static fn (mixed $value): string => is_string($value) ? $value : '', (array) ($data['credits'] ?? [])));

        $template->forceFill([
            'difficulty' => is_string($data['difficulty'] ?? null) ? mb_substr($data['difficulty'], 0, 20) : null,
            'genres' => array_values(array_filter(array_map(static fn (mixed $genre): string => is_string($genre) ? mb_strtolower(mb_substr($genre, 0, 40)) : '', (array) ($data['genre'] ?? [])))),
            'music_key' => is_string($data['key'] ?? null) ? mb_substr($data['key'], 0, 8) : null,
            'tempo' => is_numeric($data['tempo'] ?? null) ? max(0, min(65535, (int) $data['tempo'])) : null,
            'voicings' => self::voicings($data),
            'search_text' => mb_substr(implode(' ', [(string) ($data['title'] ?? ''), (string) ($data['description'] ?? ''), ...$credits]), 0, 5000),
        ])->save();

        TemplatePartRange::query()->where('template_id', $template->id)->delete();
        $ranges = [];

        foreach ((array) ($data['parts'] ?? []) as $part) {
            if (is_array($part) && is_string($part['key'] ?? null) && is_numeric($part['range']['low'] ?? null) && is_numeric($part['range']['high'] ?? null)) {
                $ranges[] = ['template_id' => $template->id, 'part_key' => mb_substr($part['key'], 0, 40), 'low' => max(0, min(127, (int) $part['range']['low'])), 'high' => max(0, min(127, (int) $part['range']['high']))];
            }
        }

        TemplatePartRange::query()->insert($ranges);
    }

    /**
     * @param  array{song: string, source?: string|null, ownWork: bool}  $declaration
     */
    public function publish(User $user, string $uuid, array $declaration, ?string $ip): Template
    {
        if (! $this->isOpen()) {
            throw new Refusal(409, 'library_closed');
        }

        Gallery::ensureAdult($user);

        if ($this->trust->of($user) < TrustLevel::SINGER || $user->isSuspended()) {
            throw new Refusal(403, 'trust_required');
        }

        // A public profile, or a hidden one (shown as Anonymous).
        if (! Gallery::mayShowWork($user)) {
            throw new Refusal(409, 'profile_required');
        }

        return DB::transaction(function () use ($user, $uuid, $declaration, $ip): Template {
            $template = Template::query()->where('slug', $uuid)->where('owner_id', $user->id)->whereNull('deleted_at')->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');

            if ($template->isImported()) {
                Imported::refuse();
            }

            $stored = self::declaration($declaration);
            $first = $template->published_at === null;
            $already = $template->isPublic();

            $template->timestamps = false;
            $template->forceFill([
                'visibility' => 'public',
                'declaration' => $stored,
                'published_at' => $template->published_at ?? now(),
                // A creator's first two wait for review (never one already listed, or hidden).
                'held_at' => ! $already && $template->hidden_at === null && $this->heldFirst($user) ? now() : $template->held_at,
            ])->save();
            $template->timestamps = true;

            AuditLog::record($first ? 'library.published' : 'library.republished', $template, $user, after: ['declaration' => $stored, 'held' => $template->held_at !== null], ip: $ip);

            return $template;
        });
    }

    public function unpublish(User $user, string $uuid, ?string $ip): Template
    {
        return DB::transaction(function () use ($user, $uuid, $ip): Template {
            $template = Template::query()->where('slug', $uuid)->where('owner_id', $user->id)->whereNull('deleted_at')->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');

            if ($template->isPublic()) {
                $template->timestamps = false;
                $template->forceFill(['visibility' => 'unlisted', 'featured_at' => null])->save();
                $template->timestamps = true;
                AuditLog::record('library.unpublished', $template, $user, ip: $ip);
            }

            return $template;
        });
    }

    /**
     * The declaration as it's kept: what was said, and when.
     *
     * @param  array{song: string, source?: string|null, ownWork: bool}  $declaration
     * @return array{song: string, source: string|null, ownWork: bool, at: string}
     */
    public static function declaration(array $declaration): array
    {
        $source = trim((string) ($declaration['source'] ?? ''));

        return ['song' => $declaration['song'], 'source' => $source !== '' ? $source : null, 'ownWork' => true, 'at' => now()->utc()->toIso8601ZuluString()];
    }

    /**
     * Whether their next public template waits for review.
     */
    public function heldFirst(User $user): bool
    {
        return $this->trust->of($user) < TrustLevel::TRUSTED
            && Template::query()->where('owner_id', $user->id)->where('visibility', 'public')->whereNull('held_at')->whereNull('hidden_at')->whereNull('deleted_at')->count() < self::HELD_FIRST;
    }

    /**
     * Templates anyone can find in the library: public, not held or hidden, by a creator whose
     * profile is public.
     *
     * @return Builder<Template>
     */
    public function listed(?User $viewer = null): Builder
    {
        $blocked = $viewer !== null ? $this->gallery->blockedIds($viewer) : [];

        return Template::query()
            ->whereNotNull('owner_id')->where('visibility', 'public')->whereNull('deleted_at')->whereNull('held_at')->whereNull('hidden_at')->whereNotNull('current_version_id')
            ->whereHas('owner', fn ($owner) => Gallery::whereMayShowWork($owner))
            ->when($blocked !== [], fn ($query) => $query->whereNotIn('owner_id', $blocked));
    }

    public function isListed(Template $template): bool
    {
        return $this->isOpen() && $this->listed()->whereKey($template->id)->exists();
    }

    /**
     * library.list.
     *
     * @param  array{q?: string|null, difficulty?: string|null, parts?: int|string|null, genre?: string|null, key?: string|null, tempo?: string|null, low?: int|string|null, high?: int|string|null, creator?: string|null, sort?: string|null, tab?: string|null}  $filters
     * @return CursorPaginator<int, Template>
     */
    public function search(?User $viewer, array $filters): CursorPaginator
    {
        if (! $this->isOpen()) {
            throw new Refusal(404, 'not_found');
        }

        $query = $this->listed($viewer)->with(['currentVersion', 'owner', 'parent.owner']);
        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $like = '%'.addcslashes($q, '%_\\').'%';
            $query->where(fn ($match) => $match->where('title', 'like', $like)->orWhere('search_text', 'like', $like));
        }

        $tempo = $filters['tempo'] ?? null;
        $difficulty = $filters['difficulty'] ?? null;
        $parts = $filters['parts'] ?? null;
        $genre = $filters['genre'] ?? null;
        $key = $filters['key'] ?? null;
        $low = $filters['low'] ?? null;
        $high = $filters['high'] ?? null;
        $creator = $filters['creator'] ?? null;
        $query
            ->when($difficulty !== null, fn ($match) => $match->where('difficulty', $difficulty))
            ->when($parts !== null, fn ($match) => $match->whereJsonContains('voicings', (int) $parts))
            ->when($genre !== null, fn ($match) => $match->whereJsonContains('genres', mb_strtolower((string) $genre)))
            ->when($key !== null, fn ($match) => $match->where('music_key', $key))
            ->when($tempo === 'slow', fn ($match) => $match->where('tempo', '<', 80))
            ->when($tempo === 'medium', fn ($match) => $match->whereBetween('tempo', [80, 120]))
            ->when($tempo === 'fast', fn ($match) => $match->where('tempo', '>', 120))
            ->when($low !== null || $high !== null, fn ($match) => $match->whereExists(
                fn ($range) => $range->selectRaw('1')->from('template_part_ranges')->whereColumn('template_part_ranges.template_id', 'templates.id')
                    ->when($low !== null, fn ($r) => $r->where('low', '>=', (int) $low))
                    ->when($high !== null, fn ($r) => $r->where('high', '<=', (int) $high))
            ))
            ->when($creator !== null, fn ($match) => $match->whereHas('owner', fn ($owner) => $owner->where('handle', mb_strtolower((string) $creator))));

        if (($filters['tab'] ?? null) === 'featured') {
            $query->whereNotNull('featured_at')->orderByDesc('featured_at');
        } else {
            match ($filters['sort'] ?? 'popular') {
                'newest' => $query->orderByDesc('published_at'),
                'top' => $query->orderByDesc('rating_score'),
                default => $query->orderByDesc('uses_count'),
            };
        }

        return $query->orderByDesc('id')->cursorPaginate(self::PER_PAGE);
    }

    /**
     * The viewer's stars for these templates.
     *
     * @param  list<int>  $templateIds
     * @return array<int, int>
     */
    public static function ratingsOf(?User $viewer, array $templateIds): array
    {
        if ($viewer === null || $templateIds === []) {
            return [];
        }

        return array_map('intval', TemplateRating::query()->where('user_id', $viewer->id)->whereIn('template_id', $templateIds)->pluck('stars', 'template_id')->all());
    }

    /**
     * The genres in use in the library, for its filter.
     *
     * @return list<string>
     */
    public function genres(?User $viewer): array
    {
        $genres = [];

        foreach ($this->listed($viewer)->pluck('genres') as $list) {
            foreach ((array) $list as $genre) {
                $genres[(string) $genre] = true;
            }
        }

        $genres = array_keys($genres);
        sort($genres);

        return array_values(array_map('strval', $genres));
    }

    /**
     * A template's `library`, for whoever is looking ($ratings: the viewer's stars by template,
     * when a list has read them all at once).
     *
     * @param  array<int, int>|null  $ratings
     * @return array<string, mixed>|null
     */
    public function view(Template $template, ?User $viewer, ?array $ratings = null): ?array
    {
        if (! $template->isPublic()) {
            return null;
        }

        $template->loadMissing(['owner', 'parent.owner']);
        $mine = match (true) {
            // Its creator can't rate their own.
            $viewer === null, $viewer->id === $template->owner_id => null,
            $ratings !== null => $ratings[$template->id] ?? null,
            default => TemplateRating::query()->where('template_id', $template->id)->where('user_id', $viewer->id)->value('stars'),
        };

        return [
            'status' => $template->libraryStatus(),
            'creator' => GalleryViews::person($template->owner),
            'uses' => $template->uses_count,
            'rating' => self::rating($template, is_numeric($mine) ? (int) $mine : null),
            'featured' => $template->featured_at !== null,
            'publishedAt' => $template->published_at?->utc()->toIso8601ZuluString(),
            'parent' => self::parent($template),
        ];
    }

    /**
     * @return array{average: float|null, count: int, mine: int|null}
     */
    public static function rating(Template $template, ?int $mine): array
    {
        return [
            'average' => $template->ratings_count >= self::RATINGS_TO_SHOW ? round($template->ratings_sum / $template->ratings_count, 1) : null,
            'count' => $template->ratings_count,
            'mine' => $mine,
        ];
    }

    /**
     * Where a remix came from, while it can still be seen (a public one, or one of our songs).
     *
     * @return array{id: string, title: string, creator: array<string, mixed>|null}|null
     */
    public static function parent(Template $template): ?array
    {
        if ($template->parent_id === null) {
            return null;
        }

        $template->loadMissing('parent.owner');
        $parent = $template->parent;

        if ($parent === null || $parent->deleted_at !== null || (! $parent->isOfficial() && ($parent->libraryStatus() !== 'listed'))) {
            return null;
        }

        return ['id' => $parent->slug, 'title' => $parent->title, 'creator' => $parent->isOfficial() ? null : GalleryViews::person($parent->owner)];
    }

    /**
     * templates.rate: only someone who made a project from it, verified, never its creator.
     *
     * @return array{average: float|null, count: int, mine: int|null}
     */
    public function rate(User $user, string $uuid, ?int $stars): array
    {
        $template = $this->publicTemplate($uuid, $user);

        if ($stars !== null && ($template->owner_id === $user->id || $this->trust->of($user) < TrustLevel::VERIFIED || ! $this->hasUsed($user, $template))) {
            throw new Refusal(403, 'rating_not_allowed');
        }

        DB::transaction(function () use ($user, $template, $stars): void {
            Template::query()->whereKey($template->id)->lockForUpdate()->first(['id']);

            if ($stars === null) {
                TemplateRating::query()->where('template_id', $template->id)->where('user_id', $user->id)->delete();
            } else {
                TemplateRating::query()->upsert([['template_id' => $template->id, 'user_id' => $user->id, 'stars' => $stars, 'created_at' => now(), 'updated_at' => now()]], ['template_id', 'user_id'], ['stars', 'updated_at']);
            }

            self::recountRatings($template);
        });

        return self::rating($template->refresh(), $stars);
    }

    /**
     * Its ratings' count, sum and the score "top" sorts by (few ratings pull toward the middle).
     */
    public static function recountRatings(Template $template): void
    {
        $count = TemplateRating::query()->where('template_id', $template->id)->count();
        $sum = (int) TemplateRating::query()->where('template_id', $template->id)->sum('stars');
        $score = ($sum + self::PRIOR_STARS * self::PRIOR_WEIGHT) / ($count + self::PRIOR_WEIGHT);

        Template::query()->whereKey($template->id)->update(['ratings_count' => $count, 'ratings_sum' => $sum, 'rating_score' => round($score, 4)]);
    }

    /**
     * The nightly recount: how many different people (not its creator) made a project from each
     * person's template.
     */
    public function recountUses(): int
    {
        $changed = 0;

        Template::query()->whereNotNull('owner_id')->whereNull('deleted_at')->select(['id', 'slug', 'owner_id', 'uses_count'])->chunkById(200, function ($templates) use (&$changed): void {
            $counts = Project::query()->whereIn('template_slug', $templates->pluck('slug'))
                ->selectRaw('template_slug, user_id')->distinct()->get()
                ->groupBy('template_slug');

            foreach ($templates as $template) {
                $uses = ($counts->get($template->slug) ?? collect())->where('user_id', '!=', $template->owner_id)->count();

                if ($uses !== $template->uses_count) {
                    Template::query()->whereKey($template->id)->update(['uses_count' => $uses]);
                    $changed++;
                }
            }
        });

        return $changed;
    }

    private function hasUsed(User $user, Template $template): bool
    {
        return Project::query()->where('user_id', $user->id)->where('template_slug', $template->slug)->exists();
    }

    /**
     * A public template the person can see (listed, or held: as if unlisted), else 404.
     */
    private function publicTemplate(string $uuid, User $viewer): Template
    {
        $template = Template::query()->where('slug', $uuid)->whereNotNull('owner_id')->where('visibility', 'public')->whereNull('deleted_at')->first();

        if ($template === null || ! $this->isOpen() || ($template->hidden_at !== null && $template->owner_id !== $viewer->id) || $this->gallery->blockedBetween($viewer, (int) $template->owner_id)) {
            throw new Refusal(404, 'not_found');
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    private static function voicings(array $data): array
    {
        $counts = array_map('intval', array_filter(array_keys((array) ($data['voicings'] ?? [])), 'is_numeric'));

        if ($counts === []) {
            $counts = [count((array) ($data['parts'] ?? []))];
        }

        sort($counts);

        return array_values(array_unique($counts));
    }
}
