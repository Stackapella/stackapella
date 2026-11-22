{{-- Hear yourself as a choir (/your-choir and /es/your-choir; docs/ai-voices-build-plan.md, phase 3's launch page). Hillel,
     2026-11-20: "I want it really impressive, as immersive as our home page: something immersive to experience one real
     voice becoming 2 fake ones". Not found until the own_choir switch is on (an admin's device sees it first, noindex).
     Its own document, as the home page is (not the public layout): its own sheet (css/your-choir.css, every class .yc-*,
     the app's glass copied in by tool/glass_copy.py; the footer is the site's own component, dressed there) and its own
     script (js/public/your-choir.js). Calm light and dark only: a game-mode visitor sees the calm page.
     The first scenes are one stage that fills the screen and stays (App\Support\Pages\YourChoirStage, from the demo's own
     voices, VoiceDemo, which keeps its gating): a singer's real recording on one lane ("really singing"), then the AI
     voice made from her singing splitting into Silent Night's tune and harmony, two lanes, each plainly AI, then the
     whole choir when all four parts are there, then "now yours". One player on the audio clock plays them in that order;
     scrolling changes the picture, never the sound, and a lane is lit only while its voice sounds. Without every voice
     the stage has no demo: the headline and "now yours" only, unpinned. After the stage the page scrolls: the ways in
     (the library, and a song of your own once its switch is on, `own`), what happens to your voice, the questions, the
     close. Every scene's words are here, in order, so the page reads and paints with no script; with reduced motion (or
     no script) nothing is pinned and the scenes are still panels. --}}
@php($web = (string) config('app.web_path'))
{{-- Without the demo's voices the stage is a picture (YourChoirStage::picture, Hillel, 2026-11-21: "lets support starting
     track B without A ... so there aren't samples"): the arrangement's notes as lanes, no sound, no singer: one voice line
     becoming four parts in the first screen, then the four parts with their words, then "now yours". The designed
     default; the audio stage, when a singer's voices are licensed, is the extra. --}}
@php($pic = $stage === null && ($picture ?? null) !== null)
@php($views = $stage !== null ? array_values(array_filter(['hero', 'real', 'split', $stage['four'] ? 'four' : null, 'yours'])) : ($pic ? ['hero', 'four', 'yours'] : ['hero', 'yours']))
@php($pinned = count($views))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ __('meta.dir') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta property="og:locale" content="{{ \App\Support\Languages\SiteLanguages::ogLocale(app()->getLocale()) }}">
    @if (! $indexed || ! config('ops.indexing'))
        <meta name="robots" content="noindex">
    @endif
    <title>{{ $title }}</title>
    @include('pages._head')
    <link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#050403">
    @if (config('hosts.pass_domain'))
        <meta name="theme-cookie-domain" content="{{ config('hosts.pass_domain') }}">
    @endif
    <link rel="preload" href="/fonts/figtree-latin.woff2" as="font" type="font/woff2" crossorigin>
    <script src="{{ \App\Support\Http\StaticVersions::url($web, '/js/theme-boot.js') }}"></script>
    <link rel="stylesheet" href="{{ \App\Support\Http\StaticVersions::url($web, '/css/your-choir.css') }}">
    {{-- The Game mode switch's own rules, as the site's sheet has them (css/game-toggle.css). --}}
    <link rel="stylesheet" href="{{ \App\Support\Http\StaticVersions::url($web, '/css/game-toggle.css') }}">
</head>
<body>
    <a class="yc-skip" href="#yc-s0">{{ __('landing.skip') }}</a>
    @if (is_string($links['partner'] ?? null))
        <div class="yc-notes" data-yc-note><p class="yc-note-bar"><span>{{ __('pages.site.partner_line') }}</span> <a href="{{ $links['partner'] }}" class="yc-lk">{{ __('pages.site.partner_link') }}</a></p></div>
    @endif
    <div class="yc" id="yc" data-yc data-view="hero" @if ($stage !== null || $pic) data-stage @endif @if ($stage !== null && $stage['four']) data-four @endif @if ($pic) data-picture @endif>
        @if ($stage !== null)
            <script type="application/json" data-yc-data>@json($stage['play'])</script>
        @endif
        <header class="yc-top" data-yc-top>
            <a href="/" class="yc-logo s-brand" aria-label="{{ __('landing.home') }}">{{ \App\Support\Site\Icons::hat() }}{{ \App\Support\Site\Icons::mark(26) }}<span>{{ __('meta.brand') }}</span></a>
            <nav class="yc-nav" aria-label="{{ __('landing.pages') }}">
                <a href="/songs">{{ __('pages.nav.songs') }}</a>
                <a href="/courses">{{ __('pages.nav.courses') }}</a>
                <a href="{{ $toolsUrl }}">{{ __('pages.nav.tools') }}</a>
                <a href="/pricing">{{ __('pages.nav.pricing') }}</a>
            </nav>
            <x-site.game-toggle size="pill" class="yc-game" />
            <x-site.look-menu class="yc-look" />
            <a href="{{ $links['app'] }}" class="yc-open">{{ __('pages.nav.open_app') }}</a>
        </header>

        <main>
            <div class="yc-pin">
                <div class="yc-stage" data-yc-stage role="group" aria-label="{{ $pic ? __('site.your_choir.picture.label') : __('site.your_choir.stage.label') }}">
                    <div class="yc-bg" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>

                    {{-- The first screen: the headline, the one press that starts the experience, and the way in. --}}
                    <section class="yc-sc yc-hero" id="yc-s0" data-s="hero" aria-labelledby="yc-h0">
                        <div class="yc-cap">
                            <div>
                                <p class="yc-eyebrow"><span class="yc-dots4" aria-hidden="true"><i class="yc-v1"></i><i class="yc-v2"></i><i class="yc-v3"></i><i class="yc-v4"></i></span>{{ __('site.your_choir.eyebrow') }}</p>
                                <h1 id="yc-h0" class="yc-h1"><span>{{ __('site.your_choir.headline_before') }}</span> <em>{{ __('site.your_choir.headline_em') }}</em></h1>
                            </div>
                            <div>
                                <p class="yc-say">{{ $own !== null ? __('site.your_choir.lead_hum') : __('site.your_choir.lead') }}</p>
                                <div class="yc-go">
                                    @if ($stage !== null)
                                        {{-- What a press commits to: one real voice, then AI, and how long. --}}
                                        <button type="button" class="yc-play" data-yc-go><span class="yc-orb bubble" aria-hidden="true"><span class="yc-i-play"><x-site.icon name="play" size="22" /></span><span class="yc-i-stop"><x-site.icon name="stop" size="22" /></span></span><span class="yc-play-w"><span class="yc-i-play">{{ __('site.your_choir.stage.play') }}</span><span class="yc-i-stop">{{ __('site.your_choir.stage.stop') }}</span><small class="yc-meta">{{ __('site.your_choir.stage.play_meta', ['length' => $stage['length']]) }}</small><small class="yc-load" data-yc-load data-loading="{{ __('site.your_choir.stage.loading') }}" data-failed="{{ __('site.your_choir.stage.failed') }}"></small></span></button>
                                        <a href="{{ $try }}" class="yc-quiet" data-your-choir-try>{{ __('site.your_choir.start') }}</a>
                                    @else
                                        {{-- No demo to hear: the way in is the page's first press (the coordinator, 2026-11-21). --}}
                                        <a href="{{ $try }}" class="yc-prim" data-your-choir-try>{{ __('site.your_choir.start_voice') }} <x-site.icon name="arrow" size="18" /></a>
                                        @if ($own !== null)
                                            <a href="{{ $own }}" class="yc-quiet" data-your-choir-hum>{{ __('site.your_choir.hum') }}</a>
                                        @endif
                                    @endif
                                </div>
                                <p class="yc-note">{{ __('site.your_choir.start_note') }}</p>
                            </div>
                        </div>
                        @if ($stage !== null || $pic)
                            <p class="yc-cue" aria-hidden="true">{{ __('site.your_choir.stage.scroll') }} <x-site.icon name="chevron" size="14" /></p>
                        @endif
                    </section>

                    @if ($pic)
                        {{-- One voice, four parts: the picture opened, in words (AI named once, here and on the lanes' chips). --}}
                        <section class="yc-sc" id="yc-s1" data-s="four" aria-labelledby="yc-hp">
                            <div class="yc-cap">
                                <div>
                                    {{-- No eyebrow when its words are empty (one would echo the heading). --}}
                                    @if ((string) __('site.your_choir.picture.two.kick') !== '')
                                        <p class="yc-kick"><span class="yc-bars" aria-hidden="true"><i class="yc-on"></i><i class="yc-on"></i><i class="yc-on"></i><i class="yc-on"></i></span>{{ __('site.your_choir.picture.two.kick') }}</p>
                                    @endif
                                    <h2 id="yc-hp" class="yc-h2">{{ __('site.your_choir.picture.two.title_before') }} <em>{{ __('site.your_choir.picture.two.title_em') }}</em></h2>
                                </div>
                                <p class="yc-say">{{ __('site.your_choir.picture.two.body') }}</p>
                            </div>
                        </section>
                    @endif

                    @if ($stage !== null)
                        {{-- One real voice: the singer's own recording, as the app plays it. --}}
                        <section class="yc-sc" id="yc-s1" data-s="real" aria-labelledby="yc-h1">
                            <div class="yc-cap">
                                <div>
                                    <p class="yc-kick"><span class="yc-bars" aria-hidden="true"><i class="yc-on"></i><i></i><i></i><i></i></span>{{ __('site.your_choir.s1.kick') }}</p>
                                    {{-- Her name in the accent, wherever the words put it. --}}
                                    <h2 id="yc-h1" class="yc-h2">{{ \Illuminate\Support\Str::before((string) __('site.your_choir.s1.title'), ':name') }}<em>{{ $stage['real']['name'] }}</em>{{ \Illuminate\Support\Str::after((string) __('site.your_choir.s1.title'), ':name') }}</h2>
                                </div>
                                <p class="yc-say">{{ __('site.your_choir.s1.body', ['name' => $stage['real']['name'], 'song' => $stage['real']['song']]) }}</p>
                            </div>
                        </section>

                        {{-- The split: the AI voice made from her singing, as the song's tune and harmony. --}}
                        <section class="yc-sc" id="yc-s2" data-s="split" aria-labelledby="yc-h2">
                            <div class="yc-cap">
                                <div>
                                    <p class="yc-kick"><span class="yc-bars" aria-hidden="true"><i class="yc-on"></i><i class="yc-on"></i><i></i><i></i></span>{{ __('site.your_choir.s2.kick') }}</p>
                                    <h2 id="yc-h2" class="yc-h2">{{ __('site.your_choir.s2.title_before') }} <em>{{ __('site.your_choir.s2.title_em') }}</em></h2>
                                </div>
                                <p class="yc-say">{{ __('site.your_choir.s2.body', ['name' => $stage['ai']['name'], 'song' => $stage['ai']['song']]) }}</p>
                            </div>
                        </section>

                        @if ($stage['four'])
                            {{-- The whole choir: all four parts, still the one voice. --}}
                            <section class="yc-sc" id="yc-s3" data-s="four" aria-labelledby="yc-h3">
                                <div class="yc-cap">
                                    <div>
                                        <p class="yc-kick"><span class="yc-bars" aria-hidden="true"><i class="yc-on"></i><i class="yc-on"></i><i class="yc-on"></i><i class="yc-on"></i></span>{{ __('site.your_choir.s3.kick') }}</p>
                                        <h2 id="yc-h3" class="yc-h2">{{ __('site.your_choir.s3.title_before') }} <em>{{ __('site.your_choir.s3.title_em') }}</em></h2>
                                    </div>
                                    <p class="yc-say">{{ __('site.your_choir.s3.body', ['name' => $stage['ai']['name']]) }}</p>
                                </div>
                            </section>
                        @endif
                    @endif

                    {{-- How it works: the three steps (the way in is the hero's, once). --}}
                    <section class="yc-sc yc-yours" id="yc-s{{ $pinned - 1 }}" data-s="yours" aria-labelledby="yc-hy">
                        <div class="yc-cap">
                            <div>
                                <p class="yc-kick">{{ __('site.your_choir.s4.kick') }}</p>
                                <h2 id="yc-hy" class="yc-h2">{{ __('site.your_choir.s4.title') }}</h2>
                                {{-- The mark's four bars: the choir that's yours to make. --}}
                                <span class="yc-mark" aria-hidden="true"><i class="yc-v1"></i><i class="yc-v2"></i><i class="yc-v3"></i><i class="yc-v4"></i></span>
                            </div>
                            <div>
                                <ol class="yc-steps">
                                    @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.your_choir.steps') as $step)
                                        <li class="yc-v{{ $loop->iteration }}"><span class="yc-n" aria-hidden="true">{{ $loop->iteration }}</span><div><h3>{{ $step['title'] }}</h3><p>{{ $own !== null && $loop->iteration === 2 ? __('site.your_choir.step_choose_hum') : $step['body'] }}</p></div></li>
                                    @endforeach
                                </ol>
                                @if ($together ?? false)
                                    <p class="yc-say" data-yc-together>{{ __('site.your_choir.together') }}</p>
                                @endif
                                {{-- No second try block: the hero's is the page's one (Hillel, 2026-11-21: "the 'try it' block is
                                     also repeated"). --}}
                            </div>
                        </div>
                    </section>

                    @if ($pic)
                        {{-- The picture's lanes: the visitor's one voice line (the song's tune), then the four parts the AI
                             sings from it, each chipped AI. Notes only: no sound, no singer, no control. --}}
                        <div class="yc-world" aria-hidden="true">
                            <div class="yc-deck" data-yc-lanes>
                                @php($lanes = [['seg' => 'you', 'lane' => $picture['you'], 'n' => 1], ...array_map(static fn (array $lane, int $i): array => ['seg' => 'ai', 'lane' => $lane, 'n' => $i % 4 + 1], $picture['parts'], array_keys($picture['parts']))])
                                @foreach ($lanes as $l)
                                    @php($part = $l['lane']['part'])
                                    <div @class(['yc-ln', 'yc-v'.$l['n'], 'yc-real yc-you' => $l['seg'] === 'you', 'yc-ai' => $l['seg'] === 'ai']) data-seg="{{ $l['seg'] }}" data-part="{{ $part }}">
                                        <div class="yc-tr">
                                            @foreach ($l['lane']['notes'] as $note)
                                                <span class="yc-g gem-note" data-b="{{ $note['beat'] }}" data-l="{{ $note['length'] }}" style="--b: {{ $note['beat'] }}; --l: {{ $note['length'] }}; --y: {{ $note['y'] }}"><span class="gem-word">{{ $note['text'] }}</span></span>
                                            @endforeach
                                        </div>
                                        <div class="yc-lb">
                                            @if ($l['seg'] === 'you')
                                                <b><span class="yc-orb bubble bubble-sm">{{ mb_substr((string) __('site.your_choir.picture.you'), 0, 1) }}</span>{{ __('site.your_choir.picture.you') }}<span class="yc-chip">{{ __('site.your_choir.picture.you_chip') }}</span></b>
                                            @else
                                                <b><span class="yc-orb bubble bubble-sm">{{ mb_substr((string) __('pages.landing.hero.parts.'.$part), 0, 1) }}</span>{{ __('pages.landing.hero.parts.'.$part) }}<span class="yc-chip">{{ __('site.your_choir.stage.ai_chip') }}</span></b>
                                                <small>{{ $picture['song'] }} · {{ $part === 'soprano' ? __('site.your_choir.stage.roles.tune') : __('site.your_choir.stage.roles.harmony') }}</small>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <nav class="yc-dots" aria-label="{{ __('landing.scenes') }}" data-yc-dots>
                            @for ($i = 0; $i < $pinned; $i++)
                                <a href="#yc-at-{{ $i }}" aria-label="{{ __('landing.scene', ['n' => $i + 1, 'count' => $pinned]) }}" @if ($i === 0) aria-current="true" @endif></a>
                            @endfor
                        </nav>
                    @endif

                    @if ($stage !== null)
                        {{-- The lanes, in every scene of the stage: the real one, then the AI ones (each says which it is,
                             once, on its label). A note is the app's glass gem with its syllable; nothing here is a
                             control: the one player is the dock's. --}}
                        <div class="yc-world" aria-hidden="true">
                            <div class="yc-deck" data-yc-lanes>
                                @php($lanes = [['seg' => 'real', 'lane' => $stage['real']['lane'], 'n' => 1], ...array_map(static fn (array $lane, int $i): array => ['seg' => 'ai', 'lane' => $lane, 'n' => $i % 4 + 1], $stage['ai']['lanes'], array_keys($stage['ai']['lanes']))])
                                @foreach ($lanes as $l)
                                    @php($part = $l['lane']['part'])
                                    <div class="yc-ln yc-{{ $l['seg'] }} yc-v{{ $l['n'] }}" data-seg="{{ $l['seg'] }}" data-part="{{ $part }}" data-join="{{ $l['seg'] === 'ai' && $stage['four'] && in_array($part, ['tenor', 'bass'], true) ? $stage['ai']['join'] : 0 }}">
                                        <div class="yc-tr">
                                            @foreach ($l['lane']['notes'] as $note)
                                                <span class="yc-g gem-note" data-b="{{ $note['beat'] }}" data-l="{{ $note['length'] }}" style="--b: {{ $note['beat'] }}; --l: {{ $note['length'] }}; --y: {{ $note['y'] }}"><span class="gem-word">{{ $note['text'] }}</span></span>
                                            @endforeach
                                        </div>
                                        <div class="yc-lb">
                                            <b><span class="yc-orb bubble bubble-sm">{{ mb_substr((string) __('pages.landing.hero.parts.'.$part), 0, 1) }}</span>{{ __('pages.landing.hero.parts.'.$part) }}<span class="yc-chip">{{ $l['seg'] === 'real' ? __('site.your_choir.stage.real_chip') : __('site.your_choir.stage.ai_chip') }}</span><i class="yc-mt"><i></i><i></i><i></i><i></i></i></b>
                                            <small>{{ $l['seg'] === 'real' ? $stage['real']['song'] : $stage['ai']['song'] }} · {{ $part === 'soprano' ? __('site.your_choir.stage.roles.tune') : __('site.your_choir.stage.roles.harmony') }}</small>
                                        </div>
                                    </div>
                                @endforeach
                                <i class="yc-beam"></i>
                            </div>
                        </div>
                        {{-- Who is heard, while it plays (the script writes it, and tells a screen reader). --}}
                        <p class="yc-now" aria-hidden="true" data-yc-now data-real="{{ __('site.your_choir.now.real', ['name' => $stage['real']['name'], 'song' => $stage['real']['song']]) }}" data-two="{{ __('site.your_choir.now.two', ['song' => $stage['ai']['song']]) }}" data-four="{{ __('site.your_choir.now.four', ['song' => $stage['ai']['song']]) }}"></p>

                        <nav class="yc-dots" aria-label="{{ __('landing.scenes') }}" data-yc-dots>
                            @for ($i = 0; $i < $pinned; $i++)
                                <a href="#yc-at-{{ $i }}" aria-label="{{ __('landing.scene', ['n' => $i + 1, 'count' => $pinned]) }}" @if ($i === 0) aria-current="true" @endif></a>
                            @endfor
                        </nav>
                    @endif
                </div>
                @if ($stage !== null || $pic)
                    {{-- One screen of scroll for each of the stage's scenes: where the page rests, and what the dots lead to. --}}
                    <div class="yc-snaps" aria-hidden="true">
                        @for ($i = 0; $i < $pinned; $i++)
                            <div id="yc-at-{{ $i }}"></div>
                        @endfor
                    </div>
                @endif
            </div>

            {{-- From here the page scrolls like a page, in the stage's dress: the same type, glass and lanes, and at the
                 foot of each section a floor of lanes that, while the song plays, is the song itself. --}}
            <div class="yc-tail" data-yc-tail>
                {{-- The ways in: the library's songs, each as its tune's opening in glass (never a photo on this page), and
                     (once its switch is on) a song of your own. --}}
                <section class="yc-sec yc-ways" aria-labelledby="yc-ways">
                    <div class="yc-cap">
                        <div>
                            <p class="yc-kick">{{ __('site.your_choir.ways.library.kick') }}</p>
                            <h2 id="yc-ways" class="yc-h2">{{ $own !== null ? __('site.your_choir.ways.title_hum') : __('site.your_choir.ways.title') }}</h2>
                        </div>
                        <div>
                            <h3 class="yc-h4">{{ __('site.your_choir.ways.library.heading') }}</h3>
                            <p class="yc-say">{{ __('site.your_choir.ways.library.body') }}</p>
                            <p class="yc-more"><a href="/songs" class="yc-lk">{{ __('site.your_choir.ways.library.all') }}</a></p>
                        </div>
                    </div>
                    @if ($songs !== [])
                        <ul class="yc-library">
                            @foreach ($songs as $song)
                                <li class="yc-v{{ $loop->iteration }}">
                                    <a href="{{ $song['url'] }}" class="yc-song">
                                        <b>{{ $song['title'] }}</b>
                                        <span class="yc-song-tr" aria-hidden="true" style="--beats: {{ max(1, $song['tune']['beats']) }}">
                                            @foreach ($song['tune']['notes'] as $note)
                                                <i class="gem-note" style="--b: {{ $note['beat'] }}; --l: {{ $note['length'] }}; --y: {{ $note['y'] }}"></i>
                                            @endforeach
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                    @if ($own !== null)
                        <article class="yc-glass yc-own">
                            <div>
                                <p class="yc-kick">{{ __('site.your_choir.ways.own.kick') }}</p>
                                <h3 class="yc-h4">{{ __('site.your_choir.ways.own.heading') }}</h3>
                            </div>
                            <div>
                                <p class="yc-say">{{ __('site.your_choir.ways.own.body') }}</p>
                                <a href="{{ $own }}" class="yc-quiet" data-your-choir-hum>{{ __('site.your_choir.ways.own.cta') }}</a>
                            </div>
                        </article>
                    @endif
                    <p class="yc-learn">{{ __('site.your_choir.learn') }}</p>
                    <div class="yc-floor" aria-hidden="true"><span><i></i><i></i><i></i><i></i></span></div>
                </section>

                {{-- What happens to your voice: plain facts, AI named once, on one panel of glass. --}}
                <section class="yc-sec" aria-labelledby="yc-voice">
                    <div class="yc-cap yc-cap-top">
                        <h2 id="yc-voice" class="yc-h2">{{ __('site.your_choir.voice.title') }}</h2>
                        <div class="yc-glass yc-facts-panel">
                            <ul class="yc-facts">
                                @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.your_choir.voice.facts') as $fact)
                                    <li>{{ $fact }}</li>
                                @endforeach
                            </ul>
                            <p class="yc-more"><a href="/about/ai-voices" class="yc-lk">{{ __('site.your_choir.more') }}</a></p>
                        </div>
                    </div>
                    <div class="yc-floor" aria-hidden="true"><span><i></i><i></i><i></i><i></i></span></div>
                </section>

                <section class="yc-sec" aria-labelledby="yc-faq">
                    <div class="yc-cap yc-cap-top">
                        <h2 id="yc-faq" class="yc-h2">{{ __('site.your_choir.faq_title') }}</h2>
                        <div class="yc-faq">
                            {{-- The questions; with songs of your own live, the humming one after the second, and the own-songs
                                 answer as it is then. --}}
                            @php($faq = (array) \Illuminate\Support\Facades\Lang::get('site.your_choir.faq'))
                            @if ($own !== null)
                                @php($faq = [...array_slice($faq, 0, 2, true), 'hum' => (array) \Illuminate\Support\Facades\Lang::get('site.your_choir.faq_hum'), ...array_slice($faq, 2, null, true)])
                                @php($faq['own_songs'][1] = (string) __('site.your_choir.own_songs_hum'))
                            @endif
                            @foreach ($faq as $qa)
                                <details class="yc-glass" name="yc-faq" @if ($loop->first) open @endif>
                                    <summary>{{ $qa[0] }}<span class="yc-orb bubble" aria-hidden="true"><span class="yc-plus-i"><x-site.icon name="plus" size="16" /></span><span class="yc-minus-i"><x-site.icon name="minus" size="16" /></span></span></summary>
                                    <p>{{ strtr((string) $qa[1], [':price_month' => $price['month'], ':price_year' => $price['year']]) }}</p>
                                </details>
                            @endforeach
                        </div>
                    </div>
                    <div class="yc-floor" aria-hidden="true"><span><i></i><i></i><i></i><i></i></span></div>
                </section>

                {{-- The end: the mark's bars, left-aligned as the mark always is (Hillel: "always keep our logo bars left
                     aligned"), and one plain button, no heading or lines again (Hillel: "feels like the text is too
                     repetitive", "the 'try it' block is also repeated"). --}}
                <section class="yc-sec yc-close" aria-label="{{ __('site.your_choir.start_voice') }}">
                    <span class="yc-mark" aria-hidden="true"><i class="yc-v1"></i><i class="yc-v2"></i><i class="yc-v3"></i><i class="yc-v4"></i></span>
                    <p class="yc-go"><a href="{{ $try }}" class="yc-prim" data-your-choir-try>{{ __('site.your_choir.start_voice') }} <x-site.icon name="arrow" size="18" /></a></p>
                </section>
            </div>
        </main>
        <div class="yc-foot-wrap"><x-site.footer /></div>

        @if ($stage !== null)
            {{-- The dock: it stays through the whole page. The one player: Play and Stop, the time, who is heard, and the
                 way in. --}}
            <div class="yc-dock" role="group" aria-label="{{ __('site.your_choir.stage.label') }}">
                <i class="yc-rail" aria-hidden="true"></i>
                <div class="yc-tp">
                    <button type="button" class="yc-disc yc-orb bubble" data-yc-play aria-label="{{ __('site.your_choir.stage.play') }}" data-label-play="{{ __('site.your_choir.stage.play') }}" data-label-stop="{{ __('site.your_choir.stage.stop') }}"><span class="yc-i-play"><x-site.icon name="play" size="20" /></span><span class="yc-i-stop"><x-site.icon name="stop" size="20" /></span></button>
                    <span class="yc-time"><span class="yc-sr">{{ __('site.your_choir.stage.time') }}</span> <span data-yc-time>0:00 / {{ $stage['length'] }}</span></span>
                    <span class="yc-load" aria-hidden="true" data-yc-load="dock" data-loading="{{ __('site.your_choir.stage.loading') }}" data-failed="{{ __('site.your_choir.stage.failed') }}"></span>
                </div>
                <p class="yc-who" aria-hidden="true" data-yc-who></p>
                <a href="{{ $try }}" class="yc-start" data-your-choir-try>{{ __('site.your_choir.start') }}</a>
            </div>
            <p class="yc-sr" role="status" aria-live="polite" data-yc-live data-playing="{{ __('site.your_choir.live.playing') }}" data-stopped="{{ __('site.your_choir.live.stopped') }}"></p>
        @endif
    </div>

    {{-- The player's words for loading, a slow load and a failure (js/public/play-state.js reads them by this id). --}}
    <template id="s-play-words" data-loading="{{ __('site.your_choir.stage.loading') }}" data-slow="{{ __('pages.site.play_slow') }}" data-failed="{{ __('site.your_choir.stage.failed') }}"></template>
    <script type="module" src="/js/public/your-choir.js"></script>
</body>
</html>
