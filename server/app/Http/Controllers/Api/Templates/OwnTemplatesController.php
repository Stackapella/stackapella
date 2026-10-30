<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Templates;

use App\Actions\Templates\OwnTemplates;
use App\Actions\Templates\TemplateRecordings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Templates\SaveTemplateRequest;
use App\Http\Requests\Templates\StartRecordingRequest;
use App\Http\Requests\Templates\UpdateTemplateRequest;
use App\Http\Resources\Templates\OwnTemplateResource;
use App\Models\Template;
use App\Models\User;
use App\Support\Http\CursorList;
use App\Support\Usage\Plans;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * templates.mine, .save, .update, .delete and .recordings.*: the person's own templates.
 */
final class OwnTemplatesController extends Controller
{
    public function index(Request $request, Plans $plans, #[CurrentUser] User $user): JsonResponse
    {
        $request->validate(['cursor' => ['nullable', 'string', 'max:512']]);

        $page = Template::query()->with(['versions:id,template_id,version', 'recordings', 'owner', 'parent.owner'])->where('owner_id', $user->id)->whereNull('deleted_at')->orderByDesc('id')->cursorPaginate(50);
        $editable = $plans->editableTemplateIds($user);

        foreach ($page->items() as $template) {
            $template->setAttribute('read_only', $editable !== null && ! $template->isPublic() && ! in_array($template->id, $editable, true));
        }

        return CursorList::response($page, OwnTemplateResource::class, $request);
    }

    public function save(SaveTemplateRequest $request, string $template, OwnTemplates $templates, #[CurrentUser] User $user): JsonResponse
    {
        $json = $request->template();
        $visibility = $request->validated('visibility');
        $optIn = $request->validated('libraryOptIn');
        /** @var array{song: string, source?: string|null, ownWork: bool}|null $declaration */
        $declaration = $request->validated('declaration');
        $parent = $request->validated('parentId');
        $saved = $templates->save($user, $template, $json, is_string($visibility) ? $visibility : null, $optIn === null ? null : (bool) $optIn, $declaration, is_string($parent) ? $parent : null);

        return $this->template($saved);
    }

    public function update(UpdateTemplateRequest $request, string $template, OwnTemplates $templates, #[CurrentUser] User $user): JsonResponse
    {
        $visibility = $request->validated('visibility');
        $optIn = $request->validated('libraryOptIn');

        return $this->template($templates->update($user, $template, is_string($visibility) ? $visibility : null, $optIn === null ? null : (bool) $optIn));
    }

    public function destroy(string $template, OwnTemplates $templates, #[CurrentUser] User $user): Response
    {
        $templates->delete($user, $template);

        return response()->noContent();
    }

    public function startRecording(StartRecordingRequest $request, string $template, TemplateRecordings $recordings, #[CurrentUser] User $user): JsonResponse
    {
        /** @var array{id: string, partKey: string, sampleRate: int, frames: int, offsetFrames: int, bytes: int, sha256: string} $data */
        $data = $request->validated();
        $started = $recordings->start($user, $template, $data);
        $upload = $started['upload'];

        return response()->json([
            'data' => OwnTemplateResource::recording($started['recording']),
            'upload' => $upload !== null ? [...$upload, 'expiresAt' => Carbon::instance($upload['expiresAt'])->utc()->toIso8601ZuluString()] : null,
        ], 201);
    }

    public function completeRecording(string $recording, TemplateRecordings $recordings, #[CurrentUser] User $user): JsonResponse
    {
        return response()->json(['data' => OwnTemplateResource::recording($recordings->complete($user, $recording))]);
    }

    public function destroyRecording(string $recording, TemplateRecordings $recordings, #[CurrentUser] User $user): Response
    {
        $recordings->delete($user, $recording);

        return response()->noContent();
    }

    private function template(Template $template): JsonResponse
    {
        return (new OwnTemplateResource($template->load(['versions:id,template_id,version', 'recordings', 'owner'])))->response()->setStatusCode(200);
    }
}
