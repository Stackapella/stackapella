{{-- Counterpoint (/courses/counterpoint): "Point against point", a love letter to counterpoint
     (docs/course-pages-polish.md; the facts: docs/courses/counterpoint-love-letter.md). Counterpoint is
     punctus contra punctum, so every drawing here is made of points: round notes joined by thin
     threads, a colour a voice, kept all the way down (I ink, II rubric red, III gold, and IV slate,
     only in the counting demo). Serious skin: vellum, an italic serif, candlelit when dark. Game
     skin: "Point vs point", neon lanes on a dot grid, the points as gems, the units as stages and
     the history as a high-score table. Sections: the hero; the round (Row, Row, Row Your Boat for
     three, with a voice to follow); what you'll be able to do; two tunes, two streams (split the
     tune); one voice or two (same way, opposite ways, and melt the octave, in one card); the books
     (the lessons, entering like voices); a thousand years against the line (with count the voices);
     seek and you shall find (where a copy fits); what the app hears, before you begin, questions,
     finis, and the sources. Every sound is made here (js/public/counterpoint.js), only when pressed.
     Words: lang/en/site.php courses.pages.counterpoint (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($tryUrl = $open ? \App\Support\Http\Hosts::app('/learn/c/'.$page['app']) : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
{{-- The count of lessons, from the course itself, in the eyebrow and the first answer. --}}
@php($lessons = $outline['lessons'] ?? null)
@if ($lessons !== null)
    @php($page['faq'][0][1] = str_replace(':n', (string) $lessons, $love['faq_long']))
@endif
{{-- Split the tune: a high tune and a low one taking turns (MIDI notes), drawn at these heights. --}}
@php($high = [79, 81, 79, 76])
@php($low = [60, 62, 64, 62])
@php($runY = [28, 110, 20, 102, 28, 94, 40, 102])
{{-- One voice or two: the same top line over a lower line in parallel fifths, then in contrary motion. --}}
@php($fuse = ['same' => [[72, 71, 69, 67], [65, 64, 62, 60]], 'opposite' => [[72, 71, 69, 67], [57, 59, 60, 64]]])
{{-- Seek and you shall find: the page's own short line (MIDI notes, a beat each). --}}
@php($seek = [60, 65, 64, 69, 67, 69, 64, 60])
{{-- Count the voices: the round's four entries, two bars (12 eighths) apart, across 84 eighths. --}}
@php($lane = 580 / 84)
@php($beats = (array) $love['history']['beats'])
<main class="cp cp-counterpoint cp-cp2" data-cp>
    <section class="cp-cp2-hero" aria-labelledby="page-h">
        <div class="s-wrap">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <p class="cp-cp2-label">{{ $lessons !== null ? str_replace(':n', (string) $lessons, $love['eyebrow']) : $page['eyebrow'] }}</p>
            <p class="cp-cp2-kicker"><x-site.skin :game="$love['kicker_game']" :serious="$love['kicker']" />@include('site.courses._cites', ['keys' => ['catholic', 'ucsb']])</p>
            <h1 id="page-h" class="cp-cp2-h1">{{ $page['hero_title'] }}</h1>
            <p class="cp-cp2-lede">{{ $page['hero_lead'] }}</p>
            <p class="cp-cp2-ctas"><a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a></p>
            @unless ($open)
                <p class="cp-cp2-quiet">{{ __('pages.site.course_soon') }}</p>
            @endunless
        </div>
    </section>

    <section class="cp-cp2-stage" aria-labelledby="stage-h" data-cp-stage>
        <div class="s-wrap">
            <div class="cp-cp2-stage-head">
                <h2 id="stage-h" class="cp-cp2-stage-title">{{ $love['card'] }}</h2>
                <button type="button" class="cp-cp2-play" data-cp-round data-play="{{ $love['round'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $love['round'] }}</span></button>
            </div>
            <figure class="cp-cp2-scroll" data-cp-canon data-cp-scroll>
                @include('site.courses._cp-canon')
                <figcaption class="sr-only">{{ $love['art_note'] }}</figcaption>
            </figure>
            <div class="cp-cp2-follow">
                <p class="cp-cp2-small">{{ $love['follow_lead'] }}</p>
                <p class="cp-cp2-btns" role="group" aria-label="{{ $love['follow_label'] }}">
                    @foreach ([1, 2, 3] as $n)
                        <button type="button" class="cp-cp2-pick cp-cp2-v{{ $n }}" data-cp-follow="{{ $n }}" aria-pressed="false"><span class="cp-cp2-swatch" aria-hidden="true"></span>{{ str_replace(':n', $love['voices'][$n - 1], $love['follow']) }}</button>
                    @endforeach
                </p>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-cp2-aims" aria-labelledby="aims-h">
        <h2 id="aims-h" class="cp-cp2-label">{{ $page['titles']['outcomes'] }}</h2>
        <ul class="cp-cp2-outcomes">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li class="cp-cp2-v{{ $loop->index % 3 + 1 }}">{{ $outcome }}</li>
            @endforeach
        </ul>
    </section>

    <section class="cp-cp2-alt" aria-labelledby="streams-h">
        <div class="s-wrap s-sec cp-cp2-split">
            <div>
                <p class="cp-cp2-label">{{ $love['streams']['kick'] }}</p>
                <h2 id="streams-h" class="cp-cp2-h2">{{ $love['streams']['title'] }}</h2>
                <p class="cp-cp2-lede">{{ $love['streams']['lead'] }}@include('site.courses._cites', ['keys' => ['mcgill', 'jep']])</p>
                <p class="cp-cp2-body">{{ $love['streams']['body'] }}@include('site.courses._cites', ['keys' => ['jep', 'asa']])</p>
            </div>
            <div class="cp-cp2-demo" data-cp-run data-high="{{ json_encode($high) }}" data-low="{{ json_encode($low) }}">
                <h3>{{ $love['streams']['demo'] }}</h3>
                <p class="cp-cp2-small">{{ $love['streams']['demo_lead'] }}</p>
                <svg viewBox="0 0 590 130" class="cp-cp2-run" aria-hidden="true">
                    <path d="M{{ implode(' L', array_map(fn (int $i): string => (24 + $i * 36).' '.$runY[$i % 8], range(0, 15))) }}" class="cp-cp2-zig" />
                    <path d="M{{ implode(' L', array_map(fn (int $i): string => (24 + $i * 72).' '.$runY[$i * 2 % 8], range(0, 7))) }}" class="cp-cp2-strand cp-cp2-v1" />
                    <path d="M{{ implode(' L', array_map(fn (int $i): string => (60 + $i * 72).' '.$runY[$i * 2 % 8 + 1], range(0, 7))) }}" class="cp-cp2-strand cp-cp2-v2" />
                    @foreach (range(0, 15) as $i)
                        <circle cx="{{ 24 + $i * 36 }}" cy="{{ $runY[$i % 8] }}" r="6" @class(['cp-cp2-dot', 'cp-cp2-v1' => $i % 2 === 0, 'cp-cp2-low' => $i % 2 === 1]) />
                    @endforeach
                </svg>
                <label class="cp-cp2-slider"><span>{{ $love['streams']['speed'] }}</span><input type="range" min="2" max="12" step="1" value="4" data-cp-rate><span class="cp-cp2-ends" aria-hidden="true"><span>{{ $love['streams']['slow'] }}</span><span>{{ $love['streams']['fast'] }}</span></span></label>
                <p class="cp-cp2-btns">
                    <button type="button" class="cp-cp2-play" data-cp-run-play data-play="{{ $love['streams']['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $love['streams']['play'] }}</span></button>
                    <span class="cp-cp2-readout" aria-live="polite" data-cp-streams data-one="{{ $love['streams']['one'] }}" data-two="{{ $love['streams']['two'] }}" data-one-game="{{ $love['streams']['one_game'] }}" data-two-game="{{ $love['streams']['two_game'] }}"><span class="s-skin-g"></span><span class="s-skin-s"></span></span>
                </p>
            </div>
        </div>
    </section>

    <section aria-labelledby="fusion-h">
        <div class="s-wrap s-sec">
            <p class="cp-cp2-label">{{ $love['fusion']['kick'] }}</p>
            <h2 id="fusion-h" class="cp-cp2-h2">{{ $love['fusion']['title'] }}</h2>
            <div class="cp-cp2-cols">
                <p class="cp-cp2-lede">{{ $love['fusion']['lead'] }}@include('site.courses._cites', ['keys' => ['hr']])</p>
                <p class="cp-cp2-body">{{ $love['fusion']['motion'] }}@include('site.courses._cites', ['keys' => ['sheffield', 'asa']])</p>
            </div>
            <div class="cp-cp2-demo cp-cp2-fusecard" data-cp-fuse data-kind="same" data-lines="{{ json_encode($fuse) }}">
                <p class="cp-cp2-seg" role="group" aria-label="{{ $love['fusion']['tabs'] }}">
                    @foreach (['same', 'opposite', 'melt'] as $tab)
                        <button type="button" data-cp-tab="{{ $tab }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $love['fusion'][$tab] }}</button>
                    @endforeach
                </p>
                <div class="cp-cp2-panel" data-cp-panel="motion">
                    <p class="cp-cp2-small"><span data-show="same">{{ $love['fusion']['same_how'] }}</span><span data-show="opposite">{{ $love['fusion']['opposite_how'] }}</span></p>
                    <svg viewBox="0 0 270 104" class="cp-cp2-fuse" aria-hidden="true">
                        @foreach ($fuse as $kind => $lines)
                            <g data-show="{{ $kind }}">
                                @foreach ($lines as $v => $notes)
                                    <g class="cp-cp2-v{{ $v + 1 }}">
                                        <polyline points="{{ implode(' ', array_map(fn (int $m, int $j): string => (30 + $j * 70).','.(8 + (74 - $m) * 5), $notes, array_keys($notes))) }}" class="cp-cp2-thread" />
                                        @foreach ($notes as $j => $m)
                                            <circle cx="{{ 30 + $j * 70 }}" cy="{{ 8 + (74 - $m) * 5 }}" r="6" class="cp-cp2-dot" data-cp-note="{{ $j }}" />
                                        @endforeach
                                    </g>
                                @endforeach
                            </g>
                        @endforeach
                    </svg>
                    <div class="cp-cp2-fuse-acts">
                        <button type="button" class="cp-cp2-play" data-cp-fuse-play data-play="{{ $love['fusion']['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $love['fusion']['play'] }}</span></button>
                        <p class="cp-cp2-btns" role="group" aria-label="{{ $love['fusion']['pick'] }}">
                            @foreach (['one', 'two'] as $heard)
                                <button type="button" class="cp-cp2-pick" data-cp-pick="{{ $heard }}" aria-pressed="false">{{ $love['fusion'][$heard] }}</button>
                            @endforeach
                        </p>
                    </div>
                    <p class="cp-cp2-answer" aria-live="polite" data-cp-answer data-same-one="{{ $love['fusion']['same_one'] }}" data-same-two="{{ $love['fusion']['same_two'] }}" data-opposite-one="{{ $love['fusion']['opposite_one'] }}" data-opposite-two="{{ $love['fusion']['opposite_two'] }}"></p>
                </div>
                <div class="cp-cp2-panel cp-cp2-melt" data-cp-panel="melt" data-cp-melt hidden>
                    <p class="cp-cp2-small">{{ $love['fusion']['melt_lead'] }}</p>
                    <svg viewBox="0 0 300 64" class="cp-cp2-octave" aria-hidden="true">
                        <g class="cp-cp2-v1"><path d="M10 44 H262" class="cp-cp2-thread" /><circle cx="278" cy="44" r="8" class="cp-cp2-dot" /></g>
                        <g class="cp-cp2-v2"><path d="M10 44 H262" class="cp-cp2-thread" data-cp-melt-thread /><circle cx="278" cy="44" r="5" class="cp-cp2-dot" data-cp-melt-dot /></g>
                    </svg>
                    <label class="cp-cp2-slider"><span>{{ $love['fusion']['cents'] }} <output data-cp-cents>0</output></span><input type="range" min="-50" max="50" step="1" value="0" data-cp-detune></label>
                    <p class="cp-cp2-btns">
                        <button type="button" class="cp-cp2-play" data-cp-melt-play data-play="{{ $love['fusion']['hold'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $love['fusion']['hold'] }}</span></button>
                        <span class="cp-cp2-readout" aria-live="polite" data-cp-melted data-one="{{ $love['fusion']['merged'] }}" data-two="{{ $love['fusion']['apart'] }}" data-one-game="{{ $love['fusion']['merged_game'] }}" data-two-game="{{ $love['fusion']['apart_game'] }}"><span class="s-skin-g"></span><span class="s-skin-s"></span></span>
                    </p>
                </div>
            </div>
            <p class="cp-cp2-body">{{ $love['fusion']['bach'] }}@include('site.courses._cites', ['keys' => ['hr', 'mp91', 'mp01']])</p>
        </div>
    </section>

    @if ($outline !== null)
        <section class="cp-cp2-alt" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-cp2-label">{{ $love['books']['kick'] }}</p>
                <h2 id="units-h" class="cp-cp2-h2">{{ $love['books']['title'] }}</h2>
                <p class="cp-cp2-small">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                <ol class="cp-cp2-books">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-cp2-book cp-cp2-v{{ $loop->index % 3 + 1 }}" style="--i: {{ $loop->index }}">
                            <details data-cp-open @if ($loop->first) open @endif>
                                <summary>
                                    <span class="cp-cp2-label"><x-site.skin :game="$stage" :serious="$unitName" /></span>
                                    <h3>{{ $unit['title'] }}</h3>
                                    @isset($page['units_say'][$loop->index])
                                        <span class="cp-cp2-say">{{ $page['units_say'][$loop->index] }}</span>
                                    @endisset
                                </summary>
                                <ol class="cp-cp2-lessons">
                                    @foreach ($unit['lessons'] as $lesson)
                                        <li>{{ $lesson }}@if ($loop->parent->first && $loop->first) <a href="{{ $tryUrl }}" class="cp-cp2-free">{{ $love['books']['free'] }}</a>@endif</li>
                                    @endforeach
                                </ol>
                            </details>
                        </li>
                    @endforeach
                </ol>
                <p class="cp-cp2-ctas"><a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
        </section>
    @endif

    <section class="cp-cp2-history" aria-labelledby="history-h">
        <div class="s-wrap s-sec">
            <p class="cp-cp2-label">{{ $love['history']['kick'] }}</p>
            <h2 id="history-h" class="cp-cp2-h2">{{ $love['history']['title'] }}</h2>
            @foreach ([array_slice($beats, 0, 5), array_slice($beats, 5)] as $part)
                @if (! $loop->first)
                    <details class="cp-cp2-more" data-cp-open><summary>{{ $love['history']['more'] }}</summary>
                @endif
                <ol class="cp-cp2-years">
                    @foreach ($part as [$when, $text, $cites, $players])
                        <li @class(['cp-cp2-year', 'cp-cp2-year-forty' => $players === 40])>
                            <p class="cp-cp2-when">{{ $when }}</p>
                            @if ($players === 40)
                                {{-- Forty hairlines, a voice each, in eight choirs of five. --}}
                                <svg viewBox="0 0 100 146" preserveAspectRatio="none" class="cp-cp2-forty" aria-hidden="true">
                                    @foreach (range(0, 39) as $i)
                                        <path d="M0 {{ $i * 3 + intdiv($i, 5) * 4 + 0.5 }} H100" class="cp-cp2-hair cp-cp2-v{{ intdiv($i, 5) % 3 + 1 }}" />
                                    @endforeach
                                </svg>
                            @endif
                            <div class="cp-cp2-year-in">
                                @if ($players !== 40)
                                    <svg viewBox="0 0 320 {{ $players * 8 + 2 }}" class="cp-cp2-band" aria-hidden="true">
                                        @foreach (range(0, $players - 1) as $i)
                                            <g class="cp-cp2-v{{ $i % 3 + 1 }}"><path d="M{{ 5 + $i * 22 }} {{ 5 + $i * 8 }} H320" class="cp-cp2-thread" /><circle cx="{{ 5 + $i * 22 }}" cy="{{ 5 + $i * 8 }}" r="3.5" class="cp-cp2-dot" /></g>
                                        @endforeach
                                    </svg>
                                @endif
                                <p class="cp-cp2-what">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                            </div>
                            <p class="cp-cp2-players">{{ str_replace(':n', (string) $players, $love['history']['players']) }}</p>
                        </li>
                        @if ($players === 40)
                            @php($count = $love['history']['count'])
                            <li class="cp-cp2-demo cp-cp2-count" data-cp-count>
                                <h3>{{ $count['title'] }}</h3>
                                <p class="cp-cp2-small">{{ $count['lead'] }}</p>
                                <svg viewBox="0 0 600 112" class="cp-cp2-lanes" aria-hidden="true">
                                    <path d="M10 106 H590" class="cp-cp2-staff" />
                                    @foreach ([0, 1, 2, 3] as $v)
                                        <g class="cp-cp2-entry cp-cp2-v{{ $v + 1 }}" data-cp-entry="{{ $v }}"><path d="M{{ round(10 + $v * 12 * $lane, 1) }} {{ 14 + $v * 22 }} H{{ round(10 + ($v * 12 + 48) * $lane, 1) }}" class="cp-cp2-thread" /><circle cx="{{ round(10 + $v * 12 * $lane, 1) }}" cy="{{ 14 + $v * 22 }}" r="6" class="cp-cp2-dot" /><text x="{{ round(22 + $v * 12 * $lane, 1) }}" y="{{ 9 + $v * 22 }}" class="cp-cp2-vn">{{ $love['voices'][$v] }}</text></g>
                                    @endforeach
                                    <g data-cp-taps></g>
                                </svg>
                                <p class="cp-cp2-btns">
                                    <button type="button" class="cp-cp2-play" data-cp-count-play data-play="{{ $count['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $count['play'] }}</span></button>
                                    <button type="button" class="cp-cp2-pick cp-cp2-tap" data-cp-tap disabled>{{ $count['tap'] }}</button>
                                </p>
                                <div aria-live="polite">
                                    <p class="cp-cp2-answer cp-cp2-caught" data-cp-caught data-say="{{ $count['result'] }}" data-say-game="{{ $count['result_game'] }}" hidden><span class="s-skin-g"></span><span class="s-skin-s"></span></p>
                                    <p class="cp-cp2-small" data-cp-count-note hidden>{{ $count['note'] }}@include('site.courses._cites', ['keys' => ['mp89']])</p>
                                </div>
                            </li>
                        @endif
                    @endforeach
                </ol>
                @if (! $loop->first)
                    </details>
                @endif
            @endforeach
        </div>
    </section>

    <section class="cp-cp2-alt" aria-labelledby="seek-h">
        <div class="s-wrap s-sec cp-cp2-split">
            <div>
                <p class="cp-cp2-label" lang="la">{{ $love['seek']['kick'] }}</p>
                <h2 id="seek-h" class="cp-cp2-h2">{{ $love['seek']['title'] }}</h2>
                <p class="cp-cp2-lede">{{ $love['seek']['lead'] }}@include('site.courses._cites', ['keys' => ['bachnl', 'cmslc']])</p>
                <p class="cp-cp2-body">{{ $page['move']['smooth'] }}</p>
            </div>
            <div class="cp-cp2-demo" data-cp-seek data-line="{{ json_encode($seek) }}">
                <p class="cp-cp2-small">{{ $love['seek']['demo_lead'] }}</p>
                <svg viewBox="0 10 520 112" class="cp-cp2-seek" aria-hidden="true">
                    <g data-cp-links></g>
                    @foreach (['cp-cp2-v1', 'cp-cp2-v2'] as $voice)
                        <g class="{{ $voice }}" @if ($loop->last) data-cp-copy @endif>
                            <polyline points="{{ implode(' ', array_map(fn (int $m, int $j): string => (24 + $j * 36).','.(112 - ($m - 60) * 10), $seek, array_keys($seek))) }}" class="cp-cp2-thread" />
                            @foreach ($seek as $j => $m)
                                <circle cx="{{ 24 + $j * 36 }}" cy="{{ 112 - ($m - 60) * 10 }}" r="{{ $loop->parent->last ? 5 : 7.5 }}" class="cp-cp2-dot" data-cp-note="{{ $j }}" />
                            @endforeach
                        </g>
                    @endforeach
                </svg>
                <label class="cp-cp2-slider"><span>{{ $love['seek']['delay'] }} <output data-cp-beats>1</output></span><input type="range" min="1" max="6" step="1" value="1" data-cp-delay></label>
                <p class="cp-cp2-ticks" aria-hidden="true">@foreach (range(1, 6) as $n)<span data-cp-tick="{{ $n }}">{{ $n }}</span>@endforeach</p>
                <p class="cp-cp2-legend" data-cp-legend hidden><span><span class="cp-cp2-fit-dot" aria-hidden="true"></span> {{ $love['seek']['fits'] }}</span><span><span class="cp-cp2-fit-dot cp-cp2-rub-dot" aria-hidden="true"></span> {{ $love['seek']['rubs'] }}</span></p>
                <p class="cp-cp2-btns">
                    <button type="button" class="cp-cp2-play" data-cp-seek-play data-play="{{ $love['seek']['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $love['seek']['play'] }}</span></button>
                    <span class="cp-cp2-readout" aria-live="polite" data-cp-fit data-smooth="{{ $love['seek']['smooth'] }}" data-one="{{ $love['seek']['rub_one'] }}" data-many="{{ $love['seek']['rub_many'] }}" data-smooth-game="{{ $love['seek']['smooth_game'] }}" data-rub-game="{{ $love['seek']['rub_game'] }}" hidden><span class="s-skin-g"></span><span class="s-skin-s"></span></span>
                </p>
            </div>
        </div>
    </section>

    @include('site.courses._inside')

    <section class="s-wrap s-sec cp-cp2-pack" aria-labelledby="pack-h">
        <h2 id="pack-h" class="cp-cp2-label">{{ $page['titles']['reader'] }}</h2>
        <div class="cp-cp2-pack-grid">
            @foreach (['who', 'need'] as $part)
                <details class="cp-cp2-fold" data-cp-open><summary><h3>{{ $page['titles'][$part] }}</h3></summary><ul>@foreach ((array) $page[$part] as $line)<li>{{ $line }}</li>@endforeach</ul></details>
            @endforeach
            <div><h3>{{ $page['titles']['safety'] }}</h3><ul>@foreach ((array) $page['safety'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
            <div><h3>{{ __('pages.site.course_songs') }}</h3><ul class="cp-cp2-songs">@foreach ((array) $page['songs'] as [$song, $credit])<li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>@endforeach</ul></div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-cp2-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-cp2-label">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-cp2-h2">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-cp2-finis" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-cp2-finis-in">
            <figure class="cp-cp2-card" data-cp-canon>
                @include('site.courses._cp-canon')
            </figure>
            <div>
                <p class="cp-cp2-label">{{ $page['kicks']['finis'] }}</p>
                <h2 id="join-h" class="cp-cp2-h2">{{ $page['titles']['join'] }}</h2>
                <p class="cp-cp2-lede">{{ $open ? $page['finis'] : __('pages.site.course_soon') }}</p>
                <p class="cp-cp2-ctas">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <button type="button" class="cp-cp2-play" data-cp-round data-play="{{ $love['again'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $love['again'] }}</span></button>
                </p>
                @if ($open)
                    <p class="cp-cp2-quiet">{{ __('pages.site.course_join_note') }}</p>
                @endif
            </div>
        </div>
    </section>

    <section class="s-wrap cp-cp2-also" aria-labelledby="also-h">
        <h2 id="also-h" class="cp-cp2-label">{{ $page['titles']['related'] }}</h2>
        @include('site.courses._related')
    </section>

    <section class="s-wrap cp-cp2-notes" aria-labelledby="notes-h">
        <details data-cp-open>
            <summary><h2 id="notes-h" class="cp-cp2-label">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/counterpoint.js"></script>
</main>
