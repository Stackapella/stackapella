<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Languages\NoteNames;
use App\Support\Languages\PartNames;
use App\Support\Languages\SongWords;
use Illuminate\Support\Facades\Storage;

/**
 * An official song as its public page shows it (docs/go-to-market-plan.md, "Search"): the parts
 * and their ranges, the preview clip, the first verse's words from the melody, and Start. Every
 * song is free with an account (2026-11-07); a page plays its opening line in four voices only for
 * a song the app bundles (Landing::line, SongPagesController::show): any other song's notes come
 * only to an account, through the API (Hillel, 2026-10-27, the design round).
 */
final class SongPage
{
    /** A song longer than this many sung lines can hide its meanings. */
    public const MEANING_LINES = 16;

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
            'title' => SongWords::title((string) $template->title, (string) $template->slug),
            'description' => SongWords::description((string) ($data['description'] ?? '')),
            // Its story where it has one (lang song_about), the part pages' About too.
            'about' => trans()->has('song_about.'.$template->slug) ? (string) __('song_about.'.$template->slug) : null,
            'difficulty' => (string) ($data['difficulty'] ?? ''),
            'genre' => array_values(array_filter((array) ($data['genre'] ?? []), 'is_string')),
            'parts' => array_map(static fn (array $part): array => [
                'key' => (string) ($part['key'] ?? ''),
                'name' => PartNames::name((string) ($part['key'] ?? ''), (string) ($part['name'] ?? '')),
                'low' => self::note((int) ($part['range']['low'] ?? 0)),
                'high' => self::note((int) ($part['range']['high'] ?? 0)),
            ], $parts),
            'previewUrl' => $template->preview_key !== null ? Storage::disk('media')->url($template->preview_key) : null,
            'previewType' => 'audio/mp4',
            'words' => self::firstVerse($data, $parts),
            // Every sung line by stanza, with its meaning in the page's language where the courses lead wrote one and the
            // line still sings so (SongMeanings; marketing's words band and meanings toggle). Null when none applies.
            'lines' => ($meanings = SongMeanings::forPage((string) $template->slug, $data, app()->getLocale()))['lines'] ?? null,
            'sungLanguage' => $meanings['sungLanguage'] ?? null,
            'credits' => array_values(array_filter([
                isset($credits['words']) ? __('pages.song.words', ['who' => SongWords::credit((string) $credits['words'])]) : null,
                isset($credits['tune']) ? __('pages.song.tune', ['who' => SongWords::credit((string) $credits['tune'])]) : null,
            ])),
            'ranges' => self::linked(self::ranges($parts), $parts, SongParts::hasPages($template) ? $base.'/songs/'.$template->slug.'/' : null),
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
                'name' => PartNames::name((string) ($part['key'] ?? ''), (string) ($part['name'] ?? '')),
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
     * Each part of the range chart with its own page's address (SongParts), or null when the song has
     * none: same order as the parts.
     *
     * @param  array{marks: list<array{name: string, at: float}>, parts: list<array<string, mixed>>}  $ranges
     * @param  list<array<string, mixed>>  $parts
     * @return array{marks: list<array{name: string, at: float}>, parts: list<array<string, mixed>>}
     */
    private static function linked(array $ranges, array $parts, ?string $base): array
    {
        $ranges['parts'] = array_map(static function (array $row, int $i) use ($parts, $base): array {
            $key = $parts[$i]['key'] ?? null;

            return [...$row, 'url' => $base !== null && is_string($key) && preg_match(SongParts::KEY, $key) === 1 ? $base.$key : null];
        }, $ranges['parts'], array_keys($ranges['parts']));

        return $ranges;
    }

    /**
     * Every verse's words, as the melody sings them, one sung line a line (Hillel's "Responsive wrapping fixes": a line
     * is a phrase, so a long one wraps under itself, never into the next): each verse, chorus or refrain a stanza, each
     * of its phrases a line, a syllable joined to the next by its trailing hyphen. A section without phrases breaks where the notes
     * mark it, as firstVerse does.
     *
     * @return list<list<string>>
     */
    public static function verses(Template $template): array
    {
        $data = (array) ($template->currentVersion->data ?? []);
        $parts = array_values(array_filter((array) ($data['parts'] ?? []), 'is_array'));
        $melody = collect($parts)->first(fn (array $p): bool => ($p['melody'] ?? false) === true) ?? ($parts[0] ?? []);
        $notes = array_values(array_filter((array) ($melody['guide']['notes'] ?? []), static fn (mixed $n): bool => is_array($n) && is_string($n['lyric'] ?? null) && $n['lyric'] !== ''));
        $sections = array_values(array_filter((array) ($data['sections'] ?? []), 'is_array'));
        if ($sections === []) {
            $sections = [['startBeat' => 0, 'endBeat' => PHP_FLOAT_MAX, 'phrases' => []]];
        }

        $verses = [];
        foreach ($sections as $section) {
            $start = (float) ($section['startBeat'] ?? 0);
            $end = (float) ($section['endBeat'] ?? PHP_FLOAT_MAX);
            $phrases = array_values(array_filter((array) ($section['phrases'] ?? []), static fn (mixed $p): bool => is_array($p) && count($p) === 2));
            $lines = [];
            $at = -1;
            foreach ($notes as $note) {
                $beat = (float) ($note['beat'] ?? 0);
                if ($beat < $start || $beat >= $end) {
                    continue;
                }
                if ($phrases !== []) {
                    // The phrase it falls in; a pickup before the first belongs to it, a note after the last to the last.
                    $line = 0;
                    foreach ($phrases as $i => [$from]) {
                        if ($beat >= (float) $from) {
                            $line = $i;
                        }
                    }
                } else {
                    $line = $at < 0 ? 0 : (($note['br'] ?? false) === true ? $at + 1 : $at);
                }
                $at = max($at, $line);
                $lyric = (string) $note['lyric'];
                $lines[$line] = ($lines[$line] ?? '').(str_ends_with($lyric, '-') ? substr($lyric, 0, -1) : $lyric.' ');
            }
            ksort($lines);
            $lines = array_values(array_filter(array_map('trim', $lines), static fn (string $l): bool => $l !== ''));
            if ($lines === []) {
                continue;
            }
            // A verse, chorus or refrain starts a stanza; any other section (a part joining, a round's entries, an
            // ending) goes on with the one before, as it's sung.
            if ($verses === [] || preg_match('/^(verse|chorus|refrain|stanza)\b/i', (string) ($section['name'] ?? '')) === 1) {
                $verses[] = $lines;
            } else {
                $verses[array_key_last($verses)] = [...$verses[array_key_last($verses)], ...$lines];
            }
        }

        return $verses;
    }

    /**
     * The song's parts as "Find your part" cards (Hillel's Danny Boy design), each with its range on the page's one
     * scale, its part page, and one tag that is true of it from the parts alone: the tune, the lowest part, the smallest
     * range, the widest range, else harmony. Never "easiest": a tag says only what the notes show.
     *
     * @param  array<string, mixed>  $song  of()
     * @return list<array{key: string, name: string, low: string, high: string, from: float, to: float, color: int, url: string|null, tag: string, melody: bool}>
     */
    public static function cards(Template $template, array $song): array
    {
        $data = (array) ($template->currentVersion->data ?? []);
        $raw = array_values(array_filter((array) ($data['parts'] ?? []), 'is_array'));
        $span = static fn (array $p): int => (int) ($p['range']['high'] ?? 0) - (int) ($p['range']['low'] ?? 0);
        $spans = array_map($span, $raw);
        $lows = array_map(static fn (array $p): int => (int) ($p['range']['low'] ?? 0), $raw);
        $several = count($raw) > 1;

        $cards = [];
        foreach ((array) ($song['parts'] ?? []) as $i => $part) {
            $range = (array) (($song['ranges']['parts'] ?? [])[$i] ?? []);
            $melody = (bool) ($raw[$i]['melody'] ?? false);
            $tag = match (true) {
                $melody => 'melody',
                $several && $lows !== [] && $lows[$i] === min($lows) && count(array_keys($lows, min($lows), true)) === 1 => 'lowest',
                $several && $spans !== [] && $spans[$i] === min($spans) && count(array_keys($spans, min($spans), true)) === 1 => 'smallest',
                $several && $spans !== [] && $spans[$i] === max($spans) && count(array_keys($spans, max($spans), true)) === 1 => 'widest',
                default => 'harmony',
            };
            $cards[] = [
                'key' => (string) $part['key'],
                'name' => (string) $part['name'],
                'low' => (string) $part['low'],
                'high' => (string) $part['high'],
                'from' => (float) ($range['from'] ?? 0),
                'to' => (float) ($range['to'] ?? 0),
                'color' => (int) ($range['color'] ?? ($i % 4 + 1)),
                'url' => isset($range['url']) && is_string($range['url']) ? $range['url'] : null,
                'tag' => $tag,
                'melody' => $melody,
            ];
        }

        return $cards;
    }

    /**
     * The words "The words" shows: a song's line by line words when it has them, a list per stanza of {sung, script?,
     * scriptLang?, meaning?} in the page's language (whatever isn't a line with its sung words is left out), else its
     * sung lines (verses()) alone.
     *
     * @param  list<list<string>>  $verses
     * @return list<list<array{sung: string, script?: string, scriptLang?: string, meaning?: string}>>
     */
    public static function lines(mixed $given, array $verses): array
    {
        $stanzas = [];
        foreach (is_array($given) ? $given : [] as $stanza) {
            $lines = [];
            foreach (is_array($stanza) ? $stanza : [] as $line) {
                if (! is_array($line) || ! is_string($line['sung'] ?? null) || $line['sung'] === '') {
                    continue;
                }
                $lines[] = array_filter(['sung' => $line['sung'], 'script' => $line['script'] ?? null, 'scriptLang' => $line['scriptLang'] ?? null, 'meaning' => $line['meaning'] ?? null], static fn (mixed $v): bool => is_string($v) && $v !== '');
            }
            if ($lines !== []) {
                $stanzas[] = $lines;
            }
        }

        return $stanzas !== [] ? $stanzas : array_map(static fn (array $verse): array => array_map(static fn (string $sung): array => ['sung' => $sung], $verse), $verses);
    }

    /**
     * Whether a song's meanings get a Show and Hide (a long song, more than MEANING_LINES sung lines, with meanings).
     *
     * @param  list<list<array<string, string>>>  $lines
     */
    public static function meaningToggle(array $lines): bool
    {
        $all = array_merge(...($lines ?: [[]]));

        return count($all) > self::MEANING_LINES && array_filter($all, static fn (array $line): bool => isset($line['meaning'])) !== [];
    }

    /**
     * The whole mix's credit line: each part a real singer sings, by name, else the synthesized voices said.
     *
     * @param  list<array{key: string, name: string}>  $cards
     * @param  array<string, array{realVoice: bool, credit: string|null, creditHandle: string|null}>  $guides
     */
    public static function mixCredit(array $cards, array $guides): string
    {
        $sung = [];
        foreach ($cards as $card) {
            $guide = $guides[$card['key']] ?? null;
            if ($guide !== null && $guide['realVoice'] && $guide['credit'] !== null) {
                $sung[] = (string) __('pages.song.mix_sung', ['part' => $card['name'], 'name' => $guide['credit']]);
            }
        }
        if ($sung === []) {
            return (string) __('pages.song.mix_synth');
        }

        return implode('; ', count($sung) < count($cards) ? [...$sung, (string) __('pages.song.mix_rest_synth')] : $sung);
    }

    /**
     * A note's name in scientific pitch (60 is C4).
     */
    /** A note's name as the page's language names notes ("Eb4", "Mi♭4" in Spanish: NoteNames). */
    public static function note(int $midi): string
    {
        return NoteNames::name(self::letterName($midi));
    }

    /** A note's letter name ("Eb4"), whatever the page's language: for what's kept or read by code, not shown. */
    public static function letterName(int $midi): string
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
