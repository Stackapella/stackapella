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

                    {{-- 0, the overture (the consultant's front page that Hillel approved as the blueprint, 2026-11-23, in our
                         own look): the headline, the first lesson as the way in, the song to press play on, and who it's
                         for, each with its one next step. --}}
                    <section class="lnf-sc" id="lnf-s0" data-s="0" aria-labelledby="lnf-h0">
                        <div class="lnf-cap">
                            <h1 id="lnf-h0" class="lnf-h1"><span>{{ __('landing.hero.before') }}</span> <em>{{ __('landing.hero.em') }}</em></h1>
                            <div>
                                <div class="lnf-go">
                                    {{-- The way in: lesson 1, no account needed, in the app (and it says so). --}}
                                    <a href="{{ $links['start'] }}" class="lnf-try" data-funnel-start><span><b>{{ __('landing.hero.try') }}</b><small>{{ __('landing.hero.try_note') }} · {{ __('landing.paths.app') }}</small></span> <x-site.icon name="arrow" size="18" /></a>
                                    @if ($stage !== null)
                                        {{-- What a press commits to: the song, how many voices, how long. --}}
                                        <button type="button" class="lnf-play" data-lnf-play><span class="lnf-orb bubble" aria-hidden="true"><span class="lnf-i-play"><x-site.icon name="play" size="22" /></span><span class="lnf-i-stop"><x-site.icon name="stop" size="22" /></span></span><span class="lnf-play-w"><span class="lnf-i-play">@if ($gameSheet)<x-site.skin :game="__('landing.game.play')" :serious="__('landing.play', ['song' => $song])" />@else{{ __('landing.play', ['song' => $song]) }}@endif</span><span class="lnf-i-stop">{{ __('landing.stop') }}</span><small class="lnf-meta">{{ __('landing.play_meta', ['count' => count($stage['lanes']), 'length' => $stage['seconds']]) }}</small><small class="lnf-load" data-lnf-load data-loading="{{ __('landing.loading') }}" data-failed="{{ __('landing.failed') }}"></small></span></button>
                                    @endif
                                </div>
                            </div>
                            {{-- Find your path: learners, singers making their own a cappella, and leaders preparing a choir,
                                 each with what they get and its one next step, the choir's way in on the first screen. Singers
                                 hear of Hear yourself as a choir while it's live (own_choir). A step into the app says so. --}}
                            {{-- The learner's line is lesson 1's chord moment: while the onb1 experiment enrols, its control arm
                                 gets no chord moment, so it waits until funnel.js knows this device's arm isn't the control. --}}
                            @php($chordTest = (($funnel['experiment']['id'] ?? null) === 'onb1') && ($funnel['experiment']['enrol'] ?? false))
                            <nav class="lnf-paths" aria-labelledby="lnf-paths-h">
                                <h2 id="lnf-paths-h" class="lnf-paths-h">{{ __('landing.paths.label') }}</h2>
                                @php($choir = \App\Support\Pages\SongParts::switchedOn('own_choir'))
                                <ul>
                                    @foreach (['learn' => ['/courses', false, 'go'], 'make' => $choir ? ['/your-choir?ref=choir-home', false, 'go_choir'] : ['#lnf-at-1', false, 'go'], 'lead' => ['/for/choir-directors?ref=for-choirs-landing', false, 'go']] as $key => [$href, $inApp, $go])
                                        <li class="lnf-path lnf-v{{ $loop->iteration === 1 ? 1 : ($loop->iteration === 2 ? 3 : 4) }}">
                                            <a href="{{ $href }}" data-lnf-path="{{ $key }}"@if ($go !== 'go') data-lnf-go-title @endif>
                                                <small class="lnf-who">{{ __('landing.paths.'.$key.'.eyebrow') }}@if ($key === 'learn' && filled(__('landing.paths.learn.badge'))) <i class="lnf-badge">{{ __('landing.paths.learn.badge') }}</i>@endif</small>
                                                <b>{{ __('landing.paths.'.$key.'.who') }}</b>
                                                <span @if ($key === 'learn' && $chordTest) hidden data-variant-except="onb1-a" @endif>{{ __('landing.paths.'.$key.'.what') }}</span>
                                                <em>{{ __('landing.paths.'.$key.'.'.$go) }}@if ($inApp) <small class="lnf-app">{{ __('landing.paths.app') }}</small>@endif <x-site.icon name="arrow" size="16" /></em>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </nav>
                        </div>
                        @if ($stage !== null)
                            <div class="lnf-foot">
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
                                    <div class="lnf-ln lnf-v{{ $lane['n'] }}" data-learn="{{ $lane['learn'] }}" data-voice="{{ $lane['key'] }}" data-name="{{ mb_strtolower($lane['name']) }}" data-title="{{ $lane['name'] }}" data-join="{{ $stage['joins'][$lane['key']] ?? 0 }}">
                                        <button type="button" class="lnf-hit" data-hear-alone aria-pressed="false" aria-label="{{ __('pages.landing.hero.hear_only', ['part' => mb_strtolower($lane['name'])]) }}" data-label="{{ __('pages.landing.hero.hear_only', ['part' => mb_strtolower($lane['name'])]) }}" data-label-back="{{ __('pages.landing.hero.hear_all_back') }}"></button>
                                        <div class="lnf-tr" aria-hidden="true">
                                            @foreach ($lane['notes'] as $note)
                                                <span class="lnf-g gem-note" data-b="{{ $note['beat'] }}" data-l="{{ $note['length'] }}" style="--b: {{ $note['beat'] }}; --l: {{ $note['length'] }}; --y: {{ $note['y'] }}"><span class="gem-word">{{ $note['text'] }}</span></span>
                                            @endforeach
                                            {{-- Your pitch as Sing draws it, shown in the Sing along scene, up to where the song is. --}}
                                            @if ($loop->first && $lane['trace'] !== '')
                                                <span class="lnf-trace"><svg viewBox="0 0 {{ $stage['beats'] }} 1" preserveAspectRatio="none" focusable="false"><path d="{{ $lane['trace'] }}" /></svg></span>
                                            @endif
                                        </div>
                                        {{-- The lane's words and its control. The name and role are plain words; the chips say
                                             where the story is, as flat tags; Solo is drawn as a control (the lane itself is the
                                             button, so the whole lane answers a tap; pressed, it says "Solo on"). The lane's
                                             button already names the voice, so this is for the eye only. --}}
                                        <div class="lnf-lb" aria-hidden="true">
                                            <b><span class="lnf-orb bubble bubble-sm">{{ mb_substr($lane['name'], 0, 1) }}</span>{{ $lane['name'] }}<i class="lnf-mt"><i></i><i></i><i></i><i></i></i></b>
                                            <small>{{ __('pages.landing.hero.roles.'.$lane['key']) }}</small>
                                            <span class="lnf-do">
                                                <span class="lnf-solo"><x-site.icon name="headphones" size="14" /><span class="lnf-solo-off">{{ __('landing.solo') }}</span><span class="lnf-solo-on">{{ __('landing.solo_on') }}</span></span>
                                            </span>
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
                                    <p class="lnf-kick"><span class="lnf-bars" aria-hidden="true">@for ($i = 1; $i <= 4; $i++)<i @class(['lnf-on' => $i <= $n])></i>@endfor</span>{{ __('landing.solo_scene.kick') }} · @if ($gameSheet)<x-site.skin :game="__('landing.game.step', ['n' => $n])" :serious="__('landing.step', ['n' => $n])" />@else{{ __('landing.step', ['n' => $n]) }}@endif</p>
                                    <h2 id="lnf-h{{ $n }}" class="lnf-h2">{{ __('landing.'.$key.'.before') }} <em>{{ __('landing.'.$key.'.em') }}</em> {{ __('landing.'.$key.'.after') }}</h2>
                                </div>
                                <div>
                                    <p class="lnf-say">{{ $body }}</p>
                                    {{-- The way to learn the voice last heard alone (the script renames it; the melody's until one
                                         is). A voice is heard alone by its lane's Solo, so the scene needs no button of its own;
                                         Hear yourself as a choir is the Singers' step in the first screen, said there once. --}}
                                    @if ($first !== null && ($n === 1 || $n === 2))
                                        <div class="lnf-go">
                                            <a href="{{ $first['learn'] }}" class="lnf-arrow" data-lnf-learn data-label="{{ __('landing.learn', ['part' => ':part']) }}"><span>{{ __('landing.learn', ['part' => mb_strtolower($first['name'])]) }}</span> <small class="lnf-app">{{ __('landing.paths.app') }}</small> <x-site.icon name="arrow" size="16" /></a>
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
                                    <p class="lnf-orbs">@for ($i = 1; $i <= 4; $i++)<span class="lnf-orb bubble lnf-v{{ $i }}">{{ __('pages.landing.steps.voice.you') }}</span>@endfor</p>
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
                    {{-- Lesson 1, previewed (the UX review's item, 2026-11-23): its last step as the app shows it, in the app's
                         own words, still and without the mic; an example note, said so. Start lesson one beside it. --}}
                    <div class="lnf-lessonrow">
                    <figure class="lnf-glass lnf-lesson">
                        <div class="lnf-ls-card" aria-hidden="true">
                            <p class="lnf-ls-top"><span>{{ \App\Support\Site\AppWords::fill('course.lessonNumber', ['number' => 1]) }}</span><span class="lnf-ls-bars"><i></i><i></i></span></p>
                            <p class="lnf-ls-ask">{{ \App\Support\Site\AppWords::get('capture.listening') }}</p>
                            <span class="lnf-ls-lane"><i class="gem-note"></i></span>
                            <p class="lnf-ls-is">{{ \App\Support\Site\AppWords::get('results.note.yours') }}</p>
                            {{-- The note as the app names it in the page's language (js/note-names.js: Spanish reads fixed Do). --}}
                            <p class="lnf-ls-note" translate="no">{{ app()->getLocale() === 'es' ? 'La3' : 'A3' }}</p>
                            <p class="lnf-ls-suits">{{ \App\Support\Site\AppWords::get('listening.suits.highMiddle') }}</p>
                        </div>
                        @if (trans()->has('landing.lesson.caption'))
                            <figcaption>{{ __('landing.lesson.caption') }}</figcaption>
                        @endif
                    </figure>
                    <div class="lnf-go lnf-after"><a href="{{ $links['start'] }}" class="lnf-prim" data-funnel-start>{{ __('landing.free.start') }} <small class="lnf-app">{{ __('landing.paths.app') }}</small> <x-site.icon name="arrow" size="18" /></a>
                        {{-- The landing's one seasonal line (Hillel, 2026-11-10), quiet; it hides by itself after 26 December
                             (Carols::linked). --}}
                        @if (\App\Support\Pages\Carols::linked())
                            <a href="{{ \App\Support\Pages\Carols::PATH }}" class="lnf-arrow lnf-carols"><span>{{ __('pages.landing.carols') }}</span></a>
                        @endif
                    </div>
                    </div>
                    {{-- Game mode, said once and optional (the consultant's front page): the real switch, with what it does. --}}
                    <div class="lnf-glass lnf-gm">
                        <x-site.game-toggle size="pill" />
                        <p><b>{{ __('landing.game_mode.title') }}</b> <span>{{ __('landing.game_mode.body') }}</span></p>
                        <i class="lnf-badge">{{ __('landing.game_mode.tag') }}</i>
                    </div>
                    <div class="lnf-floor" aria-hidden="true"><span><i></i><i></i><i></i><i></i></span></div>
                </section>

                {{-- (Choirs have their way in among the three in the first screen and their own page, /for/choir-directors:
                     the home's choir section folded into those, 2026-11-23, the consultant: "radically shorter".) --}}

                {{-- 7, the plans and four questions: the landing's own prices, lists and answers. --}}
                <section class="lnf-sc" id="lnf-s7" data-s="7" aria-labelledby="lnf-h7">
                    <div class="lnf-cap">
                        <h2 id="lnf-h7" class="lnf-h2 lnf-h2-two">{{ __('landing.plans.title') }} <em>{{ __('landing.plans.title_more') }}</em></h2>
                        <p class="lnf-say">{{ \App\Support\Site\Teams::text('landing.plans.body') }} <a href="/pricing" class="lnf-lk">{{ __('pages.landing.plans_compare') }}</a></p>
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
                                {{-- The monthly price, the yearly beside it (the marketing review, 2026-11-22). --}}
                                <p class="lnf-price">{{ $price['month'] }} <small>{{ __('pages.landing.plans_month') }}</small></p>
                                <p class="lnf-note">{{ __('pages.landing.plans_or_year', ['year' => $price['year']]) }} @unless ($plans['checkoutOpen']){{ __('pages.landing.plans_paused') }}@endunless</p>
                                <ul>
                                    @foreach (\App\Support\Pages\Landing::list('plans_plus_list') as [$gain, $fact])
                                        <li><x-site.icon name="check" size="16" /><span><b>{{ $gain }}</b> {{ strtr($fact, $replace) }}</span></li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                        <div class="lnf-faq">
                            @foreach ($questions as $qa)
                                <details class="lnf-glass" name="lnf-faq">
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
                <x-site.close-band page="home" :href="$links['start']" funnel :cue="__('landing.paths.app')" class="lnf-sc lnf-close" id="lnf-s8" data-s="8">
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
                    <button type="button" class="lnf-disc lnf-orb bubble" data-play aria-label="{{ __('pages.landing.hero.play') }}" data-label-play="{{ __('pages.landing.hero.play') }}" data-label-stop="{{ __('pages.landing.hero.stop') }}"><span class="lnf-i-play"><x-site.icon name="play" size="20" /></span><span class="lnf-i-stop"><x-site.icon name="stop" size="20" /></span></button>
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
                <a href="{{ $links['start'] }}" class="lnf-start" data-funnel-start><span>{{ __('pages.landing.start') }}</span> <small class="lnf-app">{{ __('landing.paths.app') }}</small></a>
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
