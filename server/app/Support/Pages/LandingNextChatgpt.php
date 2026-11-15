<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Template;
use App\Support\Http\Hosts;
use App\Support\Languages\LanguageMenu;
use App\Support\Languages\SiteLanguages;
use App\Support\Rights\RightsHere;
use App\Support\Site\Explore;
use App\Support\Site\SiteVisit;
use App\Support\SongAudio\SongAudioFiles;
use Illuminate\Http\Request;

/**
 * /next-chatgpt (and /es/next-chatgpt): a candidate landing page Hillel is testing beside / (2026-11-15: "a full screen
 * immersive experience"), one of several built in parallel. The whole page is one stage: the landing's song in four
 * lanes, played by the song page's own player (js/public/mixer.js), with the story told as full-screen scenes.
 *
 * Kept apart from the real site on purpose: never indexed (the header and the meta), in no sitemap and with no
 * hreflang (it's in no language's list of pages), and its visits counted under its own kind, which no growth total reads
 * (they read SiteVisit::SECTIONS).
 */
final class LandingNextChatgpt
{
    /** This page's own visit count (config counts.kinds), never one of SiteVisit's sections. */
    public const KIND = 'sv_next_chatgpt';

    public const PATH = '/next-chatgpt';

    /** The source its links into the app carry. */
    public const REF = 'next-chatgpt';

    /** The lane the story's camera moves in on until the visitor hears another alone. */
    public const FOCUS = 'alto';

    /** The languages the page is written in: English, and each of these under its prefix. */
    public const LANGUAGES = ['es'];

    /**
     * The stage: the player's data (the song page's: the opening line's notes, the rest fetched by the player, the
     * render where there is one), and every part's notes for the whole song as the lanes draw them, each note placed in
     * its own part's range (`y`: 0 its highest note, 1 its lowest) with its syllable. Null when the song isn't in the
     * library, or its music is withheld in the visitor's country.
     *
     * @return array{song: array<string, mixed>, lanes: array<int, array{key: string, name: string, color: int, real: bool, gems: array<int, array{beat: float, length: float, y: float, text: string}>}>, lines: array<int, array<int, array{beat: float, length: float, text: string, joins: bool}>>, beats: float, length: string, credit: string, learn: array<string, array{name: string, href: string}>}|null
     */
    public static function stage(Request $request): ?array
    {
        $template = Template::query()->official()->with('currentVersion')->where('slug', Landing::HERO_SONG)->first();
        if ($template === null || app(RightsHere::class)->withheld($template, RightsHere::country($request)) !== null) {
            return null;
        }

        $song = Landing::line($template);
        if ($song === null) {
            return null;
        }
        $audio = SongAudioFiles::forPage($template);
        $song['audio'] = $audio === null ? null : ['m4a' => $audio['m4a'], 'mp3' => $audio['mp3']];

        // Every note of the song, for the lanes (the player's data carries only the opening line).
        $beats = (float) $song['songBeats'];
        $whole = Landing::line($template, $beats) ?? $song;
        $guides = app(SongParts::class)->guides($template);

        // Each note's own syllable, as its singer sings it (the bass holds "oo" under the tune's words); only on a note
        // long enough to carry one.
        $own = [];
        foreach (array_values(array_filter((array) (((array) ($template->currentVersion->data ?? []))['parts'] ?? []), 'is_array')) as $part) {
            foreach (array_filter((array) ($part['guide']['notes'] ?? []), 'is_array') as $note) {
                if (is_string($note['lyric'] ?? null)) {
                    $own[(string) ($part['key'] ?? '')][(string) (float) ($note['beat'] ?? 0)] = rtrim($note['lyric'], '-');
                }
            }
        }

        $lanes = [];
        foreach ($whole['parts'] as $part) {
            $pitches = array_column($part['notes'], 'pitch');
            $high = $pitches === [] ? 0 : max($pitches);
            $span = $pitches === [] ? 1 : max(1, $high - min($pitches));
            $syllables = $own[$part['key']] ?? [];
            $lanes[] = [
                'key' => $part['key'],
                'name' => $part['name'],
                'color' => $part['color'],
                'real' => ($guides[$part['key']]['realVoice'] ?? false) === true,
                'gems' => array_map(static fn (array $note): array => [
                    'beat' => $note['beat'],
                    'length' => $note['length'],
                    'y' => round(($high - $note['pitch']) / $span, 3),
                    'text' => $note['length'] >= 1 ? (string) ($syllables[(string) $note['beat']] ?? '') : '',
                ], $part['notes']),
            ];
        }

        // The words by sung line, each word lit until the next one starts.
        $words = array_values($whole['words']);
        $lines = [];
        foreach ($words as $n => $word) {
            if ($word['br'] || $lines === []) {
                $lines[] = [];
            }
            $next = (float) ($words[$n + 1]['beat'] ?? $beats);
            $lines[array_key_last($lines)][] = ['beat' => $word['beat'], 'length' => max(0.25, $next - $word['beat']), 'text' => $word['text'], 'joins' => $word['joins']];
        }

        $learn = [];
        foreach ($whole['parts'] as $part) {
            $learn[$part['key']] = [
                'name' => mb_strtolower($part['name']),
                'href' => Hosts::app('/?'.http_build_query(['song' => $template->slug, 'part' => $part['key'], 'ref' => self::REF])),
            ];
        }

        return [
            'song' => $song,
            'lanes' => $lanes,
            'lines' => $lines,
            'beats' => $beats,
            'length' => (string) $song['length'],
            'credit' => SongPage::mixCredit($whole['parts'], $guides),
            'learn' => $learn,
        ];
    }

    /**
     * The page's links: into the app (lesson 1, the app itself), and the site's pages in the page's language where
     * that language has them.
     *
     * @return array{start: string, app: string, songs: string, courses: string, tools: string, pricing: string, help: string, choirs: string, privacy: string, terms: string}
     */
    public static function links(string $language): array
    {
        $site = static fn (string $path): string => $language !== 'en' && SiteLanguages::serves($language, $path) ? '/'.$language.$path : $path;
        $landing = Landing::links(self::REF);

        return [
            'start' => $landing['start'],
            'app' => $landing['app'],
            'songs' => $site('/songs'),
            'courses' => $site('/courses'),
            'tools' => $site('/tools'),
            'pricing' => $site('/pricing'),
            'help' => $site('/help'),
            'choirs' => $site('/for/choir-directors'),
            'privacy' => '/legal/privacy',
            'terms' => '/legal/terms',
        ];
    }

    /**
     * The language menu: this page in each language it's written in.
     *
     * @return list<array{code: string, name: string, url: string, current: bool}>
     */
    public static function languages(string $language): array
    {
        $names = LanguageMenu::NAMES;
        $choices = [];
        foreach (['en', ...self::LANGUAGES] as $code) {
            $choices[] = ['code' => $code, 'name' => $names[$code], 'url' => ($code === 'en' ? '' : '/'.$code).self::PATH, 'current' => $code === $language];
        }

        return $choices;
    }

    /** This page load's count, or null where the site counts none (counts off, or the app's host). */
    public static function visit(Request $request): ?string
    {
        return SiteVisit::kind($request) !== null ? self::KIND : null;
    }

    /**
     * The numbers the page says, read live.
     *
     * @return array{songs: int, courses: int, tools: int}
     */
    public static function counts(): array
    {
        $counts = Explore::counts();

        return ['songs' => (int) ($counts['songs'] ?? 0), 'courses' => (int) ($counts['courses'] ?? 0), 'tools' => (int) ($counts['tools'] ?? 0)];
    }
}
