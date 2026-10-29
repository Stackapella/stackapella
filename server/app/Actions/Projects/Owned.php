<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Exceptions\Refusal;
use App\Http\Resources\Projects\PartResource;
use App\Http\Resources\Projects\ProjectResource;
use App\Models\Part;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Take;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Finding someone's own project, part or take by its UUID, locked for the change about to be made.
 * Anyone else's is "not found" (never "forbidden", so UUIDs can't be probed), and so is a binned
 * project's part or take when a change needs it live.
 */
final class Owned
{
    public function project(User $user, string $uuid, bool $lock = false): Project
    {
        $project = Project::query()->where('uuid', $uuid)->when($lock, fn ($query) => $query->lockForUpdate())->first();

        if ($project === null) {
            throw new Refusal(404, 'not_found');
        }

        // A project's title, tempo, settings and life are its owner's alone.
        self::ownerOnly($user, $project);

        return $project;
    }

    public function part(User $user, string $uuid, bool $lock = false): Part
    {
        $part = Part::query()->where('uuid', $uuid)->whereHas('project', fn ($query) => $query->where('user_id', $user->id))->when($lock, fn ($query) => $query->lockForUpdate())->first();

        if ($part === null) {
            throw new Refusal(404, 'not_found');
        }

        return $part;
    }

    /**
     * A take the person may act on: in their own project, or one they recorded in a project they
     * sing in (its user_id is the project's owner, whose account it counts toward).
     */
    public function take(User $user, string $uuid, bool $lock = false): Take
    {
        $take = Take::query()->where('uuid', $uuid)->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('recorded_by', $user->id))->when($lock, fn ($query) => $query->lockForUpdate())->first();

        if ($take === null) {
            throw new Refusal(404, 'not_found');
        }

        return $take;
    }

    /**
     * Someone's place on a project: its owner, one of its singers, or nobody.
     *
     * @return 'owner'|'singer'|null
     */
    public static function role(User $user, Project $project): ?string
    {
        if ($project->user_id === $user->id) {
            return 'owner';
        }

        return ProjectMember::query()->where('project_id', $project->id)->where('user_id', $user->id)->exists() ? 'singer' : null;
    }

    /**
     * A project the person owns or sings in (contract/api.md, "Collaboration"); anyone else's is
     * not found.
     */
    public function reachable(User $user, string $uuid): Project
    {
        $project = Project::query()->with('user')->where('uuid', $uuid)->where(fn ($query) => $query->where('user_id', $user->id)->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id)))->first();

        if ($project === null) {
            // Someone who sang on it hears that the owner removed them, rather than "not found".
            $removed = DB::table('project_exits')->join('projects', 'projects.id', '=', 'project_exits.project_id')->where('projects.uuid', $uuid)->where('project_exits.user_id', $user->id)->where('project_exits.removed', true)->exists();

            throw $removed ? new Refusal(410, 'removed_from_project') : new Refusal(404, 'not_found');
        }

        return $project;
    }

    /**
     * A take in a project the person owns or sings in (anyone on a project hears every part).
     */
    public function reachableTake(User $user, string $uuid): Take
    {
        $take = Take::query()->where('uuid', $uuid)->whereHas('project', fn ($query) => $query->where('user_id', $user->id)->orWhereHas('members', fn ($members) => $members->where('user_id', $user->id)))->first();

        if ($take === null) {
            throw new Refusal(404, 'not_found');
        }

        return $take;
    }

    /**
     * The owner's alone: a singer on the project is told so, anyone else finds nothing.
     */
    public static function ownerOnly(User $user, ?Project $project): void
    {
        if ($project !== null && $project->user_id !== $user->id) {
            throw self::role($user, $project) === 'singer' ? new Refusal(403, 'owner_only') : new Refusal(404, 'not_found');
        }
    }

    /**
     * A singer changes only the parts they made; the owner changes any.
     */
    public static function mayChange(User $user, Part $part, Project $project): void
    {
        if ($project->user_id !== $user->id && $part->created_by !== $user->id) {
            throw new Refusal(403, 'not_your_part');
        }
    }

    /**
     * Lock the person's own row first, in every change that checks a limit: two devices at once
     * then count one after the other, and the row a new record will have is never gap-locked twice.
     */
    public static function lockAccount(User $user): void
    {
        User::query()->whereKey($user->id)->lockForUpdate()->first(['id']);
    }

    public static function live(Project $project): void
    {
        if ($project->binned_at !== null) {
            throw new Refusal(409, 'binned');
        }
    }

    /**
     * A save based on an old version: the server's copy goes back, to keep or to save over.
     */
    public static function conflict(Project|Part $current): Refusal
    {
        $fields = $current instanceof Project
            ? ProjectResource::fields(ProjectResource::query()->whereKey($current->id)->firstOrFail())
            : PartResource::fields($current->loadMissing('project', 'currentTake'));

        return new Refusal(409, 'version_conflict', ['current' => $fields]);
    }
}
