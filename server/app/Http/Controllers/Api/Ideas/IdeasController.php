<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Ideas;

use App\Actions\Ideas\Ideas;
use App\Actions\Ideas\IdeaVoices;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ideas\CreateIdeaRequest;
use App\Http\Requests\Ideas\IdeasListRequest;
use App\Http\Requests\Ideas\StartVoiceRequest;
use App\Models\Idea;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * ideas.* and ideas.voices.*: tunes shared with every part open, and the voices singers add.
 */
final class IdeasController extends Controller
{
    public function __construct(private readonly Ideas $ideas) {}

    public function index(IdeasListRequest $request): JsonResponse
    {
        $viewer = self::viewer($request);
        $needs = $request->validated('needs');

        return $this->page($this->ideas->shelf($viewer, (string) ($request->validated('tab') ?? 'open'), is_string($needs) ? $needs : null), $viewer);
    }

    public function mine(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['cursor' => ['nullable', 'string', 'max:500']]);

        return $this->page($this->ideas->mine($user), $user);
    }

    public function show(Request $request, string $idea): JsonResponse
    {
        $viewer = self::viewer($request);

        return response()->json(['data' => $this->ideas->show($this->ideas->find($idea, $viewer), $viewer)]);
    }

    public function store(CreateIdeaRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{templateId: string, note?: string|null, voicing?: int|null} $data */
        $data = $request->safe()->only(['templateId', 'note', 'voicing']);
        $made = $this->ideas->create($user, $data, $request->ip());

        return response()->json(['data' => $this->ideas->show($made['idea']->load(Ideas::WITH), $user)], $made['created'] ? 201 : 200);
    }

    public function destroy(Request $request, string $idea, #[CurrentUser] User $user): Response
    {
        $this->ideas->delete($user, $idea, $request->ip());

        return response()->noContent();
    }

    public function startVoice(StartVoiceRequest $request, string $idea, IdeaVoices $voices, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{id: string, partKey: string, sampleRate: int, frames: int, offsetFrames: int, bytes: int, sha256: string} $data */
        $data = $request->safe()->except('rightsConfirmed');
        $started = $voices->start($user, $idea, $data);
        $upload = $started['upload'];

        return response()->json([
            'data' => Ideas::voice($started['voice']->load('user'), $user),
            'upload' => $upload !== null ? [...$upload, 'expiresAt' => Carbon::instance($upload['expiresAt'])->utc()->toIso8601ZuluString()] : null,
        ], 201);
    }

    public function completeVoice(string $voice, IdeaVoices $voices, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => Ideas::voice($voices->complete($user, $voice)->load('user'), $user)]);
    }

    public function destroyVoice(string $voice, IdeaVoices $voices, #[CurrentUser] User $user): Response
    {
        $voices->delete($user, $voice);

        return response()->noContent();
    }

    /**
     * @param  CursorPaginator<int, Idea>  $page
     */
    private function page(CursorPaginator $page, ?User $viewer): JsonResponse
    {
        $counts = Ideas::readyByPart(array_values(array_map(static fn (Idea $idea): int => $idea->id, $page->items())));

        return response()->json([
            'data' => array_values(array_map(fn (Idea $idea): array => $this->ideas->view($idea, $viewer, $counts[$idea->id] ?? collect()), $page->items())),
            'meta' => ['nextCursor' => $page->nextCursor()?->encode()],
        ]);
    }

    private static function viewer(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
