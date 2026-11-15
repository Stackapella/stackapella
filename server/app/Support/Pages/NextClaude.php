<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Site\Explore;
use App\Support\Site\Teams;
use App\Support\SongAudio\SongAudioFiles;
use Illuminate\Support\Facades\App;

/**
 * /next-claude's data (one of four candidates for the next landing page, Hillel, 2026-11-15: "lets build all 4 so I can
 * experience each of them"): the page is one full-screen stage, the showreel's four lanes of the landing's song, played
 * by the song page's own player (js/public/mixer.js) from the song page's own data. Nothing here is written by hand:
 * the notes, their syllables and the phrases are the template's, the recording is the song's render, and the numbers
 * are the ones the site already reads.
 */
final class NextClaude
{
    /** The page's path, in English; /es/next-claude is its Spanish. */
    public const PATH = '/next-claude';

    /** Where the visitor is said to have come from, on every link into the app. */
    public const REF = 'next-claude';

    /** The questions the page answers, by their key in pages.landing.faq. */
    public const QUESTIONS = ['free', 'read_music', 'headphones', 'privacy'];

    /**
     * The stage: the player's data (the song page's shape, with the render), each part's lane with every note of the
     * song and its syllable, the sung lines, and where "I sing this" leads for each part. Null without the song.
     *
     * @return array{song: array<string, mixed>, title: string, beats: float, seconds: string, credit: string|null, lanes: list<array{key: string, name: string, n: int, notes: list<array{beat: float, length: float, y: float, text: string}>, trace: string, learn: string}>, phrases: list<array{from: float, to: float, words: list<array{beat: float, text: string}>}>}|null
     */
    public static function stage(): ?array
    {
        $template = Template::query()->official()->with('currentVersion')->where('slug', Landing::HERO_SONG)->first();
        $line = $template !== null ? Landing::line($template) : null;
        if ($template === null || $line === null) {
            return null;
        }

        $audio = SongAudioFiles::forPage($template);
        $line['audio'] = $audio === null ? null : ['m4a' => $audio['m4a'], 'mp3' => $audio['mp3']];
        $data = (array) ($template->currentVersion->data ?? []);
        $parts = array_values(array_filter((array) ($data['parts'] ?? []), 'is_array'));
        $names = array_column($line['parts'], 'name', 'key');
        $lanes = [];
        foreach ($parts as $i => $part) {
            $key = (string) ($part['key'] ?? '');
            $low = (int) ($part['range']['low'] ?? 48);
            $high = (int) ($part['range']['high'] ?? 72);
            $notes = [];
            foreach (array_filter((array) ($part['guide']['notes'] ?? []), 'is_array') as $note) {
                $notes[] = [
                    'beat' => (float) ($note['beat'] ?? 0),
                    'length' => (float) ($note['length'] ?? 1),
                    // Where the note sits in its lane: 0 at the part's highest note, 1 at its lowest.
                    'y' => round(($high - (int) ($note['pitch'] ?? $low)) / max(1, $high - $low), 3),
                    'text' => is_string($note['lyric'] ?? null) ? $note['lyric'] : '',
                ];
            }
            $lanes[] = [
                'key' => $key,
                'name' => (string) ($names[$key] ?? $key),
                'n' => $i % 4 + 1,
                'notes' => $notes,
                'trace' => self::trace($notes),
                // The song page's own way into the app for a part (SongPagesController's `learn`).
                'learn' => Hosts::app('/?'.http_build_query(['song' => $template->slug, 'part' => $key, 'ref' => self::REF])),
            ];
        }

        return [
            'song' => $line,
            'title' => (string) $line['title'],
            'beats' => (float) $line['songBeats'],
            'seconds' => (string) $line['length'],
            'credit' => self::sungBy($audio['credit'] ?? null, $names),
            'lanes' => $lanes,
            'phrases' => self::phrases($data, $parts, (float) $line['songBeats']),
        ];
    }

    /**
     * The sung lines: each phrase of the song with the melody's words in it, a word starting on its first syllable.
     *
     * @param  array<string, mixed>  $data
     * @param  list<array<string, mixed>>  $parts
     * @return list<array{from: float, to: float, words: list<array{beat: float, text: string}>}>
     */
    private static function phrases(array $data, array $parts, float $end): array
    {
        $melody = collect($parts)->first(static fn (array $p): bool => ($p['melody'] ?? false) === true) ?? ($parts[0] ?? []);
        $spans = [];
        foreach ((array) ($data['sections'] ?? []) as $section) {
            foreach ((array) (is_array($section) ? ($section['phrases'] ?? []) : []) as $phrase) {
                if (is_array($phrase) && isset($phrase[0], $phrase[1])) {
                    $spans[] = [(float) $phrase[0], (float) $phrase[1]];
                }
            }
        }
        $spans = $spans === [] ? [[0.0, $end]] : $spans;
        $out = [];
        foreach ($spans as [$from, $to]) {
            $words = [];
            $open = null;
            foreach (array_filter((array) ($melody['guide']['notes'] ?? []), 'is_array') as $note) {
                $lyric = $note['lyric'] ?? null;
                $beat = (float) ($note['beat'] ?? 0);
                if (! is_string($lyric) || $lyric === '' || $beat < $from || $beat >= $to) {
                    continue;
                }
                $open ??= ['beat' => $beat, 'text' => ''];
                $open['text'] .= rtrim($lyric, '-');
                if (! str_ends_with($lyric, '-')) {
                    $words[] = $open;
                    $open = null;
                }
            }
            if ($open !== null) {
                $words[] = $open;
            }
            if ($words !== []) {
                $out[] = ['from' => $from, 'to' => $to, 'words' => $words];
            }
        }

        return $out;
    }

    /**
     * A singer's pitch drawn over a lane's notes, as Sing draws it while you sing along: an SVG path in beats across
     * and 0 to 1 down (the lane's own scale), with the small waver of a real voice. A picture of the feature, drawn
     * from the part's own notes.
     *
     * @param  list<array{beat: float, length: float, y: float, text: string}>  $notes
     */
    private static function trace(array $notes): string
    {
        $points = [];
        foreach ($notes as $note) {
            $steps = max(2, (int) round($note['length'] * 6));
            for ($i = 0; $i <= $steps; $i++) {
                $x = $note['beat'] + 0.1 + ($note['length'] - 0.1) * $i / $steps;
                $points[] = sprintf('%.2f %.3f', $x, $note['y'] + 0.05 * sin($x * 9));
            }
        }

        return $points === [] ? '' : 'M'.implode(' L', $points);
    }

    /**
     * The singers' sentence of the render's credit ("Soprano and alto sung by ..."), when it has one: as written in
     * English, and in another language with the parts by their names there (or as written, if it names anything else).
     *
     * @param  array<string, string>  $names
     */
    private static function sungBy(?string $credit, array $names): ?string
    {
        if (! is_string($credit) || preg_match('/([^.]*?)\s+sung by\s+([^.]+)/', $credit, $m) !== 1) {
            return null;
        }
        $sentence = trim($m[0]);
        if (App::getLocale() === 'en') {
            return $sentence;
        }
        $parts = [];
        foreach (preg_split('/\s*,\s*|\s+and\s+/', trim($m[1])) ?: [] as $word) {
            $name = $names[strtolower($word)] ?? null;
            if ($name === null) {
                return $sentence;
            }
            $parts[] = $parts === [] ? $name : mb_strtolower($name);
        }

        return (string) __('next_claude.sung_by', ['parts' => implode((string) __('next_claude.and'), $parts), 'name' => trim($m[2])]);
    }

    /**
     * The four questions, each with its answer, from the landing's own (their numbers filled in as there).
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function questions(int $freeSongs, string $price): array
    {
        $keys = array_keys((array) Teams::words('pages.landing.faq'));
        $all = array_combine($keys, array_slice(Landing::faq($freeSongs, $price), 0, count($keys)));

        return array_values(array_filter(array_map(static fn (string $key): ?array => $all[$key] ?? null, self::QUESTIONS)));
    }

    /**
     * The numbers the scenes say: how many courses, songs and tools there are, as the site's Explore row counts them.
     *
     * @return array{songs: int, courses: int, tools: int}
     */
    public static function counts(): array
    {
        $counts = Explore::counts();

        return ['songs' => (int) ($counts['songs'] ?? 0), 'courses' => (int) ($counts['courses'] ?? 0), 'tools' => (int) ($counts['tools'] ?? 0)];
    }
}
