<?php

declare(strict_types=1);

namespace App\Support\Ops;

use Illuminate\Support\Facades\File;

/**
 * Our jobs' temporary files (WAVs, mixes, renders, exports) left in the worker's /tmp by a job
 * that was killed before its `finally` ran (the queue's timeout). Swept from the worker itself,
 * at most once an hour, for anything of ours older than an hour: no job runs that long.
 */
final class TempFiles
{
    /** What our jobs' temporary files and folders start with. */
    public const PREFIXES = ['take', 'harmony', 'master', 'recording', 'review-media', 'video-mix', 'export', 'list', 'guide-', 'share-video-'];

    private static int $sweptAt = 0;

    public static function sweepHourly(): int
    {
        if (self::$sweptAt > time() - 3600) {
            return 0;
        }

        self::$sweptAt = time();

        return self::sweep(sys_get_temp_dir(), time() - 3600);
    }

    public static function sweep(string $folder, int $before): int
    {
        $swept = 0;

        foreach (self::PREFIXES as $prefix) {
            foreach (glob(rtrim($folder, '/').'/'.$prefix.'*') ?: [] as $path) {
                if ((int) @filemtime($path) >= $before) {
                    continue;
                }

                $swept += is_dir($path) ? (File::deleteDirectory($path) ? 1 : 0) : (@unlink($path) ? 1 : 0);
            }
        }

        return $swept;
    }
}
