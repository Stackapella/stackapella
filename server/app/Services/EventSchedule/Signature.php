<?php

declare(strict_types=1);

namespace App\Services\EventSchedule;

/**
 * Event Schedule's v2 webhook signature (contract/event-schedule.md, 4): `ES-Signature:
 * t=<unix seconds>,v2=<hex HMAC-SHA256 of "<t>.<raw body>">`, refused when older than 5 minutes.
 */
final class Signature
{
    public const HEADER = 'ES-Signature';

    public const TOLERANCE_SECONDS = 300;

    public static function make(string $secret, string $body, int $timestamp): string
    {
        return 't='.$timestamp.',v2='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function valid(string $secret, string $body, string $header, int $now): bool
    {
        $parts = [];

        foreach (explode(',', $header) as $piece) {
            [$key, $value] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[$key][] = $value;
        }

        $timestamp = (int) ($parts['t'][0] ?? 0);

        if ($secret === '' || $timestamp === 0 || abs($now - $timestamp) > self::TOLERANCE_SECONDS) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        foreach ($parts['v2'] ?? [] as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }
}
