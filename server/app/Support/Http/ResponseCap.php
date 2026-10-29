<?php

declare(strict_types=1);

namespace App\Support\Http;

use RuntimeException;

/**
 * Guzzle options that stop reading a response past a size, so a host we don't run (a self-hosted
 * Event Schedule or Invoice Ninja) can't fill a worker's memory with one answer.
 */
final class ResponseCap
{
    public const BYTES = 2 * 1024 * 1024;

    /**
     * @return array{progress: callable(int, int, int, int): void}
     */
    public static function options(int $bytes = self::BYTES): array
    {
        return ['progress' => static function (int $downloadTotal, int $downloaded) use ($bytes): void {
            if ($downloadTotal > $bytes || $downloaded > $bytes) {
                throw new RuntimeException("The answer is over {$bytes} bytes.");
            }
        }];
    }
}
