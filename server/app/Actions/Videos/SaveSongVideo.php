<?php

declare(strict_types=1);

namespace App\Actions\Videos;

use App\Enums\SongVideoHarmony;
use App\Enums\SongVideoStatus;
use App\Exceptions\Refusal;
use App\Models\SongVideo;
use App\Models\Template;
use App\Models\User;
use App\Services\YouTube\YouTubeClient;
use App\Support\Videos\SyncMap;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a recording's sync map from the sync tool (docs/youtube-sync-plan.md): the map is checked against the song's
 * version it was made for, then the video with YouTube (it must exist and be one we may show), each a 422 on its field. A new map, or a changed
 * one, is a draft until someone approves it.
 */
final class SaveSongVideo
{
    public function __construct(private readonly YouTubeClient $youtube) {}

    /**
     * @param  list<array<string, mixed>>  $form
     * @param  list<list<float|int>>  $anchors
     * @param  list<list<float|int>>|null  $otherWords
     */
    public function handle(User $by, string $slug, int $version, string $videoId, array $form, array $anchors, SongVideoHarmony $harmony, ?array $otherWords): SongVideo
    {
        $song = Template::query()->listed()->where('slug', $slug)->first() ?? throw new Refusal(404, 'not_found');
        $template = ($song->versions()->where('version', $version)->first() ?? throw new Refusal(404, 'not_found'))->data;
        $problems = SyncMap::problems($template, $form, $anchors);
        if ($problems !== []) {
            throw ValidationException::withMessages([str_starts_with($problems[0], 'anchors') ? 'anchors' : 'form' => __('validation.song_video_map', ['problems' => implode(', ', $problems)])]);
        }
        $video = $this->youtube->videos([$videoId])[$videoId] ?? throw ValidationException::withMessages(['videoId' => __('validation.song_video_missing')]);
        if (($reason = $video->hiddenReason()) !== null) {
            throw ValidationException::withMessages(['videoId' => __('validation.song_video_unusable', ['reason' => mb_strtolower($reason->label())])]);
        }

        return DB::transaction(function () use ($by, $song, $version, $video, $form, $anchors, $harmony, $otherWords): SongVideo {
            $row = SongVideo::query()->where('template_id', $song->id)->where('video_id', $video->id)->lockForUpdate()->first()
                ?? new SongVideo;
            $row->fill(['form' => $form, 'anchors' => $anchors, 'harmony' => $harmony, 'other_words' => $otherWords]);
            $row->forceFill([
                'template_id' => $song->id,
                'template_version' => $version,
                'video_id' => $video->id,
                'title' => $video->title,
                'channel_title' => $video->channelTitle,
                'duration_seconds' => $video->durationSeconds,
                'checked_at' => now(),
                'hidden_reason' => null,
                'synced_by' => $by->id,
                'status' => SongVideoStatus::Draft,
                'approved_by' => null,
                'approved_at' => null,
            ])->save();

            return $row->load('template', 'syncer', 'approver');
        });
    }
}
