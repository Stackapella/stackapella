{{-- The combined landing, candidate "grok" (/next-grok, docs/landing-next-grok-plan.md; Hillel: "a full screen immersive
     experience ... starting from scratch with a CSS heavy amazing design"): the film, the player and the page as one
     stage. The stage (the sky, the four voice lanes, the words) is fixed behind the page; nine full-screen scenes
     scroll over it, and js/public/landing-next-grok.js tells the stage which scene is up, so the lanes lie back, face
     you, zoom to one part, fill in and fold into the mark as the film does. The sound is js/public/mixer.js's, through
     its own hooks (data-player, data-song, data-play, data-voice, data-hear-alone, data-pick, data-beat, data-time,
     --p): the real recording, a part alone through its own gain, one audio clock; scrolling never touches it. Every
     gem is placed from the song's notes (App\Support\Pages\LandingNextGrok). Calm only, light and dark, whatever the
     skin, until Hillel picks a candidate. Its styles are the .s-lng-* block in css/input.css and nothing else. --}}
@extends('public.layout')

@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('head')
    <meta name="description" content="{{ $description }}">
@endsection

@section('content')
    @php($scenes = (array) \Illuminate\Support\Facades\Lang::get('pages.landing_next_grok.scenes'))
    @php($sceneKeys = array_keys($scenes))
    @php($nav = ['songs' => '/songs', 'courses' => '/courses', 'tools' => $toolsHome, 'pricing' => '/pricing'])
    @php($appUrl = app()->getLocale() === 'en' ? $links['app'] : $links['app'].'&lang='.app()->getLocale())
    @php($songTitle = $song['title'] ?? '')
    <main class="s-lng" id="lng" data-lng @if ($song !== null) data-player @endif>
        @if ($song !== null)
            <script type="application/json" data-song>@json($song)</script>
        @endif

        {{-- The bar: slim and see-through over the stage; it tucks away as you scroll down and comes back as you scroll up. --}}
        <header class="s-lng-bar" data-lng-bar>
            <a href="/" class="s-lng-brand">{{ \App\Support\Site\Icons::mark(28) }}<span>{{ __('meta.brand') }}</span></a>
            <nav class="s-lng-nav" aria-label="{{ __('meta.brand') }}">
                @foreach ($nav as $key => $path)
                    <a href="{{ $path }}">{{ __('pages.nav.'.$key) }}</a>
                @endforeach
            </nav>
            <button type="button" class="s-lng-icon" data-theme-toggle aria-label="{{ __('pages.nav.theme') }}"><span class="s-lng-on-light"><x-site.icon name="moon" size="18" /></span><span class="s-lng-on-dark"><x-site.icon name="sun" size="18" /></span></button>
            @if ($languages !== null)
                @php($here = collect($languages['choices'])->firstWhere('current', true))
                <details class="s-lng-lang" data-lang-menu>
                    <summary class="s-lng-icon" aria-label="{{ __('pages.nav.language', ['language' => $here['name'] ?? '']) }}"><x-site.icon name="globe" size="18" /><span aria-hidden="true">{{ strtoupper($languages['current']) }}</span></summary>
                    <ul class="s-lng-pop" role="list">
                        @foreach ($languages['choices'] as $choice)
                            <li><a href="{{ $choice['url'] }}" hreflang="{{ $choice['code'] }}" lang="{{ $choice['code'] }}" data-lang-choice="{{ $choice['code'] }}" @if ($choice['current']) aria-current="true" @endif>{{ $choice['name'] }}</a></li>
                        @endforeach
                    </ul>
                </details>
            @endif
            <a href="{{ $appUrl }}" class="s-lng-open">{{ __('pages.nav.open_app') }}</a>
            <details class="s-lng-menu">
                <summary class="s-lng-icon" aria-label="{{ __('pages.landing_next_grok.menu') }}"><x-site.icon name="menu" size="18" /></summary>
                <nav class="s-lng-pop" aria-label="{{ __('pages.landing_next_grok.menu') }}">
                    @foreach ($nav as $key => $path)
                        <a href="{{ $path }}">{{ __('pages.nav.'.$key) }}</a>
                    @endforeach
                    <a href="/help">{{ __('pages.nav.help') }}</a>
                </nav>
            </details>
        </header>

        {{-- Scene 0, Play: the headline and the one primary. First in the page, so Play is the first thing a keyboard reaches after the bar. --}}
        <section class="s-lng-scene s-lng-s0" id="lng-{{ $sceneKeys[0] }}" data-lng-scene="0" aria-labelledby="lng-h0">
            <div class="s-lng-copy">
                <h1 id="lng-h0" class="s-lng-h1"><span>{{ __('pages.landing.headline') }}</span> <em>{{ __('pages.landing.headline_em') }}</em></h1>
                <p class="s-lng-lead">{{ __('pages.landing_next_grok.lead') }}</p>
            </div>
            @if ($song !== null)
                <button type="button" class="s-lng-play" data-play aria-label="{{ __('pages.landing.hero.play') }}" data-label-play="{{ __('pages.landing.hero.play') }}" data-label-stop="{{ __('pages.landing.hero.stop') }}">
                    <span class="s-lng-disc gem" aria-hidden="true"><span class="s-lng-i-play"><x-site.icon name="play" size="34" /></span><span class="s-lng-i-stop"><x-site.icon name="stop" size="30" /></span></span>
                    <span class="s-lng-play-words" aria-hidden="true"><b class="s-lng-when-idle">{{ __('pages.landing_next_grok.play', ['song' => $songTitle]) }}</b><b class="s-lng-when-playing">{{ __('pages.landing.hero.stop') }}</b><small class="s-lng-when-idle">{{ __('pages.landing_next_grok.play_note') }}</small><small class="s-lng-when-playing">{{ __('pages.landing.hero.hear_lead') }}</small></span>
                </button>
            @endif
        </section>

        {{-- The stage, fixed behind every scene. Its lanes are the player's parts: the whole lane hears that part alone,
             "I sing this" makes it yours. The gems, the meter and the words are pictures of what plays (aria-hidden);
             the buttons carry the names, and the line below says what the stage plays. --}}
        @if ($song !== null)
            <div class="s-lng-stage" data-lng-stage>
                <div class="s-lng-sky" aria-hidden="true"></div>
                <div class="s-lng-floor">
                    <div class="s-lng-deck" role="radiogroup" aria-label="{{ __('pages.landing.hero.parts_label') }}">
                        <i class="s-lng-head" aria-hidden="true"></i>
                        @foreach ($song['parts'] as $part)
                            @php($partRole = \Illuminate\Support\Facades\Lang::has('pages.landing.hero.roles.'.$part['key']) ? __('pages.landing.hero.roles.'.$part['key']) : '')
                            <div class="s-lng-lane s-lng-v{{ $part['color'] }}" data-voice="{{ $part['key'] }}" data-part-key="{{ $part['key'] }}" data-learn="{{ $learn[$part['key']] }}" data-learn-label="{{ __('pages.landing_next_grok.learn', ['part' => mb_strtolower($part['name'])]) }}">
                                <button type="button" class="s-lng-hear" aria-pressed="false" data-hear-alone aria-label="{{ __('pages.landing.hero.hear_only', ['part' => $part['name']]) }}" data-label="{{ __('pages.landing.hero.hear_only', ['part' => $part['name']]) }}" data-label-back="{{ __('pages.landing.hero.hear_all_back') }}"></button>
                                <span class="s-lng-name" aria-hidden="true"><b>{{ $part['name'] }}</b><span class="s-lng-meter"><i></i><i></i><i></i><i></i></span><span class="s-lng-tag">{{ __('pages.landing_next_grok.alone') }}</span><small>{{ $partRole }}</small><span class="s-lng-you"><i class="gem">{{ mb_substr(__('pages.landing.steps.voice.you'), 0, 1) }}</i>{{ __('pages.landing.steps.voice.you') }}</span></span>
                                <span class="s-lng-rec" aria-hidden="true">{{ __('pages.landing.steps.record.recording') }}</span>
                                <span class="s-lng-track" aria-hidden="true">
                                    <span class="s-lng-roll" translate="no">
                                        @foreach ($gems[$part['key']] as $gem)
                                            <i class="s-lng-gem gem" data-beat="{{ $gem['beat'] }}" data-length="{{ $gem['length'] }}" style="--x: {{ $gem['left'] }}%; --w: {{ $gem['width'] }}%; --y: {{ $gem['top'] }}">{{ $gem['text'] }}</i>
                                        @endforeach
                                        @if ($loop->first && $trace !== '')
                                            <svg class="s-lng-trace" viewBox="0 0 100 100" preserveAspectRatio="none"><path d="{{ $trace }}" /></svg>
                                        @endif
                                    </span>
                                </span>
                                <button type="button" class="s-lng-mine" role="radio" aria-checked="false" tabindex="{{ $loop->first ? '0' : '-1' }}" data-pick aria-label="{{ __('pages.landing.hero.mine_label', ['part' => $part['name']]) }}" data-label="{{ __('pages.landing.hero.mine_label', ['part' => $part['name']]) }}" data-label-you="{{ __('pages.landing.hero.your_part', ['part' => $part['name']]) }}"><span class="s-lng-mine-off">{{ __('pages.landing.hero.mine_pick') }}</span><span class="s-lng-mine-on">{{ __('pages.landing.hero.mine') }}</span></button>
                            </div>
                        @endforeach
                    </div>
                </div>
                <p class="s-lng-words" aria-hidden="true" translate="no">
                    @foreach ($phrases as $phrase)
                        <span class="s-lng-phrase">@foreach ($phrase as $word)<span data-beat="{{ $word['beat'] }}">{{ $word['text'] }}</span>{{ $word['joins'] ? '' : ' ' }}@endforeach</span>
                    @endforeach
                </p>
                <p class="sr-only">{{ __('pages.landing.hero.alt_open', ['song' => $songTitle, 'words' => \App\Support\Pages\Landing::sentence($song['words'])]) }}</p>
            </div>
        @endif

        {{-- Scenes 1 to 4: how it works, told on the same lanes. --}}
        <section class="s-lng-scene s-lng-s1 s-lng-v2" id="lng-{{ $sceneKeys[1] }}" data-lng-scene="1" aria-labelledby="lng-h1">
            <div class="s-lng-copy">
                <p class="s-lng-k"><b class="gem">1</b>{{ $scenes['listen'] }}</p>
                <h2 id="lng-h1" class="s-lng-h2">{{ __('pages.landing_next_grok.listen.title') }}</h2>
                <p class="s-lng-p">{{ __('pages.landing_next_grok.listen.body') }}</p>
                @if ($song !== null)
                    @php($tune = $song['parts'][0])
                    <a href="{{ $learn[$tune['key']] }}" class="s-lng-btn s-lng-btn-quiet" data-lng-learn>{{ __('pages.landing_next_grok.learn', ['part' => mb_strtolower($tune['name'])]) }} <x-site.icon name="arrow" size="16" /></a>
                @endif
            </div>
        </section>
        <section class="s-lng-scene s-lng-s2 s-lng-v1" id="lng-{{ $sceneKeys[2] }}" data-lng-scene="2" aria-labelledby="lng-h2">
            <div class="s-lng-copy">
                <p class="s-lng-k"><b class="gem">2</b>{{ $scenes['sing'] }}</p>
                <h2 id="lng-h2" class="s-lng-h2">{{ __('pages.landing_next_grok.sing.title') }}</h2>
                <p class="s-lng-p">{{ __('pages.landing_next_grok.sing.body') }}</p>
                <p class="s-lng-seg" aria-hidden="true"><span>{{ __('pages.landing.steps.voice.listen') }}</span><span class="s-lng-seg-on">{{ __('pages.landing.steps.voice.along') }}</span><span>{{ __('pages.landing.steps.voice.alone') }}</span></p>
            </div>
        </section>
        <section class="s-lng-scene s-lng-s3 s-lng-v3" id="lng-{{ $sceneKeys[3] }}" data-lng-scene="3" aria-labelledby="lng-h3">
            <div class="s-lng-copy">
                <p class="s-lng-k"><b class="gem">3</b>{{ $scenes['record'] }}</p>
                <h2 id="lng-h3" class="s-lng-h2">{{ __('pages.landing_next_grok.record.title') }}</h2>
                <p class="s-lng-p">{{ __('pages.landing.steps.record.body') }}</p>
            </div>
        </section>
        <section class="s-lng-scene s-lng-s4 s-lng-v4" id="lng-{{ $sceneKeys[4] }}" data-lng-scene="4" aria-labelledby="lng-h4">
            <div class="s-lng-copy">
                <p class="s-lng-k"><b class="gem">4</b>{{ $scenes['share'] }}</p>
                <h2 id="lng-h4" class="s-lng-h2">{{ __('pages.landing.steps.share.title') }}</h2>
                <p class="s-lng-p">{{ __('pages.landing_next_grok.share.body') }}</p>
                <p class="s-lng-chips" aria-hidden="true"><span><x-site.icon name="link" size="15" /> {{ __('pages.landing.steps.share.link') }}</span><span><x-site.icon name="video" size="15" /> {{ __('pages.landing.steps.share.video') }}</span></p>
                <a href="{{ $links['start'] }}" class="s-lng-btn">{{ __('pages.landing.start') }} <x-site.icon name="arrow" size="18" /></a>
            </div>
        </section>

        {{-- Scene 5, Free: three doors, each with its live count and a few real names. --}}
        <section class="s-lng-scene s-lng-s5" id="lng-{{ $sceneKeys[5] }}" data-lng-scene="5" aria-labelledby="lng-h5">
            <div class="s-lng-wide">
                <div class="s-lng-headrow">
                    <p class="s-lng-k">{{ __('pages.landing.free_courses_kick') }}</p>
                    <h2 id="lng-h5" class="s-lng-h2">{{ __('pages.landing.free_courses_title') }}</h2>
                </div>
                <ul class="s-lng-slabs" role="list">
                    <li class="s-lng-slab s-lng-v2">
                        <ul class="s-lng-eg" role="list">@foreach ($courses as $slug)<li>{{ __('site.courses.pages.'.$slug.'.name') }}</li>@endforeach</ul>
                        <p class="s-lng-num">{{ $counts['courses'] }}</p>
                        <h3>{{ __('pages.nav.courses') }}</h3>
                        <p>{{ __('pages.landing_next_grok.free.courses') }}</p>
                        <a href="/courses" class="s-lng-go">{{ __('pages.site.all_courses') }} <x-site.icon name="arrow" size="16" /></a>
                    </li>
                    <li class="s-lng-slab s-lng-v1">
                        <ul class="s-lng-eg" role="list" translate="no">@foreach ($songs as $name)<li>{{ $name }}</li>@endforeach</ul>
                        <p class="s-lng-num">{{ $counts['songs'] }}</p>
                        <h3>{{ __('pages.nav.songs') }}</h3>
                        <p>{{ __('pages.landing_next_grok.free.songs') }}</p>
                        <a href="/songs" class="s-lng-go">{{ __('pages.landing_next_grok.free.songs_go') }} <x-site.icon name="arrow" size="16" /></a>
                    </li>
                    <li class="s-lng-slab s-lng-v3">
                        <ul class="s-lng-eg" role="list">@foreach ($tools as $tool)<li>{{ $tool['name'] }}</li>@endforeach</ul>
                        <p class="s-lng-num">{{ $counts['tools'] }}</p>
                        <h3>{{ __('pages.nav.free_tools') }}</h3>
                        <p>{{ __('pages.landing_next_grok.free.tools') }}</p>
                        <a href="{{ $toolsHome }}" class="s-lng-go">{{ __('pages.landing.free_tools_all') }} <x-site.icon name="arrow" size="16" /></a>
                    </li>
                </ul>
            </div>
        </section>

        {{-- Scene 6, Choirs: the four lanes as four singers (sample names, said as a sample; initials only). --}}
        <section class="s-lng-scene s-lng-s6 s-lng-v3" id="lng-{{ $sceneKeys[6] }}" data-lng-scene="6" aria-labelledby="lng-h6">
            <div class="s-lng-wide s-lng-two">
                <div class="s-lng-headrow">
                    <p class="s-lng-k">{{ __('site.teams.kick') }}</p>
                    <h2 id="lng-h6" class="s-lng-h2">{{ __('site.teams.title') }}</h2>
                    <p class="s-lng-p">{{ __('pages.landing_next_grok.choirs_body') }}</p>
                    <p class="s-lng-btns"><a href="{{ \App\Support\Http\Hosts::app('/groups?start=1&ref=next-grok') }}" class="s-lng-btn">{{ __('pages.site.group_start') }}</a> <a href="/for/choir-directors" class="s-lng-btn s-lng-btn-quiet">{{ __('site.teams.directors') }}</a></p>
                </div>
                @if ($song !== null)
                    @php($status = [['recorded', 100], ['recorded', 100], ['started', 55], ['none', 0]])
                    <figure class="s-lng-choir">
                        <ul role="list">
                            @foreach ($song['parts'] as $part)
                                @php($who = $singers[$loop->index] ?? '')
                                @php($initials = collect(explode(' ', $who))->map(fn ($w) => mb_substr($w, 0, 1))->implode(''))
                                <li class="s-lng-singer s-lng-v{{ $part['color'] }} s-lng-is-{{ $status[$loop->index][0] }}"><i class="gem" aria-hidden="true">{{ $initials }}</i><span><b>{{ $who }}</b><small>{{ $part['name'] }}</small></span><span class="s-lng-far" aria-hidden="true"><i style="--w: {{ $status[$loop->index][1] }}%"></i></span><em>{{ \App\Support\Site\AppWords::get(implode('.', ['groups', 'status', $status[$loop->index][0]])) }}</em></li>
                            @endforeach
                        </ul>
                        <figcaption>{{ __('site.teams.group_caption') }}</figcaption>
                    </figure>
                @endif
            </div>
        </section>

        {{-- Scene 7, Plans: the real prices and lists (config and the landing's own words). --}}
        @php($replace = [':free_songs' => (string) $plans['freeSongs'], ':free_storage' => $plans['freeStorage'], ':storage' => $plans['storage']])
        <section class="s-lng-scene s-lng-s7" id="lng-{{ $sceneKeys[7] }}" data-lng-scene="7" aria-labelledby="lng-h7">
            <div class="s-lng-wide">
                <div class="s-lng-headrow">
                    <h2 id="lng-h7" class="s-lng-h2">{{ __('pages.landing.plans_title') }} <em>{{ __('pages.landing.plans_title_more') }}</em></h2>
                </div>
                <div class="s-lng-slabs s-lng-pair">
                    <div class="s-lng-slab s-lng-plan s-lng-v3">
                        <h3>{{ __('pages.landing.plans_free') }}</h3>
                        <p class="s-lng-price">{{ str_replace('.00', '', \App\Support\Pages\Prices::format(0, $price['currency'])) }} <small>{{ __('pages.landing.plans_free_note') }}</small></p>
                        <ul class="s-lng-list">
                            @foreach (\App\Support\Pages\Landing::list('plans_free_list', $replace) as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                        <a href="{{ $links['start'] }}" class="s-lng-btn">{{ __('pages.landing.start') }} <x-site.icon name="arrow" size="18" /></a>
                    </div>
                    <div class="s-lng-slab s-lng-plan s-lng-v1">
                        <h3>{{ __('pages.landing.plans_plus') }} @unless ($plans['checkoutOpen'])<span class="s-lng-soon">{{ __('pages.landing.plans_soon') }}</span>@endunless</h3>
                        <p class="s-lng-price">{{ $price['yearAsMonth'] }} <small>{{ __('pages.landing.plans_month') }} · {{ __('pages.landing.plans_billed_year', ['year' => $price['year']]) }}</small></p>
                        <ul class="s-lng-list">
                            @foreach (\App\Support\Pages\Landing::list('plans_plus_list') as [$gain, $fact])
                                <li><b>{{ $gain }}</b> {{ strtr($fact, $replace) }}</li>
                            @endforeach
                        </ul>
                        <a href="/pricing" class="s-lng-go">{{ __('pages.landing.plans_compare') }} <x-site.icon name="arrow" size="16" /></a>
                    </div>
                </div>
            </div>
        </section>

        {{-- Scene 8, Start: the mark the lanes folded into, the closing line, three questions and the ways out. --}}
        <section class="s-lng-scene s-lng-s8" id="lng-{{ $sceneKeys[8] }}" data-lng-scene="8" aria-labelledby="lng-h8">
            <div class="s-lng-end">
                <h2 id="lng-h8" class="s-lng-h2">{{ __('pages.site.close.home.title') }} <em>{{ __('pages.site.close.home.em') }}</em></h2>
                <a href="{{ $links['start'] }}" class="s-lng-btn s-lng-btn-big">{{ __('pages.landing.start') }} <x-site.icon name="arrow" size="18" /></a>
                <p class="s-lng-p">{{ __('pages.site.close.home.line') }}</p>
                <div class="s-lng-qs">
                    @foreach ($faq as $qa)
                        <details name="lng-faq"><summary>{{ $qa[0] }}</summary><p>{{ $qa[1] }}</p></details>
                    @endforeach
                </div>
                <footer class="s-lng-foot">
                    <nav aria-label="{{ __('pages.nav.menu') }}">
                        @foreach ([...$nav, 'help' => '/help', 'terms' => '/legal/terms', 'privacy' => '/legal/privacy'] as $key => $path)
                            <a href="{{ $path }}">{{ __('pages.nav.'.$key) }}</a>
                        @endforeach
                    </nav>
                    <p>{{ __('pages.nav.rights', ['year' => now()->year]) }}@if (is_string($song['credit'] ?? null)) · <span translate="no">{{ $song['credit'] }}</span>@endif · {{ __('pages.landing_next_grok.voices_note') }}</p>
                </footer>
            </div>
        </section>

        {{-- The bottom bar: Play wherever you are, the time, each part alone, and the nine scenes (real links). --}}
        <div class="s-lng-hud" data-lng-hud>
            @if ($song !== null)
                <div class="s-lng-mini">
                    <button type="button" class="s-lng-mini-play" data-lng-play aria-label="{{ __('pages.landing.hero.play') }}"><span class="s-lng-disc gem" aria-hidden="true"><span class="s-lng-i-play"><x-site.icon name="play" size="16" /></span><span class="s-lng-i-stop"><x-site.icon name="stop" size="14" /></span></span></button>
                    <span class="s-lng-mini-t"><b translate="no">{{ $songTitle }}</b><span data-time>0:00 / {{ $song['length'] }}</span></span>
                    <span class="s-lng-dots" role="group" aria-label="{{ __('pages.landing_next_grok.parts_bar') }}">
                        @foreach ($song['parts'] as $part)
                            <button type="button" class="gem s-lng-v{{ $part['color'] }}" data-lng-part="{{ $part['key'] }}" aria-pressed="false" aria-label="{{ __('pages.landing.hero.hear_only', ['part' => $part['name']]) }}">{{ mb_substr($part['name'], 0, 1) }}</button>
                        @endforeach
                    </span>
                </div>
            @endif
            <nav class="s-lng-rail" aria-label="{{ __('pages.landing_next_grok.scenes_label') }}">
                @foreach ($scenes as $key => $name)
                    <a href="#lng-{{ $key }}" data-lng-rail="{{ $loop->index }}" @if ($loop->first) aria-current="true" @endif><i aria-hidden="true">{{ $loop->first ? '' : $loop->index }}</i><b>{{ $name }}</b></a>
                @endforeach
            </nav>
        </div>
    </main>
    {{-- This page load's one anonymous count, its own kind (sv_next_grok): site.js sends it. --}}
    @if ($visit !== null)
        <script type="application/json" data-site-visit>@json(['kind' => $visit])</script>
    @endif
@endsection

@section('scripts')
    <script type="module" src="/js/public/site.js"></script>
    @if ($languages !== null)
        <script type="module" src="/js/public/lang-menu.js"></script>
    @endif
    <script type="module" src="/js/public/mixer.js"></script>
    <script type="module" src="/js/public/landing-next-grok.js"></script>
@endsection
