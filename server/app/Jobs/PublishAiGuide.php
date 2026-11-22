<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\AiUploadStatus;
use App\Enums\GuideSource;
use App\Models\AiUpload;
use App\Models\AiVoice;
use App\Models\Template;
use App\Models\TemplateGuide;
use App\Services\Audio\Ffmpeg;
use App\Support\AiVoices\AiGuides;
use App\Support\AiVoices\AiLicence;
use App\Support\AiVoices\AiMark;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * An AI master from the worker becomes the part's AI guide (docs/ai-voices-build-plan.md, backend item 2), the way a
 * beta take does: its audio checked by ffprobe (a 16-bit mono WAV at the declared rate and length), levelled to the
 * guides' loudness, encoded as AAC at beta.guide_kbps, on the CDN at an immutable key. Its frame 0 is the template's beat
 * 0. Checked again when it lands, under locks: a recorded voice published meanwhile, a new version, or the voice
 * withdrawn, and nothing goes up. It replaces the part's earlier AI guide. Safe to run again.
 */
final class PublishAiGuide implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public int $timeout = 300;

    public function __construct(public readonly int $uploadId)
    {
        // `long` by default; `ai` once the long workers take it, so a catalogue run never delays a user's take.
        $this->onQueue((string) config('services.ai_worker.queue', 'long'));
    }

    public function handle(Ffmpeg $ffmpeg): void
    {
        $upload = AiUpload::query()->find($this->uploadId);

        if ($upload === null || $upload->status !== AiUploadStatus::Processing || $upload->master_key === null) {
            return;
        }

        $in = (string) tempnam(sys_get_temp_dir(), 'aimaster');
        $out = sys_get_temp_dir().'/guide-'.Str::lower(Str::random(16)).'.m4a';
        file_put_contents($in, Storage::disk('private')->readStream($upload->master_key));

        try {
            $probe = $ffmpeg->probe($in);

            // An AI voice's master carries its AI marking (EU AI Act Article 50), once required.
            if ((bool) config('ai_marking.required') && ! AiMark::marked($in)) {
                $this->refuse($upload, 'unmarked');

                return;
            }

            if ($probe === null || ! str_contains($probe['format'], 'wav') || $probe['codec'] !== 'pcm_s16le' || $probe['channels'] !== 1
                || $probe['sampleRate'] !== $upload->sample_rate || $probe['frames'] === null || abs($probe['frames'] - $upload->frames) > 1) {
                $this->refuse($upload, 'bad_audio');

                return;
            }

            $volume = $ffmpeg->volume($in);
            $gain = $volume['mean'] !== null ? (float) config('beta.guide_rms_dbfs') - $volume['mean'] : 0.0;
            // Never louder than its loudest sample allows: at most -2 dBFS, room for the AAC encoder's overshoot.
            $gain = max(-24.0, min(24.0, $volume['peak'] !== null ? min($gain, -2.0 - $volume['peak']) : $gain));
            $ffmpeg->encodeGuide($in, $out, $upload->sample_rate, 0, $upload->frames, $gain, ai: true);

            $key = 'guides/'.Str::lower(Str::random(32)).'.m4a';
            $stream = fopen($out, 'rb');
            Storage::disk('media')->writeStream($key, $stream === false ? throw new \RuntimeException("Couldn't open {$out}.") : $stream, ['visibility' => 'public', 'ContentType' => 'audio/mp4', 'CacheControl' => 'public, max-age=31536000, immutable']);
            is_resource($stream) && fclose($stream);

            try {
                $failure = $this->land($upload, $key);
            } catch (Throwable $e) {
                // Nothing went up (a deadlock, a lock wait, a race on the part's one default): its file goes too, so
                // nothing an AI voice sang is left on the CDN without a guide. The retry encodes it again.
                Storage::disk('media')->delete($key);

                throw $e;
            }

            if ($failure !== null) {
                Storage::disk('media')->delete($key);
            }
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }

    /**
     * The guide, under locks taken in the same order as WithdrawAiVoice (the voice, then its uploads, then the guides),
     * and tried again on a deadlock (three attempts): null once published, else why not.
     */
    private function land(AiUpload $upload, string $key): ?string
    {
        return DB::transaction(function () use ($upload, $key): ?string {
            $voice = AiVoice::query()->whereKey($upload->ai_voice_id)->lockForUpdate()->first();
            $row = AiUpload::query()->whereKey($upload->id)->lockForUpdate()->first();

            if ($row === null || $row->status !== AiUploadStatus::Processing) {
                return 'gone';
            }

            $template = Template::query()->whereHas('versions', fn ($query) => $query->whereKey($row->template_version_id))->lockForUpdate()->first();
            $recorded = TemplateGuide::query()->where('template_version_id', $row->template_version_id)->where('part_key', $row->part_key)->lockForUpdate()->exists();

            $failure = match (true) {
                $voice === null || ! $voice->isActive() => 'voice_withdrawn',
                // The terms changed since it was sent: no new parts until its singer accepts them.
                ! AiLicence::licensed($voice) => 'licence_changed',
                $template === null || $template->current_version_id !== $row->template_version_id => 'template_changed',
                $recorded => 'real_voice_exists',
                default => null,
            };

            if ($failure !== null) {
                $row->forceFill(['status' => AiUploadStatus::Failed, 'failure' => $failure])->save();

                return $failure;
            }

            // The part's earlier AI guide, replaced by this render.
            AiGuides::retire(TemplateGuide::withAi()->where('source', GuideSource::Ai->value)->where('template_version_id', $row->template_version_id)
                ->where('part_key', $row->part_key)->lockForUpdate()->get(), null, 'rerendered');

            $guide = new TemplateGuide(['part_key' => $row->part_key]);
            $guide->forceFill([
                'template_version_id' => $row->template_version_id,
                'source' => GuideSource::Ai,
                'ai_voice_id' => $row->ai_voice_id,
                // No recorded voice sings this part (checked above, under the lock): the AI one leads until one does.
                'is_default' => true,
                'defaulted_at' => null,
                'media_key' => $key,
                'sample_rate' => $row->sample_rate,
                'frames' => $row->frames,
                'offset_frames' => 0,
                'gain' => 1,
                'kbps' => (int) config('beta.guide_kbps'),
                'published_at' => now(),
            ])->save();

            // Waits for Hillel's review (phase 1c): only approved AI guides reach the app.
            $row->forceFill(['status' => AiUploadStatus::Published, 'template_guide_id' => $guide->id, 'review' => 'pending'])->save();

            return null;
        }, 3);
    }

    /**
     * Every try failed (ffmpeg, storage or the timeout): the upload says so, and the worker can send it again.
     */
    public function failed(): void
    {
        AiUpload::query()->whereKey($this->uploadId)->where('status', AiUploadStatus::Processing->value)
            ->update(['status' => AiUploadStatus::Failed->value, 'failure' => 'publish_failed']);
    }

    /** Its audio isn't what was declared: failed, and the master goes (nothing of it is kept). */
    private function refuse(AiUpload $upload, string $failure): void
    {
        if ($upload->master_key !== null) {
            Storage::disk('private')->delete($upload->master_key);
        }

        AiUpload::query()->whereKey($upload->id)->where('status', AiUploadStatus::Processing->value)
            ->update(['status' => AiUploadStatus::Failed->value, 'failure' => $failure, 'master_key' => null]);
    }
}
