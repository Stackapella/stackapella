<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Models\Template;
use App\Support\Languages\SongWords;

/**
 * Hear yourself as a choir's stage (/your-choir; Hillel, 2026-11-20: "Something immersive to experience one real voice
 * becoming 2 fake ones"): one singer's real, published guide on one lane, then the AI guides her own AI voice sings on
 * a song she never sang, the tune and the harmony, then (when all four are there) the whole choir. Built only from the
 * demo's own voices (VoiceDemo::cards(), which keeps its gating: AI voices on, a consented and credited voice, every
 * guide published), so nothing here is ever drawn or made up: no demo, no stage.
 *
 * One play is one timeline on the audio clock (js/public/your-choir.js): the real guide from its start, a breath of
 * silence, then the AI song, its soprano and alto from its first note and, with four parts, the tenor and bass joining
 * at the phrase nearest its middle (a scheduled gain ramp, every voice started together, as the home page joins them).
 */
final class YourChoirStage
{
    /**
     * The AI landing's address in each language (Hillel, 2026-11-24, after an SEO check: /ai-harmony-singing, and its
     * Spanish page its own words, /es/armonias-con-ia): the one place they're named. The route, its Spanish twin
     * (SiteLanguages::ownPaths), the 301s from its earlier addresses, the sitemap, hreflang and the tests all read it.
     *
     * @var array{en: string, es: string}
     */
    public const SLUGS = ['en' => 'ai-harmony-singing', 'es' => 'armonias-con-ia'];

    /** The page's link cards, web/icons/og/<card>.png and es-<card>.png (server/tool/link_cards.py draws them). */
    public const CARD = 'ai-harmony-singing';

    /** The silence between the real song and the AI one (seconds). */
    public const GAP = 1.6;

    /**
     * The picture's song: the one most singers make their voice with and get as their free choir (Happy Birthday, the
     * first of the four tunes offered; the page's words never say it's the only one).
     */
    public const PICTURE_SONG = 'happy-birthday';

    public function __construct(private readonly VoiceDemo $demo) {}

    /**
     * @return array{real: array<string, mixed>, ai: array<string, mixed>, four: bool, seconds: float, length: string, play: array<string, mixed>}|null
     */
    public function stage(): ?array
    {
        $cards = collect($this->demo->cards())->keyBy('kind');
        $real = $cards->get('real');
        $ai = $cards->get('ai');
        if (! is_array($real) || ! is_array($ai) || $real['voices'] === [] || $ai['voices'] === []) {
            return null;
        }

        $config = (array) config('site.voice_demo');
        $realVoice = $real['voices'][0];
        $name = (string) ($realVoice['credit'] ?? '');
        $basedOn = (string) ($ai['voices'][0]['aiBasedOn'] ?? '');
        if ($name === '' || $basedOn === '') {
            return null;
        }

        $realSpb = 60 / max(1.0, (float) $real['tempo']);
        $aiSpb = 60 / max(1.0, (float) $ai['tempo']);
        $realEnd = self::end([$realVoice]);
        $aiStart = round($realEnd + self::GAP, 3);
        $aiLanes = array_map(static fn (array $voice): array => self::lane($voice), $ai['voices']);
        $four = count($aiLanes) >= 4;
        // With four parts, the tenor and bass come in at the start of the soprano's phrase nearest the song's middle.
        $join = $four ? self::middlePhrase($ai['voices'][0]['notes'] ?? []) : 0.0;
        $joins = [];
        foreach ($aiLanes as $lane) {
            $joins[$lane['part']] = $four && in_array($lane['part'], ['tenor', 'bass'], true) ? $join : 0.0;
        }
        $seconds = round($aiStart + self::end($ai['voices']), 3);

        return [
            'real' => [
                'name' => $name,
                'song' => self::title((string) ($config['real']['song'] ?? '')),
                'lane' => self::lane($realVoice),
            ],
            'ai' => [
                'name' => $basedOn,
                'song' => self::title((string) ($config['ai']['song'] ?? '')),
                'lanes' => $aiLanes,
                'join' => $join,
            ],
            'four' => $four,
            'seconds' => $seconds,
            'length' => self::clock($seconds),
            // What the player reads (js/public/your-choir.js): each segment's start on the timeline, its beat's length and
            // its voices as the app serves them (with their notes, so their breaths are softened), and the joins.
            'play' => [
                'seconds' => $seconds,
                'segments' => [
                    ['key' => 'real', 'start' => 0.0, 'spb' => round($realSpb, 5), 'voices' => [self::voice($realVoice, 0.0)]],
                    ['key' => 'ai', 'start' => $aiStart, 'spb' => round($aiSpb, 5), 'voices' => array_map(static fn (array $voice): array => self::voice($voice, $joins[(string) ($voice['part'] ?? '')] ?? 0.0), $ai['voices'])],
                ],
            ],
        ];
    }

    /**
     * A song's tune, its opening line or two, as a quiet lane of notes (the library on /your-choir shows its songs this
     * way, never as photos: Hillel, 2026-11-20): a part's notes (`key`, the tune's by default) up to the first line that
     * starts at or past `beats`, each with its place (0 at the highest, 1 at the lowest) and its length, from its first
     * note.
     *
     * @return array{beats: float, notes: list<array{beat: float, length: float, y: float}>}
     */
    public static function tune(Template $template, float $beats = 12.0, string $key = 'soprano'): array
    {
        $parts = array_values(array_filter((array) (((array) $template->currentVersion?->data)['parts'] ?? []), 'is_array'));
        $part = collect($parts)->firstWhere('key', $key) ?? ($parts[0] ?? []);
        $raw = array_values(array_filter((array) ($part['guide']['notes'] ?? []), static fn (mixed $n): bool => is_array($n) && isset($n['pitch'])));
        if ($raw === []) {
            return ['beats' => 0.0, 'notes' => []];
        }
        $from = (float) ($raw[0]['beat'] ?? 0);
        $line = [];
        foreach ($raw as $note) {
            $at = (float) ($note['beat'] ?? 0) - $from;
            if ($at >= $beats && ($note['br'] ?? false) === true) {
                break;
            }
            $line[] = $note;
        }
        $pitches = array_map(static fn (array $n): int => (int) $n['pitch'], $line);
        if ($pitches === []) {
            return ['beats' => 0.0, 'notes' => []];
        }
        [$high, $low] = [max($pitches), min($pitches)];
        $notes = [];
        $end = 0.0;
        foreach ($line as $note) {
            $at = round((float) ($note['beat'] ?? 0) - $from, 3);
            $length = (float) ($note['length'] ?? 1);
            $end = max($end, $at + $length);
            $notes[] = ['beat' => $at, 'length' => $length, 'y' => $high === $low ? 0.5 : round(($high - (int) $note['pitch']) / ($high - $low), 3)];
        }

        return ['beats' => $end, 'notes' => $notes];
    }

    /**
     * The page's link cards (tool/link_cards.py draws them, SitePages::linkCards lists them): /ai-harmony-singing's in English and
     * /es/armonias-con-ia's in Spanish, each its words and the stage's picture: one real lane (the demo's real song) and the two
     * AI lanes it becomes (the AI song's tune and harmony), each lane's opening notes from the songs themselves, each
     * lane's chip in the page's own words. Nothing when the songs aren't in the library.
     *
     * @return list<array{key: string, choir: array<string, mixed>}>
     */
    public static function cards(): array
    {
        $config = (array) config('site.voice_demo');
        $songs = Template::query()->whereIn('slug', [(string) ($config['real']['song'] ?? ''), (string) ($config['ai']['song'] ?? '')])->with('currentVersion')->get()->keyBy('slug');
        $real = $songs->get((string) ($config['real']['song'] ?? ''));
        $ai = $songs->get((string) ($config['ai']['song'] ?? ''));
        if (! $real instanceof Template || ! $ai instanceof Template) {
            return [];
        }
        $lanes = [
            ['seg' => 'real', 'part' => 'soprano', 'n' => 1, 'tune' => self::tune($real, 8.0)],
            ['seg' => 'ai', 'part' => 'soprano', 'n' => 1, 'tune' => self::tune($ai, 8.0)],
            ['seg' => 'ai', 'part' => 'alto', 'n' => 2, 'tune' => self::tune($ai, 8.0, 'alto')],
        ];
        $locale = app()->getLocale();
        $cards = [];
        foreach (['en' => self::CARD, 'es' => 'es-'.self::CARD] as $language => $key) {
            app()->setLocale($language);
            $cards[] = ['key' => $key, 'choir' => [
                'eyebrow' => (string) __('site.your_choir.eyebrow'),
                // Its own short headline (marketing, 2026-11-24): the page's H1 is too long for a card.
                'headline' => (string) __('site.your_choir.card.headline_before'),
                'em' => (string) __('site.your_choir.card.headline_em'),
                'line' => (string) __('site.your_choir.stage.play'),
                'lanes' => array_map(static fn (array $lane): array => [
                    ...$lane,
                    'name' => (string) __('pages.landing.hero.parts.'.$lane['part']),
                    'chip' => (string) __('site.your_choir.stage.'.($lane['seg'] === 'real' ? 'real_chip' : 'ai_chip')),
                    'song' => SongWords::title((string) ($lane['seg'] === 'real' ? $real->title : $ai->title), (string) ($lane['seg'] === 'real' ? $real->slug : $ai->slug)),
                ], $lanes),
            ]];
        }
        app()->setLocale($locale);

        return $cards;
    }

    /**
     * The page's picture while there's no audio demo (Hillel, 2026-11-21: "lets support starting track B without A, we
     * just need to adjust the your voice page so there aren't samples"): PICTURE_SONG's own arrangement as lanes, no
     * sound and no singer: its tune as the visitor's one voice line, then its four parts, each the app's glass gems with
     * their syllables, from the song's notes (never a recording). Null without the song in the library. `loaded`: the
     * song, when the page has it already.
     *
     * @return array{song: string, you: array{part: string, beats: float, notes: list<array{beat: float, length: float, y: float, text: string}>}, parts: list<array{part: string, beats: float, notes: list<array{beat: float, length: float, y: float, text: string}>}>}|null
     */
    public static function picture(?Template $loaded = null): ?array
    {
        $slug = self::PICTURE_SONG;
        // The page has usually loaded the song already (its library list): used as is, not fetched again.
        $template = $loaded !== null && $loaded->slug === $slug ? $loaded : Template::query()->where('slug', $slug)->whereNull('hidden_at')->with('currentVersion')->first();
        $parts = array_values(array_filter((array) (((array) $template?->currentVersion?->data)['parts'] ?? []), 'is_array'));
        $lanes = [];
        foreach (['soprano', 'alto', 'tenor', 'bass'] as $key) {
            $part = collect($parts)->firstWhere('key', $key);
            if (is_array($part)) {
                $lanes[] = self::lane(['part' => $key, 'notes' => (array) ($part['guide']['notes'] ?? [])]);
            }
        }
        if ($template === null || count($lanes) !== 4) {
            return null;
        }

        return ['song' => SongWords::title((string) $template->title, (string) $template->slug), 'you' => $lanes[0], 'parts' => $lanes];
    }

    /** "1:42". */
    public static function clock(float $seconds): string
    {
        $s = (int) round($seconds);

        return intdiv($s, 60).':'.str_pad((string) ($s % 60), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Where the phrase nearest the middle of a part starts (beats): the note that opens a line (`br`) closest to half the
     * part's length; the middle itself when the part marks none.
     *
     * @param  array<array-key, mixed>  $notes
     */
    public static function middlePhrase(array $notes): float
    {
        $notes = array_values(array_filter($notes, 'is_array'));
        if ($notes === []) {
            return 0.0;
        }
        $last = end($notes);
        $half = ((float) ($last['beat'] ?? 0) + (float) ($last['length'] ?? 0)) / 2;
        $best = null;
        foreach ($notes as $note) {
            if (($note['br'] ?? false) !== true || (float) ($note['beat'] ?? 0) <= 0) {
                continue;
            }
            $beat = (float) $note['beat'];
            if ($best === null || abs($beat - $half) < abs($best - $half)) {
                $best = $beat;
            }
        }

        return round($best ?? $half, 3);
    }

    /**
     * A lane: its part and its notes, each with where it sits (0 at the lane's highest note, 1 at its lowest) and its
     * syllable.
     *
     * @param  array<string, mixed>  $voice
     * @return array{part: string, beats: float, notes: list<array{beat: float, length: float, y: float, text: string}>}
     */
    private static function lane(array $voice): array
    {
        $raw = array_values(array_filter((array) ($voice['notes'] ?? []), static fn (mixed $n): bool => is_array($n) && isset($n['pitch'])));
        $pitches = array_map(static fn (array $n): int => (int) $n['pitch'], $raw);
        $high = $pitches === [] ? 72 : max($pitches);
        $low = $pitches === [] ? 60 : min($pitches);
        $notes = [];
        $beats = 0.0;
        foreach ($raw as $note) {
            $beat = (float) ($note['beat'] ?? 0);
            $length = (float) ($note['length'] ?? 1);
            $beats = max($beats, $beat + $length);
            $notes[] = [
                'beat' => $beat,
                'length' => $length,
                'y' => $high === $low ? 0.5 : round(($high - (int) $note['pitch']) / ($high - $low), 3),
                'text' => is_string($note['lyric'] ?? null) ? rtrim($note['lyric'], '-') : '',
            ];
        }

        return ['part' => (string) ($voice['part'] ?? ''), 'beats' => $beats, 'notes' => $notes];
    }

    /**
     * One voice as the player reads it: the app's recording, its notes (beats, pitch) and the beat it joins on.
     *
     * @param  array<string, mixed>  $voice
     * @return array<string, mixed>
     */
    private static function voice(array $voice, float $join): array
    {
        return [
            'part' => (string) ($voice['part'] ?? ''),
            'url' => $voice['url'] ?? null,
            'sampleRate' => $voice['sampleRate'] ?? null,
            'frames' => $voice['frames'] ?? null,
            'offsetFrames' => $voice['offsetFrames'] ?? 0,
            'notes' => array_values(array_map(static fn (array $n): array => ['beat' => (float) ($n['beat'] ?? 0), 'length' => (float) ($n['length'] ?? 1), 'pitch' => (int) ($n['pitch'] ?? 0)], array_filter((array) ($voice['notes'] ?? []), static fn (mixed $n): bool => is_array($n) && isset($n['pitch'])))),
            'join' => $join,
        ];
    }

    /**
     * How long a set of voices lasts from the segment's start (seconds): the latest end of any of them.
     *
     * @param  list<array<string, mixed>>  $voices
     */
    private static function end(array $voices): float
    {
        $end = 0.0;
        foreach ($voices as $voice) {
            $rate = max(1, (int) ($voice['sampleRate'] ?? 48000));
            $end = max($end, ((int) ($voice['offsetFrames'] ?? 0) + (int) ($voice['frames'] ?? 0)) / $rate);
        }

        return $end;
    }

    private static function title(string $slug): string
    {
        $template = $slug === '' ? null : Template::query()->where('slug', $slug)->first(['title', 'slug']);

        return $template === null ? '' : SongWords::title((string) $template->title, (string) $template->slug);
    }
}
