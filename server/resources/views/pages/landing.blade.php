{{-- The landing page, direction A ("the mixer", board-a2): the headline, its line and one call to
     action, Start singing (Hillel: no eyebrow, one CTA), a song's first line in four voices to play,
     then the four steps with your own song into the creator, your voice as a guide, who it's for, the plans, what's
     on the way (each by its flag; Hum a tune among them), the questions and Start singing. mudeo's singers alone see the banner and the
     letter (MudeoArrival). No list of songs (Hillel): Start singing leads into the app. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
    <x-site.json-ld :data="\App\Support\Site\StructuredData::landing($price, $faq)" />
@endsection

@section('content')
    @if ($mudeo)
        <div class="s-banner" role="region" aria-label="{{ __('pages.landing.mudeo.eyebrow') }}">
            <p class="s-wrap">{{ __('pages.landing.mudeo.banner') }} <a href="#mudeo" class="link-quiet tap-44">{{ __('pages.landing.mudeo.banner_link') }}</a></p>
        </div>
    @endif
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-hero s-hero-home" aria-labelledby="hero-h">
            {{-- Game mode at the top right (Hillel), level with the headline's top on a desktop and above it
                 on a phone, the same place and size in both skins so flipping it never moves it. --}}
            <div class="s-hero-top">
                <h1 id="hero-h"><span>{{ __('pages.landing.headline') }}</span> <span class="s-hero-em">{{ __('pages.landing.headline_em') }}</span></h1>
                <div class="s-hero-side">
                    <x-site.game-toggle size="hero" />
                </div>
            </div>
            <div class="s-hero-row">
                <p class="s-lead">{{ __('pages.landing.lead') }}</p>
                <div class="s-hero-go">
                    {{-- One row in both skins (Hillel): Start singing and the slot's own button, buttons only. --}}
                    <div class="s-hero-btns">
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg" data-funnel-start>{{ __('pages.landing.start_free') }}</a>
                        @if ($hero !== null)
                            <a href="#hero" class="btn-secondary btn-lg s-skin-s" data-hear="hero">{{ __('pages.landing.hear') }}</a>
                            <button type="button" class="btn-secondary btn-lg s-skin-g" data-cab-start>{{ __('pages.landing.game.play') }}</button>
                        @endif
                    </div>
                    {{-- Lesson 1's chord moment, promised in the app's own words. While the onb1 experiment
                         enrols, its control arm gets no chord moment, so the line shows only once
                         funnel.js knows this device's arm isn't the control (onb1-a). --}}
                    @php($chordTest = (($funnel['experiment']['id'] ?? null) === 'onb1') && ($funnel['experiment']['enrol'] ?? false))
                    <p class="s-hero-promise" @if ($chordTest) hidden data-variant-except="onb1-a" @endif>{{ __('pages.landing.chord_promise') }}</p>
                </div>
            </div>
            @if ($hero !== null)
                {{-- One slot (Hillel): the four-voice player in the serious skin, the cabinet in the game skin,
                     the same place and size, so switching never moves the page. The cabinet holds the app's
                     own Sing lane (js/embed/lane.js) singing the melody, the soprano (Hillel: "more recognizable"); the page draws its HUD,
                     the play button, the words and the time. Switching plays a short power-down and boot
                     (js/public/site.js). --}}
                <div class="s-heroslot" data-heroslot>
                    <div class="s-slot-s">
                        <x-site.mixer :song="$hero" id="hero">
                            <p class="s-mix-hint">{{ __('pages.landing.hero.hint') }}</p>
                        </x-site.mixer>
                    </div>
                    <div class="s-slot-g">
                        <div class="s-cabinet" data-cabinet data-take="/templates/takes/amazing-grace-soprano.json" data-glow="var(--color-part-1)">
                            <div class="s-cab-hud"><span class="s-cab-live">{{ __('pages.landing.game.live') }}</span><span translate="no">{{ $hero['title'] }} · {{ __('pages.landing.hero.parts.soprano') }}</span><span class="s-cab-combo">{{ __('pages.landing.game.combo') }} <b data-cab-combo>0</b></span></div>
                            <div class="s-cab-screen" data-cab-lane><button type="button" class="s-cab-sound" data-cab-sound hidden><x-site.icon name="muted" size="18" />{{ __('pages.landing.game.sound') }}</button></div>
                            <div class="s-cab-foot">
                                <button type="button" class="s-cab-play" data-cab-play aria-label="{{ __('pages.landing.game.play') }}" data-label-play="{{ __('pages.landing.game.play') }}" data-label-pause="{{ __('pages.landing.game.pause') }}"><span class="s-i-play"><x-site.icon name="play" size="18" /></span><span class="s-i-stop"><x-site.icon name="pause" size="18" /></span></button>
                                <div class="s-cab-words">
                                    <p translate="no">@foreach ($hero['words'] as $word)<span data-beat="{{ $word['beat'] }}">{{ $word['text'] }}</span>{{ $word['joins'] ? '' : ' ' }}@endforeach</p>
                                    <i class="s-cab-progress"><b data-cab-progress></b></i>
                                </div>
                                <span class="s-cab-time" data-cab-time>0:00</span>
                            </div>
                        </div>
                        <p class="s-cab-cap">{{ __('pages.landing.game.caption') }}</p>
                        <p class="s-cab-boot" aria-hidden="true">{{ __('pages.landing.game.final_kick') }}</p>
                    </div>
                </div>
            @endif
            <ul class="s-factrow">
                @foreach (\App\Support\Pages\Landing::list('facts', [':free_songs' => (string) $plans['freeSongs']]) as $fact)
                    <li class="s-v{{ $loop->iteration }}"><i class="gem" aria-hidden="true"></i>{{ $fact }}</li>
                @endforeach
            </ul>
        </section>

        @if (\App\Support\Site\Showreel::available())
            @include('pages.landing._showreel')
        @endif

        @if ($mudeo)
            <section id="mudeo" class="s-wrap s-sec" style="padding-top: 40px" aria-labelledby="mudeo-h">
                <div class="s-letter">
                    <div>
                        <p class="s-k">{{ \App\Support\Site\Icons::mark(24) }} {{ __('pages.landing.mudeo.eyebrow') }}</p>
                        <h2 id="mudeo-h">{{ __('pages.landing.mudeo.title') }}</h2>
                    </div>
                    <div>
                        @foreach ($mudeoLetter as $paragraph)
                            <p>{{ $paragraph }}</p>
                        @endforeach
                        <p class="s-sig">{{ __('pages.landing.mudeo.signature') }}</p>
                    </div>
                </div>
            </section>
        @endif

        @include('pages.landing._steps')
        @include('pages.landing._game')
        @include('pages.landing._guide')
        @include('pages.landing._own')
        @include('site._teams')
        @include('site._who')
        @include('pages.landing._plans')
        @include('pages.landing._way')

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
                    <p class="s-kick s-skin-g">{{ __('pages.landing.game.faq_kick') }}</p>
                    <h2 id="faq-h" class="s-h2">{{ __('pages.landing.faq_title') }}</h2>
                    <p class="s-sub">{{ __('pages.landing.faq_lead') }}</p>
                    <p class="s-more"><a href="/help" class="btn-ghost">{{ __('pages.landing.faq_more') }} <x-site.icon name="arrow" size="16" /></a></p>
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

        <section class="s-ink" aria-labelledby="final-h">
            <div class="s-wrap s-final">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                <p class="s-kick s-skin-g s-player">{{ __('pages.landing.game.final_kick') }}</p>
                <h2 id="final-h">{{ __('pages.landing.final_title') }}</h2>
                <p>{{ __('pages.landing.final_body') }}</p>
                <p class="s-final-btns">
                    <a href="{{ $links['start'] }}" class="btn-primary btn-lg" data-funnel-start>{{ __('pages.landing.start') }} <x-site.icon name="arrow" size="18" /></a>
                </p>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    <script type="module" src="/js/public/mixer.js"></script>
    <script type="module" src="/js/public/cabinet.js"></script>
    {{-- The funnel (the backend's $funnel): whether counts are on, and the experiment enrolling, if any. --}}
    <script type="application/json" data-funnel>@json($funnel ?? ['counts' => false, 'experiment' => null])</script>
    <script type="module" src="/js/public/funnel.js"></script>
@endsection
