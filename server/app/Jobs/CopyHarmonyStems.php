<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Gallery\OpenParts;
use App\Models\Harmony;
use App\Models\HarmonyStem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Open parts (N5): the parts of a harmony that stay sung, copied from the takes in its shared mix
 * as stems, placed as its parts were, so a filler sings with them even after the original project
 * changes. A take whose file is gone is left out.
 */
final class CopyHarmonyStems implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public readonly int $harmonyId)
    {
        $this->onQueue('long');
    }

    public function handle(): void
    {
        $harmony = Harmony::query()->find($this->harmonyId);

        if ($harmony === null || $harmony->deleted_at !== null || ($harmony->open_parts ?? []) === [] || $harmony->stems_ready_at !== null) {
            return;
        }

        $disk = Storage::disk('private');
        $made = [];

        try {
            $this->copy($harmony, $made);
        } catch (Throwable $e) {
            // Copies made before it failed aren't left behind.
            $disk->delete(array_column($made, 'file_key'));

            throw $e;
        }

        $this->keep($harmony, $made);
    }

    /**
     * @param  list<array<string, mixed>>  $made
     */
    private function copy(Harmony $harmony, array &$made): void
    {
        $disk = Storage::disk('private');

        foreach (OpenParts::sungParts($harmony) as $key => $take) {
            if (in_array($key, $harmony->open_parts ?? [], true) || $take->file_key === null || ! $disk->exists($take->file_key)) {
                continue;
            }

            $copy = 'stems/'.Str::lower(Str::random(32)).'.'.pathinfo($take->file_key, PATHINFO_EXTENSION);
            $disk->copy($take->file_key, $copy);
            $part = $take->part;
            $made[] = [
                'harmony_id' => $harmony->id,
                'user_id' => $take->recorded_by ?? $harmony->user_id,
                'part_key' => $key,
                'file_key' => $copy,
                'stored_bytes' => (int) $take->stored_bytes,
                'sample_rate' => $take->sample_rate,
                'frames' => $take->frames,
                'offset_frames' => $take->offset_frames + (int) $part?->nudge_frames,
                'trim_start_frames' => (int) $part?->trim_start_frames,
                'trim_end_frames' => (int) $part?->trim_end_frames,
                'volume' => $part !== null ? $part->volume : 1,
                'pan' => $part !== null ? $part->pan : 0,
                'frames_per_peak' => $take->frames_per_peak,
                'peaks' => json_encode($take->peaks ?? []),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $made
     */
    private function keep(Harmony $harmony, array $made): void
    {
        $disk = Storage::disk('private');
        $kept = DB::transaction(function () use ($harmony, $made): bool {
            $locked = Harmony::query()->whereKey($harmony->id)->lockForUpdate()->first();

            // Closed, reopened or deleted meanwhile: these copies aren't wanted.
            if ($locked === null || $locked->deleted_at !== null || $locked->open_parts !== $harmony->open_parts || $locked->stems_ready_at !== null) {
                return false;
            }

            HarmonyStem::query()->insert($made);
            $locked->forceFill(['stems_ready_at' => now()])->save();

            return true;
        });

        if (! $kept) {
            $disk->delete(array_column($made, 'file_key'));
        }
    }
}
