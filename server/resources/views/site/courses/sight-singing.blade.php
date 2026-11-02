{{-- Sight-singing (/courses/sight-singing), its own page: do is home. Calm and light, after Vibrato's
     design (white cards on a paper wash, thin headlines, quiet tracked labels), in ink with Guido of
     Arezzo's two coloured lines as its accents, the C line yellow and the F line red. The hero is the
     words beside the sol-fa ladder (the scale as rungs, do at the foot, the half steps closer). Then
     the featured demo, Find do (js/public/sight-singing.js): a drone on do under a short original line
     drawn on a stave, its names under the notes; tap the note that is do, hide the names, and move the
     line to a new key, where do moves on the stave and the tune sounds the same, only higher. No mic.
     Then the story (a thousand years of reading by eye), the outcomes, the units (from the course
     file), the science in plain words, notes in the margin, what the app listens for, before you
     begin, questions, the close and the sources. Every claim is from the research pack
     (docs/courses/sight-singing-love-letter.md), cited. Game skin: the arcade's night, square frames
     with hard shadows, and PRESS START on the demo. No drawn people. Words: lang/en/site.php
     courses.pages.sight-singing (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($names = (array) $love['names'])
@php($tryUrl = $open ? \App\Support\Http\Hosts::app('/learn/c/'.$page['app']) : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($counts = [':lessons' => (string) ($outline['lessons'] ?? ''), ':units' => (string) count($outline['units'] ?? [])])
{{-- The ladder: each rung's semitones above do, and where it sits (do at the foot, 22 a semitone). --}}
@php($rungs = [0, 2, 4, 5, 7, 9, 11, 12])
@php($rungY = fn (int $semis): int => 300 - $semis * 22)
{{-- Find do's line, as scale steps from do (0 is do): so fa mi do re mi. On the stave a step is a
     line or a space, counted from the bottom line (E in the treble clef): do is C below the stave in
     C (step -2) and G on the second line in G (step 2). --}}
@php($tune = [4, 3, 2, 0, 1, 2])
<main class="cp cp-ss">
    <section class="cp-hero cp-ss-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-ss-kick cp-eyebrow">{{ $outline !== null ? strtr($love['eyebrow'], $counts) : $page['eyebrow'] }}</p>
                <h1 id="page-h">{{ $page['hero_title'] }}</h1>
                <p class="s-lede">{{ $page['hero_lead'] }}</p>
                <p class="cp-hero-btns">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                </p>
                @unless ($open)
                    <p class="cp-ss-quiet">{{ __('pages.site.course_soon') }}</p>
                @endunless
                <ul class="cp-hero-facts">
                    @foreach ((array) $love['chips'] as $chip)
                        @if ($outline !== null || ! str_contains($chip, ':lessons'))
                            <li>{{ strtr($chip, $counts) }}</li>
                        @endif
                    @endforeach
                </ul>
            </div>
            <figure class="cp-hero-card cp-ss-ladder">
                <svg viewBox="0 0 320 330" role="img" aria-label="{{ $love['ladder_alt'] }}">
                    @foreach ($rungs as $semis)
                        @php($home = $loop->first || $loop->last)
                        <path d="M84 {{ $rungY($semis) }} H262" @class(['cp-ss-rung', 'cp-ss-rung-home' => $home]) />
                        <text x="68" y="{{ $rungY($semis) + 6 }}" @class(['cp-ss-rung-name', 'cp-ss-rung-name-home' => $home])>{{ $names[$loop->index % 7] }}</text>
                    @endforeach
                    <path d="M84 {{ $rungY(0) }} V{{ $rungY(12) }} M262 {{ $rungY(0) }} V{{ $rungY(12) }}" class="cp-ss-rail" />
                    @foreach ([[4, 5], [11, 12]] as [$low, $high])
                        <path d="M276 {{ $rungY($low) - 2 }} h10 V{{ $rungY($high) + 2 }} h-10" class="cp-ss-half" />
                    @endforeach
                    <circle cx="173" cy="{{ $rungY(0) }}" r="9" class="cp-ss-dot" />
                    <circle cx="173" cy="{{ $rungY(12) }}" r="9" class="cp-ss-dot" />
                </svg>
                <figcaption>
                    <ul class="cp-hero-legend">
                        <li><i class="cp-ss-key-home" aria-hidden="true"></i>{{ $love['ladder']['home'] }}</li>
                        <li><i class="cp-ss-key-half" aria-hidden="true"></i>{{ $love['ladder']['half'] }}</li>
                    </ul>
                    <p class="cp-ss-ladder-note">{{ $love['ladder_note'] }}@include('site.courses._cites', ['keys' => ['glover']])</p>
                </figcaption>
            </figure>
        </div>
    </section>

    <section class="s-wrap cp-dk-demosec cp-ss-demosec" aria-labelledby="demo-h">
        <div class="cp-dk-demo cp-dk-screen cp-ss-demo" data-ss-demo data-key="c" data-names="on"
            data-ready="{{ $demo['ready'] }}" data-playing="{{ $demo['playing'] }}" data-after="{{ $demo['after'] }}"
            data-right-c="{{ $demo['right_c'] }}" data-right-g="{{ $demo['right_g'] }}" data-wrong="{{ $demo['wrong'] }}"
            data-moved-c="{{ $demo['moved_c'] }}" data-moved-g="{{ $demo['moved_g'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['lesson'] }}</span></p>
            <div class="cp-ss-stage">
                <p class="cp-ss-keyname"><span class="cp-ss-in-c">{{ $demo['key_c'] }}</span><span class="cp-ss-in-g">{{ $demo['key_g'] }}</span></p>
                <div class="cp-ss-stave" role="group" aria-label="{{ $demo['stave'] }}">
                    <span class="cp-ss-lines" aria-hidden="true"></span>
                    {{-- The treble clef, its curl round the G line, and G major's sharp on the top line (F). --}}
                    <svg viewBox="0 -15 22 70" class="cp-ss-clef" aria-hidden="true">
                        <path d="M10.6 58 C8 60 5 58.6 5.4 55.6 C5.8 53 9 52.4 10 54.6 M10.6 58 C12.4 56 12.2 50 11.6 44 L9.2 4 C8.8 -3 10.2 -11 13.6 -12.4 C16.6 -13.4 16.8 -6 14.2 -1.6 C11 4 3 9.6 2.4 21 C1.8 32 7 40.6 13.4 40.4 C19.4 40.2 21.4 33.6 20.2 28.8 C18.8 23.6 12.6 22.4 10 26 C7.8 29.2 9.4 32.6 12.6 32.6" />
                    </svg>
                    <svg viewBox="0 -15 12 70" class="cp-ss-sig" aria-hidden="true">
                        <path d="M4.2 -7 V8.4 M7.8 -8.4 V7" class="cp-ss-sharp-stem" />
                        <path d="M2 -1.2 L10 -3.6 M2 4.4 L10 2" class="cp-ss-sharp-bar" />
                    </svg>
                    <ol class="cp-ss-line">
                        @foreach ($tune as $degree)
                            <li>
                                <button type="button" class="cp-ss-tap" data-ss-note="{{ $loop->index }}" data-degree="{{ $degree }}" style="--sc: {{ $degree - 2 }}; --sg: {{ $degree + 2 }}; --lc: {{ $degree === 0 ? 1 : 0 }}">
                                    <span class="sr-only">{{ str_replace(':n', (string) $loop->iteration, $demo['note_n']) }}</span>
                                    <span class="cp-ss-ledger" aria-hidden="true"></span>
                                    <span class="cp-ss-head" aria-hidden="true"></span>
                                    <span class="cp-ss-name">{{ $names[$degree] }}</span>
                                </button>
                            </li>
                        @endforeach
                    </ol>
                </div>
                <button type="button" class="cp-dk-big cp-ss-big" data-ss-play aria-pressed="false"><span class="cp-dk-big-icon" aria-hidden="true"></span><span class="cp-ss-go"><x-site.skin :game="__('pages.site.demo_press_start')" :serious="$demo['play']" /></span><span class="cp-ss-stop">{{ $demo['stop'] }}</span></button>
            </div>
            <div class="cp-dk-caption">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}</p>
                </div>
                <div>
                    <p class="cp-dk-btns cp-ss-tools">
                        <button type="button" class="cp-dk-chip cp-ss-newkey" data-ss-key>{{ $demo['new_key'] }}</button>
                        <button type="button" class="cp-ss-switch" role="switch" aria-checked="true" data-ss-names><span class="switch-track" aria-hidden="true"><span class="switch-knob"></span></span><span>{{ $demo['names'] }}</span></button>
                    </p>
                    <p class="cp-dk-say" role="status" data-ss-say>{{ $demo['ready'] }}</p>
                    <p class="cp-dk-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-ss-story" aria-labelledby="story-h">
        <p class="cp-ss-kick">{{ $page['kicks']['story'] }}</p>
        <h2 id="story-h" class="cp-ss-title">{{ $page['titles']['story'] }}</h2>
        <ol class="cp-ss-years">
            @foreach ((array) $love['story'] as [$when, $title, $text, $cites])
                <li>
                    <p class="cp-ss-year">{{ $when }}</p>
                    <div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-ss-aimsec" aria-labelledby="outcomes-h">
        <p class="cp-ss-kick">{{ $page['kicks']['aims'] }}</p>
        <h2 id="outcomes-h" class="cp-ss-title">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-ss-aims">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-ss-aim-n" aria-hidden="true">{{ $names[$loop->index] ?? '' }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    @if ($outline !== null)
        <section class="cp-ss-unitsec" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-ss-kick">{{ $page['kicks']['units'] }}</p>
                <div class="cp-ss-unithead">
                    <h2 id="units-h" class="cp-ss-title">{{ $page['titles']['units'] }}</h2>
                    <p class="cp-ss-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <ol class="cp-ss-units">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-ss-unit">
                            <p class="cp-ss-unit-n"><span class="cp-ss-unit-dot" aria-hidden="true">{{ $loop->iteration }}</span><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-ss-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            {{-- Unit 1's lessons always show; the others fold on a phone (open on a wide screen). --}}
                            <details class="cp-ss-fold" @if ($loop->first) open data-ss-always @else data-ss-wide @endif>
                                <summary>{{ __('pages.site.course_chip_lessons', ['n' => count($unit['lessons'])]) }}</summary>
                                <ol class="cp-ss-lessons">
                                    @foreach ($unit['lessons'] as $lesson)
                                        <li><span>{{ $lesson }}</span>@if ($loop->parent->first && $loop->first)<span class="cp-ss-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                                    @endforeach
                                </ol>
                            </details>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="s-wrap s-sec cp-ss-science" aria-labelledby="science-h">
        <div class="cp-ss-science-head">
            <p class="cp-ss-kick">{{ $page['kicks']['science'] }}</p>
            <h2 id="science-h" class="cp-ss-title">{{ $page['titles']['science'] }}</h2>
        </div>
        <ul class="cp-ss-cards">
            @foreach ((array) $love['science'] as [$title, $text, $cites])
                <li class="cp-ss-card">
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="cp-ss-band" aria-labelledby="margin-h">
        <div class="s-wrap s-sec cp-ss-band-grid">
            <div>
                <p class="cp-ss-kick">{{ $page['kicks']['margin'] }}</p>
                <h2 id="margin-h" class="cp-ss-title">{{ $page['titles']['margin'] }}</h2>
                <h3 class="cp-ss-kick cp-ss-inside-h">{{ $page['titles']['inside'] }}</h3>
                <p class="cp-ss-body">{{ $page['measures'] }}</p>
            </div>
            <ul class="cp-ss-margin">
                @foreach ((array) $love['margin'] as [$text, $cites])
                    <li><p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p></li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="s-wrap s-sec cp-ss-begin" aria-labelledby="begin-h">
        <p class="cp-ss-kick">{{ $page['kicks']['begin'] }}</p>
        <h2 id="begin-h" class="cp-ss-title">{{ $page['titles']['who'] }}</h2>
        <div class="cp-ss-begin-grid">
            <ul class="cp-ss-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="cp-ss-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div>
                <h3>{{ $page['titles']['safety'] }}</h3>
                <ul class="cp-ss-ticks">@foreach ((array) $page['safety'] as $item)<li><x-site.icon name="info" size="16" /><span>{{ $item }}</span></li>@endforeach</ul>
                <h3 class="cp-ss-songs-h">{{ __('pages.site.course_songs') }}</h3>
                <ul class="cp-ss-songs">
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b><span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-ss-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-ss-kick">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-ss-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-ss-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-ss-close-in">
            <p class="cp-ss-kick">{{ $page['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-ss-title">{{ $page['titles']['join'] }}</h2>
            <p class="cp-ss-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-join-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                @if ($open)
                    <a href="/pricing" class="btn-secondary btn-lg">{{ __('pages.site.course_plus') }}</a>
                @else
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @endif
            </p>
            @if ($open)
                <p class="cp-ss-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-ss-kick cp-ss-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-ss-sources" aria-labelledby="notes-h">
        <details data-ss-sources>
            <summary><h2 id="notes-h" class="cp-ss-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/sight-singing.js"></script>
</main>
