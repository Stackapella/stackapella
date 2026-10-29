<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Projects;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\EditableRequest;
use App\Models\Project;
use App\Models\User;
use App\Support\Usage\Plans;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

/**
 * projects.editable.update: after a downgrade, which projects stay editable (the rest are read-only).
 */
final class EditableController extends Controller
{
    public function update(EditableRequest $request, Plans $plans, #[CurrentUser] User $user): JsonResponse
    {
        /** @var list<string> $ids */
        $ids = array_values(array_unique((array) $request->validated('ids')));
        $limit = $plans->projectLimit($user);

        if ($limit !== null && count($ids) > $limit) {
            throw ValidationException::withMessages(['ids' => __('validation.editable_limit', ['max' => $limit])]);
        }

        $projects = Project::query()->where('user_id', $user->id)->whereIn('uuid', $ids)->get()->keyBy('uuid');
        $errors = [];

        foreach ($ids as $i => $id) {
            $project = $projects->get($id);

            if ($project === null || $project->binned_at !== null) {
                $errors['ids.'.$i] = __('validation.editable_unknown');
            } elseif ($plans->readOnly($user, $project) === 'plus_song') {
                $errors['ids.'.$i] = __('validation.editable_plus_song');
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $user->forceFill(['editable_project_ids' => $ids])->save();
        $plans->readOnlyChanged($user);
        $map = $plans->readOnlyMap($user);

        return response()->json(['data' => [
            'usage' => $plans->usage($user),
            'projects' => Project::query()->where('user_id', $user->id)->whereNull('binned_at')->orderByDesc('updated_at')->get(['id', 'uuid'])->map(fn (Project $p): array => ['id' => $p->uuid, 'readOnly' => $map[$p->id] ?? null])->all(),
        ]]);
    }
}
