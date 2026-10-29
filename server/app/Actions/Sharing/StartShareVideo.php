<?php

declare(strict_types=1);

namespace App\Actions\Sharing;

use App\Exceptions\Refusal;
use App\Jobs\RenderShareVideo;
use App\Models\Harmony;
use App\Models\ShareVideo;
use App\Support\Trust\Limits;
use Illuminate\Validation\ValidationException;

/**
 * videos.create: Share as a video (docs/more-features-plan.md, feature 4). The harmony must be
 * unlisted (its link is on the end card), ready and not moderated. The same choices again answer
 * the video already made or being made, without counting toward the limit.
 */
final class StartShareVideo
{
    public function __construct(private readonly Limits $limits) {}

    /**
     * @param  array{startSeconds: float|int, endSeconds: float|int, look?: string|null, words?: bool|null}  $data
     * @return array{video: ShareVideo, created: bool}
     */
    public function handle(Harmony $harmony, array $data, string $ip): array
    {
        if ($harmony->status !== Harmony::READY) {
            throw new Refusal(409, 'harmony_not_ready');
        }

        if ($harmony->moderation() !== null) {
            throw new Refusal(409, 'harmony_unavailable');
        }

        if ($harmony->visibility !== Harmony::UNLISTED) {
            throw new Refusal(409, 'harmony_private');
        }

        $start = (int) round((float) $data['startSeconds'] * 1000);
        $end = (int) round((float) $data['endSeconds'] * 1000);
        $length = ($end - $start) / 1000;
        $min = (int) config('sharing.videos.min_seconds');
        $max = (int) config('sharing.videos.max_seconds');

        if ($length < $min || $length > $max + 0.05 || $end > (int) round($harmony->durationSeconds() * 1000) + 50) {
            throw ValidationException::withMessages(['endSeconds' => __('validation.video_section', ['min' => $min, 'max' => $max])]);
        }

        $end = min($end, (int) round($harmony->durationSeconds() * 1000));
        $look = $data['look'] ?? 'dark';
        $words = $data['words'] ?? true;

        $same = ShareVideo::query()
            ->where('harmony_id', $harmony->id)
            ->where('start_ms', $start)->where('end_ms', $end)->where('look', $look)->where('words', $words)
            ->whereIn('status', [ShareVideo::QUEUED, ShareVideo::RENDERING, ShareVideo::READY])
            // One past its 7 days is expired, even before the hourly prune: make it again.
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->latest('id')
            ->first();

        if ($same !== null) {
            return ['video' => $same, 'created' => false];
        }

        ShareGate::count($this->limits->attempt('share-videos', $harmony->user, $ip));

        $video = new ShareVideo(['start_ms' => $start, 'end_ms' => $end, 'look' => $look, 'words' => $words]);
        $video->forceFill(['harmony_id' => $harmony->id, 'user_id' => $harmony->user_id, 'status' => ShareVideo::QUEUED, 'progress' => 0])->save();

        RenderShareVideo::dispatch($video->id);

        return ['video' => $video, 'created' => true];
    }
}
