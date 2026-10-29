<?php

declare(strict_types=1);

namespace App\Actions\Beta;

use App\Enums\SubmissionStatus;
use App\Exceptions\Refusal;
use App\Models\BetaAssignment;
use App\Models\BetaSinger;
use App\Models\BetaSubmission;
use App\Support\Beta\TakeBar;
use App\Support\Storage\Uploads;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A singer starts sending a take: what the device measured is recorded, and a presigned PUT for the
 * WAV master comes back. The take stays on the device until completeSubmission confirms it.
 */
final class StartSubmission
{
    public function __construct(private readonly Uploads $uploads) {}

    /**
     * @param  array{templateVersion?: int|null, key?: string|null, sampleRate: int, frames: int, latencyFrames: int, bytes: int, sha256: string, device?: string|null, mic?: string|null, scores?: array<string, mixed>|null, note?: string|null}  $take
     * @return array{submission: BetaSubmission, upload: array{url: string, method: string, headers: array<string, string>, expiresAt: \DateTimeInterface}}
     */
    public function handle(BetaSinger $singer, BetaAssignment $assignment, array $take): array
    {
        if (! $singer->hasConsented((int) config('beta.agreement_version'))) {
            throw new Refusal(403, 'consent_required');
        }

        if ($assignment->beta_singer_id !== $singer->id) {
            throw new Refusal(403, 'not_yours');
        }

        $version = null;

        if ($assignment->course_clip_id !== null) {
            // A course clip: no song to match, and no pitch or timing bar (a breathy tone can't be
            // scored), but only what the app's own checks called ready, and never a retired one.
            if ($assignment->clip === null || $assignment->clip->retired_at !== null) {
                throw new Refusal(403, 'not_recordable');
            }

            if (($take['scores']['verdict'] ?? null) !== 'ready') {
                throw ValidationException::withMessages(['scores' => __('validation.take_not_ready')]);
            }
        } else {
            $version = $assignment->template?->currentVersion;

            if ($version === null || ! $version->reviewed) {
                throw new Refusal(403, 'not_recordable');
            }

            if ($version->version !== ($take['templateVersion'] ?? null)) {
                throw new Refusal(409, 'template_changed');
            }

            // Below the bar: not sent (the app offers Send only above it; this is the server's check).
            TakeBar::ensureCleared($take['scores'] ?? null);

            $key = (string) ($version->data['key'] ?? '');

            if (($take['key'] ?? null) !== $key) {
                throw ValidationException::withMessages(['key' => __('validation.take_key', ['key' => $key])]);
            }
        }

        $submission = new BetaSubmission([
            'music_key' => (string) ($take['key'] ?? ''),
            'sample_rate' => $take['sampleRate'],
            'frames' => $take['frames'],
            'latency_frames' => $take['latencyFrames'],
            'bytes' => $take['bytes'],
            'sha256' => strtolower($take['sha256']),
            'device' => $take['device'] ?? null,
            'mic' => $take['mic'] ?? null,
            'scores' => $take['scores'] ?? null,
            'singer_note' => $take['note'] ?? null,
        ]);
        $submission->forceFill([
            'beta_assignment_id' => $assignment->id,
            'template_version_id' => $version?->id,
            'part_key' => $assignment->part_key,
            'status' => SubmissionStatus::Uploading,
            // Random and immutable, never guessable from the singer or the song.
            'master_key' => 'beta/masters/'.Str::lower(Str::random(32)).'.wav',
        ])->save();

        return ['submission' => $submission, 'upload' => $this->uploads->presign($submission->master_key, 'audio/wav', $submission->bytes)];
    }
}
