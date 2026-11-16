{{-- The landing's next version, the gemini variant (/next-gemini; docs/landing-next-gemini-plan.md): a candidate Hillel
     compares, built from scratch as a film in six full-screen scenes. Its own document (not the public layout), its own
     sheet (css/landing-next-gemini.css, every class .lngm-*) and its own small script; calm light and calm dark only,
     whatever the skin. The stage is one player for js/public/mixer.js (its hooks, unchanged): the verse's four lanes of
     gems, placed here by the server from the song's notes, so the first frame needs no script. Never indexed, no
     canonical, no alternates, no site header or footer. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ __('meta.dir') }}" data-lngm>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('next_gemini.title') }}</title>
    <meta name="description" content="{{ __('next_gemini.description') }}">
    <link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#050406">
    @if (config('hosts.pass_domain'))
        <meta name="theme-cookie-domain" content="{{ config('hosts.pass_domain') }}">
    @endif
    @php($web = (string) config('app.web_path'))
    <link rel="preload" href="/fonts/figtree-latin.woff2" as="font" type="font/woff2" crossorigin>
    <script src="{{ \App\Support\Http\StaticVersions::url($web, '/js/theme-boot.js') }}"></script>
    <link rel="stylesheet" href="{{ \App\Support\Http\StaticVersions::url($web, '/css/landing-next-gemini.css') }}">
</head>
<body class="lngm">
    <a class="lngm-skip" href="#lngm-stage">{{ __('next_gemini.skip') }}</a>

    {{-- The page's own bar, see-through over the stage: it shrinks as the page scrolls down and comes back on the way
         up (js/public/landing-next-gemini.js; without the script it stays whole). --}}
    <header class="lngm-bar" data-lngm-bar>
        <a href="{{ $links['home'] }}" class="lngm-brand">{{ \App\Support\Site\Icons::mark(30) }}<span translate="no">{{ __('meta.brand') }}</span></a>
        <nav class="lngm-nav" aria-label="{{ __('meta.brand') }}">
            <a href="{{ $links['songs'] }}">{{ __('pages.nav.songs') }}</a>
            <a href="{{ $links['courses'] }}">{{ __('pages.nav.courses') }}</a>
            <a href="{{ $links['tools'] }}">{{ __('pages.nav.tools') }}</a>
            <a href="{{ $links['pricing'] }}">{{ __('pages.nav.pricing') }}</a>
        </nav>
        {{-- Stops the song from any scene, once it plays (the stage's own button may be a screen away). --}}
        <button type="button" class="lngm-bar-stop" data-lngm-stop hidden aria-label="{{ __('next_gemini.stop') }}"><x-site.icon name="stop" size="16" /></button>
        <button type="button" class="lngm-iconbtn" data-lngm-theme aria-label="{{ __('pages.nav.theme') }}"><span class="lngm-on-light"><x-site.icon name="moon" size="18" /></span><span class="lngm-on-dark"><x-site.icon name="sun" size="18" /></span></button>
        <details class="lngm-lang" data-lngm-menu>
            <summary aria-label="{{ __('next_gemini.language') }}"><x-site.icon name="globe" size="18" /><span translate="no">{{ strtoupper($language) }}</span></summary>
            <ul>
                <li><a href="{{ $links['en'] }}" lang="en" hreflang="en" @if ($language === 'en') aria-current="true" @endif>{{ \App\Support\Languages\LanguageMenu::NAMES['en'] }}</a></li>
                <li><a href="{{ $links['es'] }}" lang="es" hreflang="es" @if ($language === 'es') aria-current="true" @endif>{{ \App\Support\Languages\LanguageMenu::NAMES['es'] }}</a></li>
            </ul>
        </details>
        <a href="{{ $links['app'] }}" class="lngm-open">{{ __('pages.nav.open_app') }}</a>
        <details class="lngm-more" data-lngm-menu>
            <summary aria-label="{{ __('pages.nav.menu') }}"><x-site.icon name="menu" size="20" /></summary>
            <ul>
                <li><a href="{{ $links['songs'] }}">{{ __('pages.nav.songs') }}</a></li>
                <li><a href="{{ $links['courses'] }}">{{ __('pages.nav.courses') }}</a></li>
                <li><a href="{{ $links['tools'] }}">{{ __('pages.nav.tools') }}</a></li>
                <li><a href="{{ $links['pricing'] }}">{{ __('pages.nav.pricing') }}</a></li>
                {{-- On a phone the bar keeps the logo, Open the app and this menu: light or dark and the language live here. --}}
                <li><button type="button" data-lngm-theme><span class="lngm-on-light"><x-site.icon name="moon" size="18" /></span><span class="lngm-on-dark"><x-site.icon name="sun" size="18" /></span>{{ __('pages.nav.theme') }}</button></li>
                <li><a href="{{ $links['en'] }}" lang="en" hreflang="en" @if ($language === 'en') aria-current="true" @endif>{{ \App\Support\Languages\LanguageMenu::NAMES['en'] }}</a></li>
                <li><a href="{{ $links['es'] }}" lang="es" hreflang="es" @if ($language === 'es') aria-current="true" @endif>{{ \App\Support\Languages\LanguageMenu::NAMES['es'] }}</a></li>
            </ul>
        </details>
    </header>

    <main class="lngm-film" id="lngm-film" data-player data-lngm-film data-scene="0" @if ($stage !== null) style="--beats: {{ $stage['beats'] }}; --lead-beats: {{ \App\Support\Pages\LandingNextGemini::LEAD }}" @endif>
        @if ($stage !== null)
            @php($song = $stage['song'])
            <script type="application/json" data-song>@json($song)</script>
        @endif

        {{-- Act one: the stage stays (sticky) while three scenes pass over it. --}}
        <div class="lngm-act">
            <div class="lngm-stage" id="lngm-stage">
                <div class="lngm-room" aria-hidden="true"></div>
                @if ($stage !== null)
                    <div class="lngm-deck" role="group" aria-label="{{ __('next_gemini.parts_label') }}" data-lngm-deck>
                        @foreach ($stage['lanes'] as $lane)
                            @php($part = mb_strtolower($lane['name']))
                            {{-- A lane is its voice for the mixer (data-voice): the whole lane is the button that plays it alone, and
                                 again brings every voice back. The hidden switch only tells the mixer the lane itself isn't one. --}}
                            <div class="lngm-lane lngm-v{{ $lane['n'] }}" data-voice="{{ $lane['key'] }}" data-part-key="{{ $lane['key'] }}" data-lngm-lane="{{ $loop->index }}" @if ($loop->first) data-focus @endif>
                                <span data-toggle aria-checked="true" hidden></span>
                                <button type="button" class="lngm-lane-hit" data-hear-alone aria-pressed="false" tabindex="{{ $loop->first ? '0' : '-1' }}" aria-label="{{ __('next_gemini.hear_only', ['part' => $part]) }}" data-label="{{ __('next_gemini.hear_only', ['part' => $part]) }}" data-label-back="{{ __('next_gemini.hear_all') }}" data-said="{{ __('next_gemini.said_alone', ['part' => $part]) }}"></button>
                                <span class="lngm-lane-name" aria-hidden="true"><i class="lngm-tri"></i><b>{{ $lane['name'] }}</b><span class="lngm-meter"><i></i><i></i><i></i><i></i><i></i></span>@if ($lane['role'] !== null)<small>{{ $lane['role'] }}</small>@endif<span class="lngm-chip">{{ __('next_gemini.alone') }}</span></span>
                                <span class="lngm-window" aria-hidden="true"><span class="lngm-track">
                                    @foreach ($lane['gems'] as $gem)<span @class(['lngm-gem', 'lngm-early' => $gem['early']]) data-beat="{{ $gem['beat'] }}" data-length="{{ $gem['length'] }}" style="--b: {{ $gem['beat'] }}; --l: {{ $gem['length'] }}; --y: {{ $gem['y'] }}"><span translate="no">{{ $gem['text'] }}</span></span>@endforeach
                                    <span class="lngm-trace"><svg viewBox="0 0 {{ $stage['beats'] }} 1" preserveAspectRatio="none" focusable="false"><path d="{{ $lane['trace'] }}" fill="none" vector-effect="non-scaling-stroke" /></svg></span>
                                    <span class="lngm-voice">{{ __('next_gemini.s2_voice') }}</span>
                                </span></span>
                                <a href="{{ $lane['learn'] }}" class="lngm-mine" aria-label="{{ __('next_gemini.mine_label', ['part' => $part]) }}" data-lngm-learn="{{ __('next_gemini.s2_learn', ['part' => $part]) }}">{{ __('next_gemini.mine') }}</a>
                            </div>
                        @endforeach
                        <span class="lngm-head" aria-hidden="true"></span>
                    </div>

                    <div class="lngm-go">
                        <button type="button" class="lngm-play" data-play aria-label="{{ __('next_gemini.play', ['song' => $song['title']]) }}" data-label-play="{{ __('next_gemini.play', ['song' => $song['title']]) }}" data-label-stop="{{ __('next_gemini.stop') }}"><span class="lngm-i-play"><x-site.icon name="play" size="44" /></span><span class="lngm-i-stop"><x-site.icon name="stop" size="40" /></span></button>
                        <p class="lngm-go-label" aria-hidden="true"><b translate="no">{{ __('next_gemini.hear', ['song' => $song['title']]) }}</b><span>{{ __('next_gemini.voices', ['count' => count($song['parts'])]) }} · <span data-time>0:00 / {{ $song['length'] }}</span></span></p>
                    </div>

                    <p class="lngm-words" aria-hidden="true" translate="no">@foreach ($stage['lines'] as $line)<span data-line>@foreach ($line as $word)<span data-beat="{{ $word['beat'] }}">{{ $word['text'] }}</span>{{ $word['joins'] ? '' : ' ' }}@endforeach</span>@endforeach</p>
                    <p class="lngm-foot"><span>{{ __('next_gemini.hint') }}</span>@if ($stage['credit'] !== null)<span class="lngm-credit" translate="no">{{ $stage['credit'] }}</span>@endif</p>
                    <p class="lngm-sr" role="status" data-lngm-say data-all="{{ __('next_gemini.said_all') }}"></p>
                    <p class="lngm-sr">{{ __('pages.landing.hero.alt_open', ['song' => $song['title'], 'words' => \App\Support\Pages\Landing::sentence($song['words'])]) }}</p>
                @endif
            </div>

            <section class="lngm-scene lngm-s0" id="lngm-s0" data-lngm-scene="0" aria-labelledby="lngm-h">
                <h1 id="lngm-h"><span>{{ __('next_gemini.headline') }}</span> <em>{{ __('next_gemini.headline_em') }}</em></h1>
                <div class="lngm-say">
                    <p class="lngm-lead">{{ __('next_gemini.lead') }}</p>
                    <a href="{{ $links['start'] }}" class="lngm-quiet">{{ __('next_gemini.start') }} <x-site.icon name="arrow" size="16" /></a>
                </div>
            </section>

            <section class="lngm-scene lngm-cap lngm-s1" id="lngm-s1" data-lngm-scene="1" aria-labelledby="lngm-h1">
                <span class="lngm-ghost" aria-hidden="true">1</span>
                <div class="lngm-cap-in">
                    <p class="lngm-num"><b>1</b><span>{{ __('next_gemini.steps') }}</span><span class="lngm-sr">{{ __('next_gemini.step', ['n' => 1, 'count' => 4]) }}</span></p>
                    <h2 id="lngm-h1"><span>{{ __('next_gemini.s1_title') }}</span> <em>{{ __('next_gemini.s1_em') }}</em></h2>
                    <p class="lngm-text">{{ __('next_gemini.s1_text') }}</p>
                </div>
            </section>

            <section class="lngm-scene lngm-cap lngm-s2" id="lngm-s2" data-lngm-scene="2" aria-labelledby="lngm-h2">
                <span class="lngm-ghost" aria-hidden="true">2</span>
                <div class="lngm-cap-in">
                    <p class="lngm-num"><b>2</b><span>{{ __('next_gemini.steps') }}</span><span class="lngm-sr">{{ __('next_gemini.step', ['n' => 2, 'count' => 4]) }}</span></p>
                    <h2 id="lngm-h2"><span>{{ __('next_gemini.s2_title') }}</span> <em>{{ __('next_gemini.s2_em') }}</em></h2>
                    <p class="lngm-text">{{ __('next_gemini.s2_text') }}</p>
                    @if ($stage !== null)
                        <a href="{{ $stage['lanes'][0]['learn'] }}" class="lngm-btn lngm-btn-soft" data-lngm-learn-link>{{ __('next_gemini.s2_learn', ['part' => mb_strtolower($stage['lanes'][0]['name'])]) }} <x-site.icon name="arrow" size="16" /></a>
                    @endif
                </div>
            </section>
        </div>

        <section class="lngm-scene lngm-cap lngm-s3" id="lngm-s3" data-lngm-scene="3" aria-labelledby="lngm-h3">
            <span class="lngm-ghost" aria-hidden="true">3</span>
            <div class="lngm-cap-in">
                <p class="lngm-num"><b>3</b><span>{{ __('next_gemini.steps') }}</span><span class="lngm-sr">{{ __('next_gemini.step', ['n' => 3, 'count' => 4]) }}</span></p>
                <h2 id="lngm-h3"><span>{{ __('next_gemini.s3_title') }}</span> <em>{{ __('next_gemini.s3_em') }}</em></h2>
                <p class="lngm-text">{{ __('next_gemini.s3_text') }}</p>
            </div>
            @if ($stage !== null)
                {{-- A picture of the Studio mid-song: the first part recorded, the second being recorded, the rest waiting. Its
                     waves are drawn from each part's own notes. --}}
                <ol class="lngm-takes">
                    @foreach ($stage['lanes'] as $lane)
                        @php($state = $loop->index === 0 ? 'done' : ($loop->index === 1 ? 'now' : 'next'))
                        <li class="lngm-take lngm-v{{ $lane['n'] }} lngm-take-{{ $state }}">
                            <span class="lngm-bub" aria-hidden="true">{{ $state === 'next' ? $loop->iteration : __('next_gemini.s3_you') }}</span>
                            <span class="lngm-take-name"><b>{{ $lane['name'] }}</b><small>{{ $state === 'done' ? __('next_gemini.s3_done') : ($state === 'now' ? __('next_gemini.s3_now') : __('next_gemini.s3_next')) }}</small></span>
                            <span class="lngm-wave" aria-hidden="true">@foreach (\App\Support\Pages\LandingNextGemini::wave($lane['gems'], $stage['beats']) as $level)<i style="--h: {{ $level }}; --i: {{ $loop->index }}"></i>@endforeach</span>
                            @if ($state === 'now')
                                <span class="lngm-rec" aria-hidden="true">{{ __('next_gemini.s3_rec') }}</span>
                            @elseif ($state === 'done')
                                <span class="lngm-done" aria-hidden="true">{{ __('next_gemini.s3_recorded') }} <x-site.icon name="check" size="14" /></span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="lngm-scene lngm-cap lngm-s4" id="lngm-s4" data-lngm-scene="4" aria-labelledby="lngm-h4">
            <span class="lngm-ghost" aria-hidden="true">4</span>
            <div class="lngm-cap-in">
                <p class="lngm-num"><b>4</b><span>{{ __('next_gemini.steps') }}</span><span class="lngm-sr">{{ __('next_gemini.step', ['n' => 4, 'count' => 4]) }}</span></p>
                <h2 id="lngm-h4"><span>{{ __('next_gemini.s4_title') }}</span> <em>{{ __('next_gemini.s4_em') }}</em></h2>
                <p class="lngm-text">{{ __('next_gemini.s4_text') }}</p>
                <a href="{{ $links['start'] }}" class="lngm-btn lngm-btn-soft">{{ __('next_gemini.start') }} <x-site.icon name="arrow" size="16" /></a>
            </div>
            {{-- The lanes folded into the mark's four bars, and a picture of the share card (not the card itself: nothing here is pressed). --}}
            <div class="lngm-bars" aria-hidden="true"><b class="lngm-v1"></b><b class="lngm-v2"></b><b class="lngm-v3"></b><b class="lngm-v4"></b></div>
            @if ($stage !== null)
                <div class="lngm-card" aria-hidden="true">
                    <p class="lngm-card-t" translate="no">{{ $stage['song']['title'] }}</p>
                    <p class="lngm-card-s">{{ __('next_gemini.s4_card', ['count' => count($stage['lanes'])]) }}</p>
                    <p class="lngm-who">@foreach ($stage['lanes'] as $lane)<span class="lngm-v{{ $lane['n'] }}"><i class="lngm-bub">{{ __('next_gemini.s3_you') }}</i>{{ $lane['name'] }}</span>@endforeach</p>
                    <p class="lngm-card-b"><span>{{ __('next_gemini.s4_share') }}</span><span>{{ __('next_gemini.s4_copy') }}</span></p>
                </div>
            @endif
        </section>

        <section class="lngm-scene lngm-s5" id="lngm-s5" data-lngm-scene="5" aria-labelledby="lngm-h5">
            <div class="lngm-end">
                <div class="lngm-bars lngm-bars-mark" aria-hidden="true"><b class="lngm-v1"></b><b class="lngm-v2"></b><b class="lngm-v3"></b><b class="lngm-v4"></b></div>
                <h2 id="lngm-h5">{{ __('next_gemini.s5_title') }} <em>{{ __('next_gemini.s5_em') }}</em></h2>
                <p class="lngm-text">{{ __('next_gemini.s5_text') }}</p>
                <ul class="lngm-facts">
                    <li class="lngm-v1"><a href="{{ $links['courses'] }}"><b>{{ $counts['courses'] }}</b> {{ __('next_gemini.s5_courses') }}</a></li>
                    <li class="lngm-v3"><a href="{{ $links['songs'] }}"><b>{{ $counts['songs'] }}</b> {{ __('next_gemini.s5_songs') }}</a></li>
                    <li class="lngm-v4"><a href="{{ $links['tools'] }}"><b>{{ $counts['tools'] }}</b> {{ __('next_gemini.s5_tools') }}</a></li>
                </ul>
                <p class="lngm-end-go">
                    <a href="{{ $links['start'] }}" class="lngm-btn lngm-btn-main">{{ __('next_gemini.start') }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="{{ $links['pricing'] }}" class="lngm-btn lngm-btn-soft">{{ __('next_gemini.s5_plans') }}</a>
                </p>
            </div>
            <footer class="lngm-legal">
                <a href="{{ $links['help'] }}">{{ __('pages.nav.help') }}</a>
                <a href="{{ $links['privacy'] }}">{{ __('pages.nav.privacy') }}</a>
                <a href="{{ $links['terms'] }}">{{ __('pages.nav.terms') }}</a>
                <span>{{ __('pages.nav.rights', ['year' => now()->year]) }}</span>
            </footer>
        </section>
    </main>

    {{-- Where you are in the film, and a way to any scene. --}}
    <nav class="lngm-rail" aria-label="{{ __('next_gemini.rail') }}" data-lngm-rail>
        <a href="#lngm-s0" aria-current="true"><span class="lngm-sr">{{ __('next_gemini.scene0') }}</span></a>
        <a href="#lngm-s1"><span class="lngm-sr">{{ __('next_gemini.s1_title') }} {{ __('next_gemini.s1_em') }}</span></a>
        <a href="#lngm-s2"><span class="lngm-sr">{{ __('next_gemini.s2_title') }} {{ __('next_gemini.s2_em') }}</span></a>
        <a href="#lngm-s3"><span class="lngm-sr">{{ __('next_gemini.s3_title') }} {{ __('next_gemini.s3_em') }}</span></a>
        <a href="#lngm-s4"><span class="lngm-sr">{{ __('next_gemini.s4_title') }} {{ __('next_gemini.s4_em') }}</span></a>
        <a href="#lngm-s5"><span class="lngm-sr">{{ __('next_gemini.scene5') }}</span></a>
    </nav>

    {{-- The play control's words while it loads (js/public/play-state.js), as the public layout gives them. --}}
    <template id="s-play-words" data-loading="{{ __('pages.site.play_loading') }}" data-slow="{{ __('pages.site.play_slow') }}" data-failed="{{ __('pages.site.play_failed') }}"></template>
    @if ($visit !== null)
        <script type="application/json" data-lngm-visit>@json(['kind' => $visit])</script>
    @endif
    <script type="module" src="/js/public/mixer.js"></script>
    <script type="module" src="/js/public/landing-next-gemini.js"></script>
</body>
</html>
