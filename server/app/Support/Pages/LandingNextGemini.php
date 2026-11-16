<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Site\Explore;
use App\Support\Site\SitePages;
use App\Support\SongAudio\SongAudioFiles;

/**
 * The landing's next version, the gemini variant (/next-gemini; docs/landing-next-gemini-plan.md): one of the candidates
 * Hillel compares, kept apart from the landing itself. The page is a film in six full-screen scenes round one stage:
 * the hero song's whole verse as four lanes of note gems, placed from the song's own notes (the song page's data,
 * Landing::line) and played by js/public/mixer.js. Nothing here is written by hand: every gem, word and count is read.
 */
final class LandingNextGemini
{
    /** The page's own count (config counts.kinds): never a SiteVisit section, so it stays out of the growth totals. */
    public const VISIT = 'sv_next_gemini';

    public const PATH = '/next-gemini';

    /** The source tag its links into the app carry. */
    public const REF = 'next-gemini';

    /** How far into the song the Sing along scene stands while nothing plays, in beats: the notes before it show as sung. */
    public const LEAD = 6.0;

    /**
     * The stage: the verse for the player (`song`, the mixer's shape, with the render when there is one), each part's
     * lane (`lanes`: its gems with their place in the lane, 0 at the top to 1, and its Learn address), the sung lines
     * (`lines`) and the singers' credit. Null when the song isn't in the library.
     *
     * @return array{song: array<string, mixed>, lanes: list<array{key: string, name: string, n: int, role: string|null, learn: string, gems: list<array{beat: float, length: float, y: float, text: string, early: bool}>, trace: string}>, lines: list<list<array{beat: float, text: string, joins: bool, br: bool}>>, beats: float, credit: string|null}|null
     */
    public static function stage(): ?array
    {
        $template = Template::query()->official()->with('currentVersion')->where('slug', Landing::HERO_SONG)->first();
        $opening = $template !== null ? Landing::line($template) : null;
        if ($template === null || $opening === null) {
            return null;
        }
        // The whole verse in the page, so every note has its gem and a press needs no second fetch.
        $song = Landing::line($template, (float) $opening['songBeats']);
        if ($song === null) {
            return null;
        }
        $audio = SongAudioFiles::forPage($template);
        $song['audio'] = $audio === null ? null : ['m4a' => $audio['m4a'], 'mp3' => $audio['mp3']];

        $lyrics = self::lyrics((array) ($template->currentVersion->data ?? []));
        $lanes = [];
        foreach ($song['parts'] as $i => $part) {
            $lanes[] = [
                'key' => $part['key'],
                'name' => $part['name'],
                'n' => $i % 4 + 1,
                'role' => self::role($part['key']),
                'learn' => Hosts::app('/?'.http_build_query(['song' => $template->slug, 'part' => $part['key'], 'ref' => self::REF])),
                'gems' => $gems = self::gems($part['notes'], $lyrics[$part['key']] ?? []),
                'trace' => self::trace($gems),
            ];
        }

        return [
            'song' => $song,
            'lanes' => $lanes,
            'lines' => Landing::lines($song['words']),
            'beats' => (float) $song['beats'],
            'credit' => $audio['credit'] ?? null,
        ];
    }

    /**
     * The page's links: into the app (lesson 1, the app itself) with the page's tag, and the site's pages in the
     * page's language.
     *
     * @return array<string, string>
     */
    public static function links(string $language): array
    {
        // On this host, as the page itself is: the same pages under the language's prefix (SiteLanguages lists them).
        $site = static fn (string $path): string => $language === 'en' ? $path : '/'.$language.($path === '/' ? '' : $path);
        $app = static fn (string $path): string => Hosts::app($path.(str_contains($path, '?') ? '&' : '?').http_build_query(['ref' => self::REF]));

        return [
            'start' => $app('/learn/1-comfortable'),
            'app' => $app('/'),
            'home' => $site('/'),
            'songs' => $site('/songs'),
            'courses' => $site('/courses'),
            'tools' => $site(SitePages::toolsHome()),
            'pricing' => $site('/pricing'),
            'help' => $site('/help'),
            'privacy' => '/legal/privacy',
            'terms' => '/legal/terms',
            'en' => self::PATH,
            'es' => '/es'.self::PATH,
        ];
    }

    /**
     * The last scene's counts, as the site's Explore row counts them (the open songs, the courses, the listed tools).
     *
     * @return array{songs: int, courses: int, tools: int}
     */
    public static function counts(): array
    {
        $counts = Explore::counts();

        return ['songs' => (int) ($counts['songs'] ?? 0), 'courses' => (int) ($counts['courses'] ?? 0), 'tools' => (int) ($counts['tools'] ?? 0)];
    }

    /**
     * The Record scene's takes: each part's loudness over the verse as bars (0 to 1), from where its own notes sound.
     * A picture of a take, drawn from the song's notes: louder on a note's start, quiet in its rests.
     *
     * @param  list<array{beat: float, length: float, y: float, text: string, early: bool}>  $gems
     * @return list<float>
     */
    public static function wave(array $gems, float $beats, int $bars = 96): array
    {
        $wave = [];
        for ($i = 0; $i < $bars; $i++) {
            $beat = ($i + 0.5) / $bars * $beats;
            $level = 0.06;
            foreach ($gems as $gem) {
                if ($beat >= $gem['beat'] && $beat < $gem['beat'] + $gem['length']) {
                    // A sung note swells and settles; the seed keeps a part's bars the same on every load.
                    $into = ($beat - $gem['beat']) / max(0.25, $gem['length']);
                    $level = 0.42 + 0.4 * sin(M_PI * min(1.0, $into + 0.15)) + (crc32($i.':'.$gem['beat']) % 100) / 100 * 0.18;
                    break;
                }
            }
            $wave[] = round(min(1.0, $level), 2);
        }

        return $wave;
    }

    /**
     * Each part's sung syllables by beat, from the song's data (a part's own words, where the melody's differ).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array<int|string, string>>
     */
    private static function lyrics(array $data): array
    {
        $lyrics = [];
        foreach (array_filter((array) ($data['parts'] ?? []), 'is_array') as $part) {
            foreach (array_filter((array) ($part['guide']['notes'] ?? []), 'is_array') as $note) {
                if (is_string($note['lyric'] ?? null) && $note['lyric'] !== '') {
                    $lyrics[(string) ($part['key'] ?? '')][(string) (float) ($note['beat'] ?? 0)] = $note['lyric'];
                }
            }
        }

        return $lyrics;
    }

    /**
     * A part's gems: each note with its place in the lane by pitch (0 the part's highest note here, 1 its lowest).
     *
     * @param  list<array{beat: float, length: float, pitch: int}>  $notes
     * @param  array<int|string, string>  $lyrics
     * @return list<array{beat: float, length: float, y: float, text: string, early: bool}>
     */
    private static function gems(array $notes, array $lyrics): array
    {
        $pitches = array_column($notes, 'pitch');
        $low = $pitches === [] ? 0 : min($pitches);
        $high = $pitches === [] ? 0 : max($pitches);

        return array_map(static fn (array $note): array => [
            'beat' => $note['beat'],
            'length' => $note['length'],
            'y' => $high === $low ? 0.5 : round(1 - ($note['pitch'] - $low) / ($high - $low), 3),
            'text' => $lyrics[(string) $note['beat']] ?? '',
            'early' => $note['beat'] + $note['length'] <= self::LEAD,
        ], $notes);
    }

    /**
     * The sung pitch line through a lane's gems, as an SVG path in beats across and 0 to 1 down: level along each note,
     * a quick slide into the next.
     *
     * @param  list<array{beat: float, length: float, y: float, text: string, early: bool}>  $gems
     */
    private static function trace(array $gems): string
    {
        $path = '';
        foreach ($gems as $i => $gem) {
            $from = round($gem['beat'] + 0.08, 2);
            $to = round($gem['beat'] + $gem['length'] - 0.06, 2);
            $path .= ($i === 0 ? 'M' : 'L').$from.' '.$gem['y'].'L'.max($from, $to).' '.$gem['y'];
        }

        return $path;
    }

    private static function role(string $key): ?string
    {
        $role = __('pages.landing.hero.roles.'.$key);

        return is_string($role) && $role !== 'pages.landing.hero.roles.'.$key ? $role : null;
    }
}
