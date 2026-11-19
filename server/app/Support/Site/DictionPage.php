<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Support\Pages\Lessons;
use Illuminate\Support\HtmlString;

/**
 * The Diction for singers page's pieces (site/courses/diction, Hillel's design of 2026-11-18), so the view stays plain
 * markup: the symbols in IPA that mark its sections (the course's own English: r dropped before a consonant, as unit 1
 * teaches), the vowel map's places, the beat deck's timeline, the strip of the hero's words as sung, and lesson 1's line
 * of Amazing Grace from the song's own template.
 *
 * The line: each sung syllable of "Amazing grace, how sweet the sound" with its sound, stressed when its note falls on a
 * bar's first beat (the template's time signature and pickup), so the marks follow the music; "the" is the light small
 * word the lesson names ("the" as "thuh"), ðə before a consonant. Empty when the template's line has changed from the one
 * these sounds were written for, so the page never shows the wrong sound under a word.
 */
final class DictionPage
{
    /** Each aim's sound: a vowel held, the t before the beat line, the d on it, an m held on the note. */
    public const AIMS = ['ɑː', 't|ɑː', 'ɑː|d', 'mː'];

    /** Each unit's: a vowel, a consonant before the beat, a hum, a word heard. */
    public const UNITS = ['ɑː', 't|ɑː', 'mː', 'ˈhɜːd'];

    /** The course's climb in sounds: a vowel, a consonant before it, one sung on the note, then whole words. */
    public const CLIMB = ['ɑː', 'tɑː', 'mɑː', 'ˈɛv.ɹi ˈwɜːd'];

    /** Each margin note's mark: the share of errors in consonants, the diphthong of "lie", "put it up", the share who said "very important". */
    public const NOTES = ['¾', 'aɪ', 'pʊt ɪt ʌp', '60%'];

    /** Garcia's sounds that flow on: the voiced ones, which can hold a note, and the hissing ones, air only. */
    public const VOICED = ['m', 'n', 'l', 'ɹ'];

    public const AIR = ['s', 'ʃ'];

    /** The buzz and the hiss of "Why sung words blur". */
    public const BUZZ = ['[z]', '[s]'];

    /** The beat deck's "tah", four times. */
    public const TAH = '/tɑː tɑː tɑː tɑː/';

    /** The vowels the demo and the map draw: F1 and F2 (Peterson and Barney's averages for men) and the symbol. */
    public const VOWELS = ['ee' => [270, 2290, 'i'], 'ah' => [730, 1090, 'ɑ'], 'oo' => [300, 870, 'u']];

    /** The e of "head": roughly 500 and 1800 Hz. */
    public const HEAD = [500, 1800, 'ɛ'];

    /** The beat deck: four beats 0.6 s apart from 0.3 s on a 2.4 s timeline, the t 0.09 s and the vowel 0.32 s long. */
    private const SPAN = 2.4;

    private const SOUNDS = ['a' => 'ə', 'maz' => 'meɪ', 'ing' => 'zɪŋ', 'grace' => 'ɡɹeɪs', 'how' => 'haʊ', 'sweet' => 'swiːt', 'the' => 'ðə', 'sound' => 'saʊnd'];

    private const LIGHT = ['the'];

    /** A marked line from the words: {...} in colour (the vowels, a key word), | a line break; every piece escaped. */
    public static function marked(string $text, string $class): HtmlString
    {
        $html = '';
        foreach (preg_split('/(\{[^}]*\})/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [] as $part) {
            $html .= str_starts_with($part, '{') ? '<span class="'.e($class).'">'.e(trim($part, '{}')).'</span>' : e($part);
        }

        return new HtmlString(str_replace('|', '<br>', $html));
    }

    /**
     * The hero's strip from the words ("{ɛ} v ɹ {i} ..."): the consonants before the first vowel, then each vowel with
     * the consonants after it.
     *
     * @return array{lead: list<string>, beats: list<array{vowel: string, after: list<string>}>}
     */
    public static function strip(string $words): array
    {
        $lead = [];
        $beats = [];
        $vowel = null;
        $after = [];
        foreach (preg_split('/\s+/u', trim($words)) ?: [] as $token) {
            if (str_starts_with($token, '{')) {
                if ($vowel !== null) {
                    $beats[] = ['vowel' => $vowel, 'after' => $after];
                }
                $vowel = trim($token, '{}');
                $after = [];
            } elseif ($vowel === null) {
                $lead[] = $token;
            } else {
                $after[] = $token;
            }
        }
        if ($vowel !== null) {
            $beats[] = ['vowel' => $vowel, 'after' => $after];
        }

        return ['lead' => $lead, 'beats' => $beats];
    }

    /**
     * The vowel map in its 480 by 290 drawing: F2 runs right to left (2400 to 800 Hz), F1 top to bottom (200 to 800 Hz),
     * as phoneticians draw it; each vowel's point, its symbol's place, and the triangle through them.
     *
     * @return array{xs: list<array{0: float, 1: string}>, ys: list<array{0: float, 1: int}>, points: array<string, array{x: float, y: float, ipa: string, lx: float, ly: float}>, head: array{x: float, y: float, ipa: string}, triangle: string}
     */
    public static function map(): array
    {
        $x = static fn (float $f2): float => round(60 + (2400 - $f2) / 1600 * 380, 1);
        $y = static fn (float $f1): float => round(20 + ($f1 - 200) / 600 * 240, 1);
        // ee names itself under its point, oo under and to the left, ah to its right.
        $label = ['ee' => [12, 34], 'oo' => [-24, 36], 'ah' => [16, -6]];
        $points = [];
        foreach (self::VOWELS as $vowel => [$f1, $f2, $ipa]) {
            $points[$vowel] = ['x' => $x($f2), 'y' => $y($f1), 'ipa' => $ipa, 'lx' => $x($f2) + $label[$vowel][0], 'ly' => $y($f1) + $label[$vowel][1]];
        }

        return [
            'xs' => array_map(static fn (int $f2): array => [$x($f2), number_format($f2)], [2400, 2000, 1600, 1200, 800]),
            'ys' => array_map(static fn (int $f1): array => [$y($f1), $f1], [200, 400, 600, 800]),
            'points' => $points,
            'head' => ['x' => $x(self::HEAD[1]), 'y' => $y(self::HEAD[0]), 'ipa' => self::HEAD[2]],
            'triangle' => sprintf('M%s %s L%s %s L%s %s Z', $x(self::VOWELS['ee'][1]), $y(self::VOWELS['ee'][0]), $x(self::VOWELS['oo'][1]), $y(self::VOWELS['oo'][0]), $x(self::VOWELS['ah'][1]), $y(self::VOWELS['ah'][0])),
        ];
    }

    /**
     * The beat deck's two lanes in percent of the timeline: each beat's line, the t (on it in A, just before it in B)
     * and the vowel after the t. js/public/diction.js plays the same times.
     *
     * @return array<string, list<array{beat: float, t: float, v: float}>>
     */
    public static function lanes(): array
    {
        $at = static fn (float $s): float => round($s / self::SPAN * 100, 3);
        $lanes = [];
        foreach (['a', 'b'] as $lane) {
            foreach (range(0, 3) as $i) {
                $beat = 0.3 + $i * 0.6;
                $t = $lane === 'a' ? $beat : $beat - 0.09;
                $lanes[$lane][] = ['beat' => $at($beat), 't' => $at($t), 'v' => $at($t + 0.09)];
            }
        }

        return $lanes;
    }

    /**
     * The t's and the vowel's widths on the beat deck's timeline, in percent.
     *
     * @return array{t: float, v: float}
     */
    public static function widths(): array
    {
        return ['t' => round(0.09 / self::SPAN * 100, 3), 'v' => round(0.32 / self::SPAN * 100, 3)];
    }

    /**
     * A course's lessons that have their own page, their addresses by name.
     *
     * @return array<string, string>
     */
    public static function lessonPages(string $course): array
    {
        $pages = [];
        foreach (Lessons::pages($course) as $page) {
            if (($page['kind'] ?? '') === 'lesson' && is_string($page['name'] ?? null) && is_string($page['url'] ?? null)) {
                $pages[$page['name']] = $page['url'];
            }
        }

        return $pages;
    }

    /**
     * The questions as structured data for search, as site.courses._faq gives them.
     *
     * @param  array<int, array{0: string, 1: string}>  $faq
     * @return list<array<string, mixed>>
     */
    public static function faqData(array $faq): array
    {
        return [['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(static fn (array $qa): array => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]], array_values($faq))]];
    }

    /**
     * Lesson 1's line of Amazing Grace, a syllable at a time.
     *
     * @return list<array{word: string, ipa: string, stressed: bool, light: bool, joined: bool}>
     */
    public static function grace(): array
    {
        $path = rtrim((string) config('app.web_path'), '/').'/templates/amazing-grace.json';
        $song = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        if (! is_array($song)) {
            return [];
        }

        $bar = (int) explode('/', (string) ($song['timeSignature'] ?? '4/4'))[0];
        $pickup = (float) ($song['pickupBeats'] ?? 0);
        $notes = [];
        foreach ((array) ($song['parts'] ?? []) as $part) {
            if (is_array($part) && strtolower((string) ($part['name'] ?? '')) === 'soprano') {
                $notes = (array) ($part['guide']['notes'] ?? []);
                break;
            }
        }

        $line = [];
        foreach ($notes as $note) {
            if (! is_array($note) || ! is_string($note['lyric'] ?? null) || $note['lyric'] === '') {
                continue;
            }
            $lyric = $note['lyric'];
            $key = strtolower(trim($lyric, ' -,.;:!?'));
            if (! isset(self::SOUNDS[$key]) || $bar < 1) {
                return [];
            }
            $stressed = fmod((float) ($note['beat'] ?? 0) - $pickup, (float) $bar) === 0.0;
            $line[] = [
                'word' => rtrim($lyric, '-'),
                'ipa' => ($stressed ? 'ˈ' : '').self::SOUNDS[$key],
                'stressed' => $stressed,
                'light' => in_array($key, self::LIGHT, true),
                'joined' => str_ends_with($lyric, '-'),
            ];
            if ($key === 'sound') {
                return count($line) === count(self::SOUNDS) ? $line : [];
            }
        }

        return [];
    }

    /**
     * The whole line in IPA between slashes: syllables of a word joined by a dot, or by the stress mark itself.
     *
     * @param  list<array{word: string, ipa: string, stressed: bool, light: bool, joined: bool}>  $line
     */
    public static function ipa(array $line): string
    {
        $out = '';
        foreach ($line as $i => $syllable) {
            $out .= $syllable['ipa'];
            if ($syllable['joined']) {
                $out .= str_starts_with($line[$i + 1]['ipa'] ?? '', 'ˈ') ? '' : '.';
            } elseif ($i < count($line) - 1) {
                $out .= ' ';
            }
        }

        return $out === '' ? '' : '/'.$out.'/';
    }
}
