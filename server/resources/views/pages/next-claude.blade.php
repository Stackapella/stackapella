{{-- /next-claude (and /es/next-claude): one of four candidates for the next landing page (Hillel, 2026-11-15: "I want a
     full screen immersive experience ... starting from scratch with a CSS heavy amazing design"; "lets build all 4 so I
     can experience each of them, just do light/dark serious no game mode until we decide"). The page is one stage that
     fills the screen and stays there: the showreel's four lanes of Amazing Grace, played by the song page's own player
     (js/public/mixer.js, through its hooks), and nine scenes that scroll over it (js/public/landing-next-claude.js).
     Every scene's words, links and questions are here, in order, so the page reads and paints with no script; with
     reduced motion (or no script) nothing is pinned and the scenes are nine still panels.
     Its design is its own (.s-lncl-*, css/input.css): none of the site's kit. Its data is the site's (App\Support\Pages\
     NextClaude). Calm only, light and dark: it has no game dress, whatever the skin. Private: never indexed, in no
     sitemap or language list, linked from nowhere. --}}
@extends('public.layout')

@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('head')
    <meta name="description" content="{{ $description }}">
@endsection

@section('content')
    @php($song = $stage['title'] ?? '')
    @php($sceneCount = 9)
    <div class="s-lncl" data-lncl data-scene="0">
        <header class="s-lncl-top" data-lncl-top>
            <a href="/" class="s-lncl-logo" aria-label="{{ __('next_claude.home') }}">{{ \App\Support\Site\Icons::mark(26) }}<span>{{ __('meta.brand') }}</span></a>
            <nav class="s-lncl-nav" aria-label="{{ __('next_claude.pages') }}">
                <a href="/songs">{{ __('pages.nav.songs') }}</a>
                <a href="/courses">{{ __('pages.nav.courses') }}</a>
                <a href="{{ $toolsUrl }}">{{ __('pages.nav.tools') }}</a>
                <a href="/pricing">{{ __('pages.nav.pricing') }}</a>
            </nav>
            <button type="button" class="s-lncl-round" data-theme-toggle aria-label="{{ __('pages.nav.theme') }}"><span class="s-lncl-in-light"><x-site.icon name="moon" size="18" /></span><span class="s-lncl-in-dark"><x-site.icon name="sun" size="18" /></span></button>
            @if (count($languages) > 1)
                @php($here = collect($languages)->firstWhere('current', true))
                <details class="s-lncl-lang" data-lang-menu>
                    <summary class="s-lncl-round" aria-label="{{ __('pages.nav.language', ['language' => $here['name'] ?? '']) }}"><x-site.icon name="globe" size="18" /><span aria-hidden="true">{{ strtoupper($here['code'] ?? '') }}</span></summary>
                    <ul role="list">
                        @foreach ($languages as $choice)
                            <li><a href="{{ $choice['url'] }}" hreflang="{{ $choice['code'] }}" lang="{{ $choice['code'] }}" data-lang-choice="{{ $choice['code'] }}" @if ($choice['current']) aria-current="true" @endif>{{ $choice['name'] }}</a></li>
                        @endforeach
                    </ul>
                </details>
            @endif
            <a href="{{ $links['app'] }}" class="s-lncl-open">{{ __('pages.nav.open_app') }}</a>
        </header>

        <main class="s-lncl-track">
            <div class="s-lncl-stage" id="lncl" data-player data-lncl-stage data-beats="{{ $stage['beats'] ?? 0 }}" role="group" aria-label="{{ __('next_claude.stage', ['song' => $song]) }}">
                @if ($stage !== null)
                    @php($songData = $stage['song'])
                    <script type="application/json" data-song>@json($songData)</script>
                @endif
                <div class="s-lncl-bg" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></div>

                {{-- 0, the overture: the headline, and the one press that starts the song. --}}
                <section class="s-lncl-sc" id="lncl-s0" data-s="0" aria-labelledby="lncl-h0">
                    <div class="s-lncl-cap">
                        <h1 id="lncl-h0" class="s-lncl-h1"><span>{{ __('pages.landing.headline') }}</span> <em>{{ __('pages.landing.headline_em') }}</em></h1>
                        <div>
                            <p class="s-lncl-say">{{ __('next_claude.lede') }} <span class="s-lncl-wide">{{ __('next_claude.lede_more') }}</span> {{ __('next_claude.lede_free') }}</p>
                            <div class="s-lncl-go">
                                @if ($stage !== null)
                                    <button type="button" class="s-lncl-play" data-lncl-play><span class="s-lncl-orb" aria-hidden="true"><span class="s-lncl-i-play"><x-site.icon name="play" size="22" /></span><span class="s-lncl-i-stop"><x-site.icon name="stop" size="22" /></span></span><span class="s-lncl-i-play">{{ __('next_claude.play', ['song' => $song]) }}</span><span class="s-lncl-i-stop">{{ __('next_claude.stop') }}</span></button>
                                @endif
                                <a href="{{ $links['start'] }}" class="s-lncl-quiet s-lncl-wide">{{ __('pages.landing.start') }}</a>
                            </div>
                        </div>
                    </div>
                </section>

                {{-- The four lanes, in every scene: a lane is one button (hear this voice alone, again for all four), its
                     notes as glass gems with their syllables, and the way to learn that part. --}}
                @if ($stage !== null)
                    <div class="s-lncl-world">
                        <div class="s-lncl-deck" role="group" aria-label="{{ __('next_claude.voices') }}" data-lncl-lanes>
                            @foreach ($stage['lanes'] as $lane)
                                <div class="s-lncl-ln s-lncl-v{{ $lane['n'] }}" data-voice="{{ $lane['key'] }}" data-name="{{ mb_strtolower($lane['name']) }}">
                                    <button type="button" class="s-lncl-hit" data-hear-alone aria-pressed="false" aria-label="{{ __('pages.landing.hero.hear_only', ['part' => mb_strtolower($lane['name'])]) }}" data-label="{{ __('pages.landing.hero.hear_only', ['part' => mb_strtolower($lane['name'])]) }}" data-label-back="{{ __('pages.landing.hero.hear_all_back') }}"></button>
                                    <div class="s-lncl-tr" aria-hidden="true">
                                        @foreach ($lane['notes'] as $note)
                                            <span class="s-lncl-g" data-b="{{ $note['beat'] }}" data-l="{{ $note['length'] }}" style="--b: {{ $note['beat'] }}; --l: {{ $note['length'] }}; --y: {{ $note['y'] }}">{{ $note['length'] >= 1 ? $note['text'] : '' }}</span>
                                        @endforeach
                                        {{-- Your pitch as Sing draws it, shown in the Sing along scene, up to where the song is. --}}
                                        @if ($loop->first && $lane['trace'] !== '')
                                            <span class="s-lncl-trace"><svg viewBox="0 0 {{ $stage['beats'] }} 1" preserveAspectRatio="none" focusable="false"><path d="{{ $lane['trace'] }}" /></svg></span>
                                        @endif
                                    </div>
                                    <div class="s-lncl-lb" aria-hidden="true">
                                        <b><span class="s-lncl-orb">{{ mb_substr($lane['name'], 0, 1) }}</span>{{ $lane['name'] }}<i class="s-lncl-mt"><i></i><i></i><i></i><i></i></i></b>
                                        <small>{{ __('pages.landing.hero.roles.'.$lane['key']) }}</small>
                                        <span class="s-lncl-chip s-lncl-chip-alone" data-chip="alone">{{ __('next_claude.alone') }}</span>
                                        @if ($loop->first)
                                            <span class="s-lncl-chip" data-chip="2">{{ __('next_claude.sing.chip') }}</span>
                                        @endif
                                        @if ($loop->iteration === 3)
                                            <span class="s-lncl-chip s-lncl-chip-rec" data-chip="3">{{ __('pages.landing.steps.record.recording') }}</span>
                                        @else
                                            <span class="s-lncl-chip" data-chip="{{ $loop->iteration < 3 ? '3 4' : '4' }}">{{ __('pages.landing.steps.voice.you') }}</span>
                                        @endif
                                        @if ($loop->iteration === 3)
                                            <span class="s-lncl-chip" data-chip="4">{{ __('pages.landing.steps.voice.you') }}</span>
                                        @endif
                                    </div>
                                    <a href="{{ $lane['learn'] }}" class="s-lncl-mine" aria-label="{{ __('next_claude.learn_part', ['part' => mb_strtolower($lane['name'])]) }}">{{ __('pages.landing.hero.mine_pick') }}</a>
                                </div>
                            @endforeach
                            <i class="s-lncl-beam" aria-hidden="true"></i>
                        </div>
                    </div>

                    {{-- The bar: the play disc, the time, the line being sung (the next one under it), the singers. --}}
                    <div class="s-lncl-bar">
                        <div class="s-lncl-tp">
                            <button type="button" class="s-lncl-disc s-lncl-orb" data-play aria-label="{{ __('pages.landing.hero.play') }}" data-label-play="{{ __('pages.landing.hero.play') }}" data-label-stop="{{ __('pages.landing.hero.stop') }}"><span class="s-lncl-i-play"><x-site.icon name="play" size="20" /></span><span class="s-lncl-i-stop"><x-site.icon name="stop" size="20" /></span></button>
                            <span class="s-lncl-time"><span class="sr-only">{{ __('next_claude.time') }}</span> <span data-time>0:00 / {{ $stage['seconds'] }}</span></span>
                            <span class="s-lncl-hint">{{ __('next_claude.hint') }}</span>
                        </div>
                        <div class="s-lncl-words" translate="no" data-lncl-words>
                            @foreach ($stage['phrases'] as $phrase)
                                <p class="s-lncl-line" data-from="{{ $phrase['from'] }}" data-to="{{ $phrase['to'] }}" @unless ($loop->first) hidden @endunless>@foreach ($phrase['words'] as $word)<span data-b="{{ $word['beat'] }}">{{ $word['text'] }}</span> @endforeach</p>
                            @endforeach
                            <p class="s-lncl-next" data-lncl-next aria-hidden="true">{{ implode(' ', array_column($stage['phrases'][1]['words'] ?? [], 'text')) }}</p>
                        </div>
                        <p class="s-lncl-cred">@if ($stage['credit'] !== null)<span>{{ $stage['credit'] }}</span>@endif <span class="s-lncl-cue" aria-hidden="true">{{ __('next_claude.scroll') }} <x-site.icon name="chevron" size="14" /></span></p>
                    </div>
                    <i class="s-lncl-rail" aria-hidden="true"></i>
                    <p class="sr-only" role="status" aria-live="polite" data-lncl-live data-playing="{{ __('next_claude.live.playing', ['song' => $song]) }}" data-stopped="{{ __('next_claude.live.stopped') }}" data-alone="{{ __('next_claude.live.alone', ['part' => ':part']) }}" data-all="{{ __('next_claude.live.all') }}"></p>
                @endif

                {{-- 1 to 4, the story: the app's four steps, in its own words. --}}
                @php($steps = [1 => ['hear', __('next_claude.hear.body')], 2 => ['sing', __('pages.landing.steps.voice.body')], 3 => ['record', __('pages.landing.steps.record.body')], 4 => ['share', __('pages.landing.steps.share.body')]])
                @foreach ($steps as $n => [$key, $body])
                    <section class="s-lncl-sc" id="lncl-s{{ $n }}" data-s="{{ $n }}" aria-labelledby="lncl-h{{ $n }}">
                        <div class="s-lncl-cap">
                            <div>
                                <p class="s-lncl-kick"><span class="s-lncl-bars" aria-hidden="true">@for ($i = 1; $i <= 4; $i++)<i @class(['s-lncl-on' => $i <= $n])></i>@endfor</span>{{ __('next_claude.step', ['n' => $n]) }} · {{ __('next_claude.'.$key.'.name') }}</p>
                                <h2 id="lncl-h{{ $n }}" class="s-lncl-h2">{{ __('next_claude.'.$key.'.before') }} <em>{{ __('next_claude.'.$key.'.em') }}</em> {{ __('next_claude.'.$key.'.after') }}</h2>
                            </div>
                            <div>
                                <p class="s-lncl-say">{{ $body }}</p>
                                @if ($n === 1)
                                    <p class="s-lncl-true">{{ __('next_claude.hear.true') }}</p>
                                @endif
                            </div>
                        </div>
                        @if ($n === 2)
                            {{-- The three ways to practise a part, as Sing offers them: a picture of its control. --}}
                            <p class="s-lncl-seg" aria-hidden="true"><span>{{ __('pages.landing.steps.voice.listen') }}</span><span class="s-lncl-on">{{ __('pages.landing.steps.voice.along') }}</span><span>{{ __('pages.landing.steps.voice.alone') }}</span></p>
                        @endif
                        @if ($n === 4)
                            <div class="s-lncl-glass s-lncl-share" aria-hidden="true">
                                <b>{{ __('pages.landing.steps.share.every', ['song' => $song]) }}</b>
                                <p>{{ __('next_claude.share.card') }}</p>
                                <p class="s-lncl-orbs">@for ($i = 1; $i <= 4; $i++)<span class="s-lncl-orb s-lncl-v{{ $i }}">{{ __('pages.landing.steps.voice.you') }}</span>@endfor</p>
                                <p class="s-lncl-pair"><span>{{ __('pages.landing.steps.share.link') }}</span><span>{{ __('pages.landing.steps.share.video') }}</span></p>
                            </div>
                        @endif
                    </section>
                @endforeach

                {{-- 5, free to learn: what there is, by its real numbers, each a way in. --}}
                <section class="s-lncl-sc" id="lncl-s5" data-s="5" aria-labelledby="lncl-h5">
                    <div class="s-lncl-cap">
                        <h2 id="lncl-h5" class="s-lncl-h2">{{ __('next_claude.free.before') }} <em>{{ __('next_claude.free.em') }}</em></h2>
                        <p class="s-lncl-say">{{ __('next_claude.free.body') }}</p>
                    </div>
                    <div class="s-lncl-slabs">
                        @foreach (['courses' => '/courses', 'songs' => '/songs', 'tools' => $toolsUrl] as $key => $href)
                            @php($slab = (array) \Illuminate\Support\Facades\Lang::get('next_claude.free.'.$key))
                            <a href="{{ $href }}" class="s-lncl-glass s-lncl-slab s-lncl-v{{ $loop->iteration === 1 ? 1 : ($loop->iteration === 2 ? 3 : 4) }}">
                                <strong>{{ $counts[$key] }}</strong>
                                <b>{{ $slab[0] }}</b>
                                <span>{{ $slab[1] }}</span>
                                <i aria-hidden="true"><x-site.icon name="arrow" size="18" /></i>
                            </a>
                        @endforeach
                    </div>
                </section>

                {{-- 6, choirs: a choir group as the app shows one. --}}
                <section class="s-lncl-sc" id="lncl-s6" data-s="6" aria-labelledby="lncl-h6">
                    <div class="s-lncl-cap s-lncl-cap-one">
                        <div>
                            <h2 id="lncl-h6" class="s-lncl-h2">{{ __('next_claude.choir.before') }} <em>{{ __('next_claude.choir.em') }}</em></h2>
                            <p class="s-lncl-say">{{ __('next_claude.choir.body') }}</p>
                            <div class="s-lncl-go">
                                <a href="{{ \App\Support\Http\Hosts::app('/groups?start=1&ref='.\App\Support\Pages\NextClaude::REF) }}" class="s-lncl-quiet">{{ __('pages.site.group_start') }}</a>
                                <a href="/for/choir-directors?ref={{ \App\Support\Pages\NextClaude::REF }}" class="s-lncl-quiet">{{ __('site.teams.directors') }}</a>
                            </div>
                        </div>
                    </div>
                    @if ($stage !== null)
                        @php($singers = (array) \Illuminate\Support\Facades\Lang::get('next_claude.choir.singers'))
                        @php($states = [['recorded', 's-lncl-ok'], ['recorded', 's-lncl-ok'], ['learning', 's-lncl-mid'], ['waiting', '']])
                        <div class="s-lncl-glass s-lncl-choir" aria-hidden="true">
                            <p class="s-lncl-choir-h"><b>{{ $song }}</b><span>{{ __('next_claude.choir.covered') }}</span></p>
                            @foreach ($stage['lanes'] as $lane)
                                @php($state = $states[$loop->index] ?? ['waiting', ''])
                                <p class="s-lncl-row"><span class="s-lncl-orb s-lncl-v{{ $lane['n'] }}">{{ mb_substr($lane['name'], 0, 1) }}</span><span><b>{{ $singers[$loop->index] ?? '' }}</b><small>{{ $lane['name'] }}</small></span><em class="{{ $state[1] }}">{{ __('next_claude.choir.'.$state[0]) }}</em></p>
                            @endforeach
                            <p class="s-lncl-choir-n">{{ __('next_claude.choir.note') }}</p>
                        </div>
                    @endif
                </section>

                {{-- 7, the plans and four questions: the landing's own prices, lists and answers. --}}
                <section class="s-lncl-sc" id="lncl-s7" data-s="7" aria-labelledby="lncl-h7">
                    <div class="s-lncl-cap">
                        <h2 id="lncl-h7" class="s-lncl-h2 s-lncl-h2-two">{{ __('pages.landing.plans_title') }} <em>{{ __('pages.landing.plans_title_more') }}</em></h2>
                        <p class="s-lncl-say">{{ __('next_claude.plans.body') }} <a href="/pricing" class="s-lncl-lk">{{ __('pages.landing.plans_compare') }}</a></p>
                    </div>
                    @php($replace = [':free_songs' => (string) $plans['freeSongs'], ':free_storage' => $plans['freeStorage'], ':storage' => $plans['storage']])
                    <div class="s-lncl-plans">
                        <div class="s-lncl-glass s-lncl-plan">
                            <h3>{{ __('pages.landing.plans_free') }}</h3>
                            <p class="s-lncl-price">{{ str_replace('.00', '', \App\Support\Pages\Prices::format(0, $price['currency'])) }}</p>
                            <p class="s-lncl-note">{{ __('pages.landing.plans_free_note') }}</p>
                            <ul>
                                @foreach (\App\Support\Pages\Landing::list('plans_free_list', $replace) as $line)
                                    <li><x-site.icon name="check" size="16" />{{ $line }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="s-lncl-glass s-lncl-plan s-lncl-plus">
                            <h3>{{ __('pages.landing.plans_plus') }} @unless ($plans['checkoutOpen'])<span class="s-lncl-soon">{{ __('pages.landing.plans_soon') }}</span>@endunless</h3>
                            <p class="s-lncl-price">{{ $price['yearAsMonth'] }} <small>{{ __('pages.landing.plans_month') }}</small></p>
                            <p class="s-lncl-note">{{ __('pages.landing.plans_billed_year', ['year' => $price['year']]) }} @unless ($plans['checkoutOpen']){{ __('pages.landing.plans_paused') }}@endunless</p>
                            <ul>
                                @foreach (\App\Support\Pages\Landing::list('plans_plus_list') as [$gain, $fact])
                                    <li><x-site.icon name="check" size="16" /><span><b>{{ $gain }}</b> {{ strtr($fact, $replace) }}</span></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="s-lncl-faq">
                        @foreach ($questions as $qa)
                            <details class="s-lncl-glass" name="lncl-faq" @if ($loop->first) open @endif>
                                <summary>{{ $qa[0] }}<span aria-hidden="true"><span class="s-lncl-plus-i"><x-site.icon name="plus" size="16" /></span><span class="s-lncl-minus-i"><x-site.icon name="minus" size="16" /></span></span></summary>
                                <p>{{ $qa[1] }}</p>
                            </details>
                        @endforeach
                    </div>
                </section>

                {{-- 8, the finale: the mark, the headline, Start singing, and the four bars as the four ways on. --}}
                <section class="s-lncl-sc" id="lncl-s8" data-s="8" aria-labelledby="lncl-h8">
                    <div class="s-lncl-cap s-lncl-cap-end">
                        <span class="s-lncl-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                        <h2 id="lncl-h8" class="s-lncl-h1"><span>{{ __('pages.landing.headline') }}</span> <em>{{ __('pages.landing.headline_em') }}</em></h2>
                        <p class="s-lncl-say">{{ __('pages.site.close.home.line') }}</p>
                        <div class="s-lncl-go"><a href="{{ $links['start'] }}" class="s-lncl-prim">{{ __('pages.landing.start') }} <x-site.icon name="arrow" size="18" /></a></div>
                    </div>
                    <nav class="s-lncl-ways" aria-label="{{ __('next_claude.end.ways') }}">
                        <a href="/songs" class="s-lncl-way s-lncl-v1">{{ __('pages.nav.songs') }} <small>{{ __('next_claude.end.songs', ['count' => $counts['songs']]) }}</small></a>
                        <a href="/courses" class="s-lncl-way s-lncl-v2">{{ __('pages.nav.courses') }} <small>{{ __('next_claude.end.courses', ['count' => $counts['courses']]) }}</small></a>
                        <a href="{{ $toolsUrl }}" class="s-lncl-way s-lncl-v3">{{ __('pages.nav.tools') }} <small>{{ __('next_claude.end.tools', ['count' => $counts['tools']]) }}</small></a>
                        <a href="/pricing" class="s-lncl-way s-lncl-v4">{{ __('pages.nav.pricing') }} <small>{{ __('next_claude.end.pricing') }}</small></a>
                    </nav>
                    <nav class="s-lncl-legal" aria-label="{{ __('next_claude.end.legal') }}">
                        <a href="/help">{{ __('pages.nav.help') }}</a>
                        <a href="/legal/privacy">{{ __('pages.nav.privacy') }}</a>
                        <a href="/legal/terms">{{ __('pages.nav.terms') }}</a>
                    </nav>
                </section>

                <nav class="s-lncl-dots" aria-label="{{ __('next_claude.scenes') }}" data-lncl-dots>
                    @for ($i = 0; $i < $sceneCount; $i++)
                        <a href="#lncl-at-{{ $i }}" aria-label="{{ __('next_claude.scene', ['n' => $i + 1, 'count' => $sceneCount]) }}" @if ($i === 0) aria-current="true" @endif></a>
                    @endfor
                </nav>
            </div>
            {{-- One screen of scroll for each scene: where the page rests, and what the dots lead to. --}}
            <div class="s-lncl-snaps" aria-hidden="true">
                @for ($i = 0; $i < $sceneCount; $i++)
                    <div id="lncl-at-{{ $i }}"></div>
                @endfor
            </div>
        </main>
    </div>
@endsection

@section('scripts')
    {{-- This page's one count, by its own kind (App\Support\Site\SiteVisit): never the landing's. --}}
    @php($visit = \App\Support\Site\SiteVisit::kind(request()))
    @if ($visit !== null)
        @php($visitData = ['kind' => $visit])
        <script type="application/json" data-site-visit>@json($visitData)</script>
    @endif
    <script type="module" src="/js/public/site.js"></script>
    <script type="module" src="/js/public/lang-menu.js"></script>
    <script type="module" src="/js/public/mixer.js"></script>
    <script type="module" src="/js/public/landing-next-claude.js"></script>
@endsection
