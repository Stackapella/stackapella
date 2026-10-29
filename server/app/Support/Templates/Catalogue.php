<?php

declare(strict_types=1);

namespace App\Support\Templates;

use App\Enums\TemplateAccess;
use App\Models\BetaAssignment;
use App\Models\GroupSong;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\TemplateUnlock;
use App\Models\User;
use App\Support\Usage\Plans;
use Illuminate\Support\Collection;

/**
 * The library as anyone may see it (docs/pricing-plan.md): every song's current version, its
 * parts' names and ranges, and, for a song the person may start, each part's default real voice.
 * Never the notes, and never part audio of a song they can't start.
 */
final class Catalogue
{
    /**
     * @return Collection<int, Template>
     */
    public function songs(): Collection
    {
        return Template::query()->listed()->with('currentVersion')->whereNotNull('current_version_id')->orderBy('title')->get();
    }

    /** @var array<int, array<int, int>> the songs each person kept from the song of the month */
    private array $kept = [];

    /** @var array<int, list<int>> the songs shared with each person's choir groups */
    private array $grouped = [];

    /** @var array<int, array<int, int>> each beta singer's assigned songs, by user id */
    private array $assigned = [];

    public function __construct(private readonly Plans $plans) {}

    public function mayStart(Template $template, ?User $user): bool
    {
        return $this->grant($template, $user) !== null;
    }

    /**
     * Why someone may have a song's notes and voices (contract/api.md, "Plus and billing"): a free
     * song, Plus, the song of the month (signed out too), or kept from a project started while it
     * was; null when they may not.
     *
     * @return 'free'|'plus'|'month'|'kept'|'group'|'admin'|'beta'|null
     */
    public function grant(Template $template, ?User $user): ?string
    {
        return match (true) {
            $template->access === TemplateAccess::Free => 'free',
            $user !== null && $this->plans->isPlus($user) => 'plus',
            $template->isFreeOn(now()) => 'month',
            $user !== null && in_array($template->id, $this->kept[$user->id] ??= TemplateUnlock::query()->where('user_id', $user->id)->pluck('template_id')->map(fn (mixed $id): int => (int) $id)->values()->all(), true) => 'kept',
            // A choir group's song, while the person sings in that group and its director has Plus.
            $user !== null && in_array($template->id, $this->plans->groupSongIds($user), true) => 'group',
            // Hillel and the editor, reviewing beta takes and guides.
            $user !== null && ($user->is_admin || $user->is_editor) => 'admin',
            // A beta singer's assigned song, to record their part.
            $user !== null && in_array($template->id, $this->assigned[$user->id] ??= BetaAssignment::query()->whereHas('singer', fn ($q) => $q->where('user_id', $user->id)->whereNull('revoked_at')->whereNull('forgotten_at'))->pluck('template_id')->map(fn (mixed $id): int => (int) $id)->values()->all(), true) => 'beta',
            default => null,
        };
    }

    /**
     * The song is shared with a choir group the person is in (as its director or a member).
     */
    public function inGroup(Template $template, User $user): bool
    {
        $this->grouped[$user->id] ??= array_values(GroupSong::query()->whereHas('group.members', fn ($query) => $query->where('user_id', $user->id))->pluck('template_id')->map(fn (mixed $id): int => (int) $id)->all());

        return in_array($template->id, $this->grouped[$user->id], true);
    }

    /**
     * Every published version of each part, for these template versions, keyed by
     * "versionId:part": the default first, then the newest.
     *
     * @param  array<int, int>  $versionIds
     * @return Collection<string, Collection<int, TemplateGuide>>
     */
    public function guides(array $versionIds): Collection
    {
        return TemplateGuide::query()
            ->with(['submission.assignment.singer.user', 'creditUser'])
            ->whereIn('template_version_id', $versionIds)
            ->orderByDesc('is_default')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->toBase()
            ->groupBy(static fn (TemplateGuide $guide): string => $guide->template_version_id.':'.$guide->part_key);
    }
}
