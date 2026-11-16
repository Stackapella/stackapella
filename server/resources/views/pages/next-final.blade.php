{{-- /next-final (and /es/next-final): the candidate for the final next landing page (Hillel, 2026-11-15: "lets say we go
     with Claude design, please have a UI/UX expert agent review all 4 designs and create and deploy a fifth potential
     final design based on the Claude designs which incoudes any good concpets worth taking from the other designs").
     /next-claude's page with the review's fixes and what it took from the other three.
     Its own document (not the public layout), its own sheet (css/landing-next-final.css, every class .lnf-*) and its own
     script (js/public/landing-next-final.js); calm light and dark only, whatever the skin.
     The first five scenes are one stage that fills the screen and stays: the showreel's four lanes of Amazing Grace,
     played by the song page's own player (js/public/mixer.js, through its hooks, unchanged). After Share the stage lets
     go and the rest is a page that scrolls: free, choirs, the plans and four questions, the finale. A dock stays through
     all of it: play, the time, the four voices, the line being sung, Start singing.
     Every scene's words, links and questions are here, in order, so the page reads and paints with no script; with
     reduced motion (or no script) nothing is pinned and the stage's scenes are five still panels.
     Its data is the site's (App\Support\Pages\NextFinal, NextClaude). Private: never indexed, no canonical, no
     alternates, in no sitemap or language list, linked from nowhere. --}}
@php($song = $stage['title'] ?? '')
@php($pinned = 5)
@php($web = (string) config('app.web_path'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ __('meta.dir') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>{{ $title }}</title>
    <meta name="description" content="{{ $description }}">
    <link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#050403">
    @if (config('hosts.pass_domain'))
        <meta name="theme-cookie-domain" content="{{ config('hosts.pass_domain') }}">
    @endif
    <link rel="preload" href="/fonts/figtree-latin.woff2" as="font" type="font/woff2" crossorigin>
    <script src="{{ \App\Support\Http\StaticVersions::url($web, '/js/theme-boot.js') }}"></script>
    <link rel="stylesheet" href="{{ \App\Support\Http\StaticVersions::url($web, '/css/landing-next-final.css') }}">
</head>
<body>
    <a class="lnf-skip" href="#lnf-s0">{{ __('next_final.skip') }}</a>
    {{-- The whole page is the player's: the lanes and the dock are both inside it, so the dock plays from any scene. --}}
    <div class="lnf" id="lnf" data-lnf data-player data-scene="0" data-beats="{{ $stage['beats'] ?? 0 }}" @if ($stage !== null) style="--beats: {{ $stage['beats'] }}" @endif>
        @if ($stage !== null)
            @php($songData = $stage['song'])
            <script type="application/json" data-song>@json($songData)</script>
        @endif
        <header class="lnf-top" data-lnf-top>
            <a href="/" class="lnf-logo" aria-label="{{ __('next_final.home') }}">{{ \App\Support\Site\Icons::mark(26) }}<span>{{ __('meta.brand') }}</span></a>
            <nav class="lnf-nav" aria-label="{{ __('next_final.pages') }}">
                <a href="/songs">{{ __('pages.nav.songs') }}</a>
                <a href="/courses">{{ __('pages.nav.courses') }}</a>
                <a href="{{ $toolsUrl }}">{{ __('pages.nav.tools') }}</a>
                <a href="/pricing">{{ __('pages.nav.pricing') }}</a>
            </nav>
            <button type="button" class="lnf-round" data-lnf-theme aria-label="{{ __('pages.nav.theme') }}"><span class="lnf-in-light"><x-site.icon name="moon" size="18" /></span><span class="lnf-in-dark"><x-site.icon name="sun" size="18" /></span></button>
            @if (count($languages) > 1)
                @php($here = collect($languages)->firstWhere('current', true))
                <details class="lnf-lang" data-lang-menu>
                    <summary class="lnf-round" aria-label="{{ __('pages.nav.language', ['language' => $here['name'] ?? '']) }}"><x-site.icon name="globe" size="18" /><span aria-hidden="true">{{ strtoupper($here['code'] ?? '') }}</span></summary>
                    <ul role="list">
                        @foreach ($languages as $choice)
                            <li><a href="{{ $choice['url'] }}" hreflang="{{ $choice['code'] }}" lang="{{ $choice['code'] }}" data-lang-choice="{{ $choice['code'] }}" @if ($choice['current']) aria-current="true" @endif>{{ $choice['name'] }}</a></li>
                        @endforeach
                    </ul>
                </details>
            @endif
            <a href="{{ $links['app'] }}" class="lnf-open">{{ __('pages.nav.open_app') }}</a>
        </header>

        <main>
            <div class="lnf-pin">
                <div class="lnf-stage" data-lnf-stage role="group" aria-label="{{ __('next_final.stage', ['song' => $song]) }}">
                    <div class="lnf-bg" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>

                    {{-- 0, the overture: the headline, and the one press that starts the song. --}}
                    <section class="lnf-sc" id="lnf-s0" data-s="0" aria-labelledby="lnf-h0">
                        <div class="lnf-cap">
                            <h1 id="lnf-h0" class="lnf-h1"><span>{{ __('pages.landing.headline') }}</span> <em>{{ __('pages.landing.headline_em') }}</em></h1>
                            <div>
                                <p class="lnf-say">{{ __('next_final.lede') }} <span class="lnf-wide">{{ __('next_final.lede_more') }}</span> {{ __('next_final.lede_free') }}</p>
                                <div class="lnf-go">
                                    @if ($stage !== null)
                                        {{-- What a press commits to: the song, how many voices, how long. --}}
                                        <button type="button" class="lnf-play" data-lnf-play><span class="lnf-orb" aria-hidden="true"><span class="lnf-i-play"><x-site.icon name="play" size="22" /></span><span class="lnf-i-stop"><x-site.icon name="stop" size="22" /></span></span><span class="lnf-play-w"><span class="lnf-i-play">{{ __('next_final.play', ['song' => $song]) }}</span><span class="lnf-i-stop">{{ __('next_final.stop') }}</span><small>{{ __('next_final.play_meta', ['count' => count($stage['lanes']), 'length' => $stage['seconds']]) }}</small></span></button>
                                    @endif
                                    <a href="{{ $links['start'] }}" class="lnf-quiet lnf-wide">{{ __('pages.landing.start') }}</a>
                                </div>
                            </div>
                        </div>
                        @if ($stage !== null)
                            <div class="lnf-foot">
                                <p class="lnf-cue" aria-hidden="true">{{ __('next_final.scroll') }} <x-site.icon name="chevron" size="14" /></p>
                                <p class="lnf-hint" aria-hidden="true">{{ __('next_final.hint') }}</p>
                                {{-- Who wrote it and who is heard: the recording's whole credit where there's room (it is
                                     written in English), the singers' line otherwise. --}}
                                @if ($stage['credit'] !== null || $stage['credited'] !== null)
                                    <p class="lnf-cred">
                                        @if (app()->getLocale() === 'en' && $stage['credited'] !== null)
                                            <span class="lnf-cred-full">{{ $stage['credited'] }}</span><span class="lnf-cred-short">{{ $stage['credit'] ?? $stage['credited'] }}</span>
                                        @else
                                            <span>{{ $stage['credit'] ?? $stage['credited'] }}</span>
                                        @endif
                                    </p>
                                @endif
                            </div>
                        @endif
                    </section>

                    {{-- The four lanes, in every scene of the stage: a lane is one button (hear this voice alone, again for
                         all four), its notes as glass gems with their syllables, and the way to learn that part. --}}
                    @if ($stage !== null)
                        <div class="lnf-world">
                            <div class="lnf-deck" role="group" aria-label="{{ __('next_final.voices') }}" data-lnf-lanes>
                                @foreach ($stage['lanes'] as $lane)
                                    <div class="lnf-ln lnf-v{{ $lane['n'] }}" data-voice="{{ $lane['key'] }}" data-name="{{ mb_strtolower($lane['name']) }}">
                                        <button type="button" class="lnf-hit" data-hear-alone aria-pressed="false" aria-label="{{ __('pages.landing.hero.hear_only', ['part' => mb_strtolower($lane['name'])]) }}" data-label="{{ __('pages.landing.hero.hear_only', ['part' => mb_strtolower($lane['name'])]) }}" data-label-back="{{ __('pages.landing.hero.hear_all_back') }}"></button>
                                        <div class="lnf-tr" aria-hidden="true">
                                            @foreach ($lane['notes'] as $note)
                                                <span class="lnf-g" data-b="{{ $note['beat'] }}" data-l="{{ $note['length'] }}" style="--b: {{ $note['beat'] }}; --l: {{ $note['length'] }}; --y: {{ $note['y'] }}">{{ $note['length'] >= 1 ? $note['text'] : '' }}</span>
                                            @endforeach
                                            {{-- Your pitch as Sing draws it, shown in the Sing along scene, up to where the song is. --}}
                                            @if ($loop->first && $lane['trace'] !== '')
                                                <span class="lnf-trace"><svg viewBox="0 0 {{ $stage['beats'] }} 1" preserveAspectRatio="none" focusable="false"><path d="{{ $lane['trace'] }}" /></svg></span>
                                            @endif
                                        </div>
                                        <div class="lnf-lb" aria-hidden="true">
                                            <b><span class="lnf-orb">{{ mb_substr($lane['name'], 0, 1) }}</span>{{ $lane['name'] }}<i class="lnf-mt"><i></i><i></i><i></i><i></i></i></b>
                                            <small>{{ __('pages.landing.hero.roles.'.$lane['key']) }}</small>
                                            <span class="lnf-chip lnf-chip-alone" data-chip="alone">{{ __('next_final.alone') }}</span>
                                            @if ($loop->first)
                                                <span class="lnf-chip" data-chip="2">{{ __('next_final.sing.chip') }}</span>
                                            @endif
                                            @if ($loop->iteration === 3)
                                                <span class="lnf-chip lnf-chip-rec" data-chip="3">{{ __('pages.landing.steps.record.recording') }}</span>
                                            @else
                                                <span class="lnf-chip" data-chip="{{ $loop->iteration < 3 ? '3 4' : '4' }}">{{ __('pages.landing.steps.voice.you') }}</span>
                                            @endif
                                            @if ($loop->iteration === 3)
                                                <span class="lnf-chip" data-chip="4">{{ __('pages.landing.steps.voice.you') }}</span>
                                            @endif
                                        </div>
                                        <a href="{{ $lane['learn'] }}" class="lnf-mine" data-lnf-mine aria-label="{{ __('next_final.learn_part', ['part' => mb_strtolower($lane['name'])]) }}">{{ __('pages.landing.hero.mine_pick') }}</a>
                                    </div>
                                @endforeach
                                <i class="lnf-beam" aria-hidden="true"></i>
                            </div>
                        </div>
                    @endif

                    {{-- 1 to 4, the story: the app's four steps, in its own words. --}}
                    @php($steps = [1 => ['hear', __('next_final.hear.body')], 2 => ['sing', __('pages.landing.steps.voice.body')], 3 => ['record', __('pages.landing.steps.record.body')], 4 => ['share', __('pages.landing.steps.share.body')]])
                    @php($first = $stage['lanes'][0] ?? null)
                    @foreach ($steps as $n => [$key, $body])
                        <section class="lnf-sc" id="lnf-s{{ $n }}" data-s="{{ $n }}" aria-labelledby="lnf-h{{ $n }}">
                            <div class="lnf-cap">
                                <div>
                                    <p class="lnf-kick"><span class="lnf-bars" aria-hidden="true">@for ($i = 1; $i <= 4; $i++)<i @class(['lnf-on' => $i <= $n])></i>@endfor</span>{{ __('next_final.step', ['n' => $n]) }} · {{ __('next_final.'.$key.'.name') }}</p>
                                    <h2 id="lnf-h{{ $n }}" class="lnf-h2">{{ __('next_final.'.$key.'.before') }} <em>{{ __('next_final.'.$key.'.em') }}</em> {{ __('next_final.'.$key.'.after') }}</h2>
                                </div>
                                <div>
                                    <p class="lnf-say">{{ $body }}</p>
                                    @if ($n === 1)
                                        <p class="lnf-true">{{ __('next_final.hear.true') }}</p>
                                    @endif
                                    {{-- The claim, in one press: hear a voice alone, then learn it. Both name the voice last
                                         heard alone (the script renames them; the melody's until one is). --}}
                                    @if ($first !== null && ($n === 1 || $n === 2))
                                        <div class="lnf-go">
                                            @if ($n === 1)
                                                <button type="button" class="lnf-quiet" data-lnf-hear aria-pressed="false" data-label="{{ __('pages.landing.hero.hear_only', ['part' => ':part']) }}" data-label-back="{{ __('pages.landing.hero.hear_all_back') }}">{{ __('pages.landing.hero.hear_only', ['part' => mb_strtolower($first['name'])]) }}</button>
                                            @endif
                                            <a href="{{ $first['learn'] }}" class="lnf-arrow" data-lnf-learn data-label="{{ __('next_final.learn', ['part' => ':part']) }}"><span>{{ __('next_final.learn', ['part' => mb_strtolower($first['name'])]) }}</span> <x-site.icon name="arrow" size="16" /></a>
                                        </div>
                                    @endif
                                </div>
                            </div>
                            @if ($n === 2)
                                {{-- The three ways to practise a part, as Sing offers them: a picture of its control. --}}
                                <p class="lnf-seg" aria-hidden="true"><span>{{ __('pages.landing.steps.voice.listen') }}</span><span class="lnf-on">{{ __('pages.landing.steps.voice.along') }}</span><span>{{ __('pages.landing.steps.voice.alone') }}</span></p>
                            @endif
                            @if ($n === 4)
                                <div class="lnf-glass lnf-share" aria-hidden="true">
                                    <b>{{ __('pages.landing.steps.share.every', ['song' => $song]) }}</b>
                                    <p>{{ __('next_final.share.card') }}</p>
                                    <p class="lnf-orbs">@for ($i = 1; $i <= 4; $i++)<span class="lnf-orb lnf-v{{ $i }}">{{ __('pages.landing.steps.voice.you') }}</span>@endfor</p>
                                    <p class="lnf-pair"><span>{{ __('pages.landing.steps.share.link') }}</span><span>{{ __('pages.landing.steps.share.video') }}</span></p>
                                </div>
                            @endif
                        </section>
                    @endforeach

                    <nav class="lnf-dots" aria-label="{{ __('next_final.scenes') }}" data-lnf-dots>
                        @for ($i = 0; $i < $pinned; $i++)
                            <a href="#lnf-at-{{ $i }}" aria-label="{{ __('next_final.scene', ['n' => $i + 1, 'count' => $pinned]) }}" @if ($i === 0) aria-current="true" @endif></a>
                        @endfor
                    </nav>
                </div>
                {{-- One screen of scroll for each of the stage's scenes: where the page rests, and what the dots lead to. --}}
                <div class="lnf-snaps" aria-hidden="true">
                    @for ($i = 0; $i < $pinned; $i++)
                        <div id="lnf-at-{{ $i }}"></div>
                    @endfor
                </div>
            </div>

            {{-- From here the page scrolls like a page. --}}
            <div class="lnf-tail" data-lnf-tail>
                {{-- 5, free to learn: what there is, by its real numbers, each a way in; then the first step. --}}
                <section class="lnf-sc" id="lnf-s5" data-s="5" aria-labelledby="lnf-h5">
                    <div class="lnf-cap">
                        <h2 id="lnf-h5" class="lnf-h2">{{ __('next_final.free.before') }} <em>{{ __('next_final.free.em') }}</em></h2>
                        <p class="lnf-say">{{ __('next_final.free.body') }}</p>
                    </div>
                    <div class="lnf-slabs">
                        @foreach (['courses' => '/courses', 'songs' => '/songs', 'tools' => $toolsUrl] as $key => $href)
                            @php($slab = (array) \Illuminate\Support\Facades\Lang::get('next_final.free.'.$key))
                            <a href="{{ $href }}" class="lnf-glass lnf-slab lnf-v{{ $loop->iteration === 1 ? 1 : ($loop->iteration === 2 ? 3 : 4) }}">
                                <strong>{{ $counts[$key] }}</strong>
                                <b>{{ $slab[0] }}</b>
                                <span>{{ $slab[1] }}</span>
                                <i aria-hidden="true"><x-site.icon name="arrow" size="18" /></i>
                            </a>
                        @endforeach
                    </div>
                    <div class="lnf-go lnf-after"><a href="{{ $links['start'] }}" class="lnf-prim">{{ __('next_final.free.start') }} <x-site.icon name="arrow" size="18" /></a></div>
                    <div class="lnf-floor" aria-hidden="true"><span><i></i><i></i><i></i><i></i></span></div>
                </section>

                {{-- 6, choirs: a choir group as the app shows one. --}}
                <section class="lnf-sc" id="lnf-s6" data-s="6" aria-labelledby="lnf-h6">
                    <div class="lnf-two">
                        <div>
                            <h2 id="lnf-h6" class="lnf-h2">{{ __('next_final.choir.before') }} <em>{{ __('next_final.choir.em') }}</em></h2>
                            <p class="lnf-say">{{ __('next_final.choir.body') }}</p>
                            {{-- Starting a group takes Plus while that switch is on (Hillel, 2026-11-12); the page says so. --}}
                            @if ($teams)
                                <p class="lnf-plusline"><span class="lnf-soon">{{ __('pages.landing.plans_plus') }}</span><span>{{ __('next_final.choir.plus') }}</span></p>
                            @endif
                            <div class="lnf-go">
                                <a href="{{ \App\Support\Http\Hosts::app('/groups?start=1&ref='.\App\Support\Pages\NextFinal::REF) }}" class="lnf-quiet">{{ __('pages.site.group_start') }}</a>
                                <a href="/for/choir-directors?ref={{ \App\Support\Pages\NextFinal::REF }}" class="lnf-quiet">{{ __('site.teams.directors') }}</a>
                            </div>
                        </div>
                        @if ($stage !== null)
                            @php($singers = (array) \Illuminate\Support\Facades\Lang::get('next_final.choir.singers'))
                            @php($states = [['recorded', 'lnf-ok'], ['recorded', 'lnf-ok'], ['learning', 'lnf-mid'], ['waiting', '']])
                            <div class="lnf-glass lnf-choir" aria-hidden="true">
                                <p class="lnf-choir-h"><b>{{ $song }}</b><span>{{ __('next_final.choir.covered') }}</span></p>
                                @foreach ($stage['lanes'] as $lane)
                                    @php($state = $states[$loop->index] ?? ['waiting', ''])
                                    @php($singer = (string) ($singers[$loop->index] ?? ''))
                                    <p class="lnf-row lnf-v{{ $lane['n'] }}"><span class="lnf-orb">{{ \App\Support\Pages\NextFinal::initials($singer) }}</span><span><b>{{ $singer }}</b><small>{{ $lane['name'] }}</small></span><em class="{{ $state[1] }}">{{ __('next_final.choir.'.$state[0]) }}</em></p>
                                @endforeach
                                <p class="lnf-choir-n">{{ __('next_final.choir.note') }}</p>
                            </div>
                        @endif
                    </div>
                    <div class="lnf-floor" aria-hidden="true"><span><i></i><i></i><i></i><i></i></span></div>
                </section>

                {{-- 7, the plans and four questions: the landing's own prices, lists and answers. --}}
                <section class="lnf-sc" id="lnf-s7" data-s="7" aria-labelledby="lnf-h7">
                    <div class="lnf-cap">
                        <h2 id="lnf-h7" class="lnf-h2 lnf-h2-two">{{ __('pages.landing.plans_title') }} <em>{{ __('pages.landing.plans_title_more') }}</em></h2>
                        <p class="lnf-say">{{ __('next_final.plans.body') }} <a href="/pricing" class="lnf-lk">{{ __('pages.landing.plans_compare') }}</a></p>
                    </div>
                    @php($replace = [':free_songs' => (string) $plans['freeSongs'], ':free_storage' => $plans['freeStorage'], ':storage' => $plans['storage']])
                    <div class="lnf-money">
                        <div class="lnf-plans">
                            <div class="lnf-glass lnf-plan">
                                <h3>{{ __('pages.landing.plans_free') }}</h3>
                                <p class="lnf-price">{{ str_replace('.00', '', \App\Support\Pages\Prices::format(0, $price['currency'])) }}</p>
                                <p class="lnf-note">{{ __('pages.landing.plans_free_note') }}</p>
                                <ul>
                                    @foreach (\App\Support\Pages\Landing::list('plans_free_list', $replace) as $line)
                                        <li><x-site.icon name="check" size="16" />{{ $line }}</li>
                                    @endforeach
                                </ul>
                            </div>
                            <div class="lnf-glass lnf-plan lnf-plus">
                                <h3>{{ __('pages.landing.plans_plus') }} @unless ($plans['checkoutOpen'])<span class="lnf-soon">{{ __('pages.landing.plans_soon') }}</span>@endunless</h3>
                                <p class="lnf-price">{{ $price['yearAsMonth'] }} <small>{{ __('pages.landing.plans_month') }}</small></p>
                                <p class="lnf-note">{{ __('pages.landing.plans_billed_year', ['year' => $price['year']]) }} @unless ($plans['checkoutOpen']){{ __('pages.landing.plans_paused') }}@endunless</p>
                                <ul>
                                    @foreach (\App\Support\Pages\Landing::list('plans_plus_list') as [$gain, $fact])
                                        <li><x-site.icon name="check" size="16" /><span><b>{{ $gain }}</b> {{ strtr($fact, $replace) }}</span></li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="lnf-faq">
                            @foreach ($questions as $qa)
                                <details class="lnf-glass" name="lnf-faq" @if ($loop->first) open @endif>
                                    <summary>{{ $qa[0] }}<span aria-hidden="true"><span class="lnf-plus-i"><x-site.icon name="plus" size="16" /></span><span class="lnf-minus-i"><x-site.icon name="minus" size="16" /></span></span></summary>
                                    <p>{{ $qa[1] }}</p>
                                </details>
                            @endforeach
                        </div>
                    </div>
                    <div class="lnf-floor" aria-hidden="true"><span><i></i><i></i><i></i><i></i></span></div>
                </section>

                {{-- 8, the finale: the mark, the headline, Start singing, and the four bars as the four ways on. --}}
                <section class="lnf-sc" id="lnf-s8" data-s="8" aria-labelledby="lnf-h8">
                    <div class="lnf-cap lnf-cap-end">
                        <span class="lnf-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                        <h2 id="lnf-h8" class="lnf-h1"><span>{{ __('pages.landing.headline') }}</span> <em>{{ __('pages.landing.headline_em') }}</em></h2>
                        <p class="lnf-say">{{ __('pages.site.close.home.line') }}</p>
                        <div class="lnf-go"><a href="{{ $links['start'] }}" class="lnf-prim">{{ __('pages.landing.start') }} <x-site.icon name="arrow" size="18" /></a></div>
                    </div>
                    <nav class="lnf-ways" aria-label="{{ __('next_final.end.ways') }}">
                        <a href="/songs" class="lnf-way lnf-v1">{{ __('pages.nav.songs') }} <small>{{ __('next_final.end.songs', ['count' => $counts['songs']]) }}</small></a>
                        <a href="/courses" class="lnf-way lnf-v2">{{ __('pages.nav.courses') }} <small>{{ __('next_final.end.courses', ['count' => $counts['courses']]) }}</small></a>
                        <a href="{{ $toolsUrl }}" class="lnf-way lnf-v3">{{ __('pages.nav.tools') }} <small>{{ __('next_final.end.tools', ['count' => $counts['tools']]) }}</small></a>
                        <a href="/pricing" class="lnf-way lnf-v4">{{ __('pages.nav.pricing') }} <small>{{ __('next_final.end.pricing') }}</small></a>
                    </nav>
                    <nav class="lnf-legal" aria-label="{{ __('next_final.end.legal') }}">
                        <a href="/help">{{ __('pages.nav.help') }}</a>
                        <a href="/legal/privacy">{{ __('pages.nav.privacy') }}</a>
                        <a href="/legal/terms">{{ __('pages.nav.terms') }}</a>
                    </nav>
                </section>
            </div>
        </main>

        {{-- The dock, in every scene and down the page: the play disc (the player's own button), the time, the four
             voices (each presses its lane, so a voice is heard alone from anywhere), the line being sung with the next
             under it, and Start singing. --}}
        @if ($stage !== null)
            <div class="lnf-dock" role="group" aria-label="{{ __('next_final.dock', ['song' => $song]) }}">
                <i class="lnf-rail" aria-hidden="true"></i>
                <div class="lnf-tp">
                    <button type="button" class="lnf-disc lnf-orb" data-play aria-label="{{ __('pages.landing.hero.play') }}" data-label-play="{{ __('pages.landing.hero.play') }}" data-label-stop="{{ __('pages.landing.hero.stop') }}"><span class="lnf-i-play"><x-site.icon name="play" size="20" /></span><span class="lnf-i-stop"><x-site.icon name="stop" size="20" /></span></button>
                    <span class="lnf-time"><span class="lnf-sr">{{ __('next_final.time') }}</span> <span data-time>0:00 / {{ $stage['seconds'] }}</span></span>
                    <span class="lnf-voices" role="group" aria-label="{{ __('next_final.voices') }}">
                        @foreach ($stage['lanes'] as $lane)
                            <button type="button" class="lnf-vo lnf-v{{ $lane['n'] }}" data-lnf-voice="{{ $lane['key'] }}" aria-pressed="false" aria-label="{{ __('pages.landing.hero.hear_only', ['part' => mb_strtolower($lane['name'])]) }}"><span class="lnf-orb" aria-hidden="true">{{ mb_substr($lane['name'], 0, 1) }}</span></button>
                        @endforeach
                    </span>
                </div>
                <div class="lnf-words" translate="no" data-lnf-words>
                    @foreach ($stage['phrases'] as $phrase)
                        <p class="lnf-line" data-from="{{ $phrase['from'] }}" data-to="{{ $phrase['to'] }}" @unless ($loop->first) hidden @endunless>@foreach ($phrase['words'] as $word)<span data-b="{{ $word['beat'] }}">{{ $word['text'] }}</span> @endforeach</p>
                    @endforeach
                    <p class="lnf-next" data-lnf-next aria-hidden="true">{{ implode(' ', array_column($stage['phrases'][1]['words'] ?? [], 'text')) }}</p>
                </div>
                <a href="{{ $links['start'] }}" class="lnf-start">{{ __('pages.landing.start') }}</a>
            </div>
            <p class="lnf-sr" role="status" aria-live="polite" data-lnf-live data-playing="{{ __('next_final.live.playing', ['song' => $song]) }}" data-stopped="{{ __('next_final.live.stopped') }}" data-alone="{{ __('next_final.live.alone', ['part' => ':part']) }}" data-all="{{ __('next_final.live.all') }}"></p>
        @endif
    </div>

    {{-- The player's words for loading, a slow load and a failure (js/public/play-state.js reads them by this id). --}}
    <template id="s-play-words" data-loading="{{ __('pages.site.play_loading') }}" data-slow="{{ __('pages.site.play_slow') }}" data-failed="{{ __('pages.site.play_failed') }}"></template>
    {{-- This page's one count, by its own kind (App\Support\Site\SiteVisit): never the landing's. --}}
    @php($visit = \App\Support\Site\SiteVisit::kind(request()))
    @if ($visit !== null)
        @php($visitData = ['kind' => $visit])
        <script type="application/json" data-lnf-visit>@json($visitData)</script>
    @endif
    <script type="module" src="/js/public/lang-menu.js"></script>
    <script type="module" src="/js/public/mixer.js"></script>
    <script type="module" src="/js/public/landing-next-final.js"></script>
</body>
</html>
