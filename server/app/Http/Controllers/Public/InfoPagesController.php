<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Actions\Counts\Funnel;
use App\Actions\Counts\RecordCounts;
use App\Http\Controllers\Controller;
use App\Support\Admin\StaffDevice;
use App\Support\Http\Hosts;
use App\Support\Pages\Landing;
use App\Support\Pages\LandingStage;
use App\Support\Pages\MudeoArrival;
use App\Support\Pages\Prices;
use App\Support\Pages\SongParts;
use App\Support\Site\ComingSoon;
use App\Support\Site\HelpGuide;
use App\Support\Site\OpenSource;
use App\Support\Site\PlusFeatures;
use App\Support\Site\SitePages;
use App\Support\Site\Teams;
use App\Support\Templates\Catalogue;
use App\Support\Trust\Country;
use App\Support\Usage\PrivateSongs;
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
    /**
     * An answer as its short answer (the first sentence) and the rest.
     *
     * @return array{string, string}
     */
    private static function shortFirst(string $answer): array
    {
        $parts = preg_split('/(?<=[.!?])\s+/u', $answer, 2) ?: [$answer];

        return [(string) $parts[0], (string) ($parts[1] ?? '')];
    }

    /**
     * /pricing's own questions; while own_choir is on, "Can I share what I make?" adds that a song sung by AI says so
     * wherever it's shared, and with choir seats, the choir question that a seat doesn't cover songs sung by AI (the
     * marketing review, 2026-11-22).
     *
     * @return array<array-key, mixed>
     */
    private static function pricingFaq(): array
    {
        $faq = [(array) Lang::get('pages.pricing.faq_free'), ...(array) Teams::words('pages.pricing.faq')];
        if (SongParts::switchedOn('own_choir')) {
            $share = (string) __('pages.pricing.faq.1.0');
            $choir = SongParts::switchedOn('choir_seats') ? (string) __('pages.pricing.faq.2.0') : null;
            foreach ($faq as $i => $qa) {
                if (is_array($qa) && ($qa[0] ?? null) === $share) {
                    $faq[$i][1] = $qa[1].' '.__('pages.pricing.faq_share_choir');
                }
                // A seat covers the group, not songs sung by AI (those follow the singer's own plan).
                if (is_array($qa) && $choir !== null && ($qa[0] ?? null) === $choir) {
                    $faq[$i][1] = $qa[1].' '.__('pages.pricing.faq_seat_choir');
                }
            }
        }

        return $faq;
    }

    /** The comparison's rows people decide on, shown before every detail (pages.pricing.rows keys). */
    private const DECIDE = ['storage', 'singers', 'templates', 'mp3', 'stems'];

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

        // The mudeo email's link opened: counted once a browser visit, as a number only.
        if ($mudeo && $request->cookies->get(MudeoArrival::COOKIE) !== '1' && ! StaffDevice::is($request)) {
            app(RecordCounts::class)->server('mudeo_visit', $request->user() !== null, Country::of($request));
        }
        $price = Prices::of(Prices::currency($request));
        $plans = Landing::plans();

        $response = response()->view('pages.landing', [
            'title' => __('pages.landing.title'),
            'meta' => $this->meta(__('pages.landing.title'), __('pages.landing.description'), '/'),
            'mudeo' => $mudeo,
            'mudeoLetter' => $mudeo ? Landing::mudeoLetter() : [],
            'links' => Landing::links($ref),
            // The stage (App\Support\Pages\LandingStage): the song's four lanes, played as the showreel plays it.
            'stage' => LandingStage::stage(),
            'counts' => LandingStage::counts(),
            'price' => $price,
            'plans' => $plans,
            'questions' => LandingStage::questions($plans['freeSongs'], $price),
            'toolsUrl' => SitePages::toolsHome(),
            // Starting a choir group takes Plus while this switch is on: the choir scene says so.
            'teams' => Teams::on(),
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
        $limits = [
            'projects' => [$limit($plans['free']['projects']), $limit($plans['plus']['projects'])],
            'storage' => [$limit($plans['free']['storage_bytes'], 'bytes'), $limit($plans['plus']['storage_bytes'], 'bytes')],
            'singers' => [$limit($plans['free']['singers']), $limit($plans['plus']['singers'])],
            'templates' => [$limit($plans['free']['templates']), $limit($plans['plus']['templates'])],
        ];
        $rows = (array) Teams::words('pages.pricing.rows');
        // Coaching only once it's open (it waits for after launch, docs/decisions.md).
        $groups = ComingSoon::open('coaching') ? self::PRICING_GROUPS : array_map(static fn (array $keys): array => array_values(array_diff($keys, ['coaching'])), self::PRICING_GROUPS);
        // Shared private arrangements need everyone's own Plus while the plus_private switch is on (Hillel, 2026-11-17).
        $private = app(PrivateSongs::class)->on();
        if ($private) {
            $rows['private_shared'] = (array) Lang::get('pages.pricing.private_row');
            $groups['others'] = [...$groups['others'], 'private_shared'];
        }
        // A song in your own voice downloads with Plus, while own_choir is on (Hillel, 2026-11-21).
        if (SongParts::switchedOn('own_choir')) {
            // Plus's minutes from config (own_choir.plus_minutes), as the Plus card says them.
            $rows['choir_songs'] = array_map(static fn (mixed $cell): mixed => is_string($cell) ? strtr($cell, PlusFeatures::replacements()) : $cell, (array) Lang::get('pages.pricing.choir_songs_row'));
            $rows['choir_download'] = (array) Lang::get('pages.pricing.choir_row');
            $groups['share'] = [...$groups['share'], 'choir_songs', 'choir_download'];
        }

        return response()->view('pages.pricing', [
            'title' => __('pages.pricing.title'),
            // Plus's features from the one list (PlusFeatures): `:plus_sentence` in the description.
            'meta' => $this->meta(__('pages.pricing.meta_title'), strtr((string) __('pages.pricing.description'), PlusFeatures::replacements()), '/pricing'),
            'price' => Prices::of($currency),
            'currencies' => Prices::CURRENCIES,
            'trialDays' => (int) config('billing.trial_days'),
            'freeSongs' => count((array) config('templates.free')),
            'songs' => app(Catalogue::class)->songCount(),
            'checkoutOpen' => filled(config('billing.prices.month')) && filled(config('billing.prices.year')),
            // The comparison in groups (the design round), each row's label and its Free and Plus cells; `same` marks
            // what both plans share, which "Only what's different" folds (a group whose rows are all the same, whole).
            'compare' => self::compare($groups, $rows, $limits),
            // The difference, in short: only the rows people decide on (ai-marketing's pick, 2026-11-23), every detail
            // folded under the short table. A row both plans share today (the switches decide some) isn't a difference.
            'decide' => array_values(array_filter(self::compare(['decide' => [...self::DECIDE, ...(SongParts::switchedOn('own_choir') ? ['choir_songs'] : [])]], $rows, $limits)[0]['rows'], static fn (array $row): bool => ! $row['same'])),
            'plans' => Landing::plans(),
            'smallPrint' => (array) Lang::get('pages.pricing.small_print'),
            // Each answer's first sentence is its short answer, shown first in bold (the UX review, 2026-11-23).
            // Five questions at most (Saul: "4-5 FAQ"), Is it really free? first.
            'faq' => array_slice(array_map(static fn (array $qa): array => [(string) $qa[0], ...self::shortFirst(str_replace(':days', (string) config('billing.trial_days'), (string) $qa[1]))], [
                ...self::pricingFaq(),
                ...($private ? [(array) Lang::get('pages.pricing.faq_private')] : []),
                ...(OpenSource::on() ? [(array) Lang::get('pages.pricing.faq_selfhost')] : []),
            ]), 0, 5),
        ], 200, $this->headers(varies: true));
    }

    /**
     * The pricing comparison's groups and rows: each row's label, its Free and Plus cells (a limit from config/usage
     * where there is one, else the words), and whether the two plans share it.
     *
     * @param  array<string, list<string>>  $groups
     * @param  array<mixed>  $rows
     * @param  array<string, array{string, string}>  $limits
     * @return list<array{key: string, same: bool, rows: list<array{label: string, cells: array{string, string}, same: bool}>}>
     */
    private static function compare(array $groups, array $rows, array $limits): array
    {
        $out = [];
        foreach ($groups as $group => $keys) {
            $lines = [];
            foreach ($keys as $key) {
                if (! is_array($rows[$key] ?? null)) {
                    continue;
                }
                // A plan's number fills only its placeholder (:free, :plus): a row whose words say "No" (inviting friends
                // while plus_teams is on, Free can't) keeps saying so (the UX review, 2026-11-23).
                $cells = [(string) $rows[$key][1], (string) $rows[$key][2]];
                $cells = [$cells[0] === ':free' && isset($limits[$key]) ? $limits[$key][0] : $cells[0], $cells[1] === ':plus' && isset($limits[$key]) ? $limits[$key][1] : $cells[1]];
                $lines[] = ['label' => (string) $rows[$key][0], 'cells' => $cells, 'same' => $cells[0] === $cells[1]];
            }
            $out[] = ['key' => $group, 'same' => $lines !== [] && array_filter($lines, static fn (array $l): bool => ! $l['same']) === [], 'rows' => $lines];
        }

        return $out;
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
     * The numbers in the guide's words: the prices in the visitor's currency (a month, a year, a year as a month), the
     * trial's days, and our address.
     *
     * @return array<string, string>
     */
    private function numbers(Request $request): array
    {
        $prices = Prices::of(Prices::currency($request));

        // Hear yourself as a choir's numbers, from config (own_choir), as the help page says them.
        $choir = ['plus_minutes' => (string) config('own_choir.plus_minutes'), 'plus_songs' => ltrim(PlusFeatures::replacements()[':plus_songs'], ':'), 'comped_songs' => (string) config('own_choir.comped_songs'), 'window_days' => (string) config('own_choir.window_days'), 'daily' => (string) config('own_choir.daily'), 'voice_months' => (string) config('own_choir.voice_months'), 'free_friends' => (string) config('own_choir.together.free_friends'), 'plus_friends' => (string) config('own_choir.together.plus_friends')];

        return [...$choir, 'price' => $prices['yearAsMonth'], 'price_month' => $prices['month'], 'price_year' => $prices['year'], 'days' => (string) config('billing.trial_days'), 'email' => (string) config('site.contact_email')];
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
