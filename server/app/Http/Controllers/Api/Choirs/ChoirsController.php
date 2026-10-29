<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Choirs;

use App\Actions\Choirs\Choirs;
use App\Http\Controllers\Controller;
use App\Http\Requests\Choirs\JoinChoirRequest;
use App\Http\Requests\Choirs\RemoveChoirVoiceRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * choirs.* and admin.choirs.voices.remove (N6): each of our songs' virtual choir.
 */
final class ChoirsController extends Controller
{
    public function __construct(private readonly Choirs $choirs) {}

    public function show(Request $request, string $song): JsonResponse
    {
        return response()->json(['data' => $this->choirs->show($song, $request->user() instanceof User ? $request->user() : null)]);
    }

    public function voices(Request $request, string $song): JsonResponse
    {
        $request->validate(['part' => ['nullable', 'string', 'max:40'], 'cursor' => ['nullable', 'string', 'max:500']]);
        $part = $request->query('part');

        return response()->json($this->choirs->voices($song, is_string($part) ? $part : null));
    }

    public function join(JoinChoirRequest $request, string $song, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{takeId: string, partKey: string, score: int, credit: string} $data */
        $data = $request->safe()->only(['takeId', 'partKey', 'score', 'credit']);
        $this->choirs->join($user, $song, $data, $request->ip());

        return response()->json(['data' => $this->choirs->show($song, $user)], 201);
    }

    public function leave(string $song, #[CurrentUser] User $user): Response
    {
        $this->choirs->leave($user, $song);

        return response()->noContent();
    }

    public function held(): JsonResponse
    {
        return response()->json(['data' => $this->choirs->held()]);
    }

    public function approve(Request $request, string $voice, #[CurrentUser] User $admin): JsonResponse
    {
        $this->choirs->approve($voice, $admin, $request->ip());

        return $this->held();
    }

    public function remove(RemoveChoirVoiceRequest $request, string $voice, #[CurrentUser] User $admin): Response
    {
        $this->choirs->takeOut($voice, $admin, (string) $request->validated('note'), $request->ip());

        return response()->noContent();
    }
}
