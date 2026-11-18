{{-- A cappella bass (/courses/a-cappella-bass), its own page, after Hillel's design (2026-11-18,
     course-designs/a-cappella-bass): the bass is the floor everything stands on, set as a record's
     sleeve. The hero's headline stands on a dark floor band, a bass C2 (harmonic 1), with its harmonics
     2 to 6 as lines above it, spaced as the ear hears them (closer going up; G and E, the rest of C major,
     in amber). The band carries the lead and then the featured demo, Same notes, new root
     (js/public/a-cappella-bass.js): C4 and E4 held, alone, then over a bass C3, then A2, each chord lit
     as it sounds and each one a button. Then the story as a stack of 78s (each year's line the real label
     and catalogue number where the history names a record), the outcomes, the units stepping down like a
     walking bass with unit 4 resting on the floor band, the science with The note that isn't there (G2
     from six harmonics, the 98 Hz taken away), liner notes and what the app listens for, before you
     begin, questions, and the close: "Hold the group up." over three lines (tenor, lead, baritone) with
     the floor band, you, under it. Every section is labelled like a track ("Side A · Track 1"). Type: a
     heavy headline, a serif for the history and the science, a monospace for frequencies, amber on
     near-black; calm light and dark, and game mode's arcade. Every claim is from the research pack
     (docs/courses/a-cappella-bass-love-letter.md), cited. No mic, no drawn people. Words:
     lang/en/site.php courses.pages.a-cappella-bass. --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($tones = $love['overtones'])
@php($missing = $love['missing'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($counts = [':lessons' => (string) ($outline['lessons'] ?? ''), ':units' => (string) count($outline['units'] ?? [])])
{{-- The hero's lines: harmonic n of C2 (65.4 Hz) at a log height, 1 the floor band and 6 the top. --}}
@php($c2 = 65.406)
@php($lineAt = fn (int $n): float => round(log($n, 2) / log(6, 2) * 100, 1))
@php($chordTone = [3, 5, 6])
{{-- The demo's notes as MIDI, placed from A2 (45) at the foot to E4 (64) at the top. --}}
@php($place = fn (int $midi): float => round(($midi - 45) / 19, 3))
@php($basses = [null, 48, 45])
{{-- The note that isn't there: each harmonic's bar is its level in the demo (js: HARMONICS). --}}
@php($levels = [100, 85, 70, 55, 45, 35])
@php($story = (array) $love['story'])
<main class="cp cp-acb">
    <section class="cp-acb-hero" aria-labelledby="page-h">
        <div class="s-wrap">
            <div class="cp-acb-top">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-acb-mono">{{ $outline !== null ? strtr($love['meta'], $counts) : $page['eyebrow'] }}</p>
            </div>
            <div class="cp-acb-stage">
                <p class="sr-only">{{ $tones['alt'] }}</p>
                @foreach (range(2, 6) as $n)
                    <p @class(['cp-acb-line', 'cp-acb-line-chord' => in_array($n, $chordTone, true)]) style="--at: {{ $lineAt($n) }}%" aria-hidden="true"><span>{{ __($tones['line'], ['n' => $n, 'note' => $tones['notes'][$n - 1], 'hz' => (string) round($c2 * $n)]) }}</span></p>
                @endforeach
                <h1 id="page-h">{{ $page['hero_title'] }}</h1>
            </div>
        </div>
        <div class="cp-acb-floor">
            <div class="s-wrap">
                <p class="cp-acb-strip"><span class="cp-acb-strip-floor">{{ __($tones['floor'], ['note' => $tones['notes'][0], 'hz' => (string) round($c2)]) }}</span><span>{{ $tones['spacing'] }}@include('site.courses._cites', ['keys' => ['unsw']])</span></p>
                <div class="cp-acb-floor-grid">
                    <p class="cp-acb-lead">{{ $page['hero_lead'] }}</p>
                    <div class="cp-acb-floor-act">
                        <p class="cp-acb-btns">
                            <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                            <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                        </p>
                        @unless ($open)
                            <p class="cp-acb-quiet">{{ __('pages.site.course_soon') }}</p>
                        @endunless
                        <ul class="cp-acb-facts">
                            @foreach ((array) $love['chips'] as $chip)
                                @if ($outline !== null || ! str_contains($chip, ':lessons'))
                                    <li>{{ strtr($chip, $counts) }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="cp-acb-floor cp-acb-demosec" aria-labelledby="demo-h">
        <div class="s-wrap">
            <div class="cp-acb-demo" data-acb-demo
                data-ready="{{ $demo['ready'] }}" data-after="{{ $demo['after'] }}"
                data-say-0="{{ $demo['says'][0] }}" data-say-1="{{ $demo['says'][1] }}" data-say-2="{{ $demo['says'][2] }}">
                <div class="cp-acb-demo-words">
                    <p class="cp-acb-kick">{{ $demo['live'] }} · {{ $demo['lesson'] }}</p>
                    <h2 id="demo-h" class="cp-acb-h2">{{ $demo['title'] }}</h2>
                    <p class="cp-acb-demo-lead">{{ $demo['lead'] }}</p>
                    <button type="button" class="cp-acb-play" data-acb-play aria-pressed="false"><span class="cp-acb-play-icon" aria-hidden="true"></span><span class="cp-acb-go"><x-site.skin :game="__('pages.site.demo_press_start')" :serious="$demo['play']" /></span><span class="cp-acb-stop">{{ $demo['stop'] }}</span></button>
                    <p class="cp-acb-say" role="status" data-acb-say>{{ $demo['ready'] }}</p>
                    <p class="cp-acb-mono cp-acb-demo-note">{{ $demo['hz'] }} {{ $demo['note'] }}</p>
                </div>
                <ol class="cp-acb-chords" role="group" aria-label="{{ $demo['stage'] }}">
                    @foreach ((array) $demo['chords'] as [$name, $mood])
                        @php($bass = $basses[$loop->index])
                        <li>
                            <button type="button" @class(['cp-acb-chord', 'cp-acb-chord-bass' => $bass !== null]) data-acb-chord="{{ $loop->index }}" aria-pressed="false">
                                <span class="cp-acb-col" aria-hidden="true">
                                    <span class="cp-acb-note" style="--p: {{ $place(64) }}">{{ $demo['top'][1] }}</span>
                                    <span class="cp-acb-note" style="--p: {{ $place(60) }}">{{ $demo['top'][0] }}</span>
                                    @if ($bass === null)
                                        <span class="cp-acb-note cp-acb-note-none" style="--p: {{ $place(47) }}">{{ $demo['empty'] }}</span>
                                    @else
                                        <span class="cp-acb-note cp-acb-note-bass" style="--p: {{ $place($bass) }}">{{ $demo['bass'][$loop->index] }}</span>
                                    @endif
                                </span>
                                <span class="cp-acb-chord-name"><b>{{ $name }}</b><span>{{ $mood }}</span></span>
                            </button>
                        </li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-acb-sec cp-acb-story" aria-labelledby="story-h">
        <div class="cp-acb-head cp-acb-head-ruled">
            <div>
                <p class="cp-acb-kick">{{ $page['kicks']['story'] }}</p>
                <h2 id="story-h" class="cp-acb-h2">{{ $page['titles']['story'] }}</h2>
            </div>
            <p class="cp-acb-mono">{{ __($page['story_count'], ['n' => count($story), 'from' => $story[0][0], 'to' => $story[count($story) - 1][0]]) }}</p>
        </div>
        <ol class="cp-acb-discs">
            @foreach ($story as [$when, $title, $text, $cites])
                <li>
                    <span class="cp-acb-disc cp-acb-ring cp-acb-disc-{{ [0, 1, 2, 0, 1][$loop->index] ?? 0 }}" aria-hidden="true"></span>
                    <div class="cp-acb-when">
                        <p class="cp-acb-year">{{ $when }}</p>
                        <p class="cp-acb-mono cp-acb-label">{{ $love['story_labels'][$loop->index] ?? '' }}</p>
                    </div>
                    <div class="cp-acb-entry">
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap cp-acb-sec cp-acb-aimsec" aria-labelledby="outcomes-h">
        <p class="cp-acb-kick">{{ $page['kicks']['aims'] }}</p>
        <h2 id="outcomes-h" class="cp-acb-h2">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-acb-aims">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-acb-aim-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    @if ($outline !== null)
        <section class="cp-acb-unitsec" aria-labelledby="units-h">
            <div class="s-wrap">
                <div class="cp-acb-head">
                    <div>
                        <p class="cp-acb-kick">{{ $page['kicks']['units'] }}</p>
                        <h2 id="units-h" class="cp-acb-h2">{{ $page['titles']['units'] }}</h2>
                    </div>
                    <p class="cp-acb-units-note">{{ $page['units_note'] }}</p>
                </div>
                <ol class="cp-acb-steps">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-acb-step" style="--step: {{ $loop->remaining }}">
                            <p class="cp-acb-mono cp-acb-step-n"><x-site.skin :game="$stage" :serious="$unitName" />@isset($page['unit_tags'][$loop->index]) · {{ $page['unit_tags'][$loop->index] }}@endisset</p>
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-acb-step-say">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            <ol class="cp-acb-lessons" aria-label="{{ __('pages.site.course_chip_lessons', ['n' => count($unit['lessons'])]) }}">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li @class(['cp-acb-lesson-last' => $loop->parent->last && $loop->last])><span>{{ $lesson }}</span>@if ($loop->parent->first && $loop->first)<span class="cp-acb-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                                @endforeach
                            </ol>
                        </li>
                    @endforeach
                </ol>
            </div>
            <div class="cp-acb-floor cp-acb-landing">
                <p class="s-wrap cp-acb-strip"><span class="cp-acb-strip-floor">{{ strtr($page['units_floor'], $counts) }}</span><span>{{ $page['units_land'] }}</span></p>
            </div>
        </section>
    @endif

    <section class="s-wrap cp-acb-sec cp-acb-science" aria-labelledby="science-h">
        <p class="cp-acb-kick">{{ $page['kicks']['science'] }}</p>
        <h2 id="science-h" class="cp-acb-h2">{{ $page['titles']['science'] }}</h2>
        <div class="cp-acb-mf" data-acb-missing data-after="{{ $missing['after'] }}">
            <div class="cp-acb-mf-words">
                <h3>{{ $missing['title'] }}</h3>
                <p class="cp-acb-serif">{{ $missing['lead'] }}@include('site.courses._cites', ['keys' => ['jasa', 'libre']])</p>
                <p class="cp-acb-mf-try">{{ $missing['try'] }}</p>
            </div>
            <div class="cp-acb-mf-card">
                <div class="cp-acb-bars" aria-hidden="true">
                    @foreach ($levels as $level)
                        <span @class(['cp-acb-bar', 'cp-acb-bar-fund' => $loop->first]) style="--h: {{ $level }}%" @if ($loop->first) data-acb-fund @endif></span>
                    @endforeach
                </div>
                <p class="cp-acb-mono cp-acb-hz" aria-hidden="true">@foreach (range(1, 6) as $n)<span>{{ $n * 98 }}</span>@endforeach</p>
                <div class="cp-acb-mf-act">
                    <button type="button" class="cp-acb-hear" data-acb-missing-play data-play="{{ $missing['play'] }}" data-stop="{{ $missing['stop'] }}" aria-pressed="false"><span class="cp-acb-play-icon" aria-hidden="true"></span><span data-label>{{ $missing['play'] }}</span></button>
                    <p class="cp-acb-mono cp-acb-mf-state" aria-hidden="true" data-acb-state data-with="{{ $missing['with'] }}" data-without="{{ $missing['without'] }}">{{ __($missing['hz_list'], ['list' => implode(' · ', array_map(fn (int $n): int => $n * 98, range(1, 6)))]) }}</p>
                </div>
                <p class="cp-acb-mf-say" role="status" data-acb-missing-say></p>
            </div>
        </div>
        <ul class="cp-acb-sci">
            @foreach ((array) $love['science'] as [$title, $text, $cites])
                <li>
                    <h3>{{ $title }}</h3>
                    <p class="cp-acb-serif">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="cp-acb-floor cp-acb-liner" aria-labelledby="margin-h">
        <div class="s-wrap cp-acb-liner-grid">
            <div>
                <p class="cp-acb-kick">{{ $page['kicks']['margin'] }}</p>
                <h2 id="margin-h" class="cp-acb-h2">{{ $page['titles']['margin'] }}</h2>
                <div class="cp-acb-inside">
                    <h3 class="cp-acb-kick">{{ $page['titles']['inside'] }}</h3>
                    <p>{{ $page['measures'] }}</p>
                </div>
            </div>
            <ul class="cp-acb-notes">
                @foreach ((array) $love['margin'] as [$text, $cites])
                    <li>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="s-wrap cp-acb-sec cp-acb-begin" aria-labelledby="begin-h">
        <p class="cp-acb-kick">{{ $page['kicks']['begin'] }}</p>
        <h2 id="begin-h" class="cp-acb-h2">{{ $page['titles']['who'] }}</h2>
        <div class="cp-acb-begin-grid">
            <div>
                <h3 class="cp-acb-col-h">{{ $page['titles']['who_short'] }}</h3>
                <ul class="cp-acb-rows">@foreach ((array) $page['who'] as $item)<li>{{ $item }}</li>@endforeach</ul>
            </div>
            <div>
                <h3 class="cp-acb-col-h">{{ $page['titles']['need'] }}</h3>
                <ul class="cp-acb-rows">@foreach ((array) $page['need'] as $item)<li>{{ $item }}</li>@endforeach</ul>
                <h3 class="cp-acb-col-h cp-acb-songs-h">{{ __('pages.site.course_songs') }}</h3>
                <ul class="cp-acb-rows cp-acb-songs">
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b><span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
            <div>
                <h3 class="cp-acb-col-h cp-acb-col-safe">{{ $page['titles']['safety'] }}</h3>
                <ul class="cp-acb-rows">@foreach ((array) $page['safety'] as $item)<li>{{ $item }}</li>@endforeach</ul>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-acb-sec cp-acb-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-acb-kick">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-acb-h2">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-acb-close" aria-labelledby="join-h">
        <div class="s-wrap">
            <ul class="cp-acb-parts" aria-hidden="true">
                @foreach ((array) $page['close_parts'] as $part)
                    <li><span>{{ $part }}</span></li>
                @endforeach
            </ul>
            <h2 id="join-h" class="cp-acb-close-h">{{ $page['titles']['join'] }}</h2>
        </div>
        <div class="cp-acb-floor">
            <div class="s-wrap">
                <p class="cp-acb-strip"><span class="cp-acb-strip-floor">{{ $page['kicks']['close'] }}</span></p>
                <div class="cp-acb-floor-grid">
                    <p class="cp-acb-lead">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
                    <div class="cp-acb-floor-act">
                        <p class="cp-acb-btns">
                            <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                            <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                        </p>
                        @if ($open)
                            <p class="cp-acb-quiet">{{ __('pages.site.course_join_note') }}</p>
                        @endif
                    </div>
                </div>
                <div class="cp-acb-related">
                    <h3 class="cp-acb-mono">{{ $page['titles']['related'] }}</h3>
                    @include('site.courses._related')
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-acb-sources" aria-labelledby="notes-h">
        <details data-acb-sources>
            <summary><h2 id="notes-h" class="cp-acb-mono">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/a-cappella-bass.js"></script>
</main>
