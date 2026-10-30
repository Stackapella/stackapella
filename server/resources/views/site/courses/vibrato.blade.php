{{-- Vibrato (/courses/vibrato), its own page: a voice drawn as a line. The hero is a calm, straight
     note that blooms into an even wave inside the guide's ribbon, the way the app draws it. Straight, a
     wave and a wobble side by side, never forcing it (the safety band), the units, something to try now
     (swing your voice), what the app hears, three checked facts, who it's for, questions and joining.
     Both skins; the art is wordless. Words: lang/en/site.php courses.pages.vibrato. --}}
@php($bloom = '40,130 ')
@php($kinds = ['8,32 152,32', '', ''])
@foreach (range(0, 300, 4) as $t)
    @php($bloom .= (220 + $t).','.round(130 - min(1, $t / 130) * 22 * sin(M_PI * $t / 20), 1).' ')
@endforeach
@foreach (range(0, 144, 2) as $t)
    @php($swing = 8 + 12 * abs(sin($t / 38)))
    @php($turn = M_PI * $t / 23 + 0.6 * sin($t / 17))
    @php($kinds[1] .= (8 + $t).','.round(32 - 10 * sin(M_PI * $t / 10), 1).' ')
    @php($kinds[2] .= (8 + $t).','.round(32 - $swing * sin($turn), 1).' ')
@endforeach
<main class="cp cp-vibrato">
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
            <div class="cp-vi-art" aria-hidden="true">
                <svg viewBox="20 30 520 200" class="cp-vi-svg">
                    <path d="M30 46 H530 M30 74 H530 M30 186 H530 M30 214 H530" class="cp-vi-rows" />
                    <rect x="30" y="96" width="500" height="68" rx="34" class="cp-vi-ribbon" />
                    <path d="M30 130 H530" class="cp-vi-mid" />
                    <polyline points="{{ $bloom }}" class="cp-vi-line" />
                    <polyline points="{{ $bloom }}" class="cp-vi-pulse" />
                    <circle cx="220" cy="130" r="8" class="cp-vi-bloom" />
                    <circle cx="520" cy="130" r="11" class="cp-vi-head" />
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

    <section class="s-wrap s-sec" aria-labelledby="kinds-h">
        <h2 id="kinds-h" class="s-title">{{ $page['titles']['kinds'] }}</h2>
        <div class="cp-figs">
            @foreach ((array) $page['kinds'] as [$kind, $what])
                <figure class="cp-fig cp-vi-kind cp-vi-kind-{{ $loop->iteration }}">
                    <svg viewBox="0 0 160 64" aria-hidden="true"><rect x="2" y="14" width="156" height="36" rx="18" class="cp-vi-ribbon" /><polyline points="{{ $kinds[$loop->index] }}" class="cp-vi-line" /></svg>
                    <figcaption><b>{{ $kind }}</b> {{ $what }}</figcaption>
                </figure>
            @endforeach
        </div>
        <p class="cp-fig-note">{{ $page['kinds_body'] }}</p>
    </section>

    @include('site.courses._care', ['key' => 'gentle'])

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
