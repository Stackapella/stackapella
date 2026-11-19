<?php

declare(strict_types=1);

namespace App\Actions\Videos;

use App\Enums\SongVideoHidden;
use App\Models\SongVideo;
use App\Services\YouTube\YouTubeClient;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

/**
 * The daily check YouTube's Developer Policies ask for (III.E.4.d; docs/youtube-sync-plan.md): every recording last
 * checked SongVideo::RECHECK_DAYS ago or more is checked again (50 to a call, 1 unit each), its facts refreshed and
 * hidden if YouTube no longer lets us show it; a removed video stays hidden and unrefreshed. Then, even when YouTube
 * didn't answer, every row whose last good check is SongVideo::KEEP_DAYS - 1 days old is deleted, map and all, so
 * nothing of YouTube's is kept 30 days.
 */
final class RecheckSongVideos
{
    public function __construct(private readonly YouTubeClient $youtube) {}

    /**
     * @return array{checked: int, hidden: int, deleted: int}
     */
    public function handle(): array
    {
        $checked = 0;
        $hidden = 0;
        try {
            $this->check($checked, $hidden);
        } catch (Throwable $e) {
            // YouTube down, the quota spent, a timeout: what wasn't checked today is tried tomorrow, and the deletion below
            // runs whatever happened, so nothing outlives the policy's 30 days.
            report($e);
        }
        // Deleted the day before its 30 days are up (this runs once a day), so nothing is ever kept 30 days.
        $deleted = SongVideo::query()->where('checked_at', '<', now()->subDays(SongVideo::KEEP_DAYS - 1))->delete();

        return ['checked' => $checked, 'hidden' => $hidden, 'deleted' => (int) $deleted];
    }

    private function check(int &$checked, int &$hidden): void
    {
        if ($this->youtube->configured()) {
            SongVideo::query()->where('checked_at', '<=', now()->subDays(SongVideo::RECHECK_DAYS))
                ->orderBy('id')->chunkById(YouTubeClient::BATCH, function (Collection $rows) use (&$checked, &$hidden): void {
                    /** @var Collection<int, SongVideo> $rows */
                    $found = $this->youtube->videos(array_values($rows->map(static fn (SongVideo $row): string => $row->video_id)->all()));
                    foreach ($rows as $row) {
                        $video = $found[$row->video_id] ?? null;
                        $checked++;
                        if ($video === null) {
                            $row->forceFill(['hidden_reason' => SongVideoHidden::Removed])->save();
                            $hidden++;

                            continue;
                        }
                        $reason = $video->hiddenReason();
                        $hidden += $reason === null ? 0 : 1;
                        $row->forceFill([
                            'title' => $video->title,
                            'channel_title' => $video->channelTitle,
                            'duration_seconds' => $video->durationSeconds,
                            'hidden_reason' => $reason,
                            'checked_at' => now(),
                        ])->save();
                    }
                });
        }
    }
}
