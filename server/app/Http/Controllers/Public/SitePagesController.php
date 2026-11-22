<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Support\Admin\StaffDevice;
use App\Support\Flags;
use App\Support\Http\Hosts;
use App\Support\Languages\SongWords;
use App\Support\Legal\LegalText;
use App\Support\Pages\Landing;
use App\Support\Pages\MudeoArrival;
use App\Support\Pages\Prices;
use App\Support\Pages\YourChoirStage;
use App\Support\Site\HelpGuide;
use App\Support\Site\OpenSource;
use App\Support\Site\SitePages;
use App\Support\Site\WhatsNew;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\HtmlString;

/**
 * About, the press kit and what's new (docs/public-site-plan.md). No session. About shows mudeo's
 * story only to mudeo's singers (MudeoArrival), so it's never cached as one visitor's page.
 */
final class SitePagesController extends Controller
{
    /** /your-choir's library card (merged-v4): the songs a visitor knows, each as its tune's opening notes. */
    /** The switch for hearing a song of your own as a choir (named by the backend and frontend when they build it). */
    private const OWN_SONGS = 'own_choir_songs';

    private const CHOIR_SONGS = ['silent-night', 'amazing-grace', 'happy-birthday', 'joy-to-the-world'];

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

    /**
     * About AI voices (docs/ai-voices-build-plan.md, marketing item 2): unlisted. While both the ai_voices and own_choir
     * switches are off (or not yet defined) it is not found, except on an admin's device (StaffDevice::previews, the
     * Spanish preview's key), which sees it kept out of search and kept by nobody (the coordinator, 2026-11-20). "Your voice stays yours" says
     * what's true of own-voice songs while own_choir is on.
     */
    public function aiVoices(Request $request, Flags $flags): Response
    {
        // Live with either feature it explains: AI guides (ai_voices) or own-voice songs (own_choir), whose pages link here.
        $on = self::switchedOn($flags, 'ai_voices') || self::switchedOn($flags, 'own_choir');

        if (! $on && ! StaffDevice::previews($request)) {
            return response()->view('public.page-gone', ['links' => ['app' => '/about']], 404, [...SongPagesController::headers(), 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
        }

        return response()->view('site.ai-voices', [
            ...$this->frame('ai_voices', '/about/ai-voices', Landing::ref($request->query('ref'))),
            'indexed' => $on,
            'ownChoir' => $ownChoir = self::switchedOn($flags, 'own_choir'),
            // Only the choir is live (no AI guides yet): the page explains it alone (site.ai_voices.choir_only).
            'choirOnly' => $ownChoir && ! self::switchedOn($flags, 'ai_voices'),
            'remove' => self::email((string) __('site.ai_voices.remove.body')),
        ], 200, [...SongPagesController::headers(), ...($on ? [] : ['X-Robots-Tag' => 'noindex', 'Cache-Control' => 'no-store'])]);
    }

    /**
     * Hear yourself as a choir (docs/ai-voices-build-plan.md, phase 3's launch page): ready but unpublished. While the
     * own_choir switch is off (or not yet defined) it is not found, except on an admin's device, which sees it kept out
     * of search and kept by nobody, as About AI voices is.
     */
    public function yourChoir(Request $request, Flags $flags, YourChoirStage $stage): Response
    {
        $on = self::switchedOn($flags, 'own_choir');

        if (! $on && ! StaffDevice::previews($request)) {
            return response()->view('public.page-gone', ['links' => ['app' => '/']], 404, [...SongPagesController::headers(), 'Cache-Control' => 'no-store', 'X-Robots-Tag' => 'noindex']);
        }

        $frame = $this->frame('your_choir', '/your-choir', $ref = Landing::ref($request->query('ref')));
        // Its link card in the page's language, where one is drawn (tool/link_cards.py: your-choir, es-your-choir).
        $card = (app()->getLocale() === 'en' ? '' : app()->getLocale().'-').'your-choir';
        if (is_file(dirname(base_path()).'/web/icons/og/'.$card.'.png')) {
            $frame['meta']['image'] = Hosts::public('/icons/og/'.$card.'.png');
        }

        return response()->view('site.your-choir', [
            ...$frame,
            'indexed' => $on,
            // Silent Night in the app, ready to hear yourself as a choir (the app's `choir` link, agreed with the frontend).
            'try' => Hosts::app('/?'.http_build_query(['song' => 'silent-night', 'choir' => 1, 'ref' => $ref ?? 'your-choir'])),
            // The stage: one real voice becoming the AI's two parts, then four (YourChoirStage, from VoiceDemo's own
            // voices and gating); null, and the page shows no demo, until every voice is there.
            'stage' => $shown = $stage->stage(),
            // Without the demo's voices (track B may open before any singer's voice is licensed for AI; Hillel, 2026-11-21:
            // "lets support starting track B without A ... so there aren't samples"): the arrangement as a picture, no sound.
            'picture' => $shown === null ? YourChoirStage::picture(self::choirTemplates()->get((string) config('site.voice_demo.ai.song'))) : null,
            // A song of your own (hummed, or with your own words: Hillel, 2026-11-20), only once its switch is on (the
            // coordinator: the page never promises what isn't live); the app's create screen (agreed with the frontend).
            'own' => self::switchedOn($flags, self::OWN_SONGS) ? Hosts::app('/create?'.http_build_query(['ref' => $ref ?? 'your-choir'])) : null,
            // The library card's songs, each with its story's picture, as the songs hub shows them.
            'songs' => self::choirSongs(),
            'toolsUrl' => SitePages::toolsHome(),
            // A choir with friends, once its switch is on (docs/choir-together-plan.md): one line under the steps.
            'together' => self::switchedOn($flags, 'own_choir_together'),
            // Plus's prices in the visitor's currency, for the questions that name them (the marketing review, 2026-11-22).
            'price' => Prices::of(Prices::currency($request)),
        ], 200, [...SongPagesController::headers(), 'Vary' => 'CF-IPCountry, Accept-Language', ...($on ? [] : ['X-Robots-Tag' => 'noindex', 'Cache-Control' => 'no-store'])]);
    }

    /**
     * The songs on /your-choir's library card: the four of merged-v4, those that are open, each with its title and its
     * tune's opening as a lane of notes (never a photo on this page: Hillel, 2026-11-20, "they look out of place").
     *
     * @return list<array{slug: string, title: string, tune: array{beats: float, notes: list<array{beat: float, length: float, y: float}>}, url: string}>
     */
    private static function choirSongs(): array
    {
        $songs = self::choirTemplates();

        return array_values(array_filter(array_map(static function (string $slug) use ($songs): ?array {
            $song = $songs->get($slug);

            return $song === null ? null : [
                'slug' => $slug,
                'title' => SongWords::title((string) $song->title, $slug),
                'tune' => YourChoirStage::tune($song),
                'url' => '/songs/'.$slug,
            ];
        }, self::CHOIR_SONGS)));
    }

    /**
     * The library card's songs with their current versions, loaded once a request (the picture reuses its song).
     *
     * @return Collection<string, Template>
     */
    private static function choirTemplates(): Collection
    {
        return once(static fn () => Template::query()->official()->whereNull('hidden_at')->whereIn('slug', self::CHOIR_SONGS)->with('currentVersion')->get()->keyBy('slug'));
    }

    /** A switch that may not be defined yet: off until it is. */
    private static function switchedOn(Flags $flags, string $name): bool
    {
        return $flags->exists($name) && $flags->on($name);
    }

    public function press(Request $request): Response
    {
        $price = Prices::of(Prices::currency($request));

        return response()->view('site.press', [
            ...$this->frame('press', '/press', Landing::ref($request->query('ref'))),
            // The site's own pages first (web/icons/press/<id>-light|dark|game.png: the landing, a song page and the courses
            // page, in both skins), then the user guide's screenshots (both themes), those that are shot.
            'shots' => [...self::siteShots(), ...array_values(array_filter(array_map(static function (string $id, string $caption): ?array {
                $files = HelpGuide::shot($id);

                return $files === null ? null : ['id' => $id, 'caption' => $caption, 'files' => $files];
            }, array_keys((array) Lang::get('site.press_words.shots')), array_values((array) Lang::get('site.press_words.shots')))))],
            'facts' => array_map(static fn (mixed $v): HtmlString => self::founder(str_replace([':price_month', ':price_year', ':price', ':repo'], [$price['month'], $price['year'], $price['yearAsMonth'], OpenSource::repo()], (string) $v)), [
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
     * The site's own pages for the press kit (site.press_words.site_shots), each in its light, dark and game files when
     * all three are there, in the app shots' shape.
     *
     * @return list<array{id: string, caption: string, files: array<string, array{png: string, width: int, height: int}>}>
     */
    private static function siteShots(): array
    {
        $shots = [];
        foreach ((array) Lang::get('site.press_words.site_shots') as $id => $caption) {
            $files = [];
            foreach (['light', 'dark', 'game'] as $variant) {
                $path = dirname(base_path()).'/web/icons/press/'.$id.'-'.$variant.'.png';
                $size = is_file($path) ? getimagesize($path) : false;
                if ($size === false) {
                    continue 2;
                }
                $files[$variant] = ['png' => '/icons/press/'.$id.'-'.$variant.'.png', 'width' => (int) $size[0], 'height' => (int) $size[1]];
            }
            $shots[] = ['id' => (string) $id, 'caption' => (string) $caption, 'files' => $files];
        }

        return $shots;
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
