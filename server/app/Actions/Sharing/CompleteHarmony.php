<?php

declare(strict_types=1);

namespace App\Actions\Sharing;

use App\Jobs\ProcessHarmony;
use App\Models\Harmony;
use App\Support\Storage\ReceivedUploads;
use Illuminate\Support\Facades\Storage;

/**
 * harmonies.complete: the stored WAV must be exactly the declared size and SHA-256 (a wrong one is
 * deleted at once), then ProcessHarmony runs on the `long` queue. Safe to call again.
 */
final class CompleteHarmony
{
    public function handle(Harmony $harmony): Harmony
    {
        if ($harmony->status !== Harmony::UPLOADING) {
            return $harmony;
        }

        $received = app(ReceivedUploads::class)->receive($harmony->upload_key, $harmony->upload_bytes, $harmony->sha256);

        // Another complete is checking it this moment: as it is.
        if ($received === null) {
            return $harmony->refresh();
        }

        // Claimed in one statement: two completes at once process it once.
        if (Harmony::query()->whereKey($harmony->id)->where('status', Harmony::UPLOADING)->update(['status' => Harmony::PROCESSING, 'upload_key' => $received, 'updated_at' => now()]) === 1) {
            ProcessHarmony::dispatch($harmony->id);
        } else {
            Storage::disk('private')->delete($received);
        }

        return $harmony->refresh();
    }
}
