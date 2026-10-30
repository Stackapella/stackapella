<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Boards;

use App\Actions\Boards\Boards;
use App\Http\Controllers\Controller;
use App\Http\Requests\Boards\DailyBoardRequest;
use App\Http\Requests\Boards\GhostRequest;
use App\Http\Requests\Boards\SubmitScoreRequest;
use App\Models\Ghost;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Part leaderboards (contract/api.md, "Part leaderboards"): a score, a board, the global board's
 * opt-in, and challenge links.
 */
final class BoardsController extends Controller
{
    public function __construct(private readonly Boards $boards) {}

    public function submit(SubmitScoreRequest $request, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{templateId: string, templateVersion: int, partKey: string, scoringVersion: int, score: int, mode: string, keyOffset: int, speed: float, guideMuted: bool, timingChecked: bool, output?: string|null, takeId?: string|null, appVersion?: string|null, pitchDetectorVersion: int, alignmentMs?: int|null, notes?: list<array<string, mixed>>|null} $score */
        $score = [...$request->validated(), 'templateVersion' => (int) $request->validated('templateVersion'), 'scoringVersion' => (int) $request->validated('scoringVersion'), 'score' => (int) $request->validated('score'), 'keyOffset' => (int) $request->validated('keyOffset'), 'speed' => (float) $request->validated('speed'), 'guideMuted' => $request->boolean('guideMuted'), 'timingChecked' => $request->boolean('timingChecked'), 'pitchDetectorVersion' => (int) $request->validated('pitchDetectorVersion')];

        return response()->json(['data' => $this->boards->submit($user, $score)]);
    }

    public function show(Request $request, string $template, string $part, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['scope' => ['required', 'in:global,friends'], 'version' => ['sometimes', 'integer', 'min:1'], 'period' => ['sometimes', 'in:all,month']]);
        $version = $request->query('version');

        return response()->json(['data' => $this->boards->show($user, $template, $part, (string) $request->query('scope'), is_numeric($version) ? (int) $version : null, (string) $request->query('period', 'all'))]);
    }

    public function global(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['on' => ['required', 'boolean']]);
        $this->boards->setGlobal($user, $request->boolean('on'));

        return response()->json(['data' => ['onGlobal' => ($user->getAttributes()['board_global_at'] ?? null) !== null]]);
    }

    public function challenge(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['templateId' => ['required', 'string', 'max:80'], 'partKey' => ['required', 'string', 'max:32']]);
        $made = $this->boards->challenge($user, (string) $request->input('templateId'), (string) $request->input('partKey'));

        return response()->json(['data' => [...$made, 'url' => url('/challenge/'.$made['token'])]], 201);
    }

    public function open(Request $request, string $token): JsonResponse
    {
        $viewer = $request->user();

        return response()->json(['data' => $this->boards->open($viewer instanceof User ? $viewer : null, $token)]);
    }

    /**
     * boards.daily.show: the daily challenge's board. Anyone: someone signed out gets the pick
     * without its entries.
     */
    public function daily(DailyBoardRequest $request): JsonResponse
    {
        $viewer = $request->user();
        $scope = $request->validated('scope');

        return response()->json(['data' => $this->boards->daily($viewer instanceof User ? $viewer : null, $request->day(), is_string($scope) ? $scope : 'global')]);
    }

    /**
     * boards.ghost.show: the pitch line of their own best take on a part.
     */
    public function ghost(string $template, string $part, #[CurrentUser] User $user): JsonResponse
    {
        $ghost = $this->boards->ghost($user, $template, $part);

        // None kept yet: an empty answer, not an error.
        return response()->json(['data' => $ghost !== null ? self::ghostFields($ghost) : null]);
    }

    /**
     * boards.ghost.update: keep it, for their current best only.
     */
    public function keepGhost(GhostRequest $request, string $template, string $part, #[CurrentUser] User $user): JsonResponse
    {
        $ghost = $this->boards->keepGhost($user, $template, $part, [
            'templateVersion' => (int) $request->validated('templateVersion'), 'scoringVersion' => (int) $request->validated('scoringVersion'), 'score' => (int) $request->validated('score'),
            'rate' => (int) $request->validated('rate'), 'startSeconds' => (float) $request->validated('startSeconds'), 'keyOffset' => (int) $request->validated('keyOffset'), 'pitches' => $request->pitches(),
        ]);

        return response()->json(['data' => self::ghostFields($ghost)]);
    }

    /**
     * @return array{templateVersion: int, scoringVersion: int, score: int, rate: int, startSeconds: float, keyOffset: int, pitches: string}
     */
    private static function ghostFields(Ghost $ghost): array
    {
        $version = $ghost->getRelation('templateVersion');

        return [
            'templateVersion' => (int) $version->version,
            'scoringVersion' => $ghost->scoring_version,
            'score' => $ghost->score,
            'rate' => $ghost->rate,
            'startSeconds' => (float) $ghost->start_seconds,
            'keyOffset' => $ghost->key_offset,
            'pitches' => base64_encode($ghost->pitches),
        ];
    }

    public function accept(string $token, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->boards->accept($user, $token)]);
    }
}
