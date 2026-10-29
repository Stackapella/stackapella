<?php

declare(strict_types=1);

namespace App\Http\Resources\Projects;

use App\Models\Part;
use App\Models\Project;
use App\Models\Take;
use App\Models\User;
use App\Support\Usage\Plans;
use Illuminate\Support\Collection;

/**
 * A project with its parts and their current takes, as projects.show gives it (and each of
 * projects.shared, and invites.accept). Loaded for many projects at once without an N+1.
 */
final class ProjectBundle
{
    /**
     * @param  Collection<int, Project>  $projects
     * @return array<int, array{project: array<string, mixed>, parts: list<array<string, mixed>>, takes: list<array<string, mixed>>}>
     */
    public static function of(Collection $projects): array
    {
        $ids = $projects->pluck('id')->all();

        // Each owner's read-only map once (with their subscription), not per project.
        $plans = app(Plans::class);
        foreach (User::query()->with('subscriptions')->whereKey($projects->pluck('user_id')->unique()->all())->get() as $owner) {
            $plans->readOnlyMap($owner);
        }

        $withBytes = ProjectResource::query()->whereKey($ids)->get()->keyBy('id');
        $parts = Part::query()->with(['currentTake:id,uuid', 'creator'])->whereIn('project_id', $ids)->orderBy('order')->orderBy('uuid')->get()->groupBy('project_id');
        $takes = Take::query()->whereIn('id', $parts->flatten()->pluck('current_take_id')->filter()->all())->get();
        $bundles = [];

        foreach ($projects as $project) {
            /** @var Collection<int, Part> $own */
            $own = $parts->get($project->id, collect());
            $own->each(fn (Part $part): Part => $part->setRelation('project', $project));

            $bundles[$project->id] = [
                'project' => ProjectResource::fields($withBytes->get($project->id) ?? $project),
                'parts' => array_values($own->map(fn (Part $part): array => PartResource::fields($part))->all()),
                'takes' => array_values($takes->whereIn('part_id', $own->pluck('id'))->map(fn (Take $take): array => TakeResource::fields($take->setRelation('part', $own->firstWhere('id', $take->part_id))))->all()),
            ];
        }

        return $bundles;
    }

    /**
     * @return array{project: array<string, mixed>, parts: list<array<string, mixed>>, takes: list<array<string, mixed>>}
     */
    public static function one(Project $project): array
    {
        return self::of(collect([$project]))[$project->id];
    }
}
