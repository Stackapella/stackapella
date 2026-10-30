{{-- Yodeling (/courses/yodeling), its own page: the Alps. A sky over three ranges, the yodel drawn as a
     line that flips between chest voice and head voice, its echo across the valley, and a ridge under
     the hero. Two voices and the flip (a flip against a slide), light never loud, the units as a trail
     marked like the yellow walking signs, something to try now, what the app hears, three checked
     facts, who it's for, questions and joining. Both skins; the art's only words are the two voices'
     names. Words: lang/en/site.php courses.pages.yodeling. --}}
@php($yodel = [[40, 240], [104, 240], [104, 150], [164, 150], [164, 240], [214, 240], [214, 150], [276, 150], [276, 240], [318, 240], [318, 150], [350, 150], [350, 240], [380, 240], [380, 150], [410, 150], [410, 240], [470, 240]])
<main class="cp cp-yodel">
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
            <div class="cp-yo-art" aria-hidden="true">
                <svg viewBox="0 0 560 300" class="cp-yo-svg">
                    <circle cx="452" cy="86" r="44" class="cp-yo-sun" />
                    <g class="cp-yo-echo">
                        <path d="M486 196 a40 40 0 0 1 0 64" />
                        <path d="M502 180 a62 62 0 0 1 0 96" />
                        <path d="M518 164 a84 84 0 0 1 0 128" />
                    </g>
                    <path class="cp-yo-guide" d="M40 150 H500 M40 240 H500" />
                    <text x="40" y="138" class="cp-yo-label">{{ $page['art']['head'] }}</text>
                    <text x="40" y="270" class="cp-yo-label">{{ $page['art']['chest'] }}</text>
                    <polyline class="cp-yo-line" points="{{ implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $yodel)) }}" />
                    @foreach ([104, 164, 214, 276, 318, 350, 380, 410] as $x)
                        <circle cx="{{ $x }}" cy="195" r="5" class="cp-yo-flip" />
                    @endforeach
                </svg>
            </div>
        </div>
        <svg class="cp-yo-range" viewBox="0 0 1440 220" preserveAspectRatio="none" aria-hidden="true">
            <polygon class="cp-yo-far" points="0,220 0,120 120,70 230,110 380,30 520,100 650,50 790,112 930,40 1080,104 1210,60 1340,96 1440,70 1440,220" />
            <polygon class="cp-yo-snow" points="380,30 410,58 392,56 372,66 356,52" />
            <polygon class="cp-yo-snow" points="930,40 958,64 940,62 922,72 908,58" />
            <polygon class="cp-yo-mid" points="0,220 0,150 160,104 300,150 470,92 620,148 780,108 940,156 1100,110 1260,150 1440,118 1440,220" />
            <polygon class="cp-yo-near" points="0,220 0,184 220,152 420,186 640,156 860,190 1080,160 1300,188 1440,170 1440,220" />
        </svg>
    </section>

    <section class="s-wrap s-sec cp-first" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="s-kick">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-outcomes">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-outcome-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec" aria-labelledby="voices-h">
        <div class="cp-pair">
            <div>
                <h2 id="voices-h" class="s-title">{{ $page['titles']['voices'] }}</h2>
                <p class="s-lede">{{ $page['voices']['lead'] }}</p>
                <p class="cp-fig-note">{{ $page['voices']['body'] }}</p>
            </div>
            <div class="cp-pair-figs">
                <figure class="cp-fig">
                    <svg viewBox="0 0 120 80" aria-hidden="true"><path class="cp-yo-guide" d="M6 20 H114 M6 64 H114" /><path class="cp-yo-line" d="M8 64 H56 V20 H112" /><circle cx="56" cy="42" r="4" class="cp-yo-flip" /></svg>
                    <figcaption><b>{{ $page['voices']['flip_label'] }}</b> {{ $page['voices']['flip'] }}</figcaption>
                </figure>
                <figure class="cp-fig">
                    <svg viewBox="0 0 120 80" aria-hidden="true"><path class="cp-yo-guide" d="M6 20 H114 M6 64 H114" /><path class="cp-yo-line cp-yo-slide" d="M8 64 H40 L72 20 H112" /></svg>
                    <figcaption><b>{{ $page['voices']['slide_label'] }}</b> {{ $page['voices']['slide'] }}</figcaption>
                </figure>
            </div>
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
        <svg class="cp-yo-ridge cp-yo-ridge-top" viewBox="0 0 1440 40" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40 V22 L90 8 L170 26 L260 4 L360 24 L470 10 L560 28 L660 6 L770 26 L880 12 L980 30 L1090 8 L1190 24 L1300 10 L1380 26 L1440 16 V40 Z" /></svg>
        <div class="s-wrap s-sec">
            <h2 id="join-h" class="s-title">{{ $page['titles']['join'] }}</h2>
            @include('site.courses._join')
            <h3 class="s-kick cp-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
</main>
