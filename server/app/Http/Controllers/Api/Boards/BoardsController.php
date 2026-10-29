<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Boards;

use App\Actions\Boards\Boards;
use App\Http\Controllers\Controller;
use App\Http\Requests\Boards\SubmitScoreRequest;
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
        $request->validate(['scope' => ['required', 'in:global,friends'], 'version' => ['sometimes', 'integer', 'min:1']]);
        $version = $request->query('version');

        return response()->json(['data' => $this->boards->show($user, $template, $part, (string) $request->query('scope'), is_numeric($version) ? (int) $version : null)]);
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

    public function accept(string $token, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => $this->boards->accept($user, $token)]);
    }
}
