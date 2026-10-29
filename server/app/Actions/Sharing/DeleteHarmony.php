<?php

declare(strict_types=1);

namespace App\Actions\Sharing;

use App\Models\Harmony;
use App\Services\Social\SocialPoster;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Support\Facades\DB;

/**
 * harmonies.delete: the page, the files and the videos go at once. The row stays without its files
 * (deleted_at), so a page taken down for copyright still counts toward repeat infringers.
 */
final class DeleteHarmony
{
    public function __construct(private readonly HarmonyFiles $files) {}

    public function handle(Harmony $harmony): void
    {
        // Locked, so a ProcessHarmony finishing at the same moment either sees it deleted or has
        // already saved the keys read here.
        $deleted = DB::transaction(function () use ($harmony): ?Harmony {
            $locked = Harmony::query()->whereKey($harmony->id)->whereNull('deleted_at')->lockForUpdate()->first();
            $locked?->forceFill(['deleted_at' => now()])->save();

            return $locked;
        });

        if ($deleted !== null) {
            $this->files->deleteAll($deleted);
            SocialPoster::withdraw($deleted);
        }
    }

    /**
     * Someone's voice left: every harmony with their takes in its mix comes down (in one project,
     * when they left it, or everywhere, when they deleted their account). The owner can share
     * again without them.
     */
    public function withVoiceOf(int $singerId, ?int $projectId = null): void
    {
        $harmonies = Harmony::query()->whereNull('deleted_at')->whereJsonContains('singer_ids', $singerId)
            ->when($projectId !== null, fn ($query) => $query->where('project_id', $projectId))->get();

        foreach ($harmonies as $harmony) {
            $this->handle($harmony);
        }
    }
}
