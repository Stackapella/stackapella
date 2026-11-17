<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Collaboration;

use App\Actions\Collaboration\ManageInvites;
use App\Actions\Collaboration\ManageMembers;
use App\Actions\Projects\Owned;
use App\Http\Controllers\Controller;
use App\Http\Requests\Collaboration\CreateInviteRequest;
use App\Http\Resources\Projects\ProjectBundle;
use App\Models\Project;
use App\Models\ProjectInvite;
use App\Models\ProjectMember;
use App\Models\Template;
use App\Models\User;
use App\Support\Sharing\SharedPage;
use App\Support\Usage\Plans;
use App\Support\Usage\PrivateSongs;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Friends on a project (contract/api.md, "Collaboration"): projects.shared, projects.members.*
 * and invites.*.
 */
final class CollaborationController extends Controller
{
    public function shared(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['cursor' => ['nullable', 'string', 'max:512']]);

        $page = Project::query()
            ->with('user')
            ->whereHas('members', fn ($query) => $query->where('user_id', $user->id))
            ->whereNull('binned_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->cursorPaginate(25);
        $bundles = ProjectBundle::of(collect($page->items()));

        return response()->json([
            'data' => collect($page->items())->map(fn (Project $project): array => [...$bundles[$project->id], 'owner' => ['id' => $project->user->sqid(), 'name' => $project->user->name], 'role' => ProjectMember::SINGER, 'lastActivityAt' => $project->updated_at?->utc()->toIso8601ZuluString()])->values()->all(),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function members(string $project, Owned $owned, #[CurrentUser] User $user): JsonResponse
    {
        $found = $owned->reachable($user, $project);
        $members = ProjectMember::query()->with('user')->where('project_id', $found->id)->orderBy('id')->get();

        return response()->json(['data' => [
            ['id' => $found->user->sqid(), 'name' => $found->user->name, 'role' => 'owner', 'joinedAt' => null],
            ...$members->map(fn (ProjectMember $member): array => ['id' => $member->user->sqid(), 'name' => $member->user->name, 'role' => $member->role, 'joinedAt' => $member->created_at?->utc()->toIso8601ZuluString()])->all(),
        ]]);
    }

    public function removeMember(Request $request, string $project, string $member, ManageMembers $members, #[CurrentUser] User $user): Response
    {
        $request->validate(['keepParts' => ['nullable', 'boolean']]);
        // Kept unless asked otherwise: leaving never takes someone's singing by accident.
        $members->remove($user, $project, $member, $request->boolean('keepParts', true));

        return response()->noContent();
    }

    public function createInvite(CreateInviteRequest $request, string $project, ManageInvites $invites, #[CurrentUser] User $user): JsonResponse
    {
        $made = $invites->create($user, $project, $request->validated('partKey'), $request->validated('partName'));

        return response()->json(['data' => [...self::invite($made['invite']), 'url' => $made['url']]], 201);
    }

    public function invites(string $project, Owned $owned, #[CurrentUser] User $user): JsonResponse
    {
        $found = $owned->project($user, $project);
        $open = ProjectInvite::query()->where('project_id', $found->id)->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())->orderBy('id')->get();

        return response()->json(['data' => $open->map(fn (ProjectInvite $invite): array => self::invite($invite))->all()]);
    }

    public function revokeInvite(string $invite, ManageInvites $invites, #[CurrentUser] User $user): Response
    {
        $invites->revoke($user, $invite);

        return response()->noContent();
    }

    public function showInvite(Request $request, string $token, ManageInvites $invites, PrivateSongs $private, Plans $plans): JsonResponse
    {
        $invite = $invites->open($token);
        // An invite to sing a part, opened (an anonymous count by surface: SharedPage).
        SharedPage::opened($request, 'invite');
        $project = $invite->project;
        $song = $project->template_slug !== null ? Template::query()->where('slug', $project->template_slug)->first(['slug', 'title']) : null;

        return response()->json(['data' => [
            'projectTitle' => $project->title,
            'owner' => ['name' => $project->user->name],
            'partKey' => $invite->part_key,
            'partName' => $invite->part_name,
            'song' => $song !== null ? ['id' => $song->slug, 'title' => $song->title] : null,
            'expiresAt' => $invite->expires_at->utc()->toIso8601ZuluString(),
            // A shared private arrangement needs the joiner's own Plus (plus_private): the app shows the Plus sheet
            // before Join. True for a guest too (they make an account first).
            'plusNeeded' => $private->governs($project) && ! ($request->user() instanceof User && $plans->isPlus($request->user())),
        ]]);
    }

    public function acceptInvite(string $token, ManageInvites $invites, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => ProjectBundle::one($invites->accept($user, $token))]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function invite(ProjectInvite $invite): array
    {
        return [
            'id' => $invite->sqid(),
            'partKey' => $invite->part_key,
            'partName' => $invite->part_name,
            'expiresAt' => $invite->expires_at->utc()->toIso8601ZuluString(),
            'createdAt' => $invite->created_at?->utc()->toIso8601ZuluString(),
        ];
    }
}
