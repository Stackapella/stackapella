<?php

declare(strict_types=1);

namespace App\Support\Templates;

use App\Enums\TemplateAccess;
use App\Models\GroupSong;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Models\User;
use App\Support\Pages\SongParts;
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

    /**
     * The songs the public site may show (the songbook, each song's page and its part pages, the
     * sitemap, the song counts): live, its current version reviewed, its words and tune public
     * domain. A song waiting for its Monday is reviewed false, so it stays off the site until it opens.
     *
     * @return Collection<int, Template>
     */
    public function open(): Collection
    {
        // Read once a request (it's scoped): the songbook, the sitemap and the part pages all ask.
        return $this->open ??= $this->songs()->filter(self::isOpen(...))->values();
    }

    /** @var Collection<int, Template>|null */
    private ?Collection $open = null;

    /** Whether the public site may show this song (self::open()). */
    public static function isOpen(Template $template): bool
    {
        return $template->owner_id === null && $template->withdrawn_at === null && $template->current_version_id !== null
            && $template->currentVersion?->reviewed === true && SongParts::publicDomain((array) $template->currentVersion->data);
    }

    /** @var array<int, list<int>> the songs shared with each person's choir groups */
    private array $grouped = [];

    /**
     * The library's size as the site says it: the songs it may show (self::open()), every one free
     * with an account (docs/pricing-plan.md, top).
     */
    public function songCount(): int
    {
        return $this->open()->count();
    }

    public function mayStart(Template $template, ?User $user): bool
    {
        return $this->grant($template, $user) !== null;
    }

    /**
     * Why someone may have a library song's notes and voices (contract/api.md, "Plus and billing"):
     * one of the free songs the app bundles (anyone, signed out too), Hillel and the editor (never
     * counted toward the daily cap), or any signed-in account (since 2026-11-07 every library song
     * is free with an account); null signed out.
     *
     * @return 'free'|'admin'|'account'|null
     */
    public function grant(Template $template, ?User $user): ?string
    {
        return match (true) {
            $template->access === TemplateAccess::Free => 'free',
            $user === null => null,
            $user->is_admin || $user->is_editor => 'admin',
            default => 'account',
        };
    }

    /**
     * The song is shared with a choir group the person is in (as its director or a member).
     */
    public function inGroup(Template $template, User $user): bool
    {
        // Not one taken down (hidden): that's gone from its groups too.
        $this->grouped[$user->id] ??= array_values(GroupSong::query()->whereHas('group.members', fn ($query) => $query->where('user_id', $user->id))->whereHas('template', fn ($query) => $query->whereNull('hidden_at'))->pluck('template_id')->map(fn (mixed $id): int => (int) $id)->all());

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
