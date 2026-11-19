<?php

declare(strict_types=1);

namespace App\Support\Pages;

/**
 * A song's featured picture, the one its card shows on a collection page and its collection's panel shows on /songs
 * (Hillel, 2026-11-18: "pick an image from each song to use as the featured image when showing the song on the category
 * page, and then pick one song/image from the category page for the main songs page"). Only a photograph or painting of
 * a place, a thing or a scene (Hillel, 2026-11-18: "I like the photos of places or things but not sheet music or photos
 * of pages"): never sheet music, a hymnbook or title page, a manuscript, a printed or handwritten page, a score, a letter
 * or a map, and no portraits. SCENES lists the story pictures that are one; of those, in this order:
 *
 * 1. the story's `hero`, when its front matter names one;
 * 2. FEATURED, the site's pick where a song has more than one;
 * 3. the first SCENES lists for it.
 *
 * A song with none (no reviewed story, or only pages) has none (null): its card sets its title large in its collection's
 * palette instead (site._song-picture).
 */
final class SongPictures
{
    /**
     * Each song's story pictures (by name, as media.json's files) that are a photograph or painting of a place, a thing or
     * a scene, read one by one from the pictures and their alt text (2026-11-18). Everything else in the stories is a page:
     * music, words, a title page, a manuscript, a letter or telegram, a map or chart; and the portraits and group
     * photographs of people are left out too. A new picture is a page until it's listed here. The song photos found for
     * the songs that had none (2026-11-18, Hillel: "can you find a nice photo for each song") are each song's card
     * picture ("placement": "card" in its media.json), every one a place or a thing.
     */
    public const SCENES = [
        'abide-with-me' => ['4'], 'adir-hu' => ['5'], 'adon-olam' => ['5'], 'all-through-the-night' => ['2'],
        'amazing-grace' => ['2'], 'and-the-glory-of-the-lord' => ['1'], 'angels-from-the-realms-of-glory' => ['1'],
        'angels-we-have-heard-on-high' => ['4'], 'are-you-sleeping' => ['5'], 'asleep-in-the-deep' => ['4'],
        'auld-lang-syne' => ['1'], 'ave-verum-corpus' => ['4'], 'away-in-a-manger' => ['4'], 'balm-in-gilead' => ['4'],
        'be-thou-my-vision' => ['4'], 'blow-boys-blow' => ['1'], 'blow-the-man-down' => ['3'],
        'brahms-lullaby' => ['4'], 'bully-in-the-alley' => ['4'], 'cantique-de-jean-racine' => ['1'], 'christians-awake' => ['1'],
        'come-thou-fount' => ['5'], 'come-ye-thankful-people' => ['3'], 'comin-thro-the-rye' => ['5'],
        'coventry-carol' => ['4'], 'daisy-bell' => ['4'], 'danny-boy' => ['3'], 'deck-the-halls' => ['2'],
        'deep-river' => ['4'], 'didnt-my-lord-deliver-daniel' => ['2'], 'ding-dong-merrily-on-high' => ['1'], 'down-by-the-old-mill-stream' => ['3'],
        'drunken-sailor' => ['1'], 'echad-mi-yodea' => ['4'], 'ein-keloheinu' => ['1'], 'el-cant-dels-ocells' => ['4'],
        'eliyahu-hanavi' => ['3'], 'every-time-i-feel-the-spirit' => ['1'], 'ezekiel-saw-the-wheel' => ['3'],
        'for-unto-us-a-child-is-born' => ['1'], 'go-down-moses' => ['3'], 'god-rest-ye' => ['4'],
        'good-christian-men-rejoice' => ['1'], 'good-king-wenceslas' => ['1'], 'greensleeves' => ['5'],
        'hallelujah-chorus' => ['1'], 'happy-birthday' => ['3'], 'hark-the-herald' => ['3'], 'haul-away-joe' => ['2'],
        'haul-on-the-bowline' => ['5'], 'heavenly-treasures' => ['2'], 'hey-ho-nobody-home' => ['4'],
        'holy-holy-holy' => ['4'], 'home-on-the-range' => ['1', '2'], 'i-saw-three-ships' => ['4'],
        'i-will-bow-and-be-simple' => ['1'], 'in-dulci-jubilo' => ['1'], 'in-the-bleak-midwinter' => ['1'],
        'it-came-upon-the-midnight-clear' => ['5'], 'it-is-well' => ['5'], 'jerusalem' => ['4'],
        'jingle-bells' => ['4'], 'joy-to-the-world' => ['4'], 'keep-on-the-sunny-side' => ['3'], 'ki-lo-naeh' => ['5'],
        'la-paloma' => ['1'], 'las-mananitas' => ['5'], 'leave-her-johnny' => ['1'], 'lecha-dodi' => ['1'],
        'let-me-call-you-sweetheart' => ['5'], 'lift-every-voice' => ['5'], 'loch-lomond' => ['3', '4'],
        'locus-iste' => ['1'], 'londons-burning' => ['5'], 'maoz-tzur' => ['1'], 'michael-row' => ['4'],
        'nearer-my-god' => ['5'], 'noche-de-paz' => ['5'], 'now-is-the-month-of-maying' => ['5'],
        'now-thank-we-all-our-god' => ['4'], 'o-come-all-ye-faithful' => ['3'], 'o-come-o-come-emmanuel' => ['1'],
        'o-holy-night' => ['4'], 'o-little-town-of-bethlehem' => ['2'], 'o-the-beautiful-treasure' => ['3'],
        'ode-to-joy' => ['5'], 'oh-how-lovely' => ['3'], 'once-in-royal-davids-city' => ['1'],
        'oy-chanukah' => ['2', '3'], 'pastores-a-belen' => ['4'], 'praise-to-the-lord' => ['5'],
        'red-red-rose' => ['5'], 'riu-riu-chiu' => ['3'], 'row-row-row' => ['3'], 'sakura-sakura' => ['1'], 'see-amid-the-winters-snow' => ['1'],
        'shall-we-gather' => ['1'], 'shchedryk' => ['1'], 'shema-yisrael' => ['1', '2'], 'shenandoah' => ['3'], 'shoshanat-yaakov' => ['5'],
        'silent-night' => ['1'], 'simple-gifts' => ['2', '3', '4'], 'skye-boat-song' => ['2'],
        'spanish-ladies' => ['4'], 'steal-away' => ['2'], 'sweet-adeline' => ['4'], 'swing-low' => ['3'],
        'the-first-noel' => ['4'], 'the-holly-and-the-ivy' => ['1', '3'], 'the-lord-bless-you' => ['4'],
        'the-water-is-wide' => ['3'], 'three-blind-mice' => ['5'], 'tzur-mishelo' => ['5'], 'unto-us-is-born-a-son' => ['1'],
        'wade-in-the-water' => ['3'], 'we-gather-together' => ['3'], 'we-three-kings' => ['3', '4'],
        'were-you-there' => ['3'], 'what-child-is-this' => ['1'], 'while-shepherds-watched' => ['1'], 'white-coral-bells' => ['3'],
        'who-will-bow-and-bend' => ['1'], 'will-ye-no-come-back-again' => ['1'], 'with-cat-like-tread' => ['5'],
        'ye-banks-and-braes' => ['1'],
    ];

    /** The scene a song's card shows where it has more than one and the first isn't the best: by name, from SCENES. */
    public const FEATURED = [
        'home-on-the-range' => '2', 'loch-lomond' => '4', 'oy-chanukah' => '3', 'simple-gifts' => '3', 'the-holly-and-the-ivy' => '3',
    ];

    /**
     * Where a picture's frame centres it, as CSS object-position, where the middle of the top half (the default) would cut
     * what matters.
     */
    public const FOCUS = [
        'amazing-grace' => '50% 20%', 'white-coral-bells' => '50% 40%', 'the-holly-and-the-ivy' => '50% 45%',
        'will-ye-no-come-back-again' => '50% 35%', 'deck-the-halls' => '50% 30%', 'maoz-tzur' => '50% 65%',
        'coventry-carol' => '50% 70%',
    ];

    /** A picture this much wider or taller than its frame is shown whole, on a mat, rather than cropped (a strip of music, a tall cup). */
    private const WHOLE_BELOW = 0.56;

    private const WHOLE_ABOVE = 3.0;

    /** The licences that ask for a credit wherever the picture is shown (SongMedia::LICENCES); the rest ask for none. */
    private const ATTRIBUTION = ['CC-BY-4.0'];

    /**
     * A song's featured picture as a card shows it, its alt in the page's language (English where the record has none), or
     * null when it has none. One of its SCENES: a picture placed in its story, or one added for its card alone
     * ("placement": "card" in media.json: the song photos, 2026-11-18), so a story's translations are untouched.
     *
     * @return array{name: string, src: string, srcset: string, width: int, height: int, alt: string, pixel: string|null, focus: string, whole: bool, credit: string|null, creditBefore: string|null, licenceName: string|null, licenceUrl: string|null}|null
     */
    public static function featured(string $slug): ?array
    {
        $scenes = self::SCENES[$slug] ?? [];
        if ($scenes === []) {
            return null;
        }
        $story = SongStories::read($slug);
        $hero = ($story['reviewed'] ?? false) === true && is_string($story['hero'] ?? null) ? $story['hero'] : null;

        return self::choose($slug, $hero, SongMedia::forPage($slug, $scenes, app()->getLocale(), static fn (string $caption): string => e($caption)));
    }

    /** A credit's words before its licence's name, with the comma that joins them (none when the credit is the name alone). */
    public static function creditBefore(string $credit, string $licenceName): string
    {
        $at = $licenceName !== '' ? mb_strrpos($credit, $licenceName) : false;
        $before = rtrim($at !== false ? mb_substr($credit, 0, $at) : $credit, ' ,·');

        return $before !== '' && $licenceName !== '' ? $before.', ' : $before;
    }

    /**
     * A words card's ground, one of three (s-fpic-v0 to v2), by its place on the page (`variant`, so neighbours differ),
     * else by the song.
     */
    public static function ground(?int $variant, string $key): string
    {
        return 's-fpic-v'.(($variant ?? crc32($key)) % 3);
    }

    /**
     * The song's opening words, for a card with no picture of a place or thing: its first sung line (and the next, when
     * the first is under four words), from its card's line; '' when it has none (a hummed part).
     *
     * @param  array<string, mixed>|null  $line  Landing::line()'s
     */
    public static function opening(?array $line): string
    {
        $words = array_values(array_filter((array) ($line['words'] ?? []), static fn (mixed $w): bool => is_array($w) && is_string($w['text'] ?? null)));
        $text = '';
        foreach (Landing::lines(array_map(static fn (array $w): array => ['beat' => (float) ($w['beat'] ?? 0), 'text' => (string) $w['text'], 'joins' => (bool) ($w['joins'] ?? false), 'br' => (bool) ($w['br'] ?? false)], $words)) as $sung) {
            $text = trim($text.' '.Landing::sentence($sung));
            if (str_word_count($text) >= 4) {
                break;
            }
        }

        // Without the comma or semicolon the line breaks on: it's quoted whole.
        return rtrim($text, ' ,;:');
    }

    /**
     * @param  array<string, array<string, mixed>>  $images  SongMedia::forPage()'s, by name
     * @return array{name: string, src: string, srcset: string, width: int, height: int, alt: string, pixel: string|null, focus: string, whole: bool, credit: string|null, creditBefore: string|null, licenceName: string|null, licenceUrl: string|null}|null
     */
    private static function choose(string $slug, ?string $hero, array $images): ?array
    {
        // Only its scenes (SCENES), never a page, and only one the site has sizes for.
        $scenes = self::SCENES[$slug] ?? [];
        $images = array_filter($images, static fn (array $image, int|string $name): bool => in_array((string) $name, $scenes, true) && is_string($image['src'] ?? null) && (int) ($image['width'] ?? 0) > 0 && (int) ($image['height'] ?? 0) > 0, ARRAY_FILTER_USE_BOTH);
        if ($images === []) {
            return null;
        }
        $name = match (true) {
            $hero !== null && isset($images[$hero]) => $hero,
            isset(self::FEATURED[$slug], $images[self::FEATURED[$slug]]) => self::FEATURED[$slug],
            default => (string) array_key_first($images),
        };
        /** @var array<string, mixed> $image */
        $image = $images[$name];
        $ratio = (int) $image['width'] / (int) $image['height'];
        $attribution = in_array($image['licence'] ?? null, self::ATTRIBUTION, true);
        $credit = $attribution && is_string($image['credit'] ?? null) && $image['credit'] !== '' ? $image['credit'] : null;
        $licenceName = $attribution ? (string) ($image['licenceName'] ?? '') : null;

        return [
            'name' => $name,
            'src' => (string) $image['src'],
            // Its 480 and 960 px sizes: a card is never wider than about 600 px, so the 1440 is left out.
            'srcset' => implode(', ', array_filter(array_map('trim', explode(',', (string) ($image['srcset'] ?? ''))), static fn (string $size): bool => $size !== '' && ! str_ends_with($size, ' 1440w'))),
            'width' => (int) $image['width'],
            'height' => (int) $image['height'],
            'alt' => (string) ($image['alt'] ?? ''),
            // Game mode's pixel art of it (SongMedia::PIXEL), or null.
            'pixel' => is_string($image['pixel'] ?? null) ? $image['pixel'] : null,
            'focus' => self::FOCUS[$slug] ?? '50% 25%',
            'whole' => $ratio < self::WHOLE_BELOW || $ratio > self::WHOLE_ABOVE,
            // Only where its licence asks for one: the song's page credits every picture in full.
            'credit' => $credit,
            // The credit's words before its licence's name, which the card links once ("ECetc Church Photos, via Wikimedia
            // Commons, " then "CC BY 4.0"): a credit that already names its licence ends there.
            'creditBefore' => $credit !== null ? self::creditBefore($credit, $licenceName ?? '') : null,
            'licenceName' => $licenceName,
            'licenceUrl' => $attribution && is_string($image['licenceUrl'] ?? null) ? $image['licenceUrl'] : null,
        ];
    }
}
