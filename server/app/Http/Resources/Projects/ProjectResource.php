<?php

declare(strict_types=1);

namespace App\Http\Resources\Projects;

use App\Models\Project;
use App\Models\Take;
use App\Support\Usage\Plans;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * contract/schemas/project.json. `bytes` comes from withSum (takes_bytes), or 0.
 *
 * @property Project $resource
 */
final class ProjectResource extends JsonResource
{
    /**
     * Projects with what their takes use (outside the bin) as takes_bytes.
     *
     * @return Builder<Project>
     */
    public static function query(): Builder
    {
        return Project::query()->select('projects.*')->selectSub(
            Take::query()->selectRaw('coalesce(sum(coalesce(stored_bytes, upload_bytes)), 0)')
                ->whereColumn('takes.project_id', 'projects.id')
                ->whereNull('takes.binned_at')
                ->where('takes.status', '!=', Take::FAILED),
            'takes_bytes',
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function fields(Project $project): array
    {
        return [
            'id' => $project->uuid,
            'title' => $project->title,
            'bpm' => (float) $project->bpm,
            'key' => $project->music_key,
            'timeSignature' => $project->time_signature,
            'template' => $project->template_slug !== null ? ['id' => $project->template_slug, 'version' => (int) $project->template_version] : null,
            'settings' => (object) $project->settings,
            'version' => $project->version,
            'bytes' => (int) $project->getAttribute('takes_bytes'),
            'readOnly' => app(Plans::class)->readOnlyOf($project),
            'createdAt' => ($project->device_created_at ?? $project->created_at)?->utc()->toIso8601ZuluString(),
            'updatedAt' => $project->updated_at?->utc()->toIso8601ZuluString(),
            'binnedAt' => $project->binned_at?->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return self::fields($this->resource);
    }
}
