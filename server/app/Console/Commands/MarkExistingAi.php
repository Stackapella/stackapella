<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\GuideSource;
use App\Models\Harmony;
use App\Models\OwnChoirPart;
use App\Models\ShareVideo;
use App\Models\TemplateGuide;
use App\Services\Audio\Ffmpeg;
use App\Support\AiVoices\AiMark;
use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Every stored file holding an AI voice, made before the server marked it (EU AI Act Article 50; build plan Brief G):
 * AI guides, the choir's parts and listening copies, shared mixes holding an AI voice and their share videos. Each one
 * without the marking is copied with it (streams untouched, Ffmpeg::markMp4). A public guide gets a new key, as the CDN
 * keeps a guide's file for a year; a private file is replaced where it is (its links last minutes). `--check` changes
 * nothing and fails while any is left unmarked: run before ai_marking.required goes on.
 */
final class MarkExistingAi extends Command
{
    protected $signature = 'app:ai-marking:existing {--check : Report the AI files without the marking, change nothing}';

    protected $description = 'Add the AI marking to stored AI audio made before it was marked';

    public function handle(Ffmpeg $ffmpeg): int
    {
        $check = (bool) $this->option('check');
        $counts = ['marked' => 0, 'unmarked' => 0, 'fixed' => 0, 'missing' => 0];

        $visit = function (string $disk, string $key, ?Closure $moved) use ($ffmpeg, $check, &$counts): void {
            $store = Storage::disk($disk);

            if (! $store->exists($key)) {
                $counts['missing']++;

                return;
            }

            $in = sys_get_temp_dir().'/ai-mark-'.Str::lower(Str::random(16)).'.m4a';
            $out = $in.'.marked.m4a';

            try {
                file_put_contents($in, $store->readStream($key));

                if (AiMark::mp4Marked($ffmpeg->tags($in))) {
                    $counts['marked']++;

                    return;
                }

                $counts['unmarked']++;

                if ($check) {
                    $this->line("unmarked: {$disk}:{$key}");

                    return;
                }

                $ffmpeg->markMp4($in, $out);
                $stream = fopen($out, 'rb');
                $options = $moved !== null ? ['visibility' => 'public', 'ContentType' => 'audio/mp4', 'CacheControl' => 'public, max-age=31536000, immutable'] : ['ContentType' => str_ends_with($key, '.mp4') ? 'video/mp4' : 'audio/mp4'];
                $target = $moved !== null ? 'guides/'.Str::lower(Str::random(32)).'.m4a' : $key;
                $store->writeStream($target, $stream === false ? throw new \RuntimeException("Couldn't open {$out}.") : $stream, $options);

                if ($moved !== null) {
                    $moved($target);
                    $store->delete($key);
                }

                $counts['fixed']++;
            } catch (Throwable $e) {
                $this->error("{$disk}:{$key}: {$e->getMessage()}");
            } finally {
                @unlink($in);
                @unlink($out);
            }
        };

        TemplateGuide::withAi()->where('source', GuideSource::Ai->value)->whereNotNull('media_key')->chunkById(100, function ($guides) use ($visit): void {
            foreach ($guides as $guide) {
                /** @var TemplateGuide $guide */
                $visit('media', (string) $guide->media_key, static fn (string $key) => $guide->forceFill(['media_key' => $key])->saveQuietly());
            }
        });

        OwnChoirPart::query()->whereNotNull('media_key')->chunkById(200, function ($parts) use ($visit): void {
            foreach ($parts as $part) {
                /** @var OwnChoirPart $part */
                $keys = array_unique(array_filter([$part->media_key, $part->listen_key]));

                foreach ($keys as $key) {
                    $visit('private', $key, null);
                }
            }
        });

        Harmony::query()->whereNotNull('audio_key')->chunkById(200, function ($harmonies) use ($visit): void {
            foreach ($harmonies as $harmony) {
                /** @var Harmony $harmony */
                if (! $harmony->holdsAi()) {
                    continue;
                }

                $visit('private', (string) $harmony->audio_key, null);

                ShareVideo::query()->where('harmony_id', $harmony->id)->whereNotNull('file_key')->pluck('file_key')
                    ->each(static fn (mixed $key) => $visit('private', (string) $key, null));
            }
        });

        $this->info(sprintf('%d marked already, %d without the marking, %d marked now, %d files missing.', $counts['marked'], $counts['unmarked'], $counts['fixed'], $counts['missing']));

        return $check && $counts['unmarked'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
