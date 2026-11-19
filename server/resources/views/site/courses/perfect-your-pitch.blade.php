{{-- Perfect your pitch (/courses/perfect-your-pitch), its own page after Hillel's design (2026-11-18,
     ~/Code/course-designs/perfect-your-pitch): an archery range, the board, its four ends and the cents measure running
     through every section. The hero is the headline (its full stop a small bullseye) beside lesson 1's Bullseye, live:
     the board shows a sample round, said so, until the visitor taps; the guide sings, the visitor echoes with the mic
     (opt-in, on that tap, nothing recorded or sent) and each echo lands as a dart in cents, or they pull a note in by ear
     without one. Under it the cents ruler, where the darts land too. Then aim (the outcomes as a scorecard, an end each in
     its ring's colour), the anchor point (the scale at its real semitone heights, a step played from the anchor), tuning
     by the beats (a slider sets the gap, the drawing is one second of it, and the beat rate is the gap), four ends (every
     lesson, linked to its page, so the shared list isn't repeated), a clean landing (a scoop and a clean start as pitch
     over time, each playable), the readout (a sample round's meters, said so), from the range (the facts, cited), your
     kit, questions, take aim and the sources. Every sound is made by js/public/perfect-your-pitch.js on voices.js, only
     when pressed, on the audio clock. The drawings' numbers: App\Support\Site\PerfectYourPitchPage. The calm skin's
     light and dark and the game skin from the page's tokens (css/input.css, "Perfect your pitch"). Words:
     lang/en/site.php courses.pages.perfect-your-pitch (`love` for the demos and the page's own). --}}
@section('lesson-pages-inline', '1')
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($beats = $love['beats'])
@php($anchor = $love['anchor'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($counts = [':lessons' => (string) ($outline['lessons'] ?? ''), ':units' => (string) count($outline['units'] ?? [])])
@php([$heroWords, $heroStop] = \App\Support\Site\PerfectYourPitchPage::stop((string) $page['hero_title']))
@php($sol = (array) $anchor['sol'])
@php($ladder = \App\Support\Site\PerfectYourPitchPage::ladder($sol))
@php($traces = \App\Support\Site\PerfectYourPitchPage::traces())
@php($units = $outline !== null ? \App\Support\Site\PerfectYourPitchPage::units($outline, (string) $page['slug']) : [])
@php($rings = ['g', 'r', 'b', 'k'])
@php($ringNames = array_values((array) $love['rings']))
<main class="cp cp-pt2" data-pt>
    <section class="s-wrap cp-pt2-hero" aria-labelledby="page-h">
        <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
        <div class="cp-pt2-hero-grid">
            <div class="cp-pt2-hero-copy">
                <p class="cp-pt2-kick">{{ $outline !== null ? strtr($love['eyebrow'], $counts) : $page['eyebrow'] }}</p>
                <h1 id="page-h" class="cp-pt2-h1">{{ $heroWords }}@if ($heroStop)<span class="cp-pt2-stop" aria-hidden="true"></span>@endif</h1>
                <p class="cp-pt2-lede">{{ $page['hero_lead'] }}</p>
                <p class="cp-pt2-ctas">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                </p>
                @unless ($open)
                    <p class="cp-pt2-quiet">{{ __('pages.site.course_soon') }}</p>
                @endunless
                <ul class="cp-pt2-chips">
                    @foreach ((array) $love['chips'] as $chip)
                        @if ($outline !== null || ! str_contains($chip, ':lessons'))
                            <li>{{ strtr($chip, $counts) }}</li>
                        @endif
                    @endforeach
                </ul>
            </div>
            <div class="cp-pt2-hero-demo">
                @include('site.courses._pt-bullseye', ['sample' => \App\Support\Site\PerfectYourPitchPage::SAMPLE])
            </div>
        </div>
        {{-- The cents ruler: 50 flat to 50 sharp, the darts marked on it as they land. --}}
        <figure class="cp-pt2-ruler" role="img" aria-label="{{ $love['ruler']['label'] }}">
            <div class="cp-pt2-scale" data-pt-ruler>
                @foreach (\App\Support\Site\PerfectYourPitchPage::ruler() as [$cents, $kind])
                    <span class="cp-pt2-tick cp-pt2-tick-{{ $kind }}"></span>
                @endforeach
            </div>
            <p class="cp-pt2-ruler-n" aria-hidden="true"><span>{{ $love['ruler']['flat'] }}</span><span>−25</span><b>{{ $love['ruler']['zero'] }}</b><span>+25</span><span>{{ $love['ruler']['sharp'] }}</span></p>
        </figure>
    </section>

    <section class="s-wrap s-sec cp-pt2-aim" aria-labelledby="outcomes-h">
        <div class="cp-pt2-headrow">
            <div>
                <p class="cp-pt2-kick">{{ $page['kicks']['aim'] }}</p>
                <h2 id="outcomes-h" class="cp-pt2-h2">{{ $page['titles']['outcomes'] }}</h2>
            </div>
            <p class="cp-pt2-mono">{{ $love['scorecard'] }}</p>
        </div>
        <ol class="cp-pt2-score">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li>
                    <p class="cp-pt2-score-top"><span>{{ str_replace(':n', (string) $loop->iteration, $page['end_n']) }}</span><span class="cp-pt2-ringdot cp-pt2-{{ $rings[$loop->index % 4] }}" aria-hidden="true"></span></p>
                    <span class="cp-pt2-num" aria-hidden="true">{{ $loop->iteration }}</span>
                    <p class="cp-pt2-score-aim">{{ $outcome }}</p>
                    @isset($units[$loop->index])
                        <p class="cp-pt2-mono">{{ $units[$loop->index]['title'] }}</p>
                    @endisset
                </li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-pt2-anchor" aria-labelledby="relative-h">
        <div class="cp-pt2-pair">
            <div>
                <p class="cp-pt2-kick">{{ $page['kicks']['anchor'] }}</p>
                <h2 id="relative-h" class="cp-pt2-h2">{{ $page['titles']['relative'] }}</h2>
                <p class="cp-pt2-lede">{{ $page['relative']['lead'] }}</p>
                <p class="cp-pt2-small">{{ $page['relative']['body'] }}</p>
                <p class="cp-pt2-tap">{{ $anchor['tap'] }} <x-site.icon name="arrow" size="16" /></p>
            </div>
            <figure class="cp-pt2-card cp-pt2-ladder" data-pt-ladder>
                <div class="cp-pt2-lad">
                    @foreach ($ladder['rows'] as $semis)
                        <span class="cp-pt2-lad-n" style="grid-row: {{ $loop->iteration }}" aria-hidden="true">{{ $semis }}</span>
                        <span @class(['cp-pt2-rung', 'cp-pt2-rung-home' => $semis === 0]) style="grid-row: {{ $loop->iteration }}" aria-hidden="true"></span>
                    @endforeach
                    @foreach ($ladder['steps'] as $step)
                        <button type="button" @class(['cp-pt2-sol', 'cp-pt2-sol-home' => $step['semis'] === 0]) style="grid-row: {{ $step['row'] + 1 }}; grid-column: {{ $step['col'] + 2 }}" data-pt-step="{{ $step['semis'] }}" aria-pressed="false" aria-label="{{ $step['semis'] === 0 ? $anchor['home'] : strtr($anchor['step'], [':name' => $step['name'], ':n' => (string) $step['semis']]) }}"><span>{{ $step['name'] }}</span></button>
                    @endforeach
                </div>
                <figcaption><span>{{ $anchor['axis'] }}</span><span>{{ $anchor['half'] }}</span></figcaption>
            </figure>
        </div>
    </section>

    <section class="cp-pt2-band" aria-labelledby="ring-h">
        <div class="s-wrap s-sec cp-pt2-pair">
            <div>
                <p class="cp-pt2-kick">{{ $page['kicks']['target'] }}</p>
                <h2 id="ring-h" class="cp-pt2-h2">{{ $page['titles']['ring'] }}</h2>
                <p class="cp-pt2-lede">{{ $page['ring']['lead'] }}</p>
                <p class="cp-pt2-three"><b aria-hidden="true">3</b> <span>{{ $page['ring']['body'] }}@include('site.courses._cites', ['keys' => ['beats']])</span></p>
            </div>
            <div class="cp-pt2-beatcard" data-pt-beats
                data-pair="{{ $beats['pair'] }}" data-hz="{{ $beats['hz'] }}" data-rate="{{ $beats['rate'] }}" data-rate-one="{{ $beats['rate_one'] }}" data-still-words="{{ $page['ring']['there'] }}"
                data-hint="{{ $beats['hint'] }}" data-hint-still="{{ $beats['hint_still'] }}" data-play-gap="{{ $beats['play'] }}" data-play-still="{{ $beats['play_still'] }}">
                <p class="cp-pt2-beat-top"><span data-pt-pair>{{ strtr($beats['pair'], [':f' => '223']) }}</span><b data-pt-rate>{{ strtr($beats['rate'], [':n' => '3']) }}</b></p>
                <svg viewBox="0 0 560 100" preserveAspectRatio="none" role="img" aria-label="{{ $beats['drawn'] }}"><path d="{{ \App\Support\Site\PerfectYourPitchPage::beatPath(3) }}" data-pt-wave /></svg>
                <label class="cp-pt2-gap"><span>{{ $beats['gap'] }}</span><input type="range" min="0" max="6" step="0.5" value="3" data-pt-gap><output data-pt-gap-out>{{ strtr($beats['hz'], [':n' => '3']) }}</output></label>
                <p class="cp-pt2-hint" data-pt-hint>{{ $beats['hint'] }}</p>
                <p class="cp-pt2-btns"><button type="button" class="cp-pt2-play" data-pt-beats-play data-play="{{ strtr($beats['play'], [':n' => '3']) }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-pt2-play-icon" aria-hidden="true"></span><span data-label>{{ strtr($beats['play'], [':n' => '3']) }}</span></button></p>
            </div>
        </div>
    </section>

    @if ($outline !== null)
        <section class="s-wrap s-sec cp-pt2-endsec" aria-labelledby="units-h">
            <p class="cp-pt2-kick">{{ strtr($love['ends']['kick'], $counts) }}</p>
            <h2 id="units-h" class="cp-pt2-h2">{{ $page['titles']['units'] }}</h2>
            <p class="cp-pt2-small">{{ $love['ends']['lead'] }}</p>
            <ol class="cp-pt2-ends">
                @foreach ($units as $unit)
                    @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                    @php($end = str_replace(':n', (string) $loop->iteration, $page['end_n']).' · '.($ringNames[$loop->index] ?? ''))
                    <li class="cp-pt2-end cp-pt2-end-{{ $rings[$loop->index % 4] }}">
                        <p class="cp-pt2-end-top"><span class="cp-pt2-end-n"><x-site.skin :game="$stage" :serious="$end" /></span><span class="cp-pt2-end-count">{{ str_replace(':n', (string) count($unit['lessons']), $love['ends']['count']) }}</span></p>
                        <h3>{{ $unit['title'] }}</h3>
                        @isset($page['units_say'][$loop->index])
                            <p class="cp-pt2-small">{{ $page['units_say'][$loop->index] }}</p>
                        @endisset
                        <ol class="cp-pt2-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li><span class="cp-pt2-ln" aria-hidden="true">{{ $lesson['n'] }}</span><span>@if ($lesson['url'] !== null)<a href="{{ $lesson['url'] }}">{{ $lesson['name'] }}</a>@else{{ $lesson['name'] }}@endif @if ($loop->parent->first && $loop->first)<a href="{{ $tryUrl }}" class="cp-pt2-free">{{ __('pages.site.course_free') }}</a>@endif</span></li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="s-wrap s-sec cp-pt2-first" aria-labelledby="try-h">
        <div class="cp-pt2-pair cp-pt2-first-top">
            <div>
                <p class="cp-pt2-kick">{{ $page['kicks']['first'] }}</p>
                <h2 id="try-h" class="cp-pt2-h2">{{ $page['titles']['try'] }}</h2>
                <p class="cp-pt2-lede">{{ $page['try']['lead'] }}</p>
            </div>
            <figure class="cp-pt2-traces">
                @foreach (['scoop', 'clean'] as $kind)
                    <div class="cp-pt2-trace cp-pt2-trace-{{ $kind }}">
                        <p class="cp-pt2-trace-top"><b>{{ $love['traces'][$kind] }}</b><span>{{ $love['traces'][$kind.'_tag'] }}</span></p>
                        <svg viewBox="0 0 260 110" aria-hidden="true"><rect x="0" y="26.5" width="260" height="15" class="cp-pt2-trace-band" /><path d="M0 34 H260" class="cp-pt2-trace-note" /><path d="{{ $traces[$kind] }}" class="cp-pt2-trace-line" /></svg>
                        <button type="button" class="cp-pt2-play cp-pt2-play-sm" data-pt-trace="{{ $kind }}" data-play="{{ $love['traces']['hear_'.$kind] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-pt2-play-icon" aria-hidden="true"></span><span data-label>{{ $love['traces']['hear_'.$kind] }}</span></button>
                    </div>
                @endforeach
                <figcaption class="cp-pt2-mono">{{ $love['traces']['axis'] }}</figcaption>
            </figure>
        </div>
        <ol class="cp-pt2-steps">
            @foreach ((array) $page['try']['steps'] as $step)
                <li @class(['cp-pt2-step-first' => $loop->first])><span class="cp-pt2-num" aria-hidden="true">{{ $loop->iteration }}</span>@if ($loop->first)<b class="cp-pt2-start">{{ $page['try_first'] }}</b>@endif<p>{{ $step }}</p></li>
            @endforeach
        </ol>
        <p class="cp-pt2-small">{{ $page['try']['after'] }}</p>
    </section>

    <section class="cp-pt2-band cp-pt2-readsec" aria-labelledby="inside-h">
        <div class="s-wrap s-sec cp-pt2-pair">
            <div>
                <p class="cp-pt2-kick">{{ $page['kicks']['readout'] }}</p>
                <h2 id="inside-h" class="cp-pt2-h2">{{ $page['titles']['inside'] }}</h2>
                <p class="cp-pt2-lede">{{ $page['measures'] }}</p>
                <p class="cp-pt2-small"><x-site.say :text="$page['scoring']" /></p>
            </div>
            <div class="cp-pt2-readout">
                <p class="cp-pt2-sampletag">{{ $love['readout']['sample'] }}</p>
                <ul>
                    @foreach (\App\Support\Site\PerfectYourPitchPage::meters() as $meter)
                        @php([$meterName, $meterNote] = $love['readout']['meters'][$loop->index])
                        <li class="cp-pt2-meter">
                            <p class="cp-pt2-meter-top"><b>{{ $meterName }}</b><span>{{ $meter['text'] ?? $love['readout']['clean'] }}</span></p>
                            <span class="cp-pt2-meter-bar" aria-hidden="true"><span style="--at: {{ $meter['at'] }}%"></span></span>
                            <p class="cp-pt2-meter-note">{{ $meterNote }}</p>
                        </li>
                    @endforeach
                </ul>
                <p class="cp-pt2-readout-sum">
                    <span class="cp-pt2-dots" aria-hidden="true">@foreach (\App\Support\Site\PerfectYourPitchPage::SAMPLE as $cents)<span class="cp-pt2-{{ \App\Support\Site\PerfectYourPitchPage::ring($cents) }}"></span>@endforeach</span>
                    <span>{{ strtr($demo['summary'], [':landed' => (string) \App\Support\Site\PerfectYourPitchPage::sampleLanded(), ':total' => (string) count(\App\Support\Site\PerfectYourPitchPage::SAMPLE)]) }}</span>
                </p>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-pt2-range" aria-labelledby="facts-h">
        <p class="cp-pt2-kick">{{ $page['kicks']['range'] }}</p>
        <h2 id="facts-h" class="cp-pt2-h2">{{ $page['titles']['facts'] }}</h2>
        <ul class="cp-pt2-facts">
            @foreach ((array) $love['facts'] as [$text, $source, $cites])
                <li>
                    <p class="cp-pt2-fact-head"><span>{{ $love['fact_tags'][$loop->index] ?? '' }}</span><svg viewBox="0 0 300 12" preserveAspectRatio="none" class="cp-pt2-fact-arrow" aria-hidden="true"><path d="M0 6 H290" class="cp-pt2-line" /><path d="M300 6 L289 0 V12 Z" class="cp-pt2-tip" /></svg></p>
                    <p class="cp-pt2-fact">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    <p class="cp-pt2-src">{{ $source }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap s-sec cp-pt2-kit" aria-labelledby="kit-h">
        <p class="cp-pt2-kick">{{ $page['kicks']['kit'] }}</p>
        <h2 id="kit-h" class="cp-pt2-h2">{{ $page['titles']['kit'] }}</h2>
        <div class="cp-pt2-kit-grid">
            <div><h3 class="cp-pt2-kit-h">{{ $page['titles']['who'] }}</h3><ul class="cp-pt2-ticks">@foreach ((array) $page['who'] as $line)<li><span class="cp-pt2-tickdot" aria-hidden="true"></span><span>{{ $line }}</span></li>@endforeach</ul></div>
            <div><h3 class="cp-pt2-kit-h">{{ $page['titles']['need'] }}</h3><ul class="cp-pt2-ticks">@foreach ((array) $page['need'] as $line)<li><span class="cp-pt2-tickdot" aria-hidden="true"></span><span>{{ $line }}</span></li>@endforeach</ul></div>
            <div class="cp-pt2-rules">
                <h3>{{ $page['titles']['safety'] }}</h3>
                <ul>@foreach ((array) $page['safety'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                <h3>{{ __('pages.site.course_songs') }}</h3>
                <ul>
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-pt2-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-pt2-kick">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-pt2-h2">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-pt2-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-pt2-close-in">
            <svg viewBox="0 0 88 88" class="cp-pt2-badge" aria-hidden="true">
                @foreach ([[43, 'w'], [36, 'k'], [28, 'b'], [19, 'r'], [9, 'g']] as [$r, $c])
                    <circle cx="44" cy="44" r="{{ $r }}" class="cp-pt2-ring cp-pt2-{{ $c }}" />
                @endforeach
            </svg>
            <h2 id="join-h" class="cp-pt2-h2 cp-pt2-close-h">{{ $page['titles']['join'] }}</h2>
            <p class="cp-pt2-lede">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-pt2-ctas">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </p>
            @if ($open)
                <p class="cp-pt2-quiet">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-pt2-kick cp-pt2-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-pt2-notes" aria-labelledby="notes-h">
        <details data-pt-open>
            <summary><h2 id="notes-h" class="cp-pt2-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/perfect-your-pitch.js"></script>
</main>
