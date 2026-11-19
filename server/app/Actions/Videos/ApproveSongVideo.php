<?php

declare(strict_types=1);

namespace App\Actions\Videos;

use App\Enums\SongVideoStatus;
use App\Exceptions\Refusal;
use App\Models\SongVideo;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Someone played a synced recording through and approves it (meant to be someone other than who synced it, which
 * isn't enforced: the pilot's sync is the agent's work in Hillel's account, and he approves it): singers see it from now on (unless YouTube's
 * last check hid it). Saving the map again makes it a draft again.
 */
final class ApproveSongVideo
{
    public function handle(User $by, string $sqid): SongVideo
    {
        return DB::transaction(function () use ($by, $sqid): SongVideo {
            $row = SongVideo::query()->whereSqid($sqid)->lockForUpdate()->first() ?? throw new Refusal(404, 'not_found');
            if ($row->status !== SongVideoStatus::Approved) {
                $row->forceFill(['status' => SongVideoStatus::Approved, 'approved_by' => $by->id, 'approved_at' => now()])->save();
            }

            return $row->load('template', 'syncer', 'approver');
        });
    }
}
