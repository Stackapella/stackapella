<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Projects;

use App\Actions\Projects\CompleteTake;
use App\Actions\Projects\Owned;
use App\Actions\Projects\PartTakes;
use App\Actions\Projects\RestoreTake;
use App\Actions\Projects\StartTake;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\RestoreTakeRequest;
use App\Http\Requests\Projects\StartTakeRequest;
use App\Http\Resources\Projects\PartResource;
use App\Http\Resources\Projects\TakeResource;
use App\Models\Take;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * takes.create, .complete, .show and .restore.
 */
final class TakesController extends Controller
{
    public function store(StartTakeRequest $request, StartTake $start, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{id: string, partId: string, sampleRate: int, frames: int, offsetFrames: int, bytes: int, sha256: string, scores?: array<string, mixed>|null} $data */
        $data = $request->validated();
        $started = $start->handle($user, $data);
        $upload = $started['upload'];

        return response()->json([
            'data' => TakeResource::fields($started['take']),
            'upload' => $upload !== null ? [...$upload, 'expiresAt' => Carbon::instance($upload['expiresAt'])->utc()->toIso8601ZuluString()] : null,
        ], 201);
    }

    public function complete(string $take, CompleteTake $complete, #[CurrentUser] User $user): JsonResponse
    {
        return $this->take($complete->handle($user, $take));
    }

    public function show(string $take, Owned $owned, #[CurrentUser] User $user): JsonResponse
    {
        return $this->take($owned->reachableTake($user, $take));
    }

    public function restore(RestoreTakeRequest $request, string $take, RestoreTake $restore, #[CurrentUser] User $user): JsonResponse
    {
        $nudge = $request->validated('nudgeFrames');

        return response()->json(['data' => PartResource::fields($restore->handle($user, $take, is_numeric($nudge) ? (int) $nudge : 0))]);
    }

    /**
     * parts.takes.list: every kept take of a part, newest first (PartTakes).
     */
    public function forPart(string $part, PartTakes $takes, #[CurrentUser] User $user): JsonResponse
    {
        ['part' => $found, 'takes' => $list, 'pitch' => $pitch] = $takes->list($user, $part);

        return response()->json(['data' => $list->map(static fn (Take $take): array => [
            'take' => TakeResource::fields($take->setRelation('part', $found), withUrl: true),
            'current' => $found->current_take_id === $take->id,
            // A singer on a shared song is named; the owner's own takes are null.
            'recordedBy' => $take->recorder !== null && $take->recorded_by !== $found->project->user_id ? $take->recorder->name : null,
            'pitchPercent' => $pitch[$take->uuid] ?? null,
            'durationSeconds' => round($take->frames / max(1, $take->sample_rate), 2),
        ])->values()]);
    }

    /**
     * parts.takes.use: one of the part's kept takes becomes current, whole.
     */
    public function use(string $part, string $take, PartTakes $takes, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => PartResource::fields($takes->use($user, $part, $take))]);
    }

    /**
     * takes.delete: a kept take to Recently deleted; never the part's current one.
     */
    public function destroy(string $take, PartTakes $takes, #[CurrentUser] User $user): Response
    {
        $takes->delete($user, $take);

        return response()->noContent();
    }

    private function take(Take $take): JsonResponse
    {
        return response()->json(['data' => TakeResource::fields($take->loadMissing('part'), withUrl: true)]);
    }
}
