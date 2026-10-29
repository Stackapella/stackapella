<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Groups;

use App\Actions\Groups\ManageGroups;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groups\AssignPartsRequest;
use App\Http\Requests\Groups\GroupNameRequest;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Support\Groups\GroupView;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * groups.*: choir groups (contract/api.md, "Choir groups").
 */
final class GroupsController extends Controller
{
    public function __construct(private readonly ManageGroups $groups, private readonly GroupView $view) {}

    public function index(#[CurrentUser] User $user): JsonResponse
    {
        $groups = Group::query()->with('director.subscriptions')->withCount(['members', 'songs'])->whereHas('members', fn ($query) => $query->where('user_id', $user->id))->orderBy('name')->get();

        return response()->json(['data' => $groups->map(fn (Group $group): array => [
            'id' => $group->sqid(),
            'name' => $group->name,
            'role' => $group->director_id === $user->id ? GroupMember::DIRECTOR : GroupMember::MEMBER,
            'director' => ['id' => $group->director->sqid(), 'name' => $group->director->name],
            'members' => (int) $group->getAttribute('members_count'),
            'songs' => (int) $group->getAttribute('songs_count'),
            'readOnly' => $this->groups->readOnly($group),
        ])->all()]);
    }

    public function store(GroupNameRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        return $this->show($this->groups->create($user, (string) $request->validated('name'), $request->boolean('adult')), $user, 201);
    }

    public function view(string $group, #[CurrentUser] User $user): JsonResponse
    {
        return $this->show($this->groups->find($user, $group), $user);
    }

    public function update(GroupNameRequest $request, string $group, #[CurrentUser] User $user): JsonResponse
    {
        return $this->show($this->groups->rename($user, $group, (string) $request->validated('name')), $user);
    }

    public function destroy(string $group, #[CurrentUser] User $user): Response
    {
        $this->groups->delete($user, $group);

        return response()->noContent();
    }

    public function link(string $group, #[CurrentUser] User $user): JsonResponse
    {
        $url = $this->groups->newLink($user, $group);

        return response()->json(['data' => ['url' => $url, 'createdAt' => now()->utc()->toIso8601ZuluString()]], 201);
    }

    public function joinShow(string $token): JsonResponse
    {
        $group = $this->groups->byLink($token);

        return response()->json(['data' => [
            'name' => $group->name,
            'director' => ['name' => $group->director->name],
            'members' => GroupMember::query()->where('group_id', $group->id)->count(),
        ]]);
    }

    public function join(Request $request, string $token, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['adult' => ['nullable', 'boolean']]);

        return $this->show($this->groups->join($user, $token, $request->boolean('adult')), $user);
    }

    public function removeMember(string $group, string $member, #[CurrentUser] User $user): Response
    {
        $this->groups->removeMember($user, $group, $member);

        return response()->noContent();
    }

    public function addSong(Request $request, string $group, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['templateId' => ['required', 'string', 'max:80']]);

        return $this->show($this->groups->addSong($user, $group, (string) $request->input('templateId')), $user);
    }

    public function removeSong(string $group, string $template, #[CurrentUser] User $user): Response
    {
        $this->groups->removeSong($user, $group, $template);

        return response()->noContent();
    }

    public function assign(AssignPartsRequest $request, string $group, string $template, #[CurrentUser] User $user): JsonResponse
    {
        /** @var list<array{memberId: string, partKey: string}> $assignments */
        $assignments = $request->validated('assignments');

        return $this->show($this->groups->assign($user, $group, $template, $assignments), $user);
    }

    private function show(Group $group, User $user, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $this->view->of($group->loadMissing('director'), $user)], $status);
    }
}
