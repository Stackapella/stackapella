<?php

declare(strict_types=1);

namespace App\Support\Templates;

/**
 * A song cut to its first phrase (Hillel, 2026-11-15: "Sing the first phrase, then ask"): what a guest's
 * templates.show answer carries for a library song that isn't open to guests whole (grant "phrase").
 *
 * The phrase is the first one, across the sections in order, that the melody starts a note in (the part
 * marked melody, else the first part), so an intro the melody doesn't sing in runs on to the melody's first
 * phrase (frontend2's twin, 2026-11-15). The cut runs from sections[0].phrases[0]'s start to that phrase's
 * end. A note is kept only when it starts before the end, and one that sounds past it is clipped to it;
 * a part with no note in the cut (a round's later voice) is
 * left out, with the order and voicings that name it. Chords and metre changes are kept only before the
 * end; the song builder's record (the whole song's bars) never goes. Everything else is as written, so
 * the cut is a valid template (TemplateRules::problems).
 */
final class FirstPhrase
{
    private const EPSILON = 1e-9;

    /**
     * @param  array<string, mixed>  $data  the whole template
     * @return array<string, mixed>|null the cut, or null when no phrase has a melody note to cut at
     */
    public static function of(array $data): ?array
    {
        $section = $data['sections'][0] ?? null;
        if (! is_array($section)) {
            return null;
        }
        $first = $section['phrases'][0] ?? null;
        $end = self::melodyPhraseEnd($data);

        if (! is_array($first) || ! is_numeric($first[0] ?? null) || $end === null) {
            return null;
        }

        $parts = [];

        foreach ((array) ($data['parts'] ?? []) as $part) {
            if (! is_array($part)) {
                continue;
            }
            $notes = [];

            foreach ((array) ($part['guide']['notes'] ?? []) as $note) {
                $beat = is_array($note) && is_numeric($note['beat'] ?? null) ? (float) $note['beat'] : null;

                // Notes are in order (TemplateRules), so the first at or past the end ends the part.
                if ($beat === null || $beat >= $end - self::EPSILON) {
                    break;
                }
                if ($beat + (float) ($note['length'] ?? 0) > $end + self::EPSILON) {
                    $note['length'] = $end - $beat;
                }
                $notes[] = $note;
            }

            if ($notes !== []) {
                $part['guide']['notes'] = $notes;
                $parts[] = $part;
            }
        }

        if ($parts === []) {
            return null;
        }

        $keys = array_column($parts, 'key');
        // The section and its phrase end where the last note does, when that's before the phrase's own end.
        $stop = min($end, TemplateRules::lengthBeats($parts));
        $before = static fn (mixed $item): bool => is_array($item) && is_numeric($item['beat'] ?? null) && (float) $item['beat'] < $stop - self::EPSILON;

        $data['parts'] = $parts;
        $data['sections'] = [[...$section, 'endBeat' => $stop, 'phrases' => [[$first[0], $stop]]]];

        if (isset($data['order'])) {
            $data['order'] = array_values(array_filter((array) $data['order'], static fn (mixed $key): bool => in_array($key, $keys, true)));
        }
        if (isset($data['voicings'])) {
            $voicings = array_filter((array) $data['voicings'], static fn (mixed $list): bool => is_array($list) && array_diff($list, $keys) === []);
            // An empty list would encode as [], and voicings is an object: none left, none sent.
            if ($voicings === []) {
                unset($data['voicings']);
            } else {
                $data['voicings'] = $voicings;
            }
        }
        if (isset($data['chords'])) {
            $data['chords'] = array_values(array_filter((array) $data['chords'], $before));
        }
        if (isset($data['meters'])) {
            $meters = array_values(array_filter((array) $data['meters'], $before));
            if ($meters === []) {
                unset($data['meters']);
            } else {
                $data['meters'] = $meters;
            }
        }
        unset($data['builder']);

        return $data;
    }

    /**
     * The end of the first phrase (the sections in order, each one's phrases in order) the melody starts a note in.
     *
     * @param  array<string, mixed>  $data
     */
    private static function melodyPhraseEnd(array $data): ?float
    {
        $parts = array_values(array_filter((array) ($data['parts'] ?? []), 'is_array'));
        $melody = collect($parts)->first(static fn (array $part): bool => ($part['melody'] ?? false) === true) ?? ($parts[0] ?? null);
        $beats = array_values(array_filter(array_map(
            static fn (mixed $note): ?float => is_array($note) && is_numeric($note['beat'] ?? null) ? (float) $note['beat'] : null,
            (array) ($melody['guide']['notes'] ?? []),
        ), static fn (?float $beat): bool => $beat !== null));

        foreach ((array) ($data['sections'] ?? []) as $section) {
            foreach ((array) (is_array($section) ? ($section['phrases'] ?? []) : []) as $phrase) {
                if (! is_array($phrase) || ! is_numeric($phrase[0] ?? null) || ! is_numeric($phrase[1] ?? null)) {
                    continue;
                }
                [$from, $to] = [(float) $phrase[0], (float) $phrase[1]];

                foreach ($beats as $beat) {
                    if ($beat >= $from - self::EPSILON && $beat < $to - self::EPSILON) {
                        return $to;
                    }
                }
            }
        }

        return null;
    }
}
