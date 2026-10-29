<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Support\Http\Hosts;
use App\Support\Legal\LegalText;
use App\Support\Pages\Landing;
use App\Support\Pages\MudeoArrival;
use App\Support\Pages\Prices;
use App\Support\Site\HelpGuide;
use App\Support\Site\OpenSource;
use App\Support\Site\SitePages;
use App\Support\Site\WhatsNew;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;

/**
 * About, the press kit and what's new (docs/public-site-plan.md). No session. About shows mudeo's
 * story only to mudeo's singers (MudeoArrival), so it's never cached as one visitor's page.
 */
final class SitePagesController extends Controller
{
    public function about(Request $request): Response
    {
        $ref = Landing::ref($request->query('ref'));
        $mudeo = MudeoArrival::is($request, $ref);

        return response()->view('site.about', [
            ...$this->frame('about', '/about', $ref),
            // Hillel's note: the contact address linked where it says :email, his name linked in the signature.
            'note' => array_map(static fn (mixed $paragraph): HtmlString => self::email((string) $paragraph), array_values((array) Lang::get('site.about.note'))),
            'signature' => self::founder((string) __('site.about.signature')),
            'mudeo' => $mudeo ? array_values(array_map('strval', (array) Lang::get('site.about.mudeo.body'))) : [],
            'openSource' => OpenSource::on(),
        ], 200, [...SongPagesController::headers(), 'Cache-Control' => 'no-cache', 'Vary' => 'Cookie']);
    }

    /**
     * How it works, on its own page (Hillel: like Pricing and Help): the landing's steps and its
     * four-voice mixer. There once the landing agent's view is (a missing one is the gone page).
     */
    public function howItWorks(Request $request): Response
    {
        if (! SitePages::hasHowItWorks()) {
            return response()->view('public.page-gone', ['links' => ['app' => '/']], 404, SongPagesController::headers());
        }

        $hero = Landing::hero();

        return response()->view('site.how-it-works', [
            ...$this->frame('how', '/how-it-works', Landing::ref($request->query('ref'))),
            'hero' => $hero,
            'pictures' => $hero !== null ? Landing::pictures($hero) : null,
        ], 200, [...SongPagesController::headers(), 'Cache-Control' => 'no-cache', 'Vary' => 'Cookie']);
    }

    public function press(Request $request): Response
    {
        $price = Prices::of(Prices::currency($request));

        return response()->view('site.press', [
            ...$this->frame('press', '/press', Landing::ref($request->query('ref'))),
            // The user guide's screenshots (both themes), those that are shot.
            'shots' => array_values(array_filter(array_map(static function (string $id, string $caption): ?array {
                $files = HelpGuide::shot($id);

                return $files === null ? null : ['id' => $id, 'caption' => $caption, 'files' => $files];
            }, array_keys((array) Lang::get('site.press_words.shots')), array_values((array) Lang::get('site.press_words.shots'))))),
            'facts' => array_map(static fn (mixed $v): HtmlString => self::founder(str_replace([':price', ':repo'], [$price['yearAsMonth'], OpenSource::repo()], (string) $v)), [
                ...(array) Lang::get('site.press.facts'),
                ...(OpenSource::on() ? [(string) __('site.press.open_source_label') => (string) __('site.press.open_source_fact')] : []),
            ]),
        ], 200, [...SongPagesController::headers(), 'Vary' => 'CF-IPCountry, Accept-Language']);
    }

    public function guides(Request $request, LegalText $text): Response
    {
        $title = SitePages::title((string) __('pages.guides.title'));

        return response()->view('site.guides', [
            'title' => $title,
            'meta' => ['title' => $title, 'description' => (string) __('pages.guides.description'), 'url' => Hosts::publicUrl().'/guides'],
            'links' => Landing::links(Landing::ref($request->query('ref'))),
            'cards' => array_map(static function (string $slug) use ($text): array {
                $guide = $text->read($slug, folder: 'guides');

                return ['slug' => $slug, 'name' => $guide['title'], 'summary' => (string) $guide['description'], 'url' => '/guides/'.$slug, 'soon' => false];
            }, DocumentPagesController::guides()),
        ], 200, SongPagesController::headers());
    }

    public function whatsNew(Request $request): Response
    {
        return response()->view('site.whats-new', [
            ...$this->frame('whats_new', '/whats-new', Landing::ref($request->query('ref'))),
            'entries' => WhatsNew::entries(),
        ], 200, SongPagesController::headers());
    }

    /**
     * Words with :founder made his name, linked to his site: escaped first, then the name alone linked.
     */
    private static function email(string $words): HtmlString
    {
        $email = (string) config('site.contact_email');
        $link = '<a class="s-lk" href="mailto:'.e($email).'">'.e($email).'</a>';

        return new HtmlString(str_replace(':email', $link, e($words)));
    }

    private static function founder(string $words): HtmlString
    {
        $link = '<a class="s-lk" href="'.e((string) config('site.founder_url')).'">'.e((string) config('site.founder')).'</a>';

        return new HtmlString(str_replace(':founder', $link, e($words)));
    }

    /**
     * @return array{title: string, meta: array{title: string, description: string, url: string}, links: array{start: string, app: string, plus: string, create: string}}
     */
    private function frame(string $key, string $path, ?string $ref): array
    {
        $title = SitePages::title((string) __('site.'.$key.'.title'));

        return [
            'title' => $title,
            'meta' => ['title' => $title, 'description' => (string) __('site.'.$key.'.description'), 'url' => Hosts::publicUrl().$path],
            'links' => Landing::links($ref),
        ];
    }
}
