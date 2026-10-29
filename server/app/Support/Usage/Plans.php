<?php

declare(strict_types=1);

namespace App\Support\Usage;

use App\Enums\TemplateAccess;
use App\Exceptions\Refusal;
use App\Models\GroupSong;
use App\Models\Harmony;
use App\Models\Project;
use App\Models\Take;
use App\Models\Template;
use App\Models\TemplateRecording;
use App\Models\TemplateUnlock;
use App\Models\User;
use App\Support\Sync\SyncSeq;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Which plan someone is on, what it allows, and what they use (docs/backend-plan.md C6): Plus is
 * a valid subscription (trialing, active, retrying a payment, or cancelled but paid up), or
 * users.plan set by Hillel (a coach's free Plus). Nothing else asks.
 */
final class Plans
{
    /** @var array<int, array<int, 'limit'|'plus_song'|null>> read-only reasons, per user, per project id */
    private array $readOnly = [];

    /** @var array<int, list<int>> */
    private array $groupSongs = [];

    /**
     * @return 'free'|'plus'
     */
    public function plan(User $user): string
    {
        // A self-hosted install has every Plus feature for every account (config/selfhost.php).
        if (config('selfhost.enabled') === true) {
            return 'plus';
        }

        // An approved, active coach has Plus free (docs/coaching-marketplace-plan.md F1).
        // (Read from the row as loaded: a user made in memory may not carry the column yet.)
        $paidUntil = $user->getAttributes()['plus_paid_until'] ?? null;
        $storeUntil = $user->getAttributes()['store_plus_until'] ?? null;
        $now = now()->toDateTimeString();

        // Plus by choice (demo), free for an active coach, paid by invoice until a date, bought in
        // an app (the stores' latest day), or by Stripe.
        return $user->plan === 'plus' || ($user->getAttributes()['coach_active_at'] ?? null) !== null || (is_string($paidUntil) && $paidUntil > $now) || (is_string($storeUntil) && $storeUntil > $now) || $user->subscribed('default') ? 'plus' : 'free';
    }

    public function isPlus(User $user): bool
    {
        return $this->plan($user) === 'plus';
    }

    /**
     * The songs shared with the choir groups the person sings in (or directs) whose director has
     * Plus now: a group's Plus songs are free to its singers only while it's a Plus director's.
     *
     * @return list<int>
     */
    public function groupSongIds(User $user): array
    {
        return $this->groupSongs[$user->id] ??= array_values(GroupSong::query()->with('group.director.subscriptions')
            ->whereHas('group.members', fn ($query) => $query->where('user_id', $user->id))->get(['id', 'group_id', 'template_id'])
            ->filter(fn (GroupSong $song): bool => $song->group?->director !== null && $this->isPlus($song->group->director))
            ->map(fn (GroupSong $song): int => (int) $song->template_id)->unique()->values()->all());
    }

    /**
     * Why each of the person's projects can't change (docs/pricing-plan.md, "Downgrades"), or null
     * when it can: made from a Plus song without Plus (and not kept from the song of the month), or
     * beyond the free limit, past the ones they chose to keep editable (the most recently edited by
     * default). Worked out once per request.
     *
     * @return array<int, 'limit'|'plus_song'|null>
     */
    public function readOnlyMap(User $user): array
    {
        if (isset($this->readOnly[$user->id])) {
            return $this->readOnly[$user->id];
        }

        $projects = Project::query()->where('user_id', $user->id)->get(['id', 'uuid', 'template_slug', 'binned_at', 'updated_at']);
        $map = $projects->mapWithKeys(fn (Project $p): array => [$p->id => null])->all();

        if (! $this->isPlus($user)) {
            $plusSongs = Template::query()->where('access', TemplateAccess::Plus)->whereIn('slug', $projects->pluck('template_slug')->filter()->unique())->get()->keyBy('slug');
            // Kept from the song of the month, or shared with a choir group they sing in.
            $kept = [
                ...TemplateUnlock::query()->where('user_id', $user->id)->pluck('template_id')->all(),
                ...$this->groupSongIds($user),
            ];
            $live = [];

            foreach ($projects as $project) {
                $song = $project->template_slug !== null ? $plusSongs->get($project->template_slug) : null;

                if ($song !== null && ! in_array($song->id, $kept, true) && ! $song->isFreeOn(now())) {
                    $map[$project->id] = 'plus_song';
                } elseif ($project->binned_at === null) {
                    $live[] = $project;
                }
            }

            $limit = $this->projectLimit($user);
            $chosen = array_flip($user->editable_project_ids ?? []);
            // The ones chosen (settled at the downgrade, or picked since), then the newest made:
            // never by when last edited, which a settings-only save would move.
            usort($live, fn (Project $a, Project $b): int => [isset($chosen[$b->uuid]), $b->id] <=> [isset($chosen[$a->uuid]), $a->id]);

            foreach (array_slice($live, (int) $limit) as $project) {
                $map[$project->id] = 'limit';
            }
        }

        return $this->readOnly[$user->id] = $map;
    }

    /**
     * For a resource that has the project but not its owner (one owner lookup per request).
     *
     * @return 'limit'|'plus_song'|null
     */
    public function readOnlyOf(Project $project): ?string
    {
        if (! isset($this->readOnly[$project->user_id])) {
            $owner = User::query()->find($project->user_id);

            if ($owner === null) {
                return null;
            }

            $this->readOnlyMap($owner);
        }

        return $this->readOnly[$project->user_id][$project->id] ?? null;
    }

    /**
     * At a downgrade: which projects stay editable is settled as the most recently edited, unless
     * the person already chose (they can choose others with projects.editable.update).
     */
    public function settleEditable(User $user): void
    {
        if ($user->editable_project_ids !== null) {
            return;
        }

        $limit = $this->projectLimit($user);
        $ids = Project::query()->where('user_id', $user->id)->whereNull('binned_at')->orderByDesc('updated_at')->limit((int) $limit)->pluck('uuid')->all();
        $user->forceFill(['editable_project_ids' => array_values($ids)])->save();
        unset($this->readOnly[$user->id]);
    }

    /**
     * @return 'limit'|'plus_song'|null
     */
    public function readOnly(User $user, Project $project): ?string
    {
        return $this->readOnlyMap($user)[$project->id] ?? null;
    }

    /**
     * Forget what was worked out (the plan or the choice changed), and give every project a new
     * change number so other devices hear which are read-only now; their versions stay.
     */
    public function readOnlyChanged(User $user): void
    {
        unset($this->readOnly[$user->id]);

        DB::transaction(function () use ($user): void {
            $count = Project::query()->where('user_id', $user->id)->count();

            if ($count === 0) {
                return;
            }

            // One distinct change number each, in one statement.
            $first = app(SyncSeq::class)->reserve($user, $count);
            DB::update('update projects join (select id, row_number() over (order by id) - 1 as n from projects where user_id = ?) numbered using (id) set projects.seq = ? + numbered.n', [$user->id, $first]);
        });
    }

    /**
     * Refuse a change to a read-only project: 403 project_read_only, with which and why.
     */
    public function ensureEditable(User $user, Project $project): void
    {
        $reason = $this->readOnly($user, $project);

        if ($reason !== null) {
            throw new Refusal(403, 'project_read_only', ['projectId' => $project->uuid, 'reason' => $reason]);
        }
    }

    /**
     * usage.show's numbers (contract/schemas/usage.show.json).
     *
     * @return array{plan: 'free'|'plus', projects: array{used: int, limit: int|null}, storage: array{usedBytes: int, limitBytes: int, binBytes: int}}
     */
    public function usage(User $user): array
    {
        return [
            'plan' => $this->plan($user),
            'projects' => ['used' => $this->projectsUsed($user), 'limit' => $this->projectLimit($user)],
            'storage' => ['usedBytes' => $this->storageUsed($user), 'limitBytes' => $this->storageLimit($user), 'binBytes' => $this->binBytes($user)],
        ];
    }

    /**
     * Each project's takes they recorded (outside the bin): the current ones' bytes, and the older
     * ones' waiting in the bin. Largest first.
     *
     * @return list<array{projectId: string, bytes: int, binBytes: int}>
     */
    public function bytesByProject(User $user): array
    {
        $size = 'coalesce(takes.stored_bytes, takes.upload_bytes)';

        return array_values(Take::query()
            ->join('projects', 'projects.id', '=', 'takes.project_id')
            ->where('takes.user_id', $user->id)
            ->whereNull('projects.binned_at')
            ->where('takes.status', '!=', Take::FAILED)
            ->groupBy('projects.uuid')
            ->selectRaw("projects.uuid as project_uuid, sum(case when takes.binned_at is null then {$size} else 0 end) as current_bytes, sum(case when takes.binned_at is not null then {$size} else 0 end) as bin_bytes")
            ->orderByRaw('current_bytes + bin_bytes desc')
            ->get()
            ->map(static fn (Take $row): array => ['projectId' => (string) $row->getAttribute('project_uuid'), 'bytes' => (int) $row->getAttribute('current_bytes'), 'binBytes' => (int) $row->getAttribute('bin_bytes')])
            ->all());
    }

    public function projectLimit(User $user): ?int
    {
        $limit = config('usage.plans.'.$this->plan($user).'.projects');

        return is_int($limit) ? $limit : null;
    }

    /**
     * Friends on one of this owner's projects (capacity follows the owner).
     */
    public function singerLimit(User $owner): int
    {
        return (int) config('usage.plans.'.$this->plan($owner).'.singers');
    }

    /**
     * Private or unlisted templates kept on the server; null is any number. Public ones (in the
     * library, N3) are free and never count.
     */
    public function templateLimit(User $user): ?int
    {
        $limit = config('usage.plans.'.$this->plan($user).'.templates');

        return is_int($limit) ? $limit : null;
    }

    /**
     * A template past the free limit after a downgrade: still usable, but no new versions. The most
     * recently changed ones stay editable.
     */
    public function templateReadOnly(User $user, Template $template): bool
    {
        $editable = $this->editableTemplateIds($user);

        return $editable !== null && ! $template->isPublic() && ! in_array($template->id, $editable, true);
    }

    /**
     * The private or unlisted templates still editable after a downgrade (the most recently
     * changed, up to the limit); null when there's no limit.
     *
     * @return list<int>|null
     */
    public function editableTemplateIds(User $user): ?array
    {
        $limit = $this->templateLimit($user);

        return $limit === null ? null : array_values(array_map('intval', $this->countedTemplates($user)->orderByDesc('updated_at')->orderByDesc('id')->limit($limit)->pluck('id')->all()));
    }

    /**
     * The templates the free limit counts: their own, private or unlisted.
     *
     * @return Builder<Template>
     */
    public function countedTemplates(User $user): Builder
    {
        return Template::query()->where('owner_id', $user->id)->whereNull('deleted_at')->where(fn ($q) => $q->where('visibility', '!=', 'public')->orWhereNull('visibility'));
    }

    public function storageLimit(User $user): int
    {
        return (int) config('usage.plans.'.$this->plan($user).'.storage_bytes');
    }

    public function projectsUsed(User $user): int
    {
        return Project::query()->where('user_id', $user->id)->whereNull('binned_at')->count();
    }

    /**
     * Every take outside the bin, in a project outside the bin (a failed one has no file).
     */
    public function storageUsed(User $user): int
    {
        $takes = (int) Take::query()
            ->where('takes.user_id', $user->id)
            ->whereNull('takes.binned_at')
            ->where('takes.status', '!=', Take::FAILED)
            ->whereHas('project', fn ($query) => $query->whereNull('binned_at'))
            ->sum(DB::raw('coalesce(stored_bytes, upload_bytes)'));

        // Shared harmonies' mixes count too (the WAV while it's on its way, then the AAC).
        $harmonies = (int) Harmony::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->whereNull('removed_at')
            ->where('status', '!=', Harmony::FAILED)
            ->sum(DB::raw('coalesce(stored_bytes, upload_bytes)'));

        // The creator's own recorded guides, Plus's.
        $recordings = (int) TemplateRecording::query()->where('user_id', $user->id)->where('status', '!=', TemplateRecording::FAILED)->sum(DB::raw('coalesce(stored_bytes, upload_bytes)'));

        return $takes + $harmonies + $recordings;
    }

    /**
     * What a project's takes outside the bin use (for restoring it).
     */
    public function projectBytes(Project $project): int
    {
        return (int) Take::query()->where('project_id', $project->id)->whereNull('binned_at')->where('status', '!=', Take::FAILED)->sum(DB::raw('coalesce(stored_bytes, upload_bytes)'));
    }

    public function binBytes(User $user): int
    {
        return (int) Take::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', Take::FAILED)
            ->where(fn ($query) => $query->whereNotNull('binned_at')->orWhereHas('project', fn ($project) => $project->whereNotNull('binned_at')))
            ->sum(DB::raw('coalesce(stored_bytes, upload_bytes)'));
    }

    /**
     * One more project allowed? Binned ones don't count.
     */
    public function mayAddProject(User $user): bool
    {
        $limit = $this->projectLimit($user);

        return $limit === null || $this->projectsUsed($user) < $limit;
    }
}
