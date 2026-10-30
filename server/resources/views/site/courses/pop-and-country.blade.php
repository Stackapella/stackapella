{{-- Pop and country styling (/courses/pop-and-country), its own page: a record. A sunset, a vinyl
     disc turning, and a voice line sweeping across it with a scoop into a note and a fall-off at the
     end. Four small moves (the scoop, the fall-off, the slide and the little cry, each drawn as the app
     draws it), small and light (the safety band), the units, something to try now (plain to styled),
     what the app hears, three checked facts, who it's for, questions and joining. Both skins; the art
     is wordless. Words: lang/en/site.php courses.pages.pop-and-country. --}}
@php($moves = ['M10 50 C 30 50, 34 26, 52 24 H150', 'M10 26 H100 C 120 26, 128 40, 140 58', 'M10 54 H60 C 76 54, 84 22, 100 22 H150', 'M10 40 H66 L74 18 L82 40 H150'])
<main class="cp cp-pop">
    <section class="cp-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="s-kick cp-eyebrow">{{ $page['eyebrow'] }}</p>
                <h1 id="page-h">{{ $page['hero_title'] }}</h1>
                <p class="s-lede">{{ $page['hero_lead'] }}</p>
                <ul class="cp-hero-facts">
                    @foreach ((array) $page['hero_facts'] as $fact)
                        <li>{{ $fact }}</li>
                    @endforeach
                </ul>
                <p class="cp-hero-btns">
                    @if ($open)
                        <a href="{{ \App\Support\Http\Hosts::app('/learn/c/'.$page['app']) }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                    @endif
                    <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                </p>
            </div>
            <div class="cp-pop-art" aria-hidden="true">
                <svg viewBox="0 0 520 320" class="cp-pop-svg">
                    <g class="cp-pop-disc">
                        <circle cx="300" cy="160" r="140" class="cp-pop-vinyl" />
                        <circle cx="300" cy="160" r="122" class="cp-pop-groove" />
                        <circle cx="300" cy="160" r="104" class="cp-pop-groove" />
                        <circle cx="300" cy="160" r="86" class="cp-pop-groove" />
                        <circle cx="300" cy="160" r="68" class="cp-pop-groove" />
                        <circle cx="300" cy="160" r="46" class="cp-pop-label" />
                        <circle cx="300" cy="160" r="5" class="cp-pop-hole" />
                        <path d="M224 70 A 120 120 0 0 1 340 46" class="cp-pop-shine" />
                    </g>
                    <path d="M20 214 H96 C 116 214, 120 176, 142 172 H236 C 256 172, 262 140, 282 138 H360 C 384 138, 392 160, 402 188" class="cp-pop-line" />
                    <circle cx="142" cy="172" r="6" class="cp-pop-dot" />
                    <circle cx="282" cy="138" r="6" class="cp-pop-dot" />
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

    <section class="s-wrap s-sec" aria-labelledby="moves-h">
        <h2 id="moves-h" class="s-title">{{ $page['titles']['moves'] }}</h2>
        <p class="s-lede">{{ $page['moves']['lead'] }}</p>
        <div class="cp-figs cp-pop-moves">
            @foreach ((array) $page['moves']['kinds'] as [$move, $what])
                <figure class="cp-fig">
                    <svg viewBox="0 0 160 70" aria-hidden="true"><path d="{{ $moves[$loop->index] }}" class="cp-pop-line" /></svg>
                    <figcaption><b>{{ $move }}</b> {{ $what }}</figcaption>
                </figure>
            @endforeach
        </div>
    </section>

    @include('site.courses._care', ['key' => 'light'])

    @if ($outline !== null)
        <section class="s-wrap s-sec" aria-labelledby="units-h">
            <div class="cp-head">
                <h2 id="units-h" class="s-title">{{ $page['titles']['units'] }}</h2>
                <p class="cp-head-note">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            @include('site.courses._units')
        </section>
    @endif

    <section class="s-wrap s-sec" aria-labelledby="try-h">
        <div class="cp-try">
            <h2 id="try-h" class="s-title">{{ $page['titles']['try'] }}</h2>
            @include('site.courses._try')
        </div>
    </section>

    @include('site.courses._inside')

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
            <h2 id="join-h" class="s-title">{{ $page['titles']['join'] }}</h2>
            @include('site.courses._join')
            <h3 class="s-kick cp-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
</main>
