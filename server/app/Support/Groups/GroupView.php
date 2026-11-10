<?php

declare(strict_types=1);

namespace App\Support\Groups;

use App\Actions\Groups\ManageGroups;
use App\Models\Group;
use App\Models\GroupAssignment;
use App\Models\GroupMember;
use App\Models\GroupSong;
use App\Models\Part;
use App\Models\Project;
use App\Models\Take;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * A choir group as its director or a member sees it (contract/schemas/groups.show.json). The
 * director sees every member's assignments and how far they've got (`started`: a project from
 * the song; `recorded`: that project's part for the assigned key has a ready take), never their
 * recordings. A member sees only themselves.
 */
final class GroupView
{
    public function __construct(private readonly ManageGroups $groups) {}

    /**
     * @return array<string, mixed>
     */
    public function of(Group $group, User $viewer): array
    {
        $directing = $group->director_id === $viewer->id;
        $songs = GroupSong::query()->with('template')->where('group_id', $group->id)->orderBy('id')->get();
        $members = GroupMember::query()->with('user')->where('group_id', $group->id)->orderByRaw("role = 'director' desc")->orderBy('id')->get();
        $shown = $directing ? $members : $members->where('user_id', $viewer->id)->values();
        $assignments = GroupAssignment::query()->whereIn('group_song_id', $songs->pluck('id'))->whereIn('user_id', $shown->pluck('user_id'))->get();
        $progress = $this->progress($assignments, $songs);
        $rehearsal = RehearsalDates::of($group->rehearsal_on, $group->rehearsal_weekly, now());
        // The director's count since the last rehearsal (from the start of that day, UTC): numbers only.
        $since = $directing && $rehearsal !== null && $rehearsal['previous'] !== null ? $rehearsal['previous'].'T00:00:00Z' : null;

        return [
            'id' => $group->sqid(),
            'name' => $group->name,
            'role' => $directing ? GroupMember::DIRECTOR : GroupMember::MEMBER,
            'director' => ['id' => $group->director->sqid(), 'name' => $group->director->name],
            'readOnly' => $this->groups->readOnly($group),
            'memberCount' => $members->count(),
            'songs' => $songs->map(fn (GroupSong $song): array => [
                'templateId' => $song->template->slug,
                'title' => $song->template->title,
                'access' => $song->template->owner_id !== null ? 'own' : $song->template->access->api(),
                'own' => $song->template->owner_id !== null,
                'sharedAt' => $song->created_at?->utc()->toIso8601ZuluString(),
                'sinceRehearsal' => $since !== null ? self::since($assignments->where('group_song_id', $song->id), $progress, $since) : null,
            ])->all(),
            'members' => $shown->map(fn (GroupMember $member): array => [
                'id' => $member->user->sqid(),
                'name' => $member->user->name,
                'avatarUrl' => $member->user->avatar_key !== null ? Storage::disk('media')->url($member->user->avatar_key) : null,
                'role' => $member->role,
                'joinedAt' => $member->created_at?->utc()->toIso8601ZuluString(),
                'assignments' => $assignments->where('user_id', $member->user_id)->map(fn (GroupAssignment $a): array => [
                    'templateId' => $songs->firstWhere('id', $a->group_song_id)?->template->slug,
                    'partKey' => $a->part_key,
                    'status' => $progress[$a->id]['status'] ?? 'none',
                    'updatedAt' => $progress[$a->id]['updatedAt'] ?? null,
                ])->values()->all(),
            ])->all(),
            // The director's: the link to copy (null for one made before links were kept: reset it).
            'link' => $directing && $group->link_created_at !== null ? ['url' => $group->linkUrl(), 'createdAt' => $group->link_created_at->utc()->toIso8601ZuluString()] : null,
            'rehearsal' => $rehearsal,
        ];
    }

    /**
     * How many of a song's assigned members began it, and how many recorded their part, since the
     * last rehearsal's day began.
     *
     * @param  Collection<int, GroupAssignment>  $assignments
     * @param  array<int, array{status: string, updatedAt: string|null, startedAt: string|null}>  $progress
     * @return array{started: int, recorded: int}
     */
    private static function since(Collection $assignments, array $progress, string $since): array
    {
        $shown = $assignments->map(fn (GroupAssignment $a): array => $progress[$a->id] ?? ['status' => 'none', 'updatedAt' => null, 'startedAt' => null]);

        return [
            'started' => $shown->filter(fn (array $p): bool => $p['startedAt'] !== null && $p['startedAt'] >= $since)->count(),
            'recorded' => $shown->filter(fn (array $p): bool => $p['status'] === 'recorded' && $p['updatedAt'] !== null && $p['updatedAt'] >= $since)->count(),
        ];
    }

    /**
     * Each assignment's status, from the members' own projects begun since it was assigned: in a few
     * queries for everyone.
     *
     * @param  Collection<int, GroupAssignment>  $assignments
     * @param  Collection<int, GroupSong>  $songs
     * @return array<int, array{status: string, updatedAt: string|null, startedAt: string|null}>
     */
    public function progress(Collection $assignments, Collection $songs): array
    {
        if ($assignments->isEmpty()) {
            return [];
        }

        $slugs = $songs->mapWithKeys(fn (GroupSong $song): array => [$song->id => $song->template->slug]);
        $projects = Project::query()->whereIn('user_id', $assignments->pluck('user_id')->unique())->whereIn('template_slug', $slugs->values())->whereNull('binned_at')->get(['id', 'user_id', 'template_slug', 'created_at', 'updated_at']);
        $parts = Part::query()->whereIn('project_id', $projects->pluck('id'))->whereNotNull('current_take_id')->get(['id', 'project_id', 'template_part_key', 'current_take_id']);
        $takes = Take::query()->whereIn('id', $parts->pluck('current_take_id'))->where('status', Take::READY)->whereNull('binned_at')->get(['id', 'ready_at'])->keyBy('id');
        $result = [];

        foreach ($assignments as $assignment) {
            // Only what they started for it (from the assignment on): practice from before stays
            // their own (security review, 2026-11-01).
            $theirs = $projects->where('user_id', $assignment->user_id)->where('template_slug', $slugs[$assignment->group_song_id] ?? null)
                ->filter(fn (Project $project): bool => $assignment->created_at === null || $project->created_at === null || $project->created_at->gte($assignment->created_at));
            $recorded = $parts->whereIn('project_id', $theirs->pluck('id'))->where('template_part_key', $assignment->part_key)
                ->map(fn (Part $part): ?Take => $takes->get($part->current_take_id))->filter()->sortByDesc('ready_at')->first();

            $startedAt = $theirs->min('created_at')?->utc()->toIso8601ZuluString();
            $result[$assignment->id] = match (true) {
                $recorded !== null => ['status' => 'recorded', 'updatedAt' => $recorded->ready_at?->utc()->toIso8601ZuluString(), 'startedAt' => $startedAt],
                $theirs->isNotEmpty() => ['status' => 'started', 'updatedAt' => $theirs->max('updated_at')?->utc()->toIso8601ZuluString(), 'startedAt' => $startedAt],
                default => ['status' => 'none', 'updatedAt' => null, 'startedAt' => null],
            };
        }

        return $result;
    }
}
