<?php

declare(strict_types=1);

namespace App\Support\Pages;

/**
 * A song's line meanings on its page (the courses lead's meaning/<slug>.json, marketing's words band): each sung line of
 * the song as it is today, by stanza, with what it means in the page's language. A meaning is matched by its sung text,
 * never by version, so an edit to a line drops only that line's meaning (stale), never shows a wrong one.
 */
final class SongMeanings
{
    /**
     * The song's sung lines, by section and phrase, as the courses lead's mklines.py reads them: the melody's lyrics from
     * each phrase's start beat up to its end, a syllable ending "-" joined to the next.
     *
     * @param  array<string, mixed>  $data  the template's
     * @return list<list<string>>
     */
    public static function sungLines(array $data): array
    {
        $parts = array_values(array_filter((array) ($data['parts'] ?? []), 'is_array'));
        $melody = collect($parts)->first(fn (array $p): bool => ($p['melody'] ?? false) === true) ?? ($parts[0] ?? []);
        $notes = array_values(array_filter((array) ($melody['guide']['notes'] ?? []), 'is_array'));
        $sections = [];
        foreach (array_values(array_filter((array) ($data['sections'] ?? []), 'is_array')) as $section) {
            $lines = [];
            foreach ((array) ($section['phrases'] ?? []) as $phrase) {
                [$from, $to] = array_map('floatval', array_values((array) $phrase) + [0, 0]);
                $text = '';
                foreach ($notes as $note) {
                    $beat = (float) ($note['beat'] ?? -1);
                    $lyric = $note['lyric'] ?? null;
                    if ($beat >= $from && $beat < $to && is_string($lyric) && $lyric !== '') {
                        $text .= str_ends_with($lyric, '-') ? substr($lyric, 0, -1) : $lyric.' ';
                    }
                }
                $lines[] = trim($text);
            }
            $sections[] = $lines;
        }

        return $sections;
    }

    /**
     * The page's lines: [stanza => [{sung, meaning?}]], every sung line in order, meaning in `$language` only where the
     * file has one for that very sung text; null when no line has one (no file, none in this language, all stale).
     *
     * @param  array<string, mixed>  $data
     * @return array{sungLanguage: string, lines: list<list<array{sung: string, meaning?: string}>>}|null
     */
    public static function forPage(string $slug, array $data, string $language): ?array
    {
        $file = self::file($slug);
        if ($file === null) {
            return null;
        }
        $meanings = [];
        foreach ((array) ($file['lines'] ?? []) as $line) {
            if (is_array($line) && is_string($line['sung'] ?? null) && is_string($line[$language] ?? null) && trim($line[$language]) !== '') {
                $meanings[self::key($line['sung'])] ??= trim($line[$language]);
            }
        }
        if ($meanings === []) {
            return null;
        }

        $found = false;
        $stanzas = [];
        foreach (self::sungLines($data) as $lines) {
            $stanza = [];
            foreach ($lines as $sung) {
                if ($sung === '') {
                    continue;
                }
                $meaning = $meanings[self::key($sung)] ?? null;
                $found = $found || $meaning !== null;
                $stanza[] = ['sung' => $sung, ...($meaning !== null ? ['meaning' => $meaning] : [])];
            }
            if ($stanza !== []) {
                $stanzas[] = $stanza;
            }
        }

        return $found ? ['sungLanguage' => (string) ($file['sungLanguage'] ?? ''), 'lines' => $stanzas] : null;
    }

    /**
     * The story's full words with each line's meaning filled from the page's lines where the story gives none of its own.
     *
     * @param  array<string, mixed>  $story  SongStories::story()
     * @param  list<list<array{sung: string, meaning?: string}>>|null  $lines  forPage()'s
     * @return array<string, mixed>
     */
    public static function fillFullWords(array $story, ?array $lines): array
    {
        if (! is_array($story['fullWords'] ?? null) || $lines === null) {
            return $story;
        }
        $meanings = [];
        foreach ($lines as $stanza) {
            foreach ($stanza as $line) {
                if (isset($line['meaning'])) {
                    $meanings[self::key($line['sung'])] ??= $line['meaning'];
                }
            }
        }
        foreach ($story['fullWords']['stanzas'] as $s => $stanza) {
            foreach ($stanza['lines'] as $l => $line) {
                if (! isset($line['meaning']) && isset($meanings[self::key((string) $line['sung'])])) {
                    $story['fullWords']['stanzas'][$s]['lines'][$l]['meaning'] = $meanings[self::key((string) $line['sung'])];
                }
            }
        }

        return $story;
    }

    /** A sung line compared as written, ignoring case, spacing and the punctuation around it. */
    private static function key(string $sung): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $sung), " \t.,;:!?¡¿\"'“”‘’()-"));
    }

    /** @return array<string, mixed>|null a small file, read when a page needs it */
    private static function file(string $slug): ?array
    {
        $path = rtrim((string) config('content.meanings'), '/').'/'.$slug.'.json';
        if (preg_match('/^[a-z0-9-]+$/', $slug) !== 1 || ! is_file($path)) {
            return null;
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
