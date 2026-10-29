<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Harmony;
use App\Models\ShareVideo;
use App\Support\Sharing\HarmonyFiles;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Storage;

/**
 * Someone hid their profile, showed it again, or changed their name (Hide profile,
 * docs/hide-profile-plan.md): every share card that names them, theirs and their friends' songs
 * they sang on, is drawn again with the name others should now see, and the share videos that
 * already show the old one go (a new one is made when asked for).
 */
final class RedrawPersonsCards implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $userId)
    {
        $this->onQueue('long');
    }

    public function handle(HarmonyFiles $files): void
    {
        $harmonies = Harmony::query()->whereNull('deleted_at')->where('status', Harmony::READY)->whereNotNull('card_key')
            ->where(fn ($q) => $q->where('user_id', $this->userId)->orWhereJsonContains('singer_ids', $this->userId))
            ->get();

        foreach ($harmonies as $harmony) {
            $files->redrawCard($harmony);
            $videos = ShareVideo::query()->where('harmony_id', $harmony->id)->get();
            Storage::disk('private')->delete(array_values(array_filter($videos->pluck('file_key')->all())));
            ShareVideo::query()->whereKey($videos->modelKeys())->delete();
        }
    }
}
