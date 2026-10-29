<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Projects;

use App\Actions\Projects\BinProject;
use App\Actions\Projects\Owned;
use App\Actions\Projects\SaveProject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\SaveProjectRequest;
use App\Http\Requests\Projects\VersionedDeleteRequest;
use App\Http\Resources\Projects\ProjectBundle;
use App\Http\Resources\Projects\ProjectResource;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * projects.show, .save, .delete and .restore.
 */
final class ProjectsController extends Controller
{
    public function show(string $project, Owned $owned, #[CurrentUser] User $user): JsonResponse
    {
        // The owner's, or one they sing in (a shared project comes up to date this way).
        $found = $owned->reachable($user, $project);

        return response()->json(['data' => ProjectBundle::one($found)]);
    }

    public function save(SaveProjectRequest $request, string $project, SaveProject $save, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{changeId: string, version: int, title: string, bpm: float|int, key?: string|null, timeSignature: string, template?: array{id: string, version: int}|null, settings?: array<string, mixed>, createdAt?: string|null} $data */
        $data = $request->validated();
        $saved = $save->handle($user, $project, $data);

        return $this->project($saved->id);
    }

    public function destroy(VersionedDeleteRequest $request, string $project, BinProject $bin, #[CurrentUser] User $user): Response
    {
        $bin->bin($user, $project, (int) $request->validated('version'), (string) $request->validated('changeId'));

        return response()->noContent();
    }

    public function restore(string $project, BinProject $bin, #[CurrentUser] User $user): JsonResponse
    {
        return $this->project($bin->restore($user, $project)->id);
    }

    private function project(int $id): JsonResponse
    {
        return response()->json(['data' => ProjectResource::fields(ProjectResource::query()->whereKey($id)->firstOrFail())]);
    }
}
