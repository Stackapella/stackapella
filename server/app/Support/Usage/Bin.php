<?php

declare(strict_types=1);

namespace App\Support\Usage;

use App\Models\Project;
use App\Models\Take;
use App\Models\User;

/**
 * "Recently deleted" (bin.list): binned projects, and binned takes outside them, newest first,
 * each with when the purge takes it.
 */
final class Bin
{
    public const MAX = 200;

    /**
     * @return list<array{kind: string, id: string, title: string, projectId: string, partName: string|null, bytes: int, binnedAt: string, goesAt: string}>
     */
    public function items(User $user): array
    {
        $projects = Project::query()->where('user_id', $user->id)->whereNotNull('binned_at')->withSum(['takes as bin_bytes' => fn ($query) => $query->where('status', '!=', Take::FAILED)], 'stored_bytes')->orderByDesc('binned_at')->limit(self::MAX)->get()
            ->map(fn (Project $project): array => [
                'kind' => 'project',
                'id' => $project->uuid,
                'title' => $project->title,
                'projectId' => $project->uuid,
                'partName' => null,
                'bytes' => (int) $project->getAttribute('bin_bytes'),
                'binnedAt' => (string) $project->binned_at?->utc()->toIso8601ZuluString(),
                'goesAt' => (string) $project->binned_at?->copy()->addDays(Take::BIN_DAYS)->utc()->toIso8601ZuluString(),
            ]);

        $takes = Take::query()->select(Take::WITHOUT_PEAKS)->with(['project', 'part'])->where('user_id', $user->id)->whereNotNull('binned_at')->where('status', '!=', Take::FAILED)
            ->whereHas('project', fn ($query) => $query->whereNull('binned_at'))->orderByDesc('binned_at')->limit(self::MAX)->get()
            ->map(fn (Take $take): array => [
                'kind' => 'take',
                'id' => $take->uuid,
                'title' => $take->project->title,
                'projectId' => $take->project->uuid,
                'partName' => $take->part?->name,
                'bytes' => $take->bytes(),
                'binnedAt' => (string) $take->binned_at?->utc()->toIso8601ZuluString(),
                'goesAt' => (string) $take->binned_at?->copy()->addDays(Take::BIN_DAYS)->utc()->toIso8601ZuluString(),
            ]);

        return array_values($projects->concat($takes)->sortByDesc('binnedAt')->take(self::MAX)->all());
    }
}
