<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\OwnChoir\ChoirJobs;
use App\Actions\OwnChoir\ChoirTogether;
use App\Enums\OwnChoirStatus;
use App\Models\OwnChoirRequest;
use App\Models\OwnChoirVoice;
use App\Support\OwnChoir\OwnChoir;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Hear yourself as a choir's housekeeping (track B), hourly: a clip never sent within the hour, and a song whose every
 * render try ran out, end `expired` and `render_failed`, and their files go. Nothing of a singer's voice waits around.
 */
final class PruneOwnChoir extends Command
{
    protected $signature = 'app:own-choir:prune';

    protected $description = 'Expire choir clips never sent and songs whose render tries ran out, deleting their files';

    public function handle(ChoirJobs $jobs): int
    {
        // Friends' parts whose owner has the part-minutes again (a new month of billing).
        ChoirTogether::release();

        $expired = 0;
        OwnChoirRequest::query()->where('status', OwnChoirStatus::Uploading->value)->where('created_at', '<', now()->subHour())
            ->chunkById(200, function ($requests) use (&$expired): void {
                foreach ($requests as $request) {
                    /** @var OwnChoirRequest $request */
                    // Only if it's still uploading: one completed this moment keeps its clip for its check.
                    if (OwnChoirRequest::query()->whereKey($request->id)->where('status', OwnChoirStatus::Uploading->value)->update(['status' => OwnChoirStatus::Failed->value, 'failure' => 'expired']) === 1) {
                        // Never reached the renderer: its free song is given back.
                        OwnChoir::releaseFree($request->refresh());
                        if ($request->upload_key !== null) {
                            Storage::disk('private')->delete($request->upload_key);
                        }
                        $expired++;
                    }
                }
            });

        // A clip whose check never ran (its job lost): failed after the hour, its clip gone, the singer free to try again.
        foreach (OwnChoirRequest::query()->where('status', OwnChoirStatus::Queued->value)->where('from_voice', false)->whereNull('clip_checked_at')->where('updated_at', '<', now()->subHour())->get() as $unchecked) {
            ChoirJobs::failAndClean($unchecked, 'expired');
            $expired++;
        }

        $jobs->expireStale();

        // Kept voices unused for own_choir.voice_months, and references never sent within the hour: deleted.
        $voices = 0;
        OwnChoirVoice::query()->where(fn ($query) => $query->where('status', 'ready')->whereRaw('coalesce(last_used_at, saved_at) < ?', [now()->subMonths((int) config('own_choir.voice_months'))])
            ->orWhere(fn ($stuck) => $stuck->where('status', '!=', 'ready')->where('created_at', '<', now()->subHour())))
            ->chunkById(200, function ($old) use (&$voices): void {
                foreach ($old as $voice) {
                    /** @var OwnChoirVoice $voice */
                    Storage::disk('private')->delete(array_values(array_filter([$voice->upload_key, (string) $voice->reference_key])));
                    $voice->delete();
                    $voices++;
                }
            });

        $this->info("Expired {$expired} choir clips never sent; deleted {$voices} voices.");

        return self::SUCCESS;
    }
}
