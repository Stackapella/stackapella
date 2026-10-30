{{-- The free course (/features/course), its own page: the course map. A winding road of eight stops in
     the parts' four colours, ending in the four stacked bars of a song you sang every part of. What
     you'll be able to do, how a lesson goes (stars in the game skin, the plain measure in the
     serious), the map unit by unit (unit 8 on Plus), the whole song is you, something to try now
     (your comfortable note), three checked facts, who it's for, questions, the Plus courses that
     follow, and Start singing. Both skins; the art is wordless. Words: lang/en/site.php
     features.pages.course. --}}
@php($outline = \App\Support\Site\SitePages::freeCourseOutline())
@php($stops = [[60, 250], [130, 214], [96, 160], [176, 128], [262, 170], [346, 214], [430, 180], [400, 122]])
@php($bars = [[356, 24, 140], [356, 44, 100], [356, 64, 120], [356, 84, 90]])
<main class="cp cp-free">
    <section class="cp-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/features">{{ __('pages.site.features') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="s-kick cp-eyebrow">{{ $page['eyebrow'] }}</p>
                <h1 id="page-h">{{ $page['hero_title'] }}</h1>
                <p class="s-lede">{{ $page['hero_lead'] }}</p>
                <ul class="cp-hero-facts">
                    @foreach ((array) $page['hero_facts'] as $fact)
                        <li>{{ $fact }}</li>
                    @endforeach
                </ul>
                <p class="cp-hero-btns">
                    <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                </p>
            </div>
            <div class="cp-fr-art" aria-hidden="true">
                <svg viewBox="30 10 480 270" class="cp-fr-svg">
                    <polyline points="{{ implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $stops)) }}" class="cp-fr-road" />
                    @foreach ($stops as [$x, $y])
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="{{ $loop->first ? 14 : 11 }}" class="cp-fr-stop s-v{{ $loop->index % 4 + 1 }}{{ $loop->last ? ' cp-fr-plus' : '' }}" />
                    @endforeach
                    @foreach ($bars as [$x, $y, $w])
                        <rect x="{{ $x }}" y="{{ $y }}" width="{{ $w }}" height="14" rx="7" class="cp-fr-bar s-v{{ $loop->iteration }}" />
                    @endforeach
                </svg>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-first" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="s-kick">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-outcomes">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-outcome-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec" aria-labelledby="lesson-h">
        <h2 id="lesson-h" class="s-title">{{ $page['titles']['lesson'] }}</h2>
        <ol class="cp-fr-steps">
            @foreach ((array) $page['lesson'] as [$step, $what])
                <li class="cp-fig"><span class="cp-fr-step-n s-v{{ $loop->iteration }}" aria-hidden="true">{{ $loop->iteration }}</span><p><b>{{ $step }}</b> <x-site.say :text="$what" /></p></li>
            @endforeach
        </ol>
        <div class="s-card3d s-heroshot cp-fr-shot"><x-site.appshot :id="$page['shot']" :alt="$page['shot_alt']" /></div>
    </section>

    @if ($outline !== null)
        <section class="s-wrap s-sec" aria-labelledby="units-h">
            <div class="cp-head">
                <h2 id="units-h" class="s-title">{{ $page['titles']['units'] }}</h2>
                <p class="cp-head-note">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-units">
                @foreach ($outline['units'] as $unit)
                    @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                    @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                    <li class="cp-unit">
                        <p class="cp-unit-n"><x-site.skin :game="$stage" :serious="$unitName" />@if ($loop->iteration > 7) <span class="s-free cp-fr-plus-pill">{{ $page['plus_unit'] }}</span>@endif</p>
                        <h3>{{ $unit['title'] }}</h3>
                        @isset($page['units_say'][$loop->index])
                            <p class="cp-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                        @endisset
                        <ol class="cp-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li>{{ $lesson }}</li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="s-ink" aria-labelledby="stack-h">
        <div class="s-wrap s-sec cp-pair">
            <div>
                <h2 id="stack-h" class="s-title">{{ $page['titles']['stack'] }}</h2>
                <p class="s-lede">{{ $page['stack']['lead'] }}</p>
                <p class="cp-fig-note">{{ $page['stack']['body'] }}</p>
            </div>
            <svg viewBox="0 0 320 170" class="cp-fr-layers" aria-hidden="true">
                @foreach ([1, 2, 3, 4] as $n)
                    <g class="cp-fr-layer cp-fr-layer-{{ $n }}">
                        @foreach ([[10, 70], [86, 46], [138, 90], [234, 76]] as [$x, $w])
                            <rect x="{{ $x }}" y="{{ 14 + ($n - 1) * 38 + ($loop->index % 2) * 6 }}" width="{{ $w }}" height="16" rx="8" class="s-v{{ $n }}" />
                        @endforeach
                    </g>
                @endforeach
            </svg>
        </div>
    </section>

    <section class="s-wrap s-sec" aria-labelledby="try-h">
        <div class="cp-try">
            <h2 id="try-h" class="s-title">{{ $page['titles']['try'] }}</h2>
            @include('site.courses._try')
        </div>
    </section>

    <section class="s-wrap s-sec" aria-labelledby="facts-h">
        <h2 id="facts-h" class="s-title">{{ $page['titles']['facts'] }}</h2>
        @include('site.courses._facts')
    </section>

    @include('site.courses._three')

    <section class="s-wrap s-sec" aria-labelledby="faq-h">
        <h2 id="faq-h" class="s-title">{{ $page['titles']['faq'] }}</h2>
        @include('site.courses._faq')
    </section>

    <section class="cp-join" aria-labelledby="join-h">
        <div class="s-wrap s-sec">
            <h2 id="join-h" class="s-title">{{ $page['end']['title'] }}</h2>
            <div class="cp-join-card">
                <p class="cp-join-line">{{ $page['end']['body'] }}</p>
                <p class="cp-join-btns">
                    <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                </p>
            </div>
            <h3 class="s-kick cp-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
</main>
