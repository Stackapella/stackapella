<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Groups;

use App\Actions\Groups\ManageGroups;
use App\Actions\Groups\RequestTracks;
use App\Actions\Groups\TrackRequests;
use App\Actions\Groups\TrackScores;
use App\Http\Controllers\Controller;
use App\Http\Requests\Groups\AssignPartsRequest;
use App\Http\Requests\Groups\GroupNameRequest;
use App\Http\Requests\Groups\TrackScoreRequest;
use App\Http\Requests\Groups\TracksRequest;
use App\Http\Requests\Groups\UpdateGroupRequest;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\TrackScore;
use App\Models\User;
use App\Support\Groups\GroupView;
use App\Support\Sharing\SharedPage;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

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

    public function update(UpdateGroupRequest $request, string $group, #[CurrentUser] User $user): JsonResponse
    {
        $changes = [];

        if ($request->has('name')) {
            $changes['name'] = (string) $request->validated('name');
        }

        if ($request->has('rehearsal')) {
            $changes['rehearsal'] = $request->validated('rehearsal') !== null ? ['date' => (string) $request->validated('rehearsal.date'), 'weekly' => $request->boolean('rehearsal.weekly')] : null;
        }

        return $this->show($this->groups->update($user, $group, $changes), $user);
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

    public function joinShow(Request $request, string $token): JsonResponse
    {
        $group = $this->groups->byLink($token);
        // A choir group's link, opened (an anonymous count by surface: SharedPage).
        SharedPage::opened($request, 'group');

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

    /**
     * groups.tracks.create: the director asks for learning tracks of one piece; it waits in the support inbox.
     */
    public function requestTracks(TracksRequest $request, string $group, RequestTracks $tracks, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{piece: string, composer?: string|null, voicing?: string|null, rights: string, note?: string|null, scoreIds?: list<string>|null} $data */
        $data = $request->safe()->except('rightsConfirmed');
        $message = $tracks->handle($user, $group, $data, (string) $request->ip());

        return response()->json(['data' => ['id' => $message->sqid(), 'receivedAt' => $message->created_at?->utc()->toIso8601ZuluString()]], 201);
    }

    /**
     * groups.tracks.list: the director's learning tracks requests for this group, and where each has got to.
     */
    public function tracks(string $group, TrackRequests $tracks, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $tracks->forGroup($user, $group)]);
    }

    /**
     * groups.tracks.scores.create: start sending one file of the score (a presigned PUT, as takes).
     */
    public function startScore(TrackScoreRequest $request, string $group, TrackScores $scores, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{name: string, contentType: string, bytes: int, sha256: string} $data */
        $data = $request->validated();
        $started = $scores->start($user, $group, $data);

        return response()->json(['data' => self::score($started['score']), 'upload' => [...$started['upload'], 'expiresAt' => Carbon::instance($started['upload']['expiresAt'])->utc()->toIso8601ZuluString()]], 201);
    }

    /**
     * groups.tracks.scores.complete: the file is checked (size, SHA-256, and that it is what it says).
     */
    public function completeScore(string $group, string $score, TrackScores $scores, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => self::score($scores->complete($user, $group, $score))]);
    }

    /**
     * @return array{id: string, name: string, kind: string, bytes: int, status: string}
     */
    private static function score(TrackScore $score): array
    {
        return ['id' => $score->sqid(), 'name' => $score->name, 'kind' => $score->kind, 'bytes' => $score->bytes, 'status' => $score->status];
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
