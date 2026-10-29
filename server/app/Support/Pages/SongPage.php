<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Enums\TemplateAccess;
use App\Models\Template;
use App\Support\Http\Hosts;
use Illuminate\Support\Facades\Storage;

/**
 * An official song as its public page shows it (docs/go-to-market-plan.md, "Search"): the parts
 * and their ranges, the preview clip, the first verse's words from the melody, and Start, or "On
 * Plus". A free song's page (one of the four, or the song of the month while it's free) also
 * plays its opening line in four voices (Landing::line, SongPagesController::show); a Plus song's
 * never shows its parts' notes (Hillel, 2026-10-27, the design round).
 */
final class SongPage
{
    private const NAMES = ['C', 'C#', 'D', 'Eb', 'E', 'F', 'F#', 'G', 'Ab', 'A', 'Bb', 'B'];

    /**
     * @return array<string, mixed>
     */
    public static function of(Template $template): array
    {
        $data = (array) ($template->currentVersion->data ?? []);
        $parts = array_values(array_filter((array) ($data['parts'] ?? []), 'is_array'));
        $credits = (array) ($data['credits'] ?? []);
        $base = Hosts::publicUrl();

        return [
            'slug' => $template->slug,
            'title' => $template->title,
            'description' => (string) ($data['description'] ?? ''),
            'difficulty' => (string) ($data['difficulty'] ?? ''),
            'genre' => array_values(array_filter((array) ($data['genre'] ?? []), 'is_string')),
            'parts' => array_map(static fn (array $part): array => [
                'key' => (string) ($part['key'] ?? ''),
                'name' => (string) ($part['name'] ?? ''),
                'low' => self::note((int) ($part['range']['low'] ?? 0)),
                'high' => self::note((int) ($part['range']['high'] ?? 0)),
            ], $parts),
            'previewUrl' => $template->preview_key !== null ? Storage::disk('media')->url($template->preview_key) : null,
            'previewType' => 'audio/mp4',
            'words' => self::firstVerse($data, $parts),
            'credits' => array_values(array_filter([
                isset($credits['words']) ? __('pages.song.words', ['who' => $credits['words']]) : null,
                isset($credits['tune']) ? __('pages.song.tune', ['who' => $credits['tune']]) : null,
            ])),
            'plus' => $template->access === TemplateAccess::Plus && ! $template->isFreeOn(now()),
            'freeThisMonth' => $template->isFreeOn(now()),
            'ranges' => self::ranges($parts),
            'pageUrl' => $base.'/songs/'.$template->slug,
            'startUrl' => Hosts::app('/?'.http_build_query(['song' => $template->slug, 'ref' => 'song-page'])),
        ];
    }

    /**
     * The parts' ranges on one scale, for the range chart: each part's lowest and highest note as
     * a share of the scale (from the C at or below the lowest note to the C at or above the
     * highest), and the Cs to mark on it. Each part also has its bar for the song's small glyph
     * (`glyphStart`, `glyphWidth`: at least 26% wide, kept inside the glyph).
     *
     * @param  list<array<string, mixed>>  $parts
     * @return array{marks: list<array{name: string, at: float}>, parts: list<array{name: string, color: int, low: string, high: string, from: float, to: float, glyphStart: float, glyphWidth: float}>}
     */
    public static function ranges(array $parts): array
    {
        $low = static fn (array $part): int => (int) ($part['range']['low'] ?? 60);
        $high = static fn (array $part): int => (int) ($part['range']['high'] ?? 60);
        if ($parts === []) {
            return ['marks' => [], 'parts' => []];
        }
        $bottom = intdiv(min(array_map($low, $parts)), 12) * 12;
        $top = (int) ceil(max(array_map($high, $parts)) / 12) * 12;
        $top = max($top, $bottom + 12);
        $at = static fn (int $midi): float => round(($midi - $bottom) / ($top - $bottom) * 100, 2);

        return [
            'marks' => array_map(static fn (int $c): array => ['name' => self::note($c), 'at' => $at($c)], range($bottom, $top, 12)),
            'parts' => array_map(static fn (array $part, int $i): array => [
                'name' => (string) ($part['name'] ?? ''),
                'color' => $i % 8 + 1,
                'low' => self::note($low($part)),
                'high' => self::note($high($part)),
                'from' => $at($low($part)),
                'to' => $at($high($part)),
                'glyphStart' => min($at($low($part)), 100 - max(26.0, $at($high($part)) - $at($low($part)))),
                'glyphWidth' => max(26.0, $at($high($part)) - $at($low($part))),
            ], $parts, array_keys($parts)),
        ];
    }

    /**
     * A note's name in scientific pitch (60 is C4).
     */
    public static function note(int $midi): string
    {
        return self::NAMES[$midi % 12].(intdiv($midi, 12) - 1);
    }

    /**
     * The first section's words, as the melody sings them: syllables joined into words (a
     * trailing hyphen joins the next), lines broken where the notes mark them.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $parts
     * @return list<string>
     */
    private static function firstVerse(array $data, array $parts): array
    {
        $melody = collect($parts)->first(fn (array $p): bool => ($p['melody'] ?? false) === true) ?? ($parts[0] ?? []);
        $section = (array) (($data['sections'] ?? [])[0] ?? []);
        $end = (float) ($section['endBeat'] ?? PHP_FLOAT_MAX);
        $lines = [];
        $line = '';

        foreach ((array) ($melody['guide']['notes'] ?? []) as $note) {
            if (! is_array($note) || (float) ($note['beat'] ?? 0) >= $end || ! is_string($note['lyric'] ?? null) || $note['lyric'] === '') {
                continue;
            }

            if (($note['br'] ?? false) === true && trim($line) !== '') {
                $lines[] = trim($line);
                $line = '';
            }

            $lyric = $note['lyric'];
            $line .= str_ends_with($lyric, '-') ? substr($lyric, 0, -1) : $lyric.' ';
        }

        if (trim($line) !== '') {
            $lines[] = trim($line);
        }

        return $lines;
    }
}
