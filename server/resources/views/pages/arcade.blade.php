{{-- The home in game mode, the arcade (/arcade and /es/arcade; Hillel, 2026-11-24: "on the main home page when you enable
     game mode switch to the view we used to show with the in-app game experience"; on the press shot of its cabinet:
     "exactly"): the home of 2026-11-16 (6eb40cce9), its game skin only. The headline, its line, Start singing and Hear four
     voices, then the cabinet, the app's own Sing lane (js/embed/lane.js) singing the melody with the page's HUD around it
     (js/public/cabinet.js); the four facts, then its sections with today's facts and no AI pitch (the home's rule, so the
     plans leave the songs sung by AI out). Out of search, its canonical the home; game mode turned on brings a visitor here
     and anything else sends them back to the home (App\Support\Site\ArcadeHandoff). Its sheet is css/arcade.css, the
     2026-11-16 rules for its own pieces, linked on this page only. --}}
@extends('public.layout')

@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('head')
    {{ \App\Support\Site\ArcadeHandoff::tag() }}
    @include('pages._head')
    <link rel="stylesheet" href="{{ \App\Support\Http\StaticVersions::url((string) config('app.web_path'), '/css/arcade.css') }}">
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    @php($facts = \App\Support\Pages\Landing::list('facts', [':free_songs' => (string) $plans['freeSongs']]))
    <main class="s-lv2 s-arcade-home">
        <section class="s-wrap s-hero s-hero-home" aria-labelledby="hero-h">
            <div class="s-hero-split">
                <h1 id="hero-h"><span>{{ __('pages.landing.headline') }}</span> <span class="s-hero-em">{{ __('pages.landing.headline_em') }}</span></h1>
                <div class="s-hero-go">
                    <p class="s-lead">{{ __('pages.landing.lead') }}</p>
                    <div class="s-hero-btns">
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg" data-funnel-start>{{ __('pages.landing.start') }} <x-site.icon name="arrow" size="18" /></a>
                        @if ($hero !== null)
                            <button type="button" class="btn-secondary btn-lg" data-cab-start>{{ __('pages.landing.hear') }}</button>
                        @endif
                    </div>
                    {{-- Lesson 1's chord moment, promised in the app's own words; while the onb1 experiment enrols, its control
                         arm gets none, so the line waits until funnel.js knows this device's arm isn't the control. --}}
                    @php($chordTest = (($funnel['experiment']['id'] ?? null) === 'onb1') && ($funnel['experiment']['enrol'] ?? false))
                    <p class="s-hero-promise" @if ($chordTest) hidden data-variant-except="onb1-a" @endif>{{ __('pages.landing.chord_promise') }}</p>
                </div>
            </div>
            @if ($hero !== null)
                {{-- The cabinet: the app's own Sing lane singing the melody, the soprano, its HUD, play, words and time drawn
                     by the page; nothing beside it moves while it loads or plays. --}}
                <div class="s-heroslot" data-heroslot>
                    <div class="s-slot-g">
                        <div class="s-cabinet" data-cabinet data-take="/templates/takes/amazing-grace-soprano.json" data-glow="var(--color-part-1)" data-loading-words="{{ __('pages.landing.game.loading') }}">
                            <div class="s-cab-hud"><span class="s-cab-live">{{ __('pages.landing.game.live') }}</span><span translate="no">{{ $hero['title'] }} · {{ __('pages.landing.hero.parts.soprano') }}</span><span class="s-cab-combo">{{ __('pages.landing.game.combo') }} <b data-cab-combo>0</b></span></div>
                            <div class="s-cab-screen" data-cab-lane><button type="button" class="s-cab-sound" data-cab-sound hidden><x-site.icon name="muted" size="18" />{{ __('pages.landing.game.sound') }}</button></div>
                            <div class="s-cab-foot">
                                <button type="button" class="s-cab-play" data-cab-play aria-label="{{ __('pages.landing.game.play') }}" data-label-play="{{ __('pages.landing.game.play') }}" data-label-pause="{{ __('pages.landing.game.pause') }}"><span class="s-i-play"><x-site.icon name="play" size="18" /></span><span class="s-i-pause"><x-site.icon name="pause" size="18" /></span></button>
                                <div class="s-cab-words">
                                    <p translate="no">@foreach ($hero['words'] as $word)@if ($word['br'] && ! $loop->first)<span aria-hidden="true">/ </span>@endif<span data-beat="{{ $word['beat'] }}">{{ $word['text'] }}</span>{{ $word['joins'] ? '' : ' ' }}@endforeach</p>
                                    <i class="s-cab-progress"><b data-cab-progress></b></i>
                                </div>
                                <span class="s-cab-time" data-cab-time>0:00</span>
                            </div>
                        </div>
                        <p class="s-cab-cap">{{ __('pages.landing.game.caption') }}</p>
                        {{-- PRESS START's words (js/public/cabinet.js reads them for its start button). --}}
                        <p class="s-cab-boot" aria-hidden="true">{{ __('pages.landing.game.final_kick') }}</p>
                    </div>
                </div>
            @endif
            <ul class="s-factrow">
                @foreach ($facts as $fact)
                    <li class="s-v{{ $loop->iteration }}"><i class="gem" aria-hidden="true"></i>{{ $fact }}</li>
                @endforeach
            </ul>
            @if (\App\Support\Pages\Carols::linked())
                <p class="s-hero-carols"><a href="{{ \App\Support\Pages\Carols::PATH }}" class="s-lk"><x-site.arrow-end :text="__('pages.landing.carols')" spaced /></a></p>
            @endif
        </section>

        @if (\App\Support\Site\Showreel::available())
            @include('pages.arcade._showreel')
        @endif

        @include('pages.arcade._steps')
        @include('pages.arcade._own')
        @include('pages.arcade._free')
        @include('pages.arcade._game')
        @include('site._teams', ['landing' => true])
        @include('pages.arcade._guide')
        @include('pages.arcade._plans')

        @if ($stories !== [])
            <section class="s-wrap s-sec" aria-labelledby="stories-h">
                <h2 id="stories-h" class="s-h2">{{ __('pages.landing.stories_title') }}</h2>
                <div class="s-stories">
                    @foreach ($stories as $story)
                        <figure class="s-story">
                            <blockquote>{{ $story['quote'] }}</blockquote>
                            <figcaption>{{ $story['name'] }}@isset($story['part']), {{ $story['part'] }}@endisset</figcaption>
                        </figure>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="s-wrap s-sec" aria-labelledby="faq-h">
            <div class="s-two">
                <div>
                    <p class="s-kick">{{ __('pages.landing.game.faq_kick') }}</p>
                    <h2 id="faq-h" class="s-h2">{{ __('pages.landing.faq_title') }}</h2>
                    <p class="s-sub">{{ __('pages.landing.faq_lead') }}</p>
                    <p class="s-more"><a href="/help" class="s-arrow">{{ __('pages.landing.faq_more') }} <x-site.icon name="arrow" size="16" /></a></p>
                </div>
                <div class="s-faq s-faq-round">
                    @foreach ($faq as $i => $qa)
                        <details @if ($i === 0) open @endif>
                            <summary>{{ $qa[0] }}<span class="s-faq-btn" aria-hidden="true"><span class="s-faq-plus"><x-site.icon name="plus" size="16" /></span><span class="s-faq-minus"><x-site.icon name="minus" size="16" /></span></span></summary>
                            <p>{{ $qa[1] }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>

        @include('pages.arcade._way')

        <x-site.close-band page="home" :href="$links['start']" funnel>
            <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
            <p class="s-kick s-player">{{ __('pages.landing.game.final_kick') }}</p>
        </x-site.close-band>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    @include('site._app-prefetch', ['prefetch' => \App\Support\Site\AppPrefetch::landing()])
    <script type="module" src="/js/public/cabinet.js"></script>
    <script type="application/json" data-funnel>@json($funnel ?? ['counts' => false, 'experiment' => null])</script>
    <script type="module" src="/js/public/funnel.js"></script>
@endsection
