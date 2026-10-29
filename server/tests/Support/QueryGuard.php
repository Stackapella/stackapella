<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * The N+1 guard (after iaw's, smaller). Strict models already throw on a lazy load; this catches
 * the rest: the same query, with different values, run again and again inside one request. Each
 * request a test makes is measured on its own (TestCase::call()).
 *
 * A deliberate repeat goes in tests/query-baseline.php with the reason.
 */
final class QueryGuard
{
    /** A query shape run this many times in one request is an N+1. */
    public const REPEATS = 4;

    /** @var array<string, int>|null */
    private static ?array $counts = null;

    public static function start(): void
    {
        self::$counts = [];
    }

    public static function query(string $sql): void
    {
        // The cache and the limiters' counters live in the database (as in production); reading
        // them per limiter rule is not an N+1.
        if (self::$counts === null || ! self::isSelect($sql) || preg_match('/\bfrom `cache(_locks)?`/', $sql) === 1) {
            return;
        }

        $shape = self::normalize($sql);
        self::$counts[$shape] = (self::$counts[$shape] ?? 0) + 1;
    }

    /**
     * What the request ran too often, minus what the baseline allows for this route.
     *
     * @return array<string, int>
     */
    public static function stop(?string $route): array
    {
        $counts = self::$counts ?? [];
        self::$counts = null;

        /** @var array<string, array{reason: string, queries: list<string>}> $baseline */
        $baseline = require __DIR__.'/../query-baseline.php';
        $allowed = $route !== null ? ($baseline[$route]['queries'] ?? []) : [];

        return array_filter($counts, static fn (int $count, string $shape): bool => $count >= self::REPEATS && ! in_array($shape, $allowed, true), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @param  array<string, int>  $found
     */
    public static function describe(string $label, array $found): string
    {
        $lines = array_map(static fn (string $shape, int $count): string => "  x{$count}  ".mb_strimwidth($shape, 0, 240, '...'), array_keys($found), $found);

        return "{$label} ran the same query again and again (an N+1):\n".implode("\n", $lines)
            ."\nEager load what it reads per row (a model's eager-load constant), or count it in one query. A deliberate repeat goes in tests/query-baseline.php with the reason.";
    }

    public static function isSelect(string $sql): bool
    {
        return preg_match('/^\s*select\b/i', $sql) === 1;
    }

    public static function normalize(string $sql): string
    {
        $sql = (string) preg_replace("/'(?:[^'\\\\]|\\\\.)*'/", '?', $sql);
        $sql = (string) preg_replace('/\b\d+\b/', '?', $sql);
        $sql = (string) preg_replace('/\(\s*\?(?:\s*,\s*\?)*\s*\)/', '(?...)', $sql);

        return trim((string) preg_replace('/\s+/', ' ', $sql));
    }
}
