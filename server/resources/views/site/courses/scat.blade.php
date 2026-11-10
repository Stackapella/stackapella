{{-- Scat singing (/courses/scat), its own page: sing like a horn. Calm and light, after Vibrato's and
     Sight-singing's design (white cards on a warm paper wash, thin headlines, quiet tracked labels), in
     ink with a brass, a blue and a red for the blues' three chords. The hero is the words beside the
     twelve-bar chart in its own card (a chord a bar, coloured by its job, with a legend). Then the
     featured demo, Straight or swung? (js/public/scat.js): one scat line, eight notes up a C7 chord
     and back, played straight, swung or like a soloist, each note a tile placed by when it starts and
     how long it lasts, lit as it sounds; only the timing changes. No mic. Then the story (voices that
     answer the band, 1914 to 1957), the outcomes, the units (from the course file), the science in
     plain words, liner notes and what the app listens for, before you begin, questions, the close and
     the sources. Every claim is from the research pack (docs/courses/scat-love-letter.md), cited.
     Game skin: the arcade's night, square frames with hard shadows, and PRESS START on the demo. No
     drawn people. Words: lang/en/site.php courses.pages.scat (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($chart = $love['chart'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($counts = [':lessons' => (string) ($outline['lessons'] ?? ''), ':units' => (string) count($outline['units'] ?? [])])
{{-- The chart's bars by their job: I is home, IV away, V the turn back. --}}
@php($jobs = ['home', 'home', 'home', 'home', 'away', 'away', 'home', 'home', 'turn', 'away', 'home', 'turn'])
{{-- The demo's line (MIDI notes: C4 E4 G4 A4 B flat 4 A4 G4 E4), eighth notes at 120 beats a minute,
     a 500 ms beat; each feel gives a pair's long and short notes [start in the beat, length] in ms.
     The same numbers place the tiles (as a share of the 2000 ms bar) and time the sound (scat.js). --}}
@php($line = [60, 64, 67, 69, 70, 69, 67, 64])
@php($feels = ['straight' => [[0, 250], [250, 250]], 'swung' => [[0, 333], [333, 167]], 'soloist' => [[33, 300], [333, 200]]])
@php($syllables = (array) $demo['syllables'])
@php($noteNames = (array) $demo['notes'])
@php($places = [])
@foreach ($line as $i => $m)
    @php($place = '')
    @foreach ($feels as $feel => $pair)
        @php($on = intdiv($i, 2) * 500 + $pair[$i % 2][0])
        @php($place .= '--x-'.$feel.': '.($on / 20).'; --w-'.$feel.': '.round($pair[$i % 2][1] / 20, 2).'; ')
    @endforeach
    @php($places[$i] = $place)
@endforeach
<main class="cp cp-sc">
    <section class="cp-hero cp-sc-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-sc-kick cp-eyebrow">{{ $outline !== null ? strtr($love['eyebrow'], $counts) : $page['eyebrow'] }}</p>
                <h1 id="page-h">{{ $page['hero_title'] }}</h1>
                <p class="s-lede">{{ $page['hero_lead'] }}</p>
                <p class="cp-hero-btns">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                </p>
                @unless ($open)
                    <p class="cp-sc-quiet">{{ __('pages.site.course_soon') }}</p>
                @endunless
                <ul class="cp-hero-facts">
                    @foreach ((array) $love['chips'] as $chip)
                        @if ($outline !== null || ! str_contains($chip, ':lessons'))
                            <li>{{ strtr($chip, $counts) }}</li>
                        @endif
                    @endforeach
                </ul>
            </div>
            <figure class="cp-hero-card cp-sc-chart">
                <p class="cp-sc-kick cp-sc-chart-h">{{ $chart['title'] }}</p>
                <ol class="cp-sc-bars" aria-label="{{ $chart['alt'] }}">
                    @foreach ((array) $chart['bars'] as $bar)
                        <li class="cp-sc-bar cp-sc-{{ $jobs[$loop->index] }}">
                            <span class="cp-sc-bar-n">{{ str_replace(':n', (string) $loop->iteration, $chart['bar_n']) }}</span>
                            <b translate="no">{{ $bar }}</b>
                            <span class="cp-sc-bar-job" aria-hidden="true">{{ $chart['numerals'][$jobs[$loop->index]] }}</span>
                        </li>
                    @endforeach
                </ol>
                <figcaption>
                    <ul class="cp-hero-legend">
                        @foreach ((array) $chart['legend'] as $job => $what)
                            <li><i class="cp-sc-key-{{ $job }}" aria-hidden="true"></i>{{ $what }}</li>
                        @endforeach
                    </ul>
                    <p class="cp-sc-chart-note">{{ $chart['note'] }}@include('site.courses._cites', ['keys' => ['handy']])</p>
                </figcaption>
            </figure>
        </div>
    </section>

    <section class="s-wrap cp-dk-demosec cp-sc-demosec" aria-labelledby="demo-h">
        <div class="cp-dk-demo cp-dk-screen cp-sc-demo" data-sc-demo data-feel="swung"
            data-line="{{ json_encode($line) }}" data-feels="{{ json_encode($feels) }}"
            data-ready="{{ $demo['ready'] }}" data-after="{{ $demo['after'] }}"
            data-playing-straight="{{ $demo['playing']['straight'] }}" data-playing-swung="{{ $demo['playing']['swung'] }}" data-playing-soloist="{{ $demo['playing']['soloist'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['lesson'] }}</span></p>
            <div class="cp-sc-stage">
                <p class="cp-sc-readout" aria-live="polite">
                    @foreach ($feels as $feel => $pair)
                        <span class="cp-sc-show-{{ $feel }}">{{ $demo['readout'][$feel] }}</span>
                    @endforeach
                </p>
                <div class="cp-sc-lane" role="img" aria-label="{{ $demo['lane'] }}">
                    <div class="cp-sc-grid" aria-hidden="true">
                        @foreach ([0, 25, 50, 75, 100] as $x)
                            <span class="cp-sc-beat" style="--x: {{ $x }}"></span>
                        @endforeach
                    </div>
                    <ol class="cp-sc-notes" aria-hidden="true">
                        @foreach ($line as $i => $m)
                            <li @class(['cp-sc-note', 'cp-sc-short' => $i % 2 === 1]) data-sc-note="{{ $i }}" style="--y: {{ ($m - 60) * 10 }}; {{ $places[$i] }}">
                                <span class="cp-sc-syl">{{ $syllables[$i] ?? '' }}</span>
                                <span class="cp-sc-name" translate="no">{{ $noteNames[$i] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
                <p class="cp-sc-counts" aria-hidden="true">
                    @foreach ($line as $i => $m)
                        <span @class(['cp-sc-count', 'cp-sc-and' => $i % 2 === 1]) style="{{ $places[$i] }}">{{ $i % 2 ? $demo['and'] : $demo['beats'][intdiv($i, 2)] }}</span>
                    @endforeach
                </p>
                <button type="button" class="cp-dk-big cp-sc-big" data-sc-play aria-pressed="false"><span class="cp-dk-big-icon" aria-hidden="true"></span><span class="cp-sc-go"><x-site.skin :game="__('pages.site.demo_press_start')" :serious="$demo['play']" /></span><span class="cp-sc-stop">{{ $demo['stop'] }}</span></button>
            </div>
            <div class="cp-dk-caption">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}</p>
                </div>
                <div>
                    <fieldset class="cp-dk-pick cp-sc-feels">
                        <legend>{{ $demo['feel'] }}</legend>
                        @foreach ((array) $demo['feels'] as $feel => $name)
                            <button type="button" class="cp-dk-chip" data-sc-feel="{{ $feel }}" aria-pressed="{{ $feel === 'swung' ? 'true' : 'false' }}"><span class="cp-dk-tick" aria-hidden="true"></span>{{ $name }}</button>
                        @endforeach
                    </fieldset>
                    <p class="cp-dk-say" role="status" data-sc-say>{{ $demo['ready'] }}</p>
                    <p class="cp-dk-note">{{ $demo['note'] }}@include('site.courses._cites', ['keys' => ['kth']])</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-sc-story" aria-labelledby="story-h">
        <p class="cp-sc-kick">{{ $page['kicks']['story'] }}</p>
        <h2 id="story-h" class="cp-sc-title">{{ $page['titles']['story'] }}</h2>
        <p class="cp-sc-body">{{ $page['story_lead'] }}</p>
        <ol class="cp-sc-years">
            @foreach ((array) $love['story'] as [$when, $title, $text, $cites])
                <li>
                    <p class="cp-sc-year">{{ $when }}</p>
                    <div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-sc-aimsec" aria-labelledby="outcomes-h">
        <p class="cp-sc-kick">{{ $page['kicks']['aims'] }}</p>
        <h2 id="outcomes-h" class="cp-sc-title">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-sc-aims">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-sc-aim-n" aria-hidden="true">0{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    @if ($outline !== null)
        <section class="cp-sc-unitsec" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-sc-kick">{{ $page['kicks']['units'] }}</p>
                <div class="cp-sc-unithead">
                    <h2 id="units-h" class="cp-sc-title">{{ $page['titles']['units'] }}</h2>
                    <p class="cp-sc-count-n">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <ol class="cp-sc-units">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-sc-unit">
                            <p class="cp-sc-unit-n"><span class="cp-sc-unit-dot" aria-hidden="true">{{ $loop->iteration }}</span><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-sc-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            {{-- Unit 1's lessons always show; the others fold on a phone (open on a wide screen). --}}
                            <details class="cp-sc-fold" @if ($loop->first) open data-sc-always @else data-sc-wide @endif>
                                <summary>{{ __('pages.site.course_chip_lessons', ['n' => count($unit['lessons'])]) }}</summary>
                                <ol class="cp-sc-lessons">
                                    @foreach ($unit['lessons'] as $lesson)
                                        <li><span>{{ $lesson }}</span>@if ($loop->parent->first && $loop->first)<span class="cp-sc-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                                    @endforeach
                                </ol>
                            </details>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="s-wrap s-sec cp-sc-science" aria-labelledby="science-h">
        <p class="cp-sc-kick">{{ $page['kicks']['science'] }}</p>
        <h2 id="science-h" class="cp-sc-title">{{ $page['titles']['science'] }}</h2>
        <ul class="cp-sc-cards">
            @foreach ((array) $love['science'] as [$title, $text, $cites])
                <li class="cp-sc-card">
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="cp-sc-band" aria-labelledby="margin-h">
        <div class="s-wrap s-sec cp-sc-band-grid">
            <div>
                <p class="cp-sc-kick">{{ $page['kicks']['margin'] }}</p>
                <h2 id="margin-h" class="cp-sc-title">{{ $page['titles']['margin'] }}</h2>
                <h3 class="cp-sc-kick cp-sc-inside-h">{{ $page['titles']['inside'] }}</h3>
                <p class="cp-sc-body">{{ $page['measures'] }}</p>
            </div>
            <ul class="cp-sc-margin">
                @foreach ((array) $love['margin'] as [$text, $cites])
                    <li><p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p></li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="s-wrap s-sec cp-sc-begin" aria-labelledby="begin-h">
        <p class="cp-sc-kick">{{ $page['kicks']['begin'] }}</p>
        <h2 id="begin-h" class="cp-sc-title">{{ $page['titles']['who'] }}</h2>
        <div class="cp-sc-begin-grid">
            <ul class="cp-sc-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="cp-sc-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div>
                <h3>{{ $page['titles']['safety'] }}</h3>
                <ul class="cp-sc-ticks">@foreach ((array) $page['safety'] as $item)<li><x-site.icon name="info" size="16" /><span>{{ $item }}</span></li>@endforeach</ul>
                <h3 class="cp-sc-songs-h">{{ __('pages.site.course_songs') }}</h3>
                <ul class="cp-sc-songs">
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b><span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-sc-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-sc-kick">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-sc-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-sc-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-sc-close-in">
            <p class="cp-sc-kick">{{ $page['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-sc-title">{{ $page['titles']['join'] }}</h2>
            <p class="cp-sc-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-join-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                @if ($open)
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @else
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @endif
            </p>
            @if ($open)
                <p class="cp-sc-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-sc-kick cp-sc-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-sc-sources" aria-labelledby="notes-h">
        <details data-sc-sources>
            <summary><h2 id="notes-h" class="cp-sc-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/scat.js"></script>
</main>
