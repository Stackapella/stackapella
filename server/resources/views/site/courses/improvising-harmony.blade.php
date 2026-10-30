{{-- Improvising a harmony (/courses/improvising-harmony), its own page: lights going out. Night blue,
     the tune as notes on the lane and a harmony above it whose lights fade from lit to unlit across
     the hero, the way the course hands the part over to your ear. The next chord tone up (a third, and
     a fourth over so, its rubbing third a ghost), from lights to no lights (four small lanes), the
     units, something to try now (a part above), what the app hears, three checked facts, who it's for,
     questions and joining. Both skins; the art is wordless. Words: lang/en/site.php
     courses.pages.improvising-harmony. --}}
@php($tune = [[40, 236, 70], [116, 206, 34], [156, 176, 70], [232, 206, 34], [272, 176, 34], [312, 206, 70], [388, 236, 34], [428, 206, 70]])
@php($rise = [60, 60, 90, 60, 90, 60, 60, 60])
@php($glow = ['on', 'on', 'on', 'half', 'half', 'dim', 'off', 'off'])
@php($lanes = [['on', 'on', 'on', 'on'], ['on', 'half', 'dim', 'off'], ['pick', 'pick', 'pick', 'pick'], ['off', 'off', 'off', 'off']])
<main class="cp cp-imp">
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
            <div class="cp-imp-art" aria-hidden="true">
                <svg viewBox="24 70 496 186" class="cp-imp-svg">
                    <path d="M30 86 H510 M30 116 H510 M30 146 H510 M30 176 H510 M30 206 H510 M30 236 H510" class="cp-imp-lane" />
                    @foreach ($tune as [$x, $y, $w])
                        <rect x="{{ $x }}" y="{{ $y - 10 }}" width="{{ $w }}" height="20" rx="10" class="cp-imp-tune" />
                        <rect x="{{ $x }}" y="{{ $y - $rise[$loop->index] - 10 }}" width="{{ $w }}" height="20" rx="10" class="cp-imp-part cp-imp-{{ $glow[$loop->index] }}" />
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

    <section class="s-wrap s-sec" aria-labelledby="rule-h">
        <div class="cp-pair">
            <div>
                <h2 id="rule-h" class="s-title">{{ $page['titles']['rule'] }}</h2>
                <p class="s-lede">{{ $page['rule']['lead'] }}</p>
                <p class="cp-fig-note">{{ $page['rule']['body'] }}</p>
            </div>
            <div class="cp-pair-figs">
                <figure class="cp-fig">
                    <svg viewBox="0 0 120 120" aria-hidden="true"><path d="M10 100 H110 M10 76 H110 M10 52 H110 M10 28 H110" class="cp-imp-lane" /><rect x="30" y="93" width="60" height="14" rx="7" class="cp-imp-tune" /><rect x="30" y="45" width="60" height="14" rx="7" class="cp-imp-part cp-imp-on" /></svg>
                    <figcaption><b>{{ $page['rule']['third'][0] }}</b> {{ $page['rule']['third'][1] }}</figcaption>
                </figure>
                <figure class="cp-fig">
                    <svg viewBox="0 0 120 120" aria-hidden="true"><path d="M10 100 H110 M10 76 H110 M10 52 H110 M10 28 H110" class="cp-imp-lane" /><rect x="30" y="93" width="60" height="14" rx="7" class="cp-imp-tune" /><rect x="30" y="45" width="60" height="14" rx="7" class="cp-imp-ghost" /><path d="M26 60 L94 44" class="cp-imp-strike" /><rect x="30" y="21" width="60" height="14" rx="7" class="cp-imp-part cp-imp-on" /></svg>
                    <figcaption><b>{{ $page['rule']['fourth'][0] }}</b> {{ $page['rule']['fourth'][1] }}</figcaption>
                </figure>
            </div>
        </div>
    </section>

    <section class="s-ink" aria-labelledby="lights-h">
        <div class="s-wrap s-sec">
            <h2 id="lights-h" class="s-title">{{ $page['titles']['lights'] }}</h2>
            <ol class="cp-imp-steps">
                @foreach ((array) $page['lights'] as [$step, $what])
                    <li>
                        <svg viewBox="0 0 160 70" aria-hidden="true">
                            @foreach ($lanes[$loop->index] as $state)
                                @if ($state === 'pick')
                                    <rect x="{{ 8 + $loop->index * 38 }}" y="8" width="30" height="10" rx="5" class="cp-imp-part cp-imp-half" />
                                    <rect x="{{ 8 + $loop->index * 38 }}" y="24" width="30" height="10" rx="5" class="cp-imp-part cp-imp-half" />
                                @else
                                    <rect x="{{ 8 + $loop->index * 38 }}" y="{{ 18 - $loop->index % 2 * 6 }}" width="30" height="10" rx="5" class="cp-imp-part cp-imp-{{ $state }}" />
                                @endif
                                <rect x="{{ 8 + $loop->index * 38 }}" y="{{ 50 - $loop->index % 2 * 6 }}" width="30" height="10" rx="5" class="cp-imp-tune" />
                            @endforeach
                        </svg>
                        <p><b>{{ $step }}</b> {{ $what }}</p>
                    </li>
                @endforeach
            </ol>
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
