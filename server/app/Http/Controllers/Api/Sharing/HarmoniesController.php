<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Sharing;

use App\Actions\Sharing\CompleteHarmony;
use App\Actions\Sharing\DeleteHarmony;
use App\Actions\Sharing\StartHarmony;
use App\Actions\Sharing\UpdateHarmony;
use App\Exceptions\Refusal;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sharing\StartHarmonyRequest;
use App\Http\Requests\Sharing\UpdateHarmonyRequest;
use App\Http\Resources\Sharing\HarmonyResource;
use App\Models\Harmony;
use App\Models\Project;
use App\Models\User;
use App\Support\Http\CursorList;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * harmonies.create, .complete, .list, .show, .update and .delete: the person's own shared
 * harmonies. Anyone else's is "not found".
 */
final class HarmoniesController extends Controller
{
    public function store(StartHarmonyRequest $request, StartHarmony $start, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{changeId: string, projectId: string, partId?: string|null, title?: string|null, visibility: string, sampleRate: int, channels: int, frames: int, bytes: int, sha256: string, beatZeroSeconds?: float|int|null, guides?: list<string>|null, tracks: list<array{name: string, color: string, peaks: string}>} $data */
        $data = $request->validated();
        $started = $start->handle($user, $data, (string) $request->ip());
        $upload = $started['upload'];

        return response()->json([
            'data' => HarmonyResource::fields($started['harmony']->load('project', 'part', 'user')),
            'upload' => $upload !== null ? [...$upload, 'expiresAt' => Carbon::instance($upload['expiresAt'])->utc()->toIso8601ZuluString()] : null,
        ], 201);
    }

    public function complete(string $harmony, CompleteHarmony $complete, #[CurrentUser] User $user): JsonResponse
    {
        return $this->harmony($complete->handle($this->owned($user, $harmony)));
    }

    public function index(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['projectId' => ['nullable', 'uuid'], 'cursor' => ['nullable', 'string', 'max:512']]);
        $project = $request->query('projectId');

        $page = Harmony::query()
            ->with('project', 'part', 'user', 'templateVersion')
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->when(is_string($project), fn ($query) => $query->where('project_id', Project::query()->where('uuid', $project)->where('user_id', $user->id)->value('id') ?? 0))
            ->orderByDesc('id')
            ->cursorPaginate(50);

        return CursorList::response($page, HarmonyResource::class, $request);
    }

    public function show(string $harmony, #[CurrentUser] User $user): JsonResponse
    {
        return $this->harmony($this->owned($user, $harmony));
    }

    public function update(UpdateHarmonyRequest $request, string $harmony, UpdateHarmony $update, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{title?: string|null, visibility?: string, mayFeature?: bool, inGallery?: bool} $data */
        $data = $request->validated();

        return $this->harmony($update->handle($this->owned($user, $harmony), $data, (string) $request->ip()));
    }

    public function destroy(string $harmony, DeleteHarmony $delete, #[CurrentUser] User $user): Response
    {
        $found = Harmony::query()->where('public_id', $harmony)->where('user_id', $user->id)->first();

        if ($found !== null) {
            $delete->handle($found);
        }

        return response()->noContent();
    }

    private function owned(User $user, string $sqid): Harmony
    {
        return Harmony::query()->where('public_id', $sqid)->where('user_id', $user->id)->whereNull('deleted_at')->first() ?? throw new Refusal(404, 'not_found');
    }

    private function harmony(Harmony $harmony): JsonResponse
    {
        return response()->json(['data' => HarmonyResource::fields($harmony->loadMissing('project', 'part', 'user'))]);
    }
}
