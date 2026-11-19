{{-- Vibrato (/courses/vibrato), after Hillel's design of 2026-11-18 (course-designs/vibrato): a pitch trace that starts
     straight and grows into vibrato down the page, the course's own journey. Calm and light: thin Figtree headlines,
     quiet tracked labels and monospace readings on a warm paper, white cards, lavender washes and one dark band.
     The hero's held note blooms into a wave (Hear the bloom plays it); the lines between sections widen from ±0 to ±50
     cents; three surfaces, each moving and playable; the outcomes, each with its small wave; the units as stones on one
     line, every lesson 01 to 17 a link to its page; lesson 1's steps beside your own wave over a drone, named as the app
     names it; what the app listens for, with 5.5 to 7 over one second of six waves; the course's facts that carry a year;
     before you begin and singing safely; the questions; the close; the sources. Every wave is drawn to its scale from its
     rate and width (App\Support\Pages\VibratoPage), moves only while on screen and sits still under reduced motion, and
     every sound is a voices.js voice whose vibrato is an LFO on its pitch, on the audio clock (js/public/vibrato.js),
     never by itself. The words are lang/en/site.php courses.pages.vibrato; no drawn people. --}}
@php($vi = \App\Support\Pages\VibratoPage::class)
@php($demo = $page['demo'])
@php($units = $vi::units($page, $outline))
@php($years = $vi::years($page))
@php($sources = $vi::sources($page, $years))
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php([$h1Before, $h1After] = $vi::around($page['hero_title'], ':wave'))
@php($wave = $vi::WAVE)
@php($bloom = $vi::BLOOM)
@php($n = fn (float $x): string => $vi::number($x))
@if ($vi::linksEveryLesson($units))
    @section('lesson-pages-inline', '1')
@endif
<main class="cp cp-vibrato" data-vi-demo
    data-straight="{{ $demo['say_straight'] }}" data-wave="{{ $demo['say_wave'] }}" data-wobble="{{ $demo['say_wobble'] }}" data-own="{{ $demo['say_own'] }}" data-bloom="{{ $demo['say_bloom'] }}"
    data-after="{{ $demo['after'] }}" data-rate-out="{{ $demo['rate_out'] }}" data-width-out="{{ $demo['width_out'] }}"
    data-playing="{{ $demo['kick'] }}" data-idle="{{ $page['deck']['try'] }}"
    data-natural="{{ implode(',', $vi::NATURAL) }}" data-wobble-rate="{{ implode(',', $vi::WOBBLE['rate']) }}" data-wobble-cents="{{ implode(',', $vi::WOBBLE['cents']) }}"
    data-pitches="{{ implode(',', $vi::PITCHES) }}" data-bloom-from="{{ $bloom['from'] }}" data-bloom-tau="{{ $bloom['tau'] }}" data-bloom-secs="{{ $bloom['secs'] }}">

    {{-- The hero: the words at left, the held note's pitch trace at right. --}}
    <section class="cp-vi-hero" aria-labelledby="page-h">
        <div class="s-wrap">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <div class="cp-vi-hero-grid">
                <div>
                    <p class="cp-vi-kick">{{ $page['eyebrow'] }}</p>
                    <h1 id="page-h" class="cp-vi-h1">{{ $h1Before }}<span class="cp-vi-wavy">{{ $page['hero_wave'] }}</span>{{ $h1After }}</h1>
                    <p class="cp-vi-lead">{{ $page['hero_lead'] }}</p>
                    <ul class="cp-vi-facts">
                        @foreach ((array) $page['hero_facts'] as $fact)
                            <li>{{ $fact }}</li>
                        @endforeach
                    </ul>
                    <p class="cp-vi-btns">
                        <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                        <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                    </p>
                </div>
                <figure class="cp-vi-trace">
                    <p class="cp-vi-trace-top"><span><span class="cp-vi-dot" aria-hidden="true"></span>{{ $page['trace']['label'] }}</span><span data-vi-note translate="no">{{ $vi::noteName($vi::PITCHES['mid']) }}</span></p>
                    <div class="cp-vi-trace-box">
                        <span class="cp-vi-grid cp-vi-grid-up" aria-hidden="true"></span>
                        <span class="cp-vi-grid cp-vi-grid-mid" aria-hidden="true"></span>
                        <span class="cp-vi-grid cp-vi-grid-down" aria-hidden="true"></span>
                        <span class="cp-vi-axis cp-vi-axis-up" aria-hidden="true">{{ str_replace(':n', (string) $wave['cents'], $page['trace']['up']) }}</span>
                        <span class="cp-vi-axis cp-vi-axis-down" aria-hidden="true">{{ str_replace(':n', (string) $wave['cents'], $page['trace']['down']) }}</span>
                        <svg viewBox="0 0 600 240" preserveAspectRatio="none" role="img" aria-label="{{ str_replace([':rate', ':width'], [(string) $wave['rate'], (string) $wave['cents']], $page['trace']['picture']) }}">
                            @include('site.courses._vibrato-wave', ['spec' => ['shape' => 'bloom', 'w' => 600, 'h' => 240, 'secs' => $bloom['secs'], 'rate' => $wave['rate'], 'cents' => $wave['cents'], 'scale' => 1.2, 'from' => $bloom['from'], 'tau' => $bloom['tau'], 'speed' => 1.2, 'n' => 360], 'class' => 'cp-vi-line cp-vi-line-hero'])
                        </svg>
                        <span class="cp-vi-bloom-at" style="--at: {{ round($bloom['from'] / $bloom['secs'] * 100, 2) }}%" aria-hidden="true"><span>{{ $page['trace']['bloom'] }}</span></span>
                        <span class="cp-vi-axis cp-vi-axis-straight" aria-hidden="true">{{ $page['trace']['straight'] }}</span>
                        <span class="cp-vi-head" data-vi-head hidden aria-hidden="true"></span>
                    </div>
                    <p class="cp-vi-secs" aria-hidden="true">@foreach (range(0, (int) $bloom['secs']) as $s)<span>{{ str_replace(':n', (string) $s, $page['trace']['secs']) }}</span>@endforeach</p>
                    <div class="cp-vi-trace-foot">
                        <dl class="cp-vi-reads">
                            <div><dt>{{ $page['trace']['rate'] }}</dt><dd>{{ $wave['rate'] }} <span>{{ $page['trace']['rate_unit'] }}</span>@include('site.courses._cites', ['keys' => ['prame']])</dd></div>
                            <div><dt>{{ $page['trace']['width'] }}</dt><dd>±{{ $wave['cents'] }} <span>{{ $page['trace']['width_unit'] }}</span></dd></div>
                        </dl>
                        <button type="button" class="cp-vi-hear cp-vi-hear-big" data-vi-play="bloom" data-play="{{ $page['trace']['hear'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-vi-hear-icon" aria-hidden="true"></span><span data-label>{{ $page['trace']['hear'] }}</span></button>
                    </div>
                </figure>
            </div>
        </div>
    </section>

    @include('site.courses._vibrato-divider', ['i' => 0])

    {{-- Three surfaces: straight, a wave and a wobble, each moving on one scale and playable; then the outcomes. --}}
    <section class="s-wrap cp-vi-sec" aria-labelledby="kinds-h">
        <div class="cp-vi-headrow">
            <div>
                <p class="cp-vi-kick">{{ $page['kicks']['surfaces'] }}</p>
                <h2 id="kinds-h" class="cp-vi-title">{{ $page['titles']['kinds'] }}</h2>
            </div>
            <div>
                <p class="cp-vi-side">{{ $page['kinds_body'] }}</p>
                <p class="cp-vi-press">{{ $page['press'] }}</p>
                <div class="cp-vi-pitch">
                    <span id="vi-pitch-l">{{ $page['pitch']['label'] }}</span>
                    <div class="seg" role="group" aria-labelledby="vi-pitch-l">
                        @foreach (array_keys($vi::PITCHES) as $key)
                            <button type="button" class="seg-option" data-vi-pitch="{{ $key }}" aria-pressed="{{ $key === 'mid' ? 'true' : 'false' }}">{{ $page['pitch'][$key] }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <ul class="cp-vi-kinds">
            @foreach ($vi::kinds($page) as $kind)
                <li class="cp-vi-kind cp-vi-kind-{{ $kind['key'] }}">
                    <div class="cp-vi-kind-box">
                        <span class="cp-vi-grid cp-vi-grid-mid" aria-hidden="true"></span>
                        <svg viewBox="0 0 400 140" preserveAspectRatio="none" aria-hidden="true">
                            @include('site.courses._vibrato-wave', ['spec' => $kind['spec']])
                        </svg>
                        <span class="cp-vi-spec">{{ $kind['spec_words'] }}</span>
                    </div>
                    <div class="cp-vi-kind-head"><h3>{{ $kind['name'] }}</h3><span class="cp-vi-tag">{{ $kind['tag'] }}</span></div>
                    <p>{{ $kind['what'] }}@if ($kind['key'] === 'wave')@include('site.courses._cites', ['keys' => ['prame', 'sundberg']])@endif</p>
                    <button type="button" class="cp-vi-hear" data-vi-play="{{ $kind['key'] }}" data-play="{{ $demo['hear'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false" aria-label="{{ $demo['hear'] }}: {{ $kind['name'] }}"><span class="cp-vi-hear-icon" aria-hidden="true"></span><span data-label>{{ $demo['hear'] }}</span></button>
                </li>
            @endforeach
        </ul>
        <p class="cp-vi-small">{{ $page['kinds_scale'] }}</p>
        <p class="cp-vi-say" role="status" data-vi-say>{{ $demo['ready'] }}</p>

        <h2 id="outcomes-h" class="cp-vi-kick cp-vi-aims-h">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-vi-aims">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li>
                    <p class="cp-vi-aim-top"><span class="cp-vi-aim-n">0{{ $loop->iteration }}</span><svg viewBox="0 0 120 56" preserveAspectRatio="none" aria-hidden="true">@include('site.courses._vibrato-wave', ['spec' => $vi::aims()[$loop->index] ?? ['shape' => 'even']])</svg></p>
                    <p>{{ $outcome }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    @include('site.courses._vibrato-divider', ['i' => 1])

    {{-- Stepping stones: the units on one line that turns from straight to wavy, then each unit's lessons, 01 to 17. --}}
    @if ($units !== [])
        <section class="s-wrap cp-vi-sec" aria-labelledby="units-h">
            <div class="cp-vi-headrow cp-vi-headrow-end">
                <div>
                    <p class="cp-vi-kick">{{ $page['kicks']['stones'] }}</p>
                    <h2 id="units-h" class="cp-vi-title">{{ $page['titles']['units'] }}</h2>
                </div>
                <p class="cp-vi-count">{{ __('pages.site.course_size', ['units' => count($units), 'lessons' => $outline['lessons'] ?? 0]) }}</p>
            </div>
            <div class="cp-vi-river" aria-hidden="true">
                <svg viewBox="0 0 1120 120" preserveAspectRatio="none">@include('site.courses._vibrato-wave', ['spec' => ['shape' => 'grow', 'w' => 1120, 'h' => 120, 'secs' => 7, 'rate' => $wave['rate'], 'cents' => $wave['cents'], 'scale' => 0.68, 'from' => 0.2, 'to' => 0.85, 'speed' => 0.5, 'n' => 560]])</svg>
                <ol style="--units: {{ count($units) }}">@foreach ($units as $unit)<li>{{ $loop->iteration }}</li>@endforeach</ol>
            </div>
            <ol class="cp-vi-units">
                @foreach ($units as $unit)
                    <li class="cp-vi-unit">
                        <p class="cp-vi-unit-top"><span><x-site.skin :game="__('pages.site.course_stage', ['n' => $loop->iteration])" :serious="__('pages.site.course_unit', ['n' => $loop->iteration])" /></span><span>{{ $unit['guide'] }}</span></p>
                        <h3>{{ $unit['title'] }}</h3>
                        <p class="cp-vi-unit-say">{{ $unit['say'] }}</p>
                        <ol class="cp-vi-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li>
                                    @if ($lesson['url'] !== null)
                                        <a href="{{ $lesson['url'] }}"><span class="cp-vi-lesson-n">{{ $lesson['n'] }}</span><span class="cp-vi-lesson-name"><b>{{ $lesson['name'] }}</b>@if ($lesson['line'] !== '')<span>{{ $lesson['line'] }}</span>@endif @if ($loop->parent->first && $loop->first)<span class="cp-vi-free">{{ __('pages.site.course_free') }}</span>@endif</span></a>
                                    @else
                                        <span class="cp-vi-lesson-n">{{ $lesson['n'] }}</span><span class="cp-vi-lesson-name"><b>{{ $lesson['name'] }}</b> @if ($loop->parent->first && $loop->first)<span class="cp-vi-free">{{ __('pages.site.course_free') }}</span>@endif</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @include('site.courses._vibrato-divider', ['i' => 2])

    {{-- Wade in: lesson 1's steps, each setting its shape on your own wave, played over a drone and changed as it plays. --}}
    <section class="s-wrap cp-vi-sec cp-vi-wade" aria-labelledby="try-h">
        <div>
            <p class="cp-vi-kick">{{ $demo['wade_kick'] }}</p>
            <h2 id="try-h" class="cp-vi-title">{{ $page['titles']['try'] }}</h2>
            <p class="cp-vi-side">{{ $page['try']['lead'] }}</p>
            <p class="cp-vi-small">{{ $page['try']['hint'] }}</p>
            <ol class="cp-vi-steps">
                @foreach ((array) $page['try']['steps'] as $step)
                    @php($shape = $vi::stepShapes()[$loop->index] ?? ['rate' => 6, 'cents' => 0])
                    <li><button type="button" class="cp-vi-step" data-vi-step data-rate="{{ $shape['rate'] }}" data-cents="{{ $shape['cents'] }}" aria-pressed="false"><span class="cp-vi-step-n" aria-hidden="true">{{ $loop->iteration }}</span><span>{{ $step }}</span></button></li>
                @endforeach
            </ol>
        </div>
        <div>
            <div class="cp-vi-deck">
                <p class="cp-vi-deck-bar"><span><span class="cp-vi-dot" aria-hidden="true"></span><span data-vi-status>{{ $page['deck']['try'] }}</span></span><span>{{ $demo['lesson'] }}</span></p>
                <h3 class="cp-vi-deck-h">{{ $demo['own'] }}</h3>
                <div class="cp-vi-deck-box">
                    <span class="cp-vi-grid cp-vi-grid-top" aria-hidden="true"></span>
                    <span class="cp-vi-grid cp-vi-grid-mid" aria-hidden="true"></span>
                    <span class="cp-vi-grid cp-vi-grid-bottom" aria-hidden="true"></span>
                    <span class="cp-vi-axis cp-vi-axis-top" aria-hidden="true">{{ $page['deck']['up'] }}</span>
                    <span class="cp-vi-axis cp-vi-axis-bottom" aria-hidden="true">{{ $page['deck']['down'] }}</span>
                    <svg viewBox="0 0 600 220" preserveAspectRatio="none" aria-hidden="true">
                        @include('site.courses._vibrato-wave', ['spec' => ['shape' => 'even', 'w' => 600, 'h' => 220, 'x0' => 20, 'x1' => 580, 'secs' => 2, 'rate' => $wave['rate'], 'cents' => $wave['cents'], 'scale' => 0.6, 'speed' => 1.2, 'n' => 300], 'own' => true])
                    </svg>
                </div>
                <p class="cp-vi-secs cp-vi-deck-secs" aria-hidden="true">@foreach ((array) $page['deck']['secs'] as $s)<span>{{ $s }}</span>@endforeach</p>
                <div class="cp-vi-sliders">
                    <label class="cp-vi-slider"><span>{{ $demo['rate'] }} <output data-vi-rate-out>{{ str_replace(':n', (string) $wave['rate'], $demo['rate_out']) }}</output></span><input type="range" min="2" max="8" step="0.5" value="{{ $wave['rate'] }}" data-vi-rate></label>
                    <label class="cp-vi-slider"><span>{{ $demo['width'] }} <output data-vi-width-out>{{ str_replace(':n', (string) $wave['cents'], $demo['width_out']) }}</output></span><input type="range" min="0" max="150" step="5" value="{{ $wave['cents'] }}" data-vi-width></label>
                </div>
                <div class="cp-vi-deck-foot">
                    <button type="button" class="cp-vi-hear cp-vi-hear-big" data-vi-play="own" data-play="{{ $demo['own_play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-vi-hear-icon" aria-hidden="true"></span><span data-label>{{ $demo['own_play'] }}</span></button>
                    <p class="cp-vi-verdict" data-vi-verdict>
                        @foreach ($vi::verdicts($page) as $state => [$words, $glyph])
                            <span data-state="{{ $state }}" @if ($state !== 'natural') hidden @endif><svg viewBox="0 0 24 12" aria-hidden="true"><path d="{{ $glyph }}" /></svg>{{ $words }}</span>
                        @endforeach
                    </p>
                </div>
                <p class="cp-vi-deck-note">{{ $page['deck']['verdict_note'] }}@include('site.courses._cites', ['keys' => ['prame', 'sundberg']])</p>
            </div>
            <p class="cp-vi-small">{{ $page['try']['after'] }}</p>
        </div>
    </section>

    {{-- Under the surface: what the app listens for and calls each wave, 5.5 to 7 over one second of six waves, and the
         three facts about the wave. --}}
    <section class="cp-vi-under" aria-labelledby="inside-h">
        <div class="s-wrap cp-vi-under-grid">
            <div>
                <p class="cp-vi-kick">{{ $page['kicks']['under'] }}</p>
                <h2 id="inside-h" class="cp-vi-title">{{ $page['titles']['inside'] }}</h2>
                <p class="cp-vi-side">{{ $page['measures'] }}</p>
                <p class="cp-vi-side">{{ $vi::measure($page) }}</p>
                <p class="cp-vi-small"><x-site.say :text="$page['scoring']" /></p>
                <div class="cp-vi-big">
                    <p class="cp-vi-big-n"><span>{{ $n($vi::NATURAL[0]) }}</span> <span class="cp-vi-big-to">{{ $page['big']['to'] }}</span> <span>{{ $n($vi::NATURAL[1]) }}</span> <span class="cp-vi-big-line">{{ $page['big']['line'] }}@include('site.courses._cites', ['keys' => ['prame', 'sundberg']])</span></p>
                    <svg viewBox="0 0 520 56" preserveAspectRatio="none" aria-hidden="true">@include('site.courses._vibrato-wave', ['spec' => ['shape' => 'even', 'w' => 520, 'h' => 56, 'secs' => 1, 'rate' => $wave['rate'], 'cents' => $wave['cents'], 'scale' => 0.4, 'speed' => 0.6, 'n' => 260]])</svg>
                    <p class="cp-vi-strip"><span>{{ $page['big']['start'] }}</span><span>{{ str_replace(':n', (string) $wave['rate'], $page['big']['strip']) }}@include('site.courses._cites', ['keys' => ['prame']])</span><span>{{ $page['big']['end'] }}</span></p>
                </div>
            </div>
            <ul class="cp-vi-cards" aria-label="{{ $page['titles']['facts'] }}">
                @foreach ((array) $page['facts'] as [$text, $source, $keys])
                    <li><p>{{ $text }}@include('site.courses._cites', ['keys' => $keys])</p><p class="cp-vi-card-src">{{ $source }}</p></li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Upstream: the course's own lesson facts that carry a year, oldest first, each with the lesson that tells it. --}}
    @if ($years !== [])
        <section class="s-wrap cp-vi-sec" aria-labelledby="years-h">
            <div class="cp-vi-headrow">
                <div>
                    <p class="cp-vi-kick">{{ $page['kicks']['years'] }}</p>
                    <h2 id="years-h" class="cp-vi-title">{{ $page['titles']['years'] }}</h2>
                </div>
                <p class="cp-vi-side">{{ $page['years_lead'] }}</p>
            </div>
            <ol class="cp-vi-years">
                @foreach ($years as $year)
                    <li>
                        <p class="cp-vi-year">{{ $year['year'] }}</p>
                        <div>
                            <p>{{ $year['text'] }}@include('site.courses._cites', ['keys' => [$year['id']]])</p>
                            <p class="cp-vi-told"><a href="{{ $year['at'] }}">{{ str_replace(':lesson', $year['lesson'], $page['years_told']) }}</a></p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- On the bank: who it's for, what you need and the songs, then singing it safely. --}}
    <section class="s-wrap cp-vi-sec" aria-labelledby="bank-h">
        <p class="cp-vi-kick">{{ $page['kicks']['bank'] }}</p>
        <h2 id="bank-h" class="cp-vi-title">{{ $page['titles']['bank'] }}</h2>
        <div class="cp-vi-bank">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul class="cp-vi-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="cp-vi-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div>
                <h3>{{ __('pages.site.course_songs') }}</h3>
                <ul class="cp-vi-songs">
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b><span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="cp-vi-calm">
            <div>
                <h3 class="cp-vi-kick">{{ $page['titles']['safety'] }}</h3>
                <p class="cp-vi-calm-h">{{ $page['titles']['gentle'] }}</p>
                <p class="cp-vi-side">{{ $page['gentle']['lead'] }}</p>
            </div>
            <ul class="cp-vi-calm-points">
                @foreach ($vi::safety($page) as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    @include('site.courses._vibrato-divider', ['i' => 3])

    <section class="s-wrap cp-vi-sec cp-vi-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-vi-kick">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-vi-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    {{-- The close: the headline over a faint wave that blooms, the way in, and the courses that go with it. --}}
    <section class="cp-vi-close" aria-labelledby="join-h">
        <svg viewBox="0 0 1440 260" preserveAspectRatio="none" class="cp-vi-close-wave" aria-hidden="true">@include('site.courses._vibrato-wave', ['spec' => ['shape' => 'grow', 'w' => 1440, 'h' => 260, 'secs' => 3.67, 'rate' => $wave['rate'], 'cents' => $wave['cents'], 'scale' => 1.4, 'from' => 0.45, 'to' => 0.8, 'speed' => 0.5, 'n' => 600]])</svg>
        <div class="s-wrap cp-vi-close-in">
            <p class="cp-vi-kick">{{ $page['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-vi-close-h">{{ $page['titles']['join'] }}</h2>
            <p class="cp-vi-side">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-vi-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </p>
            @if ($open)
                <p class="cp-vi-small">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-vi-kick cp-vi-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-vi-sources" aria-labelledby="sources-h">
        <h2 id="sources-h" class="cp-vi-kick">{{ __('pages.site.sources') }}</h2>
        <ol>
            @foreach ($sources as [$label, $url])
                <li id="src-{{ $loop->iteration }}">@if ($url !== null)<a href="{{ $url }}" rel="noopener">{{ $label }}</a>@else{{ $label }}@endif</li>
            @endforeach
        </ol>
    </section>
    <script type="module" src="/js/public/vibrato.js"></script>
</main>
