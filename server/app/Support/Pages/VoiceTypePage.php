<?php

declare(strict_types=1);

namespace App\Support\Pages;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * A voice type's page in Hillel's design (2026-11-11, "Alto Voice page redesign"): what site/voice-type.blade.php shows
 * beside the reference page's own words. Its place among the six, its neighbours (the types its guide tells it from),
 * every type's usual range as a bar on one shared scale, and the song parts inside its range, each as a bar inside it.
 * Every number is Reference::VOICE_TYPES's range and the song files' own parts (SongParts::within). Words:
 * site.reference.voices.index.
 */
final class VoiceTypePage
{
    /** The types each one's guide tells it from ("Am I an alto or a mezzo-soprano?"), in the guide's order. */
    public const NEIGHBOURS = [
        'bass' => ['baritone'], 'baritone' => ['tenor', 'bass'], 'tenor' => ['baritone'],
        'alto' => ['mezzo-soprano'], 'mezzo-soprano' => ['soprano', 'alto'], 'soprano' => ['mezzo-soprano'],
    ];

    /** Each type's part colour (s-v1 to s-v6), the same everywhere: the four choir parts as the songs colour them, then the
     * two in between (game draws each one's bar in it; game, 2026-11-11). */
    public const COLOURS = ['soprano' => 1, 'alto' => 2, 'tenor' => 3, 'bass' => 4, 'mezzo-soprano' => 5, 'baritone' => 6];

    /** The scale's labelled notes besides its two ends: each C between them (MIDI). */
    private const TICKS = [48, 60, 72];

    /**
     * Everything the page needs besides Reference::page: its place (n of total), its neighbours, the ladder of every
     * type on one scale with the scale's ticks, its song parts each with its bar inside the range, and its contents.
     *
     * @param  list<array{title: string, name: string, url: string, low: int, high: int, lowName: string, highName: string}>  $songs
     * @return array{n: int, total: int, colour: int, lower: string, neighbours: list<array{name: string, url: string}>, ladder: list<array{name: string, range: string, url: string, left: float, width: float, current: bool, colour: int}>, ticks: list<array{name: string, left: float}>, songs: list<array{title: string, name: string, url: string, range: string, left: float, width: float}>, partsHeading: string, contents: list<array{0: string, 1: string}>}
     */
    public static function of(string $slug, array $songs, bool $faq): array
    {
        $index = (array) Lang::get('site.reference.voices.index');
        $slugs = Reference::slugs('voice-types');
        $page = Reference::page('voice-types', $slug);
        $range = (string) Lang::get('site.reference.voices.card.range');
        $short = (array) ($index['short'] ?? []);
        [, , $low, $high] = Reference::VOICE_TYPES[$slug];

        // One scale for all six, from the lowest type's bottom to the highest type's top.
        $bottom = min(array_column(Reference::VOICE_TYPES, 2));
        $top = max(array_column(Reference::VOICE_TYPES, 3));
        $at = static fn (int $midi, int $from, int $to): float => round(($midi - $from) / max(1, $to - $from) * 100, 2);

        $ladder = array_map(static function (string $s) use ($slug, $range, $at, $bottom, $top): array {
            [, , $a, $b] = Reference::VOICE_TYPES[$s];
            $p = Reference::page('voice-types', $s);

            return ['name' => (string) $p['name'], 'range' => str_replace([':low', ':high'], [SongPage::note($a), SongPage::note($b)], $range), 'url' => (string) $p['url'], 'left' => $at($a, $bottom, $top), 'width' => $at($b, $bottom, $top) - $at($a, $bottom, $top), 'current' => $s === $slug, 'colour' => self::COLOURS[$s]];
        }, $slugs);

        $ticks = array_map(static fn (int $m): array => ['name' => SongPage::note($m), 'left' => $at($m, $bottom, $top)], [$bottom, ...array_values(array_filter(self::TICKS, static fn (int $m): bool => $m > $bottom && $m < $top)), $top]);

        $bars = array_map(static fn (array $song): array => [
            'title' => $song['title'], 'name' => $song['name'], 'url' => $song['url'],
            'range' => str_replace([':low', ':high'], [$song['lowName'], $song['highName']], $range),
            'left' => $at($song['low'], $low, $high), 'width' => $at($song['high'], $low, $high) - $at($song['low'], $low, $high),
        ], $songs);

        $lower = Str::lower((string) $page['name']);
        $own = Reference::VOICE_PARTS[$slug];

        $contents = [];
        foreach (array_values((array) ($page['guide'] ?? [])) as $i => $section) {
            $contents[] = ['#guide-'.($i + 1), (string) ($section['heading'] ?? '')];
        }
        $contents[] = ['#types', (string) $index['toc_types']];
        if ($bars !== []) {
            $contents[] = ['#parts', (string) $index['toc_parts']];
        }
        if ($faq) {
            $contents[] = ['#faq', (string) $index['toc_faq']];
        }

        return [
            'n' => (int) array_search($slug, $slugs, true) + 1,
            'colour' => self::COLOURS[$slug],
            'total' => count($slugs),
            'lower' => $lower,
            'neighbours' => array_values(array_map(static fn (string $s): array => ['name' => (string) ($short[$s] ?? Reference::page('voice-types', $s)['name']), 'url' => (string) Reference::page('voice-types', $s)['url']], array_filter(self::NEIGHBOURS[$slug], static fn (string $s): bool => in_array($s, $slugs, true)))),
            'ladder' => array_values($ladder),
            'ticks' => $ticks,
            'songs' => $bars,
            // "Alto parts to start with" when the type sings one choir part; a baritone's or a mezzo's are two parts' songs.
            'partsHeading' => count($own) === 1 ? str_replace(':name', (string) $page['name'], (string) $index['parts_h']) : (string) $index['parts_h_any'],
            'contents' => $contents,
        ];
    }
}
