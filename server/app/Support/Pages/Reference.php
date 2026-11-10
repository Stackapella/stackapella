<?php

declare(strict_types=1);

namespace App\Support\Pages;

use App\Support\Site\SitePages;
use Illuminate\Support\Facades\Lang;

/**
 * The reference pages (docs/seo-growth-plan.md item 4): /intervals, /chords and /voice-types, each an index and one
 * page per item, around frontend's cards (js/public/reference-cards.js). The one list of each family's addresses, each
 * slug mapped to what its card takes: an interval's semitones (and its pure ratio, as just.js INTERVALS has it), a
 * chord's quality (just.js RATIOS) or a voice type (theory.js VOICE_TYPES, with its CHOIR_PARTS part).
 * ReferencePagesTest checks the three against the JavaScript. A page is listed once its words are written
 * (site.reference.<family>.pages.<slug>). No page listens.
 */
final class Reference
{
    /** The card module every page mounts. */
    public const MODULE = '/js/public/reference-cards.js';

    /** Each family: its words (site.reference.<words>), its card's mount, and its ?ref= tag (counts.sources). */
    public const FAMILIES = [
        'intervals' => ['words' => 'intervals', 'mount' => 'mountIntervalCard'],
        'chords' => ['words' => 'chords', 'mount' => 'mountChordCard'],
        'voice-types' => ['words' => 'voices', 'mount' => 'mountVoiceRangeCard'],
    ];

    /** Each interval in an octave: its semitones and its pure ratio (the one its card plays). */
    public const INTERVALS = [
        'minor-second' => [1, '16:15'], 'major-second' => [2, '9:8'], 'minor-third' => [3, '6:5'], 'major-third' => [4, '5:4'],
        'perfect-fourth' => [5, '4:3'], 'tritone' => [6, '7:5'], 'perfect-fifth' => [7, '3:2'], 'minor-sixth' => [8, '8:5'],
        'major-sixth' => [9, '5:3'], 'minor-seventh' => [10, '9:5'], 'major-seventh' => [11, '15:8'], 'octave' => [12, '2:1'],
    ];

    /** Each chord type: its quality in just.js. */
    public const CHORDS = [
        'major' => 'maj', 'minor' => 'min', 'diminished' => 'dim', 'augmented' => 'aug', 'sus2' => 'sus2', 'sus4' => 'sus4',
        'major-seventh' => 'maj7', 'minor-seventh' => 'min7', 'dominant-seventh' => 'dom7', 'diminished-seventh' => 'dim7',
        'half-diminished' => 'hdim7', 'major-sixth' => 'maj6', 'minor-sixth' => 'min6', 'add9' => 'add9',
    ];

    /** Each voice type: its key in theory.js VOICE_TYPES, its choir part in CHOIR_PARTS, and its usual range (MIDI). */
    public const VOICE_TYPES = [
        'bass' => ['bass', 'bass', 40, 64], 'baritone' => ['baritone', 'bassOrTenor2', 43, 67], 'tenor' => ['tenor', 'tenor', 48, 69],
        'alto' => ['alto', 'alto', 53, 74], 'mezzo-soprano' => ['mezzo', 'sopranoOrAlto', 57, 77], 'soprano' => ['soprano', 'soprano', 60, 81],
    ];

    /** The song parts each voice type looks for first on its page (SongParts::within), by its choir part. */
    public const VOICE_PARTS = ['bass' => ['bass'], 'baritone' => ['bass', 'tenor'], 'tenor' => ['tenor'], 'alto' => ['alto'], 'mezzo-soprano' => ['soprano', 'alto'], 'soprano' => ['soprano']];

    /**
     * What a family's pages feature below them in the free tools: its index's `related` line and tool.
     *
     * @return array<int, string>|null
     */
    public static function related(string $family): ?array
    {
        $related = Lang::get('site.reference.'.self::FAMILIES[$family]['words'].'.index.related');

        return is_array($related) ? array_values(array_map('strval', $related)) : null;
    }

    /**
     * A family's pages with their words written, in order.
     *
     * @return list<string>
     */
    public static function slugs(string $family): array
    {
        $words = self::FAMILIES[$family]['words'] ?? null;
        if ($words === null || ! Lang::has('site.reference.'.$words.'.index.headline')) {
            return [];
        }

        return array_values(array_filter(array_keys(self::items($family)), static fn (string $slug): bool => Lang::has('site.reference.'.$words.'.pages.'.$slug.'.headline')));
    }

    /**
     * A family's index words.
     *
     * @return array<string, mixed>
     */
    public static function index(string $family): array
    {
        return (array) Lang::get('site.reference.'.self::FAMILIES[$family]['words'].'.index');
    }

    /**
     * One page's words, with its slug and address.
     *
     * @return array<string, mixed>
     */
    public static function page(string $family, string $slug): array
    {
        return [...(array) Lang::get('site.reference.'.self::FAMILIES[$family]['words'].'.pages.'.$slug), 'slug' => $slug, 'url' => '/'.$family.'/'.$slug];
    }

    /**
     * What a page's card takes besides its words (page.js passes it to the mount).
     *
     * @return array<string, mixed>
     */
    public static function data(string $family, string $slug): array
    {
        return match ($family) {
            'intervals' => ['semis' => self::INTERVALS[$slug][0]],
            'chords' => ['quality' => self::CHORDS[$slug]],
            default => ['type' => self::VOICE_TYPES[$slug][0], 'rangeTest' => in_array('vocal-range-test', SitePages::listed(), true) ? '/tools/vocal-range-test' : '/tools'],
        };
    }

    /**
     * A page's card words: the family's, with a chord's own symbol (its :name) and any tone named for it alone (a
     * diminished seventh's 9 semitones, an add9's ninth).
     *
     * @return array<string, mixed>
     */
    public static function card(string $family, string $slug): array
    {
        $words = self::FAMILIES[$family]['words'];
        $card = (array) Lang::get('site.reference.'.$words.'.card');
        if ($family !== 'chords') {
            return $card;
        }
        $page = self::page($family, $slug);

        return [...$card, 'name' => (string) ($page['symbol'] ?? ''), 'tones' => array_replace((array) ($card['tones'] ?? []), (array) ($page['tones'] ?? []))];
    }

    /**
     * The index's table: each page's cells, its name first (linked by the view to `url`).
     *
     * @return list<array{url: string, name: string, cells: list<string>}>
     */
    public static function rows(string $family): array
    {
        $none = '-';
        $parts = (array) Lang::get('site.reference.voices.card.part');

        return array_map(static function (string $slug) use ($family, $none, $parts): array {
            $page = self::page($family, $slug);
            $cells = match ($family) {
                'intervals' => [(string) self::INTERVALS[$slug][0], (string) (($page['up'] ?? '') !== '' ? $page['up'] : $none), (string) (($page['down'] ?? '') !== '' ? $page['down'] : $none), self::INTERVALS[$slug][1]],
                'chords' => [(string) $page['formula'], (string) $page['in_c'], 'C'.$page['symbol']],
                default => [(string) $page['range'], (string) ($parts[self::VOICE_TYPES[$slug][1]] ?? '')],
            };

            return ['url' => (string) $page['url'], 'name' => (string) $page['name'], 'cells' => $cells];
        }, self::slugs($family));
    }

    /**
     * Every reference page's address, for the sitemap: each family's index once it has a page, and its pages.
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        $paths = [];
        foreach (array_keys(self::FAMILIES) as $family) {
            $slugs = self::slugs($family);
            if ($slugs !== []) {
                $paths = [...$paths, '/'.$family, ...array_map(static fn (string $slug): string => '/'.$family.'/'.$slug, $slugs)];
            }
        }

        return $paths;
    }

    /**
     * Every slug the family knows, mapped to its card's key.
     *
     * @return array<string, mixed>
     */
    private static function items(string $family): array
    {
        return match ($family) {
            'intervals' => self::INTERVALS,
            'chords' => self::CHORDS,
            'voice-types' => self::VOICE_TYPES,
            default => [],
        };
    }
}
