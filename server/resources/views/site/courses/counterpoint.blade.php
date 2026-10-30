{{-- Counterpoint (/courses/counterpoint), its own page: a music manuscript. Parchment, a serif hand
     (in the serious skin) and rubric red; the hero is a two-voice score, a slow cantus and a
     counterpoint moving against it. Three ways two lines move, the units from a round to your own
     line, something to try now (a round with your own recording), what the app hears, three checked
     facts as notes in the margin, who it's for, questions and joining. Both skins; the art is
     wordless. Words: lang/en/site.php courses.pages.counterpoint. --}}
@php($cantus = [[70, 180], [180, 170], [290, 160], [400, 170], [510, 190]])
@php($upper = [[70, 130], [180, 140], [290, 150], [400, 120], [510, 110]])
@php($moves = [[[[12, 62], [52, 50], [92, 38], [132, 26]], [[12, 82], [52, 70], [92, 58], [132, 46]]], [[[12, 62], [52, 50], [92, 38], [132, 26]], [[12, 34], [52, 46], [92, 58], [132, 70]]], [[[12, 50], [52, 50], [92, 50], [132, 50]], [[12, 80], [52, 66], [92, 72], [132, 58]]]])
<main class="cp cp-counterpoint">
    <section class="cp-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="s-kick cp-eyebrow">{{ $page['eyebrow'] }}</p>
                <h1 id="page-h">{{ $page['hero_title'] }}</h1>
                <p class="s-lede cp-cp-lede">{{ $page['hero_lead'] }}</p>
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
            <div class="cp-cp-art" aria-hidden="true">
                <svg viewBox="10 24 560 196" class="cp-cp-svg">
                    <path class="cp-cp-staff" d="M20 110 H560 M20 130 H560 M20 150 H560 M20 170 H560 M20 190 H560" />
                    <path class="cp-cp-bar" d="M20 110 V190 M560 110 V190 M125 110 V190 M235 110 V190 M345 110 V190 M455 110 V190" />
                    <polyline class="cp-cp-path cp-cp-lo" points="{{ implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $cantus)) }}" />
                    <polyline class="cp-cp-path cp-cp-hi" points="{{ implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $upper)) }}" />
                    @foreach ($cantus as [$x, $y])
                        <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="13" ry="9" class="cp-cp-whole" />
                    @endforeach
                    @foreach ($upper as [$x, $y])
                        <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="11" ry="8" transform="rotate(-22 {{ $x }} {{ $y }})" class="cp-cp-note" />
                        <path d="M{{ $x + 10 }} {{ $y - 2 }} V{{ $y - 52 }}" class="cp-cp-stem" />
                    @endforeach
                    <path class="cp-cp-slur" d="M60 92 C 160 40, 300 40, 400 74" />
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

    <section class="s-wrap s-sec" aria-labelledby="move-h">
        <h2 id="move-h" class="s-title">{{ $page['titles']['move'] }}</h2>
        <p class="s-lede">{{ $page['move']['lead'] }}</p>
        <div class="cp-cp-moves">
            @foreach ((array) $page['move']['kinds'] as [$kind, $what])
                <figure class="cp-cp-move">
                    <svg viewBox="0 0 144 96" aria-hidden="true">
                        <path class="cp-cp-staff" d="M4 26 H140 M4 46 H140 M4 66 H140" />
                        @foreach ($moves[$loop->index] as $i => $line)
                            <polyline class="cp-cp-path {{ $i === 0 ? 'cp-cp-hi' : 'cp-cp-lo' }}" points="{{ implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $line)) }}" />
                            @foreach ($line as [$x, $y])
                                <circle cx="{{ $x }}" cy="{{ $y }}" r="5" class="{{ $i === 0 ? 'cp-cp-dot-hi' : 'cp-cp-dot-lo' }}" />
                            @endforeach
                        @endforeach
                    </svg>
                    <figcaption><b>{{ $kind }}</b> {{ $what }}</figcaption>
                </figure>
            @endforeach
        </div>
        <p class="cp-cp-smooth">{{ $page['move']['smooth'] }}</p>
    </section>

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
