<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Requests;

use App\Actions\Requests\SongRequests;
use App\Http\Controllers\Controller;
use App\Http\Requests\Requests\CreateSongRequest;
use App\Http\Requests\Requests\RequestsListRequest;
use App\Http\Requests\Requests\UpdateSongRequest;
use App\Models\Report;
use App\Models\SongRequest;
use App\Models\SongRequestVote;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * requests.* and admin.requests.* (N7): what people want to sing next.
 */
final class RequestsController extends Controller
{
    public function __construct(private readonly SongRequests $requests) {}

    public function index(RequestsListRequest $request): JsonResponse
    {
        $viewer = $request->user() instanceof User ? $request->user() : null;
        $q = $request->validated('q');
        $status = $request->validated('status');
        $page = $this->requests->list(is_string($q) ? $q : null, is_string($status) ? $status : null, (string) ($request->validated('sort') ?? 'wanted'));
        $voted = $viewer !== null ? SongRequestVote::query()->where('user_id', $viewer->id)->whereIn('song_request_id', array_map(static fn (SongRequest $r): int => $r->id, $page->items()))->pluck('song_request_id')->all() : [];

        return response()->json([
            'data' => array_values(array_map(static fn (SongRequest $r): array => SongRequests::view($r, $viewer, in_array($r->id, $voted, true)), $page->items())),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
        ]);
    }

    public function store(CreateSongRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{title: string, writer?: string|null, why?: string|null} $data */
        $data = $request->validated();
        $made = $this->requests->create($user, $data, $request->ip());

        return response()->json(['data' => SongRequests::view($made['request']->load('template'), $user, true)], $made['created'] ? 201 : 200);
    }

    public function vote(string $id, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => SongRequests::view($this->requests->vote($user, $id, true), $user, true)]);
    }

    public function unvote(string $id, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => SongRequests::view($this->requests->vote($user, $id, false), $user, false)]);
    }

    public function adminIndex(Request $request): JsonResponse
    {
        $request->validate(['cursor' => ['nullable', 'string', 'max:500']]);
        $page = SongRequest::query()->with(['template', 'mergedInto'])->orderByRaw('held_at is null')->orderByDesc('votes_count')->orderByDesc('id')->cursorPaginate(100);
        $ids = array_map(static fn (SongRequest $r): int => $r->id, $page->items());
        $reports = Report::query()->where('reportable_type', (new SongRequest)->getMorphClass())->whereIn('reportable_id', $ids)->where('status', Report::OPEN)
            ->selectRaw('reportable_id, count(*) as n')->groupBy('reportable_id')->pluck('n', 'reportable_id');

        return response()->json(['data' => array_values(array_map(fn (SongRequest $r): array => self::adminView($r, (int) ($reports[$r->id] ?? 0)), $page->items())), 'meta' => ['nextCursor' => $page->nextCursor()?->encode()]]);
    }

    public function update(UpdateSongRequest $request, string $id, #[CurrentUser] User $admin): JsonResponse
    {
        /** @var array{status?: string|null, templateId?: string|null, mergeInto?: string|null, hidden?: bool|null, approve?: bool|null} $changes */
        $changes = $request->validated();

        $updated = $this->requests->update($admin, $id, $changes, $request->ip())->load('mergedInto');

        return response()->json(['data' => self::adminView($updated, Report::query()->whereMorphedTo('reportable', $updated)->where('status', Report::OPEN)->count())]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function adminView(SongRequest $request, int $reports): array
    {
        return [
            ...SongRequests::view($request, null, false),
            // Hillel sees where it stands even when it isn't ready yet.
            'template' => $request->template !== null ? ['id' => $request->template->slug, 'title' => $request->template->title] : null,
            'held' => $request->held_at !== null,
            'hidden' => $request->hidden_at !== null,
            'mergedInto' => $request->mergedInto?->sqid(),
            'reports' => $reports,
        ];
    }
}
