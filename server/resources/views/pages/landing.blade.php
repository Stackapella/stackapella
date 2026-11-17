{{-- The landing, the site's home page at / (and /es): Hillel, 2026-11-16, "make next-final the main home page", the
     design chosen from the candidates of 2026-11-15 ("combine the 3 into one": the song you can play, the showreel's look
     and the page's story).
     Its own document (not the public layout), its own sheet (css/landing.css, every class .lnf-*; the footer is the
     site's own component, dressed there) and its own script (js/public/landing.js); calm light and dark, and game mode's
     dress once its sheet is there (css/landing-game.css, the game agent's: linked only in game mode, only if it exists).
     The first five scenes are one stage that fills the screen and stays: the showreel's four lanes of Amazing Grace,
     played by the song page's own player (js/public/mixer.js, through its hooks), as the showreel plays it: twice
     through, the voices joining one by one the first time, each lane lighting as its voice comes in; only the recorded
     voices, their breaths ducked. After Share the stage lets go and the rest is a page that scrolls: free, choirs, the
     plans and four questions, the finale, the site's footer. A dock stays through all of it: play, the time, the line
     being sung, Start singing. A voice is heard alone by its lane.
     Every scene's words, links and questions are here, in order, so the page reads and paints with no script; with
     reduced motion (or no script) nothing is pinned and the stage's scenes are five still panels.
     As the home page it carries what the public layout gives every page (the description, link card and canonical
     address, whether search may index it, the Open Graph locale, the outreach partner's line), the landing's own
     structured data, a mudeo arrival's banner and letter, the funnel (js/public/funnel.js: site_visit with its source,
     the Start links marked), the app's first screen fetched ahead, and the footer's count and heartbeat (js/public/site.js).
     Its data is the site's (App\Support\Pages\LandingStage, Landing). --}}
@php($song = $stage['title'] ?? '')
@php($pinned = 5)
<!DOCTYPE html>
{{-- On the app's arcade screen in game mode (data-gs), as the public layout marks the home page. --}}
@php($web = (string) config('app.web_path'))
@php($gameSheet = is_file($web.'/css/landing-game.css'))
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ __('meta.dir') }}" data-gs>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta property="og:locale" content="{{ \App\Support\Languages\SiteLanguages::ogLocale(app()->getLocale()) }}">
    {{-- Kept out of search only while the whole site is (SITE_INDEXING), as on every public page. --}}
    @if (! config('ops.indexing'))
        <meta name="robots" content="noindex">
    @endif
    <title>{{ $title }}</title>
    @include('pages._head')
    <x-site.json-ld :data="\App\Support\Site\StructuredData::landing($price, $questions)" />
    <link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
    <meta name="theme-color" content="#050403">
    @if (config('hosts.pass_domain'))
        <meta name="theme-cookie-domain" content="{{ config('hosts.pass_domain') }}">
    @endif
    @if ($gameSheet)
        {{-- Game mode's dress, fetched only in game mode (js/theme-boot.js preloads it, js/game-css.js links it right
             after the page's own sheet), with its fonts (App\Support\Site\GamePreload; calm asks for Figtree). --}}
        <meta name="game-css" content="{{ \App\Support\Http\StaticVersions::url($web, '/css/landing-game.css') }}">
        {{ \App\Support\Site\GamePreload::tag() }}
    @else
        <link rel="preload" href="/fonts/figtree-latin.woff2" as="font" type="font/woff2" crossorigin>
    @endif
    <script src="{{ \App\Support\Http\StaticVersions::url($web, '/js/theme-boot.js') }}"></script>
    <link rel="stylesheet" href="{{ \App\Support\Http\StaticVersions::url($web, '/css/landing.css') }}">
    @if ($gameSheet)
        <script src="{{ \App\Support\Http\StaticVersions::url($web, '/js/game-css.js') }}"></script>
    @endif
    {{-- The Game mode switch's own rules, as the site's sheet has them (css/game-toggle.css). --}}
    <link rel="stylesheet" href="{{ \App\Support\Http\StaticVersions::url($web, '/css/game-toggle.css') }}">
</head>
<body>
    <a class="lnf-skip" href="#lnf-s0">{{ __('landing.skip') }}</a>
    {{-- Someone who came by an organization's outreach link (a known o- tag; Landing::links): one quiet line to the
         partner offer, above everything else, as on every public page. --}}
    @if (is_string($links['partner'] ?? null) || $mudeo)
        <div class="lnf-notes" data-lnf-note>
            @if (is_string($links['partner'] ?? null))
                <p class="lnf-note-bar"><span>{{ __('pages.site.partner_line') }}</span> <a href="{{ $links['partner'] }}" class="lnf-lk">{{ __('pages.site.partner_link') }}</a></p>
            @endif
            {{-- A mudeo arrival (the email's link): the line that says where they've landed, and the way to the letter. --}}
            @if ($mudeo)
                <div class="lnf-note-bar" role="region" aria-label="{{ __('pages.landing.mudeo.eyebrow') }}">
                    <p>{{ __('pages.landing.mudeo.banner') }} <a href="#mudeo" class="lnf-lk">{{ __('pages.landing.mudeo.banner_link') }}</a></p>
                </div>
            @endif
        </div>
    @endif
    {{-- The whole page is the player's: the lanes and the dock are both inside it, so the dock plays from any scene. --}}
    <div class="lnf" id="lnf" data-lnf data-player data-scene="0" data-beats="{{ $stage['beats'] ?? 0 }}" data-passes="{{ \App\Support\Pages\LandingStage::PASSES }}" @if ($stage !== null) style="--beats: {{ $stage['beats'] }}" @endif>
        @if ($stage !== null)
            @php($songData = $stage['song'])
            <script type="application/json" data-song>@json($songData)</script>
        @endif
        <header class="lnf-top" data-lnf-top>
            <a href="/" class="lnf-logo s-brand" aria-label="{{ __('landing.home') }}">{{ \App\Support\Site\Icons::hat() }}{{ \App\Support\Site\Icons::mark(26) }}<span>{{ __('meta.brand') }}</span></a>
            <nav class="lnf-nav" aria-label="{{ __('landing.pages') }}">
                <a href="/songs">{{ __('pages.nav.songs') }}</a>
                <a href="/courses">{{ __('pages.nav.courses') }}</a>
                <a href="{{ $toolsUrl }}">{{ __('pages.nav.tools') }}</a>
                <a href="/pricing">{{ __('pages.nav.pricing') }}</a>
            </nav>
            {{-- Game mode on or off: the site header's own switch, where it has it (before Language and appearance); on a
                 phone it's in that pill's menu, as on every page. --}}
            <x-site.game-toggle size="pill" class="lnf-game" />
            {{-- Language and appearance, the site header's own pill (Hillel, 2026-11-16: "combine the two buttons"). --}}
            <x-site.look-menu class="lnf-look" />
            <a href="{{ $links['app'] }}" class="lnf-open">{{ __('pages.nav.open_app') }}</a>
        </header>

        <main>
            <div class="lnf-pin">
                <div class="lnf-stage" data-lnf-stage role="group" aria-label="{{ __('landing.stage', ['song' => $song]) }}">
                    <div class="lnf-bg" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>

                    {{-- 0, the overture: the headline, and the one press that starts the song. --}}
                    <section class="lnf-sc" id="lnf-s0" data-s="0" aria-labelledby="lnf-h0">
                        <div class="lnf-cap">
                            <h1 id="lnf-h0" class="lnf-h1"><span>{{ __('pages.landing.headline') }}</span> <em>{{ __('pages.landing.headline_em') }}</em></h1>
                            <div>
                                <p class="lnf-say">{{ __('landing.lede') }} <span class="lnf-wide">{{ __('landing.lede_more') }}</span> {{ __('landing.lede_free') }}</p>
                                <div class="lnf-go">
                                    @if ($stage !== null)
                                        {{-- What a press commits to: the song, how many voices, how long. --}}
                                        <button type="button" class="lnf-play" data-lnf-play><span class="lnf-orb" aria-hidden="true"><span class="lnf-i-play"><x-site.icon name="play" size="22" /></span><span class="lnf-i-stop"><x-site.icon name="stop" size="22" /></span></span><span class="lnf-play-w"><span class="lnf-i-play">@if ($gameSheet)<x-site.skin :game="__('landing.game.play')" :serious="__('landing.play', ['song' => $song])" />@else{{ __('landing.play', ['song' => $song]) }}@endif</span><span class="lnf-i-stop">{{ __('landing.stop') }}</span><small class="lnf-meta">{{ __('landing.play_meta', ['count' => count($stage['lanes']), 'length' => $stage['seconds']]) }}</small><small class="lnf-load" data-lnf-load data-loading="{{ __('landing.loading') }}" data-failed="{{ __('landing.failed') }}"></small></span></button>
                                    @endif
                                    <a href="{{ $links['start'] }}" class="lnf-quiet lnf-wide" data-funnel-start>{{ __('pages.landing.start') }}</a>
                                </div>
                                {{-- Lesson 1's chord moment, promised in the app's own words. While the onb1 experiment enrols,
                                     its control arm gets no chord moment, so the line shows only once funnel.js knows this
                                     device's arm isn't the control (onb1-a). --}}
                                @php($chordTest = (($funnel['experiment']['id'] ?? null) === 'onb1') && ($funnel['experiment']['enrol'] ?? false))
                                <p class="lnf-promise" @if ($chordTest) hidden data-variant-except="onb1-a" @endif>{{ __('pages.landing.chord_promise') }}</p>
                            </div>
                        </div>
                        @if ($stage !== null)
                            <div class="lnf-foot">
                                <p class="lnf-cue" aria-hidden="true">{{ __('landing.scroll') }} <x-site.icon name="chevron" size="14" /></p>
                                <p class="lnf-hint" aria-hidden="true">{{ __('landing.hint') }}</p>
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
                            <div class="lnf-deck" role="group" aria-label="{{ __('landing.voices') }}" data-lnf-lanes>
                                @foreach ($stage['lanes'] as $lane)
                                    <div class="lnf-ln lnf-v{{ $lane['n'] }}" data-voice="{{ $lane['key'] }}" data-name="{{ mb_strtolower($lane['name']) }}" data-title="{{ $lane['name'] }}" data-join="{{ $stage['joins'][$lane['key']] ?? 0 }}">
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
                                            <span class="lnf-chip lnf-chip-alone" data-chip="alone">{{ __('landing.alone') }}</span>
                                            @if ($loop->first)
                                                <span class="lnf-chip" data-chip="2">{{ __('landing.sing.chip') }}</span>
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
                                        <a href="{{ $lane['learn'] }}" class="lnf-mine" data-lnf-mine aria-label="{{ __('landing.learn_part', ['part' => mb_strtolower($lane['name'])]) }}">{{ __('pages.landing.hero.mine_pick') }}</a>
                                    </div>
                                @endforeach
                                <i class="lnf-beam" aria-hidden="true"></i>
                            </div>
                        </div>
                        {{-- What is being heard, as the song builds: who sings, who has just joined, then all four (the
                             script writes it and tells a screen reader the same words). --}}
                        <p class="lnf-now" aria-hidden="true" data-lnf-now data-first="{{ __('landing.build.first', ['part' => ':part']) }}" data-join="{{ __('landing.build.join', ['part' => ':part']) }}" data-all="{{ __('landing.build.all') }}" data-alone="{{ __('landing.build.alone', ['part' => ':part']) }}"></p>
                    @endif

                    {{-- 1 to 4, the story: the app's four steps, in its own words. --}}
                    @php($steps = [1 => ['hear', __('landing.hear.body')], 2 => ['sing', __('pages.landing.steps.voice.body')], 3 => ['record', __('pages.landing.steps.record.body')], 4 => ['share', __('pages.landing.steps.share.body')]])
                    @php($first = $stage['lanes'][0] ?? null)
                    @foreach ($steps as $n => [$key, $body])
                        <section class="lnf-sc" id="lnf-s{{ $n }}" data-s="{{ $n }}" aria-labelledby="lnf-h{{ $n }}">
                            <div class="lnf-cap">
                                <div>
                                    <p class="lnf-kick"><span class="lnf-bars" aria-hidden="true">@for ($i = 1; $i <= 4; $i++)<i @class(['lnf-on' => $i <= $n])></i>@endfor</span>@if ($gameSheet)<x-site.skin :game="__('landing.game.step', ['n' => $n])" :serious="__('landing.step', ['n' => $n])" />@else{{ __('landing.step', ['n' => $n]) }}@endif · {{ __('landing.'.$key.'.name') }}</p>
                                    <h2 id="lnf-h{{ $n }}" class="lnf-h2">{{ __('landing.'.$key.'.before') }} <em>{{ __('landing.'.$key.'.em') }}</em> {{ __('landing.'.$key.'.after') }}</h2>
                                </div>
                                <div>
                                    <p class="lnf-say">{{ $body }}</p>
                                    @if ($n === 1)
                                        <p class="lnf-true">{{ __('landing.hear.true') }}</p>
                                    @endif
                                    {{-- The claim, in one press: hear a voice alone, then learn it. Both name the voice last
                                         heard alone (the script renames them; the melody's until one is). --}}
                                    @if ($first !== null && ($n === 1 || $n === 2))
                                        <div class="lnf-go">
                                            @if ($n === 1)
                                                <button type="button" class="lnf-quiet" data-lnf-hear aria-pressed="false" data-label="{{ __('pages.landing.hero.hear_only', ['part' => ':part']) }}" data-label-back="{{ __('pages.landing.hero.hear_all_back') }}">{{ __('pages.landing.hero.hear_only', ['part' => mb_strtolower($first['name'])]) }}</button>
                                            @endif
                                            <a href="{{ $first['learn'] }}" class="lnf-arrow" data-lnf-learn data-label="{{ __('landing.learn', ['part' => ':part']) }}"><span>{{ __('landing.learn', ['part' => mb_strtolower($first['name'])]) }}</span> <x-site.icon name="arrow" size="16" /></a>
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
                                    <p>{{ __('landing.share.card') }}</p>
                                    <p class="lnf-orbs">@for ($i = 1; $i <= 4; $i++)<span class="lnf-orb lnf-v{{ $i }}">{{ __('pages.landing.steps.voice.you') }}</span>@endfor</p>
                                    <p class="lnf-pair"><span>{{ __('pages.landing.steps.share.link') }}</span><span>{{ __('pages.landing.steps.share.video') }}</span></p>
                                </div>
                            @endif
                        </section>
                    @endforeach

                    <nav class="lnf-dots" aria-label="{{ __('landing.scenes') }}" data-lnf-dots>
                        @for ($i = 0; $i < $pinned; $i++)
                            <a href="#lnf-at-{{ $i }}" aria-label="{{ __('landing.scene', ['n' => $i + 1, 'count' => $pinned]) }}" @if ($i === 0) aria-current="true" @endif></a>
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
                        <h2 id="lnf-h5" class="lnf-h2">{{ __('landing.free.before') }} <em>{{ __('landing.free.em') }}</em></h2>
                        <p class="lnf-say">{{ __('landing.free.body') }}</p>
                    </div>
                    <div class="lnf-slabs">
                        @foreach (['courses' => '/courses', 'songs' => '/songs', 'tools' => $toolsUrl] as $key => $href)
                            @php($slab = (array) \Illuminate\Support\Facades\Lang::get('landing.free.'.$key))
                            <a href="{{ $href }}" class="lnf-glass lnf-slab lnf-v{{ $loop->iteration === 1 ? 1 : ($loop->iteration === 2 ? 3 : 4) }}">
                                <strong>{{ $counts[$key] }}</strong>
                                <b>{{ $slab[0] }}</b>
                                <span>{{ $slab[1] }}</span>
                                <i aria-hidden="true"><x-site.icon name="arrow" size="18" /></i>
                            </a>
                        @endforeach
                    </div>
                    <div class="lnf-go lnf-after"><a href="{{ $links['start'] }}" class="lnf-prim" data-funnel-start>{{ __('landing.free.start') }} <x-site.icon name="arrow" size="18" /></a>
                        {{-- The landing's one seasonal line (Hillel, 2026-11-10), quiet; it hides by itself after 26 December
                             (Carols::linked). --}}
                        @if (\App\Support\Pages\Carols::linked())
                            <a href="{{ \App\Support\Pages\Carols::PATH }}" class="lnf-arrow lnf-carols"><span>{{ __('pages.landing.carols') }}</span></a>
                        @endif
                    </div>
                    <div class="lnf-floor" aria-hidden="true"><span><i></i><i></i><i></i><i></i></span></div>
                </section>

                {{-- 6, choirs: a choir group as the app shows one. --}}
                <section class="lnf-sc" id="lnf-s6" data-s="6" aria-labelledby="lnf-h6">
                    <div class="lnf-two">
                        <div>
                            <h2 id="lnf-h6" class="lnf-h2">{{ __('landing.choir.before') }} <em>{{ __('landing.choir.em') }}</em></h2>
                            <p class="lnf-say">{{ __('landing.choir.body') }}</p>
                            {{-- Starting a group takes Plus while that switch is on (Hillel, 2026-11-12); the page says so. --}}
                            @if ($teams)
                                <p class="lnf-plusline"><span class="lnf-soon">{{ __('pages.landing.plans_plus') }}</span><span>{{ __('landing.choir.plus') }}</span></p>
                            @endif
                            <div class="lnf-go">
                                <a href="{{ \App\Support\Http\Hosts::app('/groups?start=1&ref=landing') }}" class="lnf-quiet">{{ __('pages.site.group_start') }}</a>
                                <a href="/for/choir-directors?ref=landing" class="lnf-quiet">{{ __('site.teams.directors') }}</a>
                            </div>
                        </div>
                        @if ($stage !== null)
                            @php($singers = (array) \Illuminate\Support\Facades\Lang::get('landing.choir.singers'))
                            @php($states = [['recorded', 'lnf-ok'], ['recorded', 'lnf-ok'], ['learning', 'lnf-mid'], ['waiting', '']])
                            <div class="lnf-glass lnf-choir" aria-hidden="true">
                                <p class="lnf-choir-h"><b>{{ $song }}</b><span>{{ __('landing.choir.covered') }}</span></p>
                                @foreach ($stage['lanes'] as $lane)
                                    @php($state = $states[$loop->index] ?? ['waiting', ''])
                                    @php($singer = (string) ($singers[$loop->index] ?? ''))
                                    <p class="lnf-row lnf-v{{ $lane['n'] }}"><span class="lnf-orb">{{ \App\Support\Pages\LandingStage::initials($singer) }}</span><span><b>{{ $singer }}</b><small>{{ $lane['name'] }}</small></span><em class="{{ $state[1] }}">{{ __('landing.choir.'.$state[0]) }}</em></p>
                                @endforeach
                                <p class="lnf-choir-n">{{ __('landing.choir.note') }}</p>
                            </div>
                        @endif
                    </div>
                    <div class="lnf-floor" aria-hidden="true"><span><i></i><i></i><i></i><i></i></span></div>
                </section>

                {{-- 7, the plans and four questions: the landing's own prices, lists and answers. --}}
                <section class="lnf-sc" id="lnf-s7" data-s="7" aria-labelledby="lnf-h7">
                    <div class="lnf-cap">
                        <h2 id="lnf-h7" class="lnf-h2 lnf-h2-two">{{ __('pages.landing.plans_title') }} <em>{{ __('pages.landing.plans_title_more') }}</em></h2>
                        <p class="lnf-say">{{ __('landing.plans.body') }} <a href="/pricing" class="lnf-lk">{{ __('pages.landing.plans_compare') }}</a></p>
                    </div>
                    @php($replace = [':free_songs' => (string) $plans['freeSongs'], ':free_storage' => $plans['freeStorage'], ':storage' => $plans['storage']])
                    <div class="lnf-money">
                        <div class="lnf-plans">
                            <div class="lnf-glass lnf-plan">
                                <h3>{{ __('pages.landing.plans_free') }}</h3>
                                <p class="lnf-price">{{ str_replace('.00', '', \App\Support\Pages\Prices::format(0, $price['currency'])) }}</p>
                                <p class="lnf-note">{{ __('pages.landing.plans_free_note') }}</p>
                                <ul>
                                    {{-- Every course is free, one line; the beginner course alone until the courses open (plus_courses), as on /pricing. --}}
                                    @foreach (\App\Support\Pages\Landing::list('plans_free_list', $replace) as $line)
                                        <li><x-site.icon name="check" size="16" />{{ $loop->first && ! app(\App\Support\Flags::class)->on('plus_courses') ? __('pages.landing.plans_free_beginner') : $line }}</li>
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

                {{-- 8, the close: the five marketing pages' one closing band (components/site/close-band.blade.php; Hillel,
                     2026-11-16, of what the old landing had: bring back only "Explore Stackapella"), dressed by this page's
                     sheet: the mark's four bars, the page's line, Start singing, and the other four pages. --}}
                <x-site.close-band page="home" :href="$links['start']" funnel class="lnf-sc lnf-close" id="lnf-s8" data-s="8">
                    <span class="lnf-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                    @if ($gameSheet)
                        <p class="s-kick s-skin-g s-player">{{ __('pages.landing.game.final_kick') }}</p>
                    @endif
                </x-site.close-band>
                {{-- A mudeo arrival's letter (the email's link leads here). --}}
                @if ($mudeo)
                    <section class="lnf-sc lnf-letter" id="mudeo" aria-labelledby="mudeo-h">
                        <div>
                            <p class="lnf-kick">{{ \App\Support\Site\Icons::mark(24) }} {{ __('pages.landing.mudeo.eyebrow') }}</p>
                            <h2 id="mudeo-h" class="lnf-h2 lnf-h2-two">{{ __('pages.landing.mudeo.title') }}</h2>
                        </div>
                        <div>
                            @foreach ($mudeoLetter as $paragraph)
                                <p class="lnf-say">{{ $paragraph }}</p>
                            @endforeach
                            <p class="lnf-say"><b>{{ __('pages.landing.mudeo.signature') }}</b></p>
                        </div>
                    </section>
                @endif
            </div>
        </main>
        {{-- The site's own foot (its links to every part of the site, its count and its script), dressed by this page's sheet. --}}
        <div class="lnf-foot-wrap"><x-site.footer /></div>

        {{-- The dock, in every scene and down the page: the play disc (the player's own button), the time, the line being
             sung with the next under it, and Start singing. --}}
        @if ($stage !== null)
            <div class="lnf-dock" role="group" aria-label="{{ __('landing.dock', ['song' => $song]) }}">
                <i class="lnf-rail" aria-hidden="true"></i>
                <div class="lnf-tp">
                    <button type="button" class="lnf-disc lnf-orb" data-play aria-label="{{ __('pages.landing.hero.play') }}" data-label-play="{{ __('pages.landing.hero.play') }}" data-label-stop="{{ __('pages.landing.hero.stop') }}"><span class="lnf-i-play"><x-site.icon name="play" size="20" /></span><span class="lnf-i-stop"><x-site.icon name="stop" size="20" /></span></button>
                    <span class="lnf-time"><span class="lnf-sr">{{ __('landing.time') }}</span> <span data-time>0:00 / {{ $stage['seconds'] }}</span></span>
                    {{-- How far the recorded voices' download is, while a press waits for them (the script writes it; a failure
                         is said over the disc, by the player). --}}
                    <span class="lnf-load" aria-hidden="true" data-lnf-load="dock" data-loading="{{ __('landing.loading') }}"></span>
                </div>
                <div class="lnf-words" translate="no" data-lnf-words>
                    @foreach ($stage['phrases'] as $phrase)
                        <p class="lnf-line" data-from="{{ $phrase['from'] }}" data-to="{{ $phrase['to'] }}" @unless ($loop->first) hidden @endunless>@foreach ($phrase['words'] as $word)<span data-b="{{ $word['beat'] }}">{{ $word['text'] }}</span> @endforeach</p>
                    @endforeach
                    <p class="lnf-next" data-lnf-next aria-hidden="true">{{ implode(' ', array_column($stage['phrases'][1]['words'] ?? [], 'text')) }}</p>
                </div>
                <a href="{{ $links['start'] }}" class="lnf-start" data-funnel-start>{{ __('pages.landing.start') }}</a>
            </div>
            <p class="lnf-sr" role="status" aria-live="polite" data-lnf-live data-playing="{{ __('landing.live.playing', ['song' => $song]) }}" data-stopped="{{ __('landing.live.stopped') }}" data-alone="{{ __('landing.live.alone', ['part' => ':part']) }}" data-all="{{ __('landing.live.all') }}"></p>
        @endif
    </div>

    {{-- The player's words for loading, a slow load and a failure (js/public/play-state.js reads them by this id, shows
         them and says them to a screen reader): here the load is the recorded voices' download. --}}
    <template id="s-play-words" data-loading="{{ __('landing.loading') }}" data-slow="{{ __('pages.site.play_slow') }}" data-failed="{{ __('landing.failed') }}"></template>
    {{-- The app's first screen, fetched while the visitor reads: timing only. --}}
    @include('site._app-prefetch', ['prefetch' => \App\Support\Site\AppPrefetch::landing()])
    {{-- The funnel (the backend's $funnel): whether counts are on, and the experiment enrolling, if any. --}}
    @php($funnelData = $funnel ?? ['counts' => false, 'experiment' => null])
    <script type="application/json" data-funnel>@json($funnelData)</script>
    <script type="module" src="/js/public/funnel.js"></script>
    <script type="module" src="/js/public/mixer.js"></script>
    <script type="module" src="/js/public/landing.js"></script>
</body>
</html>
