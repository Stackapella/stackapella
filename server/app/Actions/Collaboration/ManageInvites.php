<?php

declare(strict_types=1);

namespace App\Actions\Collaboration;

use App\Actions\Projects\Owned;
use App\Exceptions\Refusal;
use App\Models\Project;
use App\Models\ProjectInvite;
use App\Models\ProjectMember;
use App\Models\User;
use App\Support\Templates\Imported;
use App\Support\Usage\Plans;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inviting friends to sing on a project (docs/backend-plan.md C7): a link the owner sends
 * themselves (`/join/<token>`, only its hash kept), for one person, valid 14 days, revocable.
 * How many may sing on it follows the owner's plan.
 */
final class ManageInvites
{
    public function __construct(private readonly Owned $owned, private readonly Plans $plans) {}

    /**
     * @return array{invite: ProjectInvite, url: string}
     */
    public function create(User $user, string $projectUuid, ?string $partKey, ?string $partName): array
    {
        return DB::transaction(function () use ($user, $projectUuid, $partKey, $partName): array {
            Owned::lockAccount($user);
            $project = $this->owned->project($user, $projectUuid, lock: true);
            Owned::live($project);

            // A song made from imported sheet music stays with its singer (and their choir groups).
            if (Imported::project($project)) {
                Imported::refuse();
            }

            if ($this->taken($project) >= $this->plans->singerLimit($user)) {
                throw new Refusal(403, 'invite_limit');
            }

            $token = Str::random(32);
            $invite = new ProjectInvite(['part_key' => $partKey, 'part_name' => $partName, 'expires_at' => now()->addDays((int) config('usage.invite_days'))]);
            $invite->forceFill(['project_id' => $project->id, 'created_by' => $user->id, 'token_hash' => hash('sha256', $token)])->save();

            return ['invite' => $invite, 'url' => rtrim((string) config('app.url'), '/').'/join/'.$token];
        });
    }

    public function revoke(User $user, string $sqid): void
    {
        $invite = ProjectInvite::query()->with('project')->whereSqid($sqid)->first();

        if ($invite === null || $invite->project->user_id !== $user->id) {
            return;
        }

        if ($invite->used_at === null && $invite->revoked_at === null) {
            $invite->forceFill(['revoked_at' => now()])->save();
        }
    }

    /**
     * The invite behind a link, if it can still be used.
     */
    public function open(string $token): ProjectInvite
    {
        $invite = $this->find($token);
        $this->usable($invite);

        return $invite;
    }

    /**
     * Join the project. Your own project, or one you already sing in, answers as it is.
     */
    public function accept(User $user, string $token): Project
    {
        return DB::transaction(function () use ($user, $token): Project {
            $found = $this->find($token);
            $owner = $found->project->user;
            Owned::lockAccount($owner);
            $invite = ProjectInvite::query()->with('project')->whereKey($found->id)->lockForUpdate()->firstOrFail();
            $project = $invite->project;

            if (Owned::role($user, $project) !== null) {
                return $project;
            }

            $this->usable($invite);
            Owned::live($project);

            if (ProjectMember::query()->where('project_id', $project->id)->count() >= $this->plans->singerLimit($owner)) {
                throw new Refusal(403, 'invite_limit');
            }

            $member = new ProjectMember(['role' => ProjectMember::SINGER]);
            $member->forceFill(['project_id' => $project->id, 'user_id' => $user->id])->save();
            DB::table('project_exits')->where('project_id', $project->id)->where('user_id', $user->id)->delete();
            $invite->forceFill(['used_at' => now(), 'used_by' => $user->id])->save();

            return $project;
        });
    }

    /**
     * Singers on it, and invites still waiting: together they can't pass the limit.
     */
    private function taken(Project $project): int
    {
        $waiting = ProjectInvite::query()->where('project_id', $project->id)->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())->count();

        return ProjectMember::query()->where('project_id', $project->id)->count() + $waiting;
    }

    private function find(string $token): ProjectInvite
    {
        return ProjectInvite::query()->with('project.user')->where('token_hash', hash('sha256', $token))->first() ?? throw new Refusal(404, 'not_found');
    }

    private function usable(ProjectInvite $invite): void
    {
        match (true) {
            $invite->revoked_at !== null || $invite->project->binned_at !== null => throw new Refusal(410, 'invite_revoked'),
            $invite->used_at !== null => throw new Refusal(410, 'invite_used'),
            $invite->expires_at->isPast() => throw new Refusal(410, 'invite_expired'),
            default => null,
        };
    }
}
