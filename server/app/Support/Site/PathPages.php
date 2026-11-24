<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Http\Controllers\Public\DocumentPagesController;
use App\Support\Legal\LegalText;
use App\Support\Pages\Landing;
use App\Support\Pages\SongParts;
use Illuminate\Support\Facades\Lang;

/**
 * The home's paths, each with a page of its own (Hillel, 2026-11-23: "the home's Leaders path has its own page; do the
 * same for Singers and Learners"): /for/learners and /for/singers gather what the site already has for that way in
 * (its songs, courses, tools, guides and questions, each drawn as its own card), in the order the page's sections tell
 * it. Nothing new is offered here, and nothing about AI (that's /ai-harmony-singing's). The words are site.for.pages.<who>.
 */
final class PathPages
{
    public const PATHS = ['learners', 'singers'];

    /**
     * What each page lists, by slug: the songs' level (easiest first for learners, the harder first for singers), its
     * courses, tools and guides, the feature cards, and which of the home's questions it answers.
     */
    private const CONTENT = [
        'learners' => [
            'levels' => ['beginner'],
            // The songs most people already know (the course's own, a round, a birthday), before the rest of the easy ones.
            'first' => ['amazing-grace', 'row-row-row', 'happy-birthday', 'silent-night', 'ode-to-joy', 'the-water-is-wide'],
            'guides_hear' => ['what-is-harmony', 'how-to-hear-harmony', 'satb-explained'],
            'features' => ['course'],
            'courses' => ['perfect-your-pitch', 'range-and-registers', 'ear-training', 'breath-and-stamina', 'humming-and-lip-trills', 'diction'],
            'tools' => ['vocal-range-test', 'pitch-matching', 'drone'],
            'guides_sing' => ['sing-harmony-with-yourself', 'how-to-sing-harmony', 'hold-your-part'],
            'faq' => ['read_music', 'free', 'headphones', 'iphone'],
            'also' => 'beginners',
        ],
        'singers' => [
            'levels' => ['advanced', 'intermediate', 'beginner'],
            'first' => ['hallelujah-chorus', 'and-the-glory-of-the-lord', 'cantique-de-jean-racine', 'the-water-is-wide'],
            'features' => ['studio'],
            'courses' => ['sight-singing', 'improvising-harmony', 'ear-training', 'counterpoint', 'barbershop', 'a-cappella-bass'],
            'tools' => ['audio-latency-test', 'just-intonation-tuner', 'metronome'],
            // Beside the tools that line the parts up, the guide to recording them at home (marketing, 2026-11-24).
            'guides_mix' => ['record-vocal-harmonies-at-home'],
            'share' => ['share'],
            'faq' => ['mic', 'headphones', 'instruments', 'privacy'],
            'also' => 'a-cappella',
        ],
    ];

    /** How many songs a page shows before "All songs". */
    public const SONGS = 6;

    /** The course or the studio and the courses after it fill two rows of three: the first five live ones listed. */
    public const COURSES = 5;

    /**
     * The page's content, ready to draw.
     *
     * @param  array{month: string, year: string, yearAsMonth: string}  $price
     * @return array<string, mixed>
     */
    public static function content(string $who, array $price): array
    {
        $plan = self::CONTENT[$who];
        $guide = static function (string $slug): ?array {
            if (! in_array($slug, DocumentPagesController::guides(), true)) {
                return null;
            }
            // In the page's language, as the guide's own page reads it (English where a guide has no translation yet).
            $doc = app(LegalText::class)->read($slug, app()->getLocale(), 'guides');

            return ['name' => (string) $doc['title'], 'summary' => (string) $doc['description'], 'url' => '/guides/'.$slug];
        };
        $tools = collect(SitePages::tools())->keyBy('slug');
        $courses = SitePages::courses();
        $questions = (array) Teams::words('pages.landing.faq');
        $answers = array_combine(array_keys($questions), array_slice(Landing::faq(count((array) config('templates.free')), $price), 0, count($questions)));

        return [
            'songs' => self::songs($plan['levels'], $plan['first']),
            'guidesHear' => array_values(array_filter(array_map($guide, $plan['guides_hear'] ?? []))),
            'features' => array_values(array_filter(SitePages::cards($plan['features']), static fn (array $card): bool => ! $card['soon'])),
            'courses' => array_slice(array_values(array_filter($plan['courses'], static fn (string $slug): bool => in_array($slug, $courses, true))), 0, self::COURSES),
            'tools' => array_values(array_filter(array_map(static fn (string $slug): ?array => $tools->get($slug), $plan['tools']))),
            'toolsMore' => [SitePages::toolsHome(), (string) __('site.tools.index.see_all', ['count' => count(SitePages::listed())])],
            'guidesSing' => array_values(array_filter(array_map($guide, $plan['guides_sing'] ?? []))),
            'guidesMix' => array_values(array_filter(array_map($guide, $plan['guides_mix'] ?? []))),
            'share' => array_values(array_filter(SitePages::cards($plan['share'] ?? []), static fn (array $card): bool => ! $card['soon'])),
            // Plus without its AI lines, as the home lists it: its first three, a row of cards.
            'plus' => $who === 'singers' ? array_slice(PlusFeatures::items(false), 0, 3) : [],
            // The course page's own words for how progress works (both skins), for learners.
            'progress' => $who === 'learners' ? array_slice(SitePages::page('features', 'course')['sections'], 0, 3) : [],
            'faq' => array_values(array_filter(array_map(static fn (string $key): ?array => $answers[$key] ?? null, $plan['faq']))),
            'also' => '/for/'.$plan['also'],
            // Friends on the other parts (singers): the friends and family page's own card.
            'friends' => $who === 'singers' ? [['name' => (string) __('site.for.pages.friends-and-family.name'), 'summary' => (string) __('site.for.pages.friends-and-family.summary'), 'url' => '/for/friends-and-family']] : [],
        ];
    }

    /**
     * A real screen of the app for the page's head (Hillel, 2026-11-23: the picture shows the page's promise, a frame that
     * fits it): lesson 1 finding your note for learners, the studio's stacked parts for singers. Its words are the help
     * guide's own for the same screen (pages.help.pages: wherever it shows it), so the two never drift.
     *
     * @return array{id: string, alt: string}
     */
    public static function shot(string $who): array
    {
        $id = $who === 'learners' ? 'your-voice--note' : 'studio--overview';
        $alt = '';
        $find = static function (array $words) use (&$find, $id, &$alt): void {
            foreach ($words as $value) {
                if (! is_array($value) || $alt !== '') {
                    continue;
                }
                if (($value['shot'] ?? null) === $id && is_string($value['alt'] ?? null)) {
                    $alt = $value['alt'];

                    return;
                }
                $find($value);
            }
        };
        $find((array) Lang::get('pages.help.pages'));

        return ['id' => $id, 'alt' => $alt];
    }

    /**
     * Open songs: those named in `$first` (in that order) that are open, then the levels in `$levels` order, each as a
     * card to its page with its level.
     *
     * @param  list<string>  $levels
     * @param  list<string>  $first
     * @return list<array{name: string, summary: string, url: string, soon: bool}>
     */
    private static function songs(array $levels, array $first): array
    {
        $songs = collect(app(SongParts::class)->index())
            ->filter(static fn (array $song): bool => in_array($song['difficulty'], $levels, true))
            ->sortBy(static fn (array $song): array => [in_array($song['slug'], $first, true) ? (int) array_search($song['slug'], $first, true) : count($first), (int) array_search($song['difficulty'], $levels, true)])
            ->take(self::SONGS);

        return array_values($songs->map(static fn (array $song): array => [
            'name' => $song['title'],
            'summary' => (string) __('site.carols.levels.'.$song['difficulty']),
            'url' => '/songs/'.$song['slug'],
            'soon' => false,
        ])->all());
    }
}
