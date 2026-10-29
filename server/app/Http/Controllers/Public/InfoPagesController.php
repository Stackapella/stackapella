<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Counts\Funnel;
use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Pages\Landing;
use App\Support\Pages\MudeoArrival;
use App\Support\Pages\Prices;
use App\Support\Site\ComingSoon;
use App\Support\Site\HelpGuide;
use App\Support\Site\OpenSource;
use App\Support\Site\SitePages;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;

/**
 * The landing page (/ for first-time visitors), pricing and the help pages: the backend's pages,
 * in the public pages' frame (docs/decisions.md, "who builds the public pages"). No session; the
 * only cookie is the landing's mudeo mark (MudeoArrival). The words are in lang/en/pages.php and the
 * numbers come from config.
 */
final class InfoPagesController extends Controller
{
    /** The pricing comparison's groups and the rows (pages.pricing.rows) in each. */
    private const PRICING_GROUPS = [
        'learn' => ['songs', 'preview', 'course', 'workout', 'report', 'guides'],
        'make' => ['projects', 'storage', 'singers', 'templates'],
        'share' => ['share', 'wav', 'mp3', 'stems'],
        'others' => ['groups', 'coaching'],
    ];

    public function landing(Request $request): Response
    {
        $ref = Landing::ref($request->query('ref'));
        $mudeo = MudeoArrival::is($request, $ref);
        $ref ??= $mudeo ? MudeoArrival::REF : null;
        $price = Prices::of(Prices::currency($request));
        $plans = Landing::plans();

        $response = response()->view('pages.landing', [
            'title' => __('pages.landing.title'),
            'meta' => $this->meta(__('pages.landing.title'), __('pages.landing.description'), '/'),
            'mudeo' => $mudeo,
            'mudeoLetter' => $mudeo ? Landing::mudeoLetter() : [],
            'links' => Landing::links($ref),
            'hero' => $hero = Landing::hero(),
            'pictures' => $hero !== null ? Landing::pictures($hero) : null,
            'way' => Landing::onTheWay(),
            'who' => SitePages::who(),
            'stories' => array_values((array) config('pages.stories')),
            'price' => $price,
            'plans' => $plans,
            'faq' => Landing::faq($plans['freeSongs'], $price['yearAsMonth'], OpenSource::on()),
            // The funnel (App\Actions\Counts\Funnel): whether to send f_site_start at all, and the running
            // experiment ({ id, arms, enrol } or null) the page draws a Start arm for in the browser.
            'funnel' => ['counts' => config('counts.enabled') === true, 'experiment' => app(Funnel::class)->current()],
            // At / the app answers too, for anyone who has used it: never cached as the landing.
        ], 200, [...$this->headers(varies: true), 'Cache-Control' => 'no-cache', 'Vary' => 'Cookie, CF-IPCountry, Accept-Language']);

        // A mudeo link is remembered for the visit (MudeoArrival).
        return $mudeo && $request->cookies->get(MudeoArrival::COOKIE) !== '1' ? $response->withCookie(MudeoArrival::cookie($request)) : $response;
    }

    public function pricing(Request $request): Response
    {
        $plans = (array) config('usage.plans');
        $limit = static fn (?int $value, string $unit = ''): string => $value === null ? __('pages.pricing.unlimited') : ($unit === 'bytes' ? round($value / 1024 ** 3).' GB' : (string) $value);
        $currency = Prices::currency($request);

        return response()->view('pages.pricing', [
            'title' => __('pages.pricing.title'),
            'meta' => $this->meta(__('pages.pricing.meta_title'), __('pages.pricing.description'), '/pricing'),
            'price' => Prices::of($currency),
            'currencies' => Prices::CURRENCIES,
            'trialDays' => (int) config('billing.trial_days'),
            'freeSongs' => count((array) config('templates.free')),
            'limits' => [
                'projects' => [$limit($plans['free']['projects']), $limit($plans['plus']['projects'])],
                'storage' => [$limit($plans['free']['storage_bytes'], 'bytes'), $limit($plans['plus']['storage_bytes'], 'bytes')],
                'singers' => [$limit($plans['free']['singers']), $limit($plans['plus']['singers'])],
                'templates' => [$limit($plans['free']['templates']), $limit($plans['plus']['templates'])],
            ],
            'checkoutOpen' => filled(config('billing.prices.month')) && filled(config('billing.prices.year')),
            // The page's lists, as lang/en/pages.php has them.
            'rows' => (array) Lang::get('pages.pricing.rows'),
            // The comparison in groups (the design round), each row's key in the order shown;
            // coaching only once it's open (it waits for after launch, docs/decisions.md).
            'groups' => ComingSoon::open('coaching') ? self::PRICING_GROUPS : array_map(static fn (array $rows): array => array_values(array_diff($rows, ['coaching'])), self::PRICING_GROUPS),
            'plans' => Landing::plans(),
            'smallPrint' => (array) Lang::get('pages.pricing.small_print'),
            'faq' => array_map(static fn (array $qa): array => [$qa[0], str_replace(':days', (string) config('billing.trial_days'), (string) $qa[1])], [
                ...(array) Lang::get('pages.pricing.faq'),
                ...(OpenSource::on() ? [(array) Lang::get('pages.pricing.faq_selfhost')] : []),
            ]),
        ], 200, $this->headers(varies: true));
    }

    public function help(): Response
    {
        return response()->view('pages.help', [
            'title' => __('pages.help.title'),
            'meta' => $this->meta(__('pages.help.meta_title'), __('pages.help.description'), '/help'),
            'groups' => HelpGuide::groups(),
            'path' => array_map(HelpGuide::card(...), array_values(array_filter(HelpGuide::PATH, HelpGuide::written(...)))),
            'most' => array_map(static fn (string $slug): array => [...HelpGuide::card($slug), 'icon' => HelpGuide::MOST_ASKED[$slug]], array_values(array_filter(array_keys(HelpGuide::MOST_ASKED), HelpGuide::written(...)))),
            // Contact us is in the app (Settings, then Contact us), which records where they came from.
            'contactUrl' => Hosts::app('/contact?'.http_build_query(['from' => '/help'])),
        ], 200, $this->headers());
    }

    public function helpPage(Request $request, string $slug): Response|RedirectResponse
    {
        if (isset(HelpGuide::MOVED[$slug])) {
            return redirect('/help/'.HelpGuide::MOVED[$slug], 301);
        }
        if (! in_array($slug, HelpGuide::slugs(), true)) {
            return response()->view('public.page-gone', ['links' => ['app' => '/help']], 404, $this->headers());
        }

        $card = HelpGuide::card($slug);

        return response()->view('pages.help-page', [
            'slug' => $slug,
            'title' => $card['title'],
            'meta' => $this->meta($card['title'], $card['summary'], '/help/'.$slug),
            'sections' => HelpGuide::sections($slug, $this->numbers($request)),
            'groups' => HelpGuide::groups(),
            'pager' => HelpGuide::neighbours($slug),
            'contactUrl' => Hosts::app('/contact?'.http_build_query(['from' => '/help/'.$slug])),
            // Plus and billing names the price in the visitor's currency.
        ], 200, $this->headers(varies: true));
    }

    /**
     * The guide's search rows, fetched by js/public/help-search.js when the search is first used.
     */
    public function helpSearch(Request $request): JsonResponse
    {
        return response()->json(HelpGuide::searchIndex($this->numbers($request)), 200, $this->headers(varies: true));
    }

    /**
     * The numbers in the guide's words: the price in the visitor's currency, the trial's days, and our address.
     *
     * @return array<string, string>
     */
    private function numbers(Request $request): array
    {
        return ['price' => Prices::of(Prices::currency($request))['yearAsMonth'], 'days' => (string) config('billing.trial_days'), 'email' => (string) config('site.contact_email')];
    }

    /**
     * @return array{title: string, description: string, url: string}
     */
    private function meta(string $title, string $description, string $path): array
    {
        return ['title' => $title, 'description' => $description, 'url' => Hosts::publicUrl().$path];
    }

    /**
     * Cached 5 minutes like the song pages, kept out of search until launch; the prices vary by
     * the visitor's country and language.
     *
     * @return array<string, string>
     */
    private function headers(bool $varies = false): array
    {
        return [...SongPagesController::headers(), ...($varies ? ['Vary' => 'CF-IPCountry, Accept-Language'] : [])];
    }
}
