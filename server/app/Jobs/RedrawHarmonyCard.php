<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Harmony;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * A renamed harmony's share card, drawn off the request (the CPU review, 2026-11-21): one waiting per harmony, a
 * moment after the rename, so a run of renames draws (and purges the CDN) once, with the last title. Unique only
 * until it starts, so a rename while it draws queues the next.
 */
final class RedrawHarmonyCard implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $harmonyId)
    {
        $this->onQueue('long');
        $this->delay(now()->addSeconds(30));
    }

    public function uniqueId(): string
    {
        return (string) $this->harmonyId;
    }

    public function handle(HarmonyFiles $files): void
    {
        $harmony = Harmony::query()->find($this->harmonyId);

        if ($harmony !== null) {
            $files->redrawCard($harmony);
        }
    }
}
