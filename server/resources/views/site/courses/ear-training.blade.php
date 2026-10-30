{{-- Ear training (/courses/ear-training), its own page: listening, drawn. The hero is the scale as
     a ladder of uneven rungs, with three leaps arcing up from a gold home note (a three, a four and a
     five) and sound rippling out from it. Leaps you already carry (each with its song), bright or dark
     (a chord's third lowered a half step), the units, something to try now (song starts), what the
     app hears, three checked facts, who it's for, questions and joining. Both skins; the art is
     wordless. Words: lang/en/site.php courses.pages.ear-training. --}}
@php($rungs = [0, 2, 4, 5, 7, 9, 11, 12])
@php($leaps = [[230, 4], [330, 5], [430, 7]])
@php($sizes = [4, 5, 7])
<main class="cp cp-ear">
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
            <div class="cp-ear-art" aria-hidden="true">
                <svg viewBox="20 40 500 250" class="cp-ear-svg">
                    @foreach ($rungs as $step)
                        <path d="M40 {{ 260 - $step * 16 }} H500" class="cp-ear-rung" />
                    @endforeach
                    <g class="cp-ear-ripple">
                        <path d="M70 236 a34 34 0 0 0 0 48" />
                        <path d="M58 222 a52 52 0 0 0 0 76" />
                        <path d="M46 208 a70 70 0 0 0 0 104" />
                    </g>
                    @foreach ($leaps as [$x, $step])
                        <path d="M96 254 Q {{ ($x + 96) / 2 }} {{ 150 - $step * 14 }} {{ $x }} {{ 266 - $step * 16 }}" class="cp-ear-arc cp-ear-arc-{{ $loop->iteration }}" />
                        <circle cx="{{ $x }}" cy="{{ 260 - $step * 16 }}" r="11" class="cp-ear-to cp-ear-to-{{ $loop->iteration }}" />
                    @endforeach
                    <circle cx="90" cy="260" r="14" class="cp-ear-home" />
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

    <section class="s-wrap s-sec" aria-labelledby="leaps-h">
        <h2 id="leaps-h" class="s-title">{{ $page['titles']['leaps'] }}</h2>
        <p class="s-lede">{{ $page['leaps']['lead'] }}</p>
        <div class="cp-figs">
            @foreach ((array) $page['leaps']['kinds'] as [$leap, $song, $how])
                <figure class="cp-fig cp-ear-leap cp-ear-leap-{{ $loop->iteration }}">
                    <svg viewBox="0 0 160 90" aria-hidden="true">
                        <path d="M10 80 H150 M10 {{ 80 - $sizes[$loop->index] * 9 }} H150" class="cp-ear-rung" />
                        <path d="M30 80 Q 80 {{ 40 - $sizes[$loop->index] * 6 }} 130 {{ 80 - $sizes[$loop->index] * 9 }}" class="cp-ear-arc" />
                        <circle cx="30" cy="80" r="8" class="cp-ear-home" />
                        <circle cx="130" cy="{{ 80 - $sizes[$loop->index] * 9 }}" r="8" class="cp-ear-to" />
                    </svg>
                    <figcaption><b>{{ $leap }}</b> <i translate="no">{{ $song }}</i> {{ $how }}</figcaption>
                </figure>
            @endforeach
        </div>
        <p class="cp-fig-note">{{ $page['leaps']['body'] }}</p>
    </section>

    <section class="s-ink" aria-labelledby="colour-h">
        <div class="s-wrap s-sec cp-pair">
            <div>
                <h2 id="colour-h" class="s-title">{{ $page['titles']['colour'] }}</h2>
                <p class="s-lede">{{ $page['colour']['lead'] }}</p>
            </div>
            <div class="cp-pair-figs">
                @foreach (['major' => 4, 'minor' => 3] as $kind => $third)
                    <figure class="cp-ear-chord cp-ear-{{ $kind }}">
                        <svg viewBox="0 0 120 130" aria-hidden="true">
                            @if ($kind === 'minor')
                                <circle cx="60" cy="{{ 112 - 4 * 13 }}" r="12" class="cp-ear-ghost" />
                            @endif
                            <circle cx="60" cy="112" r="12" class="cp-ear-note" />
                            <circle cx="60" cy="{{ 112 - $third * 13 }}" r="12" class="cp-ear-third" />
                            <circle cx="60" cy="{{ 112 - 7 * 13 }}" r="12" class="cp-ear-note" />
                        </svg>
                        <figcaption><b>{{ $page['colour'][$kind][0] }}</b> {{ $page['colour'][$kind][1] }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
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
