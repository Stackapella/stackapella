<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Template;
use App\Support\SongAudio\SongAudioFiles;

/**
 * The combined landing's stage (/next-grok, docs/landing-next-grok-plan.md; a candidate Hillel is comparing): the
 * hero song whole, in the shape js/public/mixer.js plays (Landing::line), with where each note's gem sits on its
 * lane. Everything drawn comes from the song's own notes and words: nothing here is written by hand.
 */
final class LandingNextGrok
{
    /** The page's path, in English; its Spanish is /es/<PATH>. */
    public const PATH = 'next-grok';

    /** How many beats of the song a lane shows at once on a wide screen (a page of the roll). */
    public const PAGE_BEATS = 24;

    /** How many beats of words the stage shows at once. */
    private const PHRASE_BEATS = 12;

    /**
     * The song, every beat of it in the page, with its recording when there is one of this version.
     *
     * @return array<string, mixed>|null
     */
    public static function song(): ?array
    {
        $template = Template::query()->official()->with('currentVersion')->where('slug', Landing::HERO_SONG)->first();
        $whole = $template !== null ? Landing::line($template) : null;
        if ($template === null || $whole === null) {
            return null;
        }
        // Its last beat as the line's end: the player has the whole song's notes, so nothing is fetched to light them.
        $song = Landing::line($template, (float) $whole['songBeats']) ?? $whole;
        $audio = SongAudioFiles::forPage($template);
        $song['audio'] = $audio === null ? null : ['m4a' => $audio['m4a'], 'mp3' => $audio['mp3']];
        $song['credit'] = $audio['credit'] ?? null;

        return $song;
    }

    /**
     * A part's notes as gems on its lane: where each starts and how long it is (fractions of the song), how high it
     * sits in the part's own range (0 at its top note, 1 at its lowest), and the syllable it carries: the word that
     * starts on its beat, for a part that sings the words; a part whose rhythm isn't the tune's shows none.
     *
     * @param  array<string, mixed>  $part
     * @param  array<string, mixed>  $song
     * @return list<array{beat: float, length: float, left: float, width: float, top: float, text: string}>
     */
    public static function gems(array $part, array $song): array
    {
        $beats = max(1.0, (float) $song['beats']);
        $span = max(1, (int) $part['range']['high'] - (int) $part['range']['low']);
        $words = [];
        foreach ((array) $song['words'] as $word) {
            $words[(string) (float) $word['beat']] = rtrim((string) $word['text'], ',.;:!?').($word['joins'] ? '-' : '');
        }
        $notes = array_values((array) $part['notes']);
        // A part that sings the words has about a note a syllable (the bass's long "oo" notes are half as many).
        $sings = $words !== [] && count($notes) / count($words) >= 0.75;

        return array_map(static fn (array $note): array => [
            'beat' => (float) $note['beat'],
            'length' => (float) $note['length'],
            'left' => round((float) $note['beat'] / $beats * 100, 3),
            'width' => round((float) $note['length'] / $beats * 100, 3),
            'top' => round(min(1.0, max(0.0, ((int) $part['range']['high'] - (int) $note['pitch']) / $span)), 3),
            'text' => $sings && (float) $note['length'] >= 1 ? ($words[(string) (float) $note['beat']] ?? '') : '',
        ], $notes);
    }

    /**
     * The words a few beats at a time, each sung line cut where a word starts after PHRASE_BEATS of it, so the stage
     * shows a short phrase large.
     *
     * @param  list<array{beat: float, text: string, joins: bool, br: bool}>  $words
     * @return list<list<array{beat: float, text: string, joins: bool, br: bool}>>
     */
    public static function phrases(array $words): array
    {
        $phrases = [];
        foreach (Landing::lines($words) as $line) {
            $start = (float) ($line[0]['beat'] ?? 0);
            $phrase = [];
            $joined = false;
            foreach ($line as $word) {
                // Never between the syllables of one word.
                if ($phrase !== [] && ! $joined && (float) $word['beat'] - $start >= self::PHRASE_BEATS) {
                    $phrases[] = $phrase;
                    $phrase = [];
                    $start = (float) $word['beat'];
                }
                $phrase[] = $word;
                $joined = (bool) $word['joins'];
            }
            if ($phrase !== []) {
                $phrases[] = $phrase;
            }
        }

        return $phrases;
    }

    /**
     * A sung pitch line over a part's first notes (up to `until` beats), as an SVG path in a 0 to 100 box the size of
     * the lane's roll: the picture of singing along, stepping from note to note as a voice does.
     *
     * @param  array<string, mixed>  $part
     * @param  array<string, mixed>  $song
     */
    public static function trace(array $part, array $song, float $until): string
    {
        $path = '';
        foreach (self::gems($part, $song) as $gem) {
            if ($gem['beat'] >= $until) {
                break;
            }
            $y = round(14 + 72 * $gem['top'], 2);
            $from = $gem['left'];
            $to = round(min($until, $gem['beat'] + $gem['length']) / max(1.0, (float) $song['beats']) * 100, 3);
            $path .= ($path === '' ? 'M' : 'L').$from.' '.$y.'L'.$to.' '.$y;
        }

        return $path;
    }
}
