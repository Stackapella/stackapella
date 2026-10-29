<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Projects;

use App\Actions\Projects\DeletePart;
use App\Actions\Projects\SavePart;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\SavePartRequest;
use App\Http\Requests\Projects\VersionedDeleteRequest;
use App\Http\Resources\Projects\PartResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * parts.save and .delete.
 */
final class PartsController extends Controller
{
    public function save(SavePartRequest $request, string $part, SavePart $save, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{changeId: string, projectId: string, version: int, order: int, name: string, color: string, volume: float|int, pan: float|int, muted: bool, solo: bool, nudgeFrames: int, trimStartFrames: int, trimEndFrames: int, templatePartKey?: string|null, currentTakeId?: string|null, settings?: array<string, mixed>, createdAt?: string|null} $data */
        $data = $request->validated();

        return response()->json(['data' => PartResource::fields($save->handle($user, $part, $data))]);
    }

    public function destroy(VersionedDeleteRequest $request, string $part, DeletePart $delete, #[CurrentUser] User $user): Response
    {
        $delete->handle($user, $part, (int) $request->validated('version'), (string) $request->validated('changeId'));

        return response()->noContent();
    }
}
