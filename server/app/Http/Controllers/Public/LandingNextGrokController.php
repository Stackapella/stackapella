<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Languages\LanguageMenu;
use App\Support\Pages\Landing;
use App\Support\Pages\LandingNextGrok;
use App\Support\Pages\Prices;
use App\Support\Site\Explore;
use App\Support\Site\SitePages;
use App\Support\Site\SiteVisit;
use App\Support\Templates\Catalogue;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/**
 * The combined landing, candidate "grok" (/next-grok and /es/next-grok; docs/landing-next-grok-plan.md): the film, the
 * player and the page as one full-screen stage, for Hillel to compare with the other candidates. Never in search (the
 * header here and the view's robots meta, whatever SITE_INDEXING says), in no sitemap, with no hreflang, linked from
 * nowhere, and counted only as its own kind (sv_next_grok, SiteVisit::TRIALS), outside the growth totals. The landing
 * at / is untouched.
 */
final class LandingNextGrokController extends Controller
{
    /** The page's own ?ref on every link into the app, so the landing's sources never count it. */
    private const REF = 'next-grok';

    public function show(Request $request): Response
    {
        $song = LandingNextGrok::song();
        $plans = Landing::plans();
        $price = Prices::of(Prices::currency($request));
        $parts = $song['parts'] ?? [];

        return response()->view('pages.landing-next-grok', [
            'title' => __('pages.landing_next_grok.title'),
            'description' => __('pages.landing_next_grok.description'),
            'song' => $song,
            // Each part's gems, placed from its notes; the words a phrase at a time; the pitch line over the tune's opening.
            'gems' => array_combine(array_column($parts, 'key'), array_map(static fn (array $part): array => LandingNextGrok::gems($part, (array) $song), $parts)),
            'phrases' => $song !== null ? LandingNextGrok::phrases($song['words']) : [],
            'trace' => $parts !== [] ? LandingNextGrok::trace($parts[0], (array) $song, 9.5) : '',
            'pageBeats' => LandingNextGrok::PAGE_BEATS,
            'links' => Landing::links(self::REF),
            // Where "Learn the alto part" leads: the app, on this song and that part, as the song page's does.
            'learn' => array_combine(array_column($parts, 'key'), array_map(static fn (array $part): string => Hosts::app('/?'.http_build_query(['song' => $song['song'] ?? '', 'part' => $part['key'], 'ref' => self::REF])), $parts)),
            'counts' => Explore::counts(),
            'courses' => array_slice(Landing::featuredCourses(), 0, 5),
            'songs' => array_slice(array_column(app(Catalogue::class)->openIndex(), 'title'), 0, 5),
            'tools' => array_slice(Landing::featuredTools(), 0, 5),
            'toolsHome' => SitePages::toolsHome(),
            'singers' => array_values((array) Lang::get('site.teams.group_sample')),
            'plans' => $plans,
            'price' => $price,
            'faq' => array_slice(Landing::faq($plans['freeSongs'], $price['yearAsMonth']), 0, 3),
            'languages' => LanguageMenu::for($request),
            'visit' => SiteVisit::kind($request),
        ], 200, [
            ...SongPagesController::headers(),
            'Vary' => 'CF-IPCountry, Accept-Language',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
