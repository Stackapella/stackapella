{{-- Perfect your pitch (/courses/perfect-your-pitch), its own page: a target. Navy and gold, a dartboard
     of rings with notes landed as darts, one in the bull, over the drone's steady wave. The kind of
     pitch you can train (relative pitch, honestly: every note a step from your anchor), tuning by the
     beats (a little apart against right there), the units, something to try now (a clean landing),
     what the app hears, three checked facts, who it's for, questions and joining. Both skins; the art is
     wordless. Words: lang/en/site.php courses.pages.perfect-your-pitch. --}}
@php($drone = '')
@php($beats = '')
@php($still = '')
@foreach (range(0, 520, 4) as $t)
    @php($drone .= (20 + $t).','.round(296 - 6 * sin(M_PI * $t / 20), 1).' ')
@endforeach
@foreach (range(0, 188) as $t)
    @php($beat = 26 * abs(cos(M_PI * $t / 94)))
    @php($beats .= (6 + $t).','.round(35 - $beat * sin(M_PI * $t / 4), 1).' ')
    @php($still .= (6 + $t).','.round(35 - 20 * sin(M_PI * $t / 4), 1).' ')
@endforeach
@php($ladder = [0, 2, 4, 5, 7, 9, 11, 12])
<main class="cp cp-pitch">
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
            <div class="cp-pt-art" aria-hidden="true">
                <svg viewBox="20 -10 520 320" class="cp-pt-svg">
                    <g class="cp-pt-board">
                        <circle cx="280" cy="135" r="134" class="cp-pt-ring-a" />
                        <circle cx="280" cy="135" r="108" class="cp-pt-ring-b" />
                        <circle cx="280" cy="135" r="82" class="cp-pt-ring-a" />
                        <circle cx="280" cy="135" r="56" class="cp-pt-ring-b" />
                        <circle cx="280" cy="135" r="26" class="cp-pt-bull" />
                    </g>
                    @foreach ([[284, 131], [318, 100], [240, 168], [344, 182]] as [$x, $y])
                        <g class="cp-pt-dart">
                            <path d="M{{ $x }} {{ $y }} l26 -26 m-8 -6 l8 6 l6 8" class="cp-pt-tail" />
                            <circle cx="{{ $x }}" cy="{{ $y }}" r="7" class="cp-pt-tip" />
                        </g>
                    @endforeach
                    <polyline points="{{ $drone }}" class="cp-pt-drone" />
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

    <section class="s-wrap s-sec" aria-labelledby="relative-h">
        <div class="cp-pair">
            <div>
                <h2 id="relative-h" class="s-title">{{ $page['titles']['relative'] }}</h2>
                <p class="s-lede">{{ $page['relative']['lead'] }}</p>
                <p class="cp-fig-note">{{ $page['relative']['body'] }}</p>
            </div>
            <figure class="cp-fig cp-pt-ladder" aria-hidden="true">
                <svg viewBox="0 0 220 140">
                    @foreach ($ladder as $step)
                        <path d="M10 {{ 124 - $step * 9 }} H210" class="cp-pt-rung" />
                        <circle cx="{{ 24 + $loop->index * 25 }}" cy="{{ 124 - $step * 9 }}" r="{{ $loop->first ? 9 : 6 }}" class="{{ $loop->first ? 'cp-pt-anchor' : 'cp-pt-step' }}" />
                    @endforeach
                </svg>
            </figure>
        </div>
    </section>

    <section class="s-ink" aria-labelledby="ring-h">
        <div class="s-wrap s-sec">
            <h2 id="ring-h" class="s-title">{{ $page['titles']['ring'] }}</h2>
            <p class="s-lede">{{ $page['ring']['lead'] }}</p>
            <div class="cp-pt-beats">
                <figure class="cp-pt-beat">
                    <svg viewBox="0 0 200 70" aria-hidden="true"><polyline points="{{ $beats }}" /></svg>
                    <figcaption>{{ $page['ring']['apart'] }}</figcaption>
                </figure>
                <figure class="cp-pt-beat cp-pt-there">
                    <svg viewBox="0 0 200 70" aria-hidden="true"><polyline points="{{ $still }}" /></svg>
                    <figcaption>{{ $page['ring']['there'] }}</figcaption>
                </figure>
            </div>
            <p class="cp-fig-note">{{ $page['ring']['body'] }}</p>
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
