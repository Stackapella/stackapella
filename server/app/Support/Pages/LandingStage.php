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
 * The landing's stage (the home page, pages/landing.blade.php; Hillel, 2026-11-16: "make next-final the main home
 * page"): one full-screen stage, the showreel's four lanes of the landing's song, played by the song page's own player
 * (js/public/mixer.js) from the song page's own data. Nothing here is written by hand: the notes, their syllables and
 * the phrases are the template's, the voices are the song's recordings, and the numbers are the ones the site already
 * reads.
 */
final class LandingStage
{
    /** What a lane's way into the app carries, so a part taken from the landing can be told from one taken on a song page. */
    public const REF = 'landing-lane';

    /** The candidates' old addresses (the four tried on 2026-11-15 and the one chosen): each now leads to the home page. */
    public const GONE = ['next-grok', 'next-chatgpt', 'next-gemini', 'next-claude', 'next-final'];

    /** How many times a press sings the song through: the voices join one by one, then all four together. */
    public const PASSES = 2;

    /** The questions the page answers, by their key in pages.landing.faq. */
    public const QUESTIONS = ['free', 'read_music', 'headphones', 'privacy'];

    /**
     * The stage: the player's data (the song page's shape, with the render), each part's lane with every note of the
     * song and its syllable, the sung lines, and where "I sing this" leads for each part. Null without the song.
     *
     * @return array{song: array<string, mixed>, title: string, beats: float, seconds: string, credit: string|null, lanes: list<array{key: string, name: string, n: int, notes: list<array{beat: float, length: float, y: float, text: string}>, trace: string, learn: string}>, phrases: list<array{from: float, to: float, words: list<array{beat: float, text: string}>}>}|null
     */
    private static function lanes(string $ref): ?array
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
                'learn' => Hosts::app('/?'.http_build_query(['song' => $template->slug, 'part' => $key, 'ref' => $ref])),
            ];
        }

        return [
            'song' => $line,
            'title' => (string) $line['title'],
            'beats' => (float) $line['songBeats'],
            'seconds' => (string) $line['length'],
            'credit' => self::sungBy($audio['credit'] ?? null, $names),
            // The recording's whole credit, as written (English), and the song's part of it (words, tune, arrangement)
            // without the singers' sentence, which `credit` says first, on its own line.
            'credited' => $audio['credit'] ?? null,
            'song_credit' => self::songCredit($audio['credit'] ?? null),
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
     * The song's credit (words, tune, arrangement) from the recording's whole credit, its "... sung by ..." sentence left
     * out (the singers' line says that). Null when there is nothing else.
     */
    private static function songCredit(?string $credit): ?string
    {
        if (! is_string($credit)) {
            return null;
        }
        $song = trim((string) preg_replace('/\s*[^.]*?\s+sung by\s+[^.]+\.?/', '', $credit));

        return $song === '' ? null : $song;
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

        return (string) __('landing.sung_by', ['parts' => implode((string) __('landing.and'), $parts), 'name' => trim($m[2])]);
    }

    /**
     * The four questions, each with its answer, from the landing's own (their numbers filled in as there).
     *
     * @param  array{month: string, year: string, yearAsMonth: string}  $price
     * @return list<array{0: string, 1: string}>
     */
    public static function questions(int $freeSongs, array $price): array
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

    /**
     * The stage, played as the showreel plays it (Hillel, 2026-11-16: "when you press play, the song plays
     * twice, but the first time it plays each voice is added one by one"): the song's data asks the player
     * (js/public/mixer.js) for two passes and says the beat each voice joins on, `joins` holds the same beats for the
     * page, and `seconds` is the length of both passes.
     *
     * @return array<string, mixed>|null
     */
    public static function stage(): ?array
    {
        $stage = self::lanes(self::REF);
        if ($stage === null) {
            return null;
        }
        $beats = (float) $stage['beats'];
        $joins = self::joins(array_column($stage['lanes'], 'key'), array_column($stage['phrases'], 'from'), $beats);
        $stage['joins'] = $joins;
        $stage['song']['passes'] = self::PASSES;
        $stage['song']['joins'] = $joins;
        // Only the recorded voices sing here, never the app's practice voice (Hillel, 2026-11-16: "only play the real
        // voice audio on the main page, wiat f we have to but show the user porgoress").
        $stage['song']['realOnly'] = true;
        $seconds = (int) round($beats * self::PASSES * 60 / max(1.0, (float) ($stage['song']['tempo'] ?? 80)));
        $stage['seconds'] = sprintf('%d:%02d', intdiv($seconds, 60), $seconds % 60);

        return $stage;
    }

    /**
     * The beat of the first pass each voice joins on: the first voice sings from the start, the second comes in with
     * the second sung line, and so on (the lines' first beats, `$starts`); with fewer lines than voices, the rest share
     * the song evenly. Only beats inside the song.
     *
     * @param  list<string>  $keys
     * @param  list<float>  $starts
     * @return array<string, float>
     */
    public static function joins(array $keys, array $starts, float $beats): array
    {
        $joins = [];
        foreach ($keys as $i => $key) {
            $beat = (float) ($starts[$i] ?? $beats * $i / max(1, count($keys)));
            if ($i > 0 && $beat > 0 && $beat < $beats) {
                $joins[$key] = $beat;
            }
        }

        return $joins;
    }
}
