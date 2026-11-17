<?php

declare(strict_types=1);

namespace App\Support\Usage;

use App\Actions\Ideas\Ideas;
use App\Exceptions\Refusal;
use App\Models\GroupMember;
use App\Models\GroupSong;
use App\Models\Project;
use App\Models\Take;
use App\Models\Template;
use App\Models\User;
use App\Support\Flags;

/**
 * Private arrangements shared with anyone need Plus for every person in them (Hillel, 2026-11-17: "make sure to access
 * private arrangments all of the users (creator and otehrs) have plus accounts"; docs/pricing-plan.md, top). Behind the
 * `plus_private` switch; off, nothing here refuses anything.
 *
 * A private arrangement is a person's own template that isn't public, isn't listed as an Idea and isn't a song we gave
 * them (Template::isGiven: a choir's learning tracks stay exempt; their own copy of one is theirs, and counts). It is
 * shared once it has a live link (unlisted) or anyone besides its owner sings on a project made from it. A choir group
 * stays outside the rule: its leader's Plus covers its members (Hillel: "Leader's Plus covers"). Alone, it stays free.
 *
 * The rule is per person (Hillel: "They keep singing"): each needs their own Plus, and nobody's Plus covers anyone
 * else's. Without it the song stays listed with every take kept, and nothing is deleted; worked out on read, so it
 * opens again the moment Plus is back.
 */
final class PrivateSongs
{
    /** @var array<int, bool> whether each template is shared, worked out once a request */
    private array $shared = [];

    /** @var array<string, Template|null> each project's template, by slug */
    private array $bySlug = [];

    /** @var array<int, list<int>> each template's choir groups' singers */
    private array $groupSingers = [];

    /** @var array<int, bool> whether each user has Plus, worked out once a request */
    private array $plus = [];

    public function __construct(private readonly Plans $plans, private readonly Flags $flags) {}

    /** 403 plus_required, saying it's this rule (`reason: private_shared`), so the app words the Plus sheet for it. */
    public static function refusal(): Refusal
    {
        return new Refusal(403, 'plus_required', ['reason' => 'private_shared']);
    }

    public function on(): bool
    {
        return $this->flags->on('plus_private');
    }

    /** A person's own template, not public, not a listed Idea and not one we gave them. */
    public function isPrivate(Template $template): bool
    {
        return $template->owner_id !== null && ! $template->isPublic() && ! $template->isGiven() && ! app(Ideas::class)->isListedFor($template);
    }

    /**
     * A live link, or anyone besides its owner on a project made from it. Choir groups stay outside the rule (Hillel:
     * "Leader's Plus covers"): a member of a group the song is shared with never makes it shared.
     */
    public function isShared(Template $template): bool
    {
        if (isset($this->shared[$template->id])) {
            return $this->shared[$template->id];
        }

        $covered = [(int) $template->owner_id, ...$this->groupSingers($template)];

        return $this->shared[$template->id] = $template->visibility === 'unlisted'
            || Project::query()->where('template_slug', $template->slug)->whereNull('binned_at')
                ->where(fn ($query) => $query->whereNotIn('user_id', $covered)->orWhereHas('members', fn ($members) => $members->whereNotIn('user_id', $covered)))
                ->exists();
    }

    /**
     * Everyone in a choir group the song is shared with (its leader's Plus covers them).
     *
     * @return list<int>
     */
    private function groupSingers(Template $template): array
    {
        return $this->groupSingers[$template->id] ??= array_values(array_map('intval', GroupMember::query()
            ->whereIn('group_id', GroupSong::query()->where('template_id', $template->id)->select('group_id'))
            ->pluck('user_id')->all()));
    }

    /**
     * Whether this person needs Plus to open it now: the switch on, a private arrangement, and either someone else's
     * or the owner's own once it's shared, without Plus. A guest (null) always needs it.
     */
    public function needsPlus(?User $user, Template $template): bool
    {
        if (! $this->on() || ! $this->isPrivate($template)) {
            return false;
        }

        if ($user === null) {
            return true;
        }

        if ($template->owner_id === $user->id) {
            if (! $this->isShared($template)) {
                return false;
            }
        } elseif (in_array($user->id, $this->groupSingers($template), true)) {
            // In a choir group it's shared with: its leader's Plus covers them, as today.
            return false;
        }

        return ! ($this->plus[$user->id] ??= $this->plans->isPlus($user));
    }

    /** As needsPlus, for a project: its template's rule, for this person (a project with no template is never one). */
    public function needsPlusFor(?User $user, Project $project): bool
    {
        $template = $this->on() ? $this->templateOf($project) : null;

        return $template !== null && $this->needsPlus($user, $template);
    }

    /** Whether this rule decides who may sing on the project: the switch on and a private arrangement's project. */
    public function governs(Project $project): bool
    {
        $template = $this->on() ? $this->templateOf($project) : null;

        return $template !== null && $this->isPrivate($template);
    }

    /** A person's own template the project is made from, if it is one (once a request). */
    private function templateOf(Project $project): ?Template
    {
        if ($project->template_slug === null) {
            return null;
        }

        if (! array_key_exists($project->template_slug, $this->bySlug)) {
            $this->bySlug[$project->template_slug] = Template::query()->where('slug', $project->template_slug)->whereNotNull('owner_id')->whereNull('deleted_at')->first();
        }

        return $this->bySlug[$project->template_slug];
    }

    /** 403 plus_required when this person needs Plus for it (the app answers with the Plus sheet, never an error). */
    public function ensureMayOpen(?User $user, Template $template): void
    {
        if ($this->needsPlus($user, $template)) {
            throw self::refusal();
        }
    }

    /**
     * Turning on a song's link shares it, so its creator needs Plus (403 plus_required): never for a song we gave them.
     * Going back to private is always allowed.
     */
    public function ensureMayLink(User $user, Template $template, ?string $from, ?string $to): void
    {
        if ($this->on() && $to === 'unlisted' && $from !== 'unlisted' && ! $template->isGiven() && ! ($this->plus[$user->id] ??= $this->plans->isPlus($user))) {
            throw self::refusal();
        }
    }

    /**
     * Whether this person may hear the take: always their own (they keep every take); another singer's only while
     * they may open the song.
     */
    public function mayHear(User $user, Take $take, Project $project): bool
    {
        $theirs = $take->recorded_by === $user->id || ($take->recorded_by === null && $project->user_id === $user->id);

        return $theirs || ! $this->needsPlusFor($user, $project);
    }
}
