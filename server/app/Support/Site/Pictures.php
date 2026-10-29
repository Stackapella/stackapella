<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Pages\Landing;
use Illuminate\Support\HtmlString;

/**
 * The feature drawings' parts (components/site/pic): the hero song's parts by key, and each part's
 * notes drawn as shapes.
 */
final class Pictures
{
    /**
     * @param  array{parts?: list<array<string, mixed>>}|null  $hero
     * @return array<string, array<string, mixed>>
     */
    public static function parts(?array $hero): array
    {
        return array_column($hero['parts'] ?? [], null, 'key');
    }

    /**
     * @param  array{beats: float}  $hero
     * @param  array{notes: list<array{beat: float, length: float, pitch: int}>, range: array{low: int, high: int}}  $part
     */
    public static function roll(array $hero, array $part, int $height = 30): HtmlString
    {
        return Drawings::roll($part['notes'], $part['range'], (float) $hero['beats'], 300, $height);
    }

    /**
     * The song page's Pick your part: each named part's range, and the visitor's (a sample voice,
     * G3 to C5), as left and width in percent of one shared scale with a little room either side.
     *
     * @param  array{parts?: list<array<string, mixed>>}|null  $hero
     * @param  list<string>  $keys
     * @return array{parts: list<array{part: array<string, mixed>, left: float, width: float}>, you: array{left: float, width: float}}
     */
    public static function picks(?array $hero, array $keys): array
    {
        $parts = array_values(array_intersect_key(self::parts($hero), array_flip($keys)));
        $you = ['low' => 55, 'high' => 72];
        $ranges = array_map(static fn (array $part): array => $part['range'], $parts);
        $low = min($you['low'], ...array_column($ranges, 'low')) - 2;
        $span = max($you['high'], ...array_column($ranges, 'high')) + 2 - $low;
        $place = static fn (array $range): array => [
            'left' => round(($range['low'] - $low) / $span * 100, 1),
            'width' => round(($range['high'] - $range['low']) / $span * 100, 1),
        ];

        return [
            'parts' => array_map(static fn (array $part): array => ['part' => $part, ...$place($part['range'])], $parts),
            'you' => $place($you),
        ];
    }

    /**
     * @param  array{name?: string}  $part
     */
    public static function initial(array $part): string
    {
        return mb_substr((string) ($part['name'] ?? ''), 0, 1);
    }

    /**
     * The song's first `count` words, from its opening line (Landing::hero's words).
     *
     * @param  array{words?: list<array{beat: float, text: string, joins: bool}>}|null  $hero
     */
    public static function firstWords(?array $hero, int $count): string
    {
        $words = preg_split('/\s+/', Landing::sentence($hero['words'] ?? [])) ?: [];

        return implode(' ', array_slice(array_filter($words, static fn (string $w): bool => $w !== ''), 0, $count));
    }

    /**
     * Trim's "Keeping 15.8s of 18.0s" for the hero's opening line: its length, less a breath before
     * and the reach for Stop after.
     *
     * @param  array{beats: float, tempo: float|int}  $hero
     * @return array{kept: string, total: string}
     */
    public static function kept(array $hero): array
    {
        $total = (float) $hero['beats'] * 60 / (float) $hero['tempo'] + 2.2;

        return ['kept' => sprintf('%.1fs', $total - 2.2), 'total' => sprintf('%.1fs', $total)];
    }
}
