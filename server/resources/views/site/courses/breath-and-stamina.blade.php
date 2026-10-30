{{-- Breath and stamina (/courses/breath-and-stamina), its own page: air. An airy sky, a calm stream of
     breath, a held note that ends on purpose (with the sag it would have had, faintly, if it had run
     out) and the breath bar the app draws in steady seconds. A long note is about its ending (running
     out against ending on purpose), easy never gasping (the safety band), the units, something to try
     now (the long note), what the app hears, three checked facts, who it's for, questions and joining.
     Both skins; the art is wordless. Words: lang/en/site.php courses.pages.breath-and-stamina. --}}
<main class="cp cp-breath">
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
            <div class="cp-br-art" aria-hidden="true">
                <svg viewBox="20 30 520 230" class="cp-br-svg">
                    <g class="cp-br-air">
                        <path d="M30 70 C 130 44, 210 96, 310 70 S 470 44, 530 66" />
                        <path d="M60 98 C 150 78, 240 118, 330 98 S 470 80, 520 96" />
                        <path d="M30 44 C 110 30, 190 58, 270 44 S 410 30, 470 42" />
                    </g>
                    <path d="M400 152 C 440 154, 466 172, 488 200 l6 -9 l6 13" class="cp-br-sag" />
                    <path d="M40 152 H400" class="cp-br-note" />
                    <path d="M414 136 V168" class="cp-br-stop" />
                    <rect x="40" y="214" width="480" height="20" rx="10" class="cp-br-track" />
                    <rect x="40" y="214" width="374" height="20" rx="10" class="cp-br-fill" />
                    <path d="M80 242 v8 M120 242 v8 M160 242 v8 M200 242 v8 M240 242 v8 M280 242 v8 M320 242 v8 M360 242 v8 M400 242 v8 M440 242 v8 M480 242 v8" class="cp-br-ticks" />
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

    <section class="s-wrap s-sec" aria-labelledby="ending-h">
        <div class="cp-pair">
            <div>
                <h2 id="ending-h" class="s-title">{{ $page['titles']['ending'] }}</h2>
                <p class="s-lede">{{ $page['ending']['lead'] }}</p>
                <p class="cp-fig-note">{{ $page['ending']['body'] }}</p>
            </div>
            <div class="cp-pair-figs">
                <figure class="cp-fig">
                    <svg viewBox="0 0 160 80" aria-hidden="true"><path d="M8 34 H96 C 116 36, 124 48, 132 62 l5 -7 l5 10" class="cp-br-sag cp-br-solid" /></svg>
                    <figcaption><b>{{ $page['ending']['out'][0] }}</b> {{ $page['ending']['out'][1] }}</figcaption>
                </figure>
                <figure class="cp-fig">
                    <svg viewBox="0 0 160 80" aria-hidden="true"><path d="M8 34 H122" class="cp-br-note" /><path d="M134 22 V46" class="cp-br-stop" /></svg>
                    <figcaption><b>{{ $page['ending']['purpose'][0] }}</b> {{ $page['ending']['purpose'][1] }}</figcaption>
                </figure>
            </div>
        </div>
    </section>

    @include('site.courses._care', ['key' => 'easy'])

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
