<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Gallery\GalleryGuides;
use App\Models\Harmony;
use App\Models\Part;
use App\Models\Take;
use App\Models\TemplateGuide;
use App\Services\Audio\Ffmpeg;
use App\Support\Gallery\Gallery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A take from a song in the gallery published as a voice of its template's part (GalleryGuides):
 * levelled as a beta guide is, as an AAC on the CDN at an immutable key, placed where the take
 * plays in the song. Checked again when it runs: a song that left the gallery meanwhile publishes
 * nothing.
 */
final class PublishGalleryGuide implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $harmonyId, public readonly int $partId, public readonly int $takeId, public readonly bool $makeDefault = false)
    {
        $this->onQueue('long');
    }

    public function handle(Ffmpeg $ffmpeg, Gallery $gallery): void
    {
        $harmony = Harmony::query()->find($this->harmonyId);
        $part = Part::query()->find($this->partId);
        $take = Take::query()->find($this->takeId);

        if ($harmony === null || $part === null || $take === null || $take->file_key === null || $harmony->template_version_id === null || ! $gallery->isPublic($harmony)) {
            return;
        }

        $in = (string) tempnam(sys_get_temp_dir(), 'take');
        $out = sys_get_temp_dir().'/guide-'.Str::lower(Str::random(16)).'.m4a';
        file_put_contents($in, Storage::disk('private')->readStream($take->file_key));

        try {
            $volume = $ffmpeg->volume($in);
            $gain = $volume['mean'] !== null ? (float) config('beta.guide_rms_dbfs') - $volume['mean'] : 0.0;
            $gain = max(-24.0, min(24.0, $volume['peak'] !== null ? min($gain, -2.0 - $volume['peak']) : $gain));
            $ffmpeg->encodeGuide($in, $out, $take->sample_rate, 0, $take->frames, $gain);

            $key = 'guides/'.Str::lower(Str::random(32)).'.m4a';
            $stream = fopen($out, 'rb');
            Storage::disk('media')->writeStream($key, $stream === false ? throw new \RuntimeException("Couldn't open {$out}.") : $stream, ['visibility' => 'public', 'ContentType' => 'audio/mp4', 'CacheControl' => 'public, max-age=31536000, immutable']);
            is_resource($stream) && fclose($stream);

            $published = DB::transaction(function () use ($harmony, $part, $take, $key, $gallery): bool {
                // No longer public while this ran (by any of its rules, read afresh under the
                // lock that taking it down also takes): nothing goes up.
                $locked = Harmony::query()->whereKey($harmony->id)->lockForUpdate()->first();

                if ($locked === null || ! $gallery->isPublic($locked) || ! GalleryGuides::inMix($locked, $take) || ! GalleryGuides::creditAdult($locked, $take)) {
                    return false;
                }

                $partKey = (string) $part->template_part_key;
                $hasDefault = TemplateGuide::query()->where('template_version_id', $harmony->template_version_id)->where('part_key', $partKey)->where('is_default', true)->lockForUpdate()->exists();
                $isDefault = $this->makeDefault || ! $hasDefault;

                if ($isDefault) {
                    TemplateGuide::query()->where('template_version_id', $harmony->template_version_id)->where('part_key', $partKey)->update(['is_default' => false]);
                }

                $guide = new TemplateGuide(['part_key' => $partKey]);
                $guide->forceFill([
                    'template_version_id' => $harmony->template_version_id,
                    'harmony_id' => $harmony->id,
                    'take_id' => $take->id,
                    'credit_user_id' => $take->recorded_by ?? $harmony->user_id,
                    'is_default' => $isDefault,
                    'defaulted_at' => $this->makeDefault ? now() : null,
                    'media_key' => $key,
                    'sample_rate' => $take->sample_rate,
                    'frames' => $take->frames,
                    'offset_frames' => $take->offset_frames + $part->nudge_frames,
                    'gain' => 1,
                    'published_at' => now(),
                ])->save();

                return true;
            });

            if (! $published) {
                Storage::disk('media')->delete($key);
            }
        } finally {
            @unlink($in);
            @unlink($out);
        }
    }
}
