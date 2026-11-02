{{-- Gospel and soul runs (/courses/gospel-runs), its own page: a love letter in Vibrato's calm, light
     design (white cards, thin headlines, the hero's picture in its own card with a legend), from the
     research pack (docs/courses/gospel-runs-love-letter.md), every claim with its numbered notes. The
     hero is the major pentatonic as five stairs, F and B left out, the demo's run drawn down them. Then
     the featured demo, one run at three speeds (2, 4 and 8 notes a second), each note lit as it sounds
     (js/public/gospel-runs.js). By the end (the outcomes), in plain words (a melisma, why fast runs
     smear: 124, 120 and 125 ms, with the run sung clean and then smeared, its pitch drawn; slow and
     correct), the blue note (a bend over a C drone, stopped anywhere from 300 to 400 cents), from the
     archive (1644 to 1948), the course (the units, from the course file), lesson 1 beside what the app
     hears, margin notes (the facts), before you begin, questions, the close and the sources. The
     spirituals and gospel as Black American sacred music: standard words, no imitation of anyone. No
     drawn people; every sound only when pressed, on the audio clock. Game skin: square arcade frames on
     the night. Words: lang/en/site.php courses.pages.gospel-runs (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($plain = $love['plain'])
@php($smear = $love['smear'])
@php($blue = $love['blue'])
@php($tryUrl = $open ? \App\Support\Http\Hosts::app('/learn/c/'.$page['app']) : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
{{-- The hero's stairs, C4 to C5: each step's semitones above C; F and B (the fourth and seventh) are gaps. --}}
@php($steps = [0, 2, 4, 5, 7, 9, 11, 12])
@php($gaps = [3, 6])
@php($stairX = fn (int $i): float => 24 + $i * 69)
@php($stairY = fn (int $s): float => 178 - $s * 11)
@php($runIdx = [7, 5, 4, 2, 1, 0])
{{-- The run, as the JS plays it: C5, A4, G4, E4, D4, C4 (MIDI). --}}
@php($run = [72, 69, 67, 64, 62, 60])
@php($pent = [60, 62, 64, 67, 69, 72])
{{-- The featured deck: three groups, one a speed, the six notes in each; height by pitch. --}}
@php($gw = 172)
@php($runY = fn (int $m): float => 196 - ($m - 60) * 11.5)
@php($groupX = fn (int $g): float => 20 + $g * ($gw + 22))
@php($noteW = $gw / 6)
{{-- The smear deck: two groups (clean, smeared), eight slots of 125 ms each, the last note held three. --}}
@php($sw = 270)
@php($slot = $sw / 8)
@php($smY = fn (int $m): float => 170 - ($m - 60) * 10.5)
@php($smX = fn (int $g, float $s): float => round(20 + $g * ($sw + 20) + $s * $slot, 1))
{{-- The bend's lane: 300 to 400 cents above C4, and where the bend stops (the slider's start). --}}
@php($bendY = fn (float $c): float => round(170 - ($c - 300) * 1.4, 1))
@php($bendStart = 400)
@php($bendSteps = $bendStart / 100 - 9)
@php($bendHz = number_format(440 * 2 ** ($bendSteps / 12), 2))
<main class="cp cp-gospel" data-gr>
    <section class="cp-hero cp-gr-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-hero-chip"><b>{{ __('pages.site.course_chip_plus') }}</b>@if ($outline !== null)<span>{{ __('pages.site.course_chip_lessons', ['n' => $outline['lessons']]) }}</span>@endif</p>
                <h1 id="page-h">{{ $page['hero_title'] }}</h1>
                <p class="s-lede">{{ $page['hero_lead'] }}</p>
                <ul class="cp-hero-facts">
                    @foreach ((array) $page['hero_facts'] as $fact)
                        <li>{{ $fact }}</li>
                    @endforeach
                </ul>
                <p class="cp-hero-btns">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="{{ $outline !== null ? '#units-h' : '#outcomes-h' }}" class="btn-secondary btn-lg">{{ $outline !== null ? $page['see_lessons'] : $page['see_aims'] }}</a>
                </p>
                @unless ($open)
                    <p class="cp-gr-quiet">{{ __('pages.site.course_soon') }}</p>
                @endunless
            </div>
            <figure class="cp-hero-card cp-gr-stairs">
                <svg viewBox="0 0 600 236" class="cp-gr-stairs-art" aria-hidden="true">
                    @foreach ($steps as $i => $s)
                        @if (in_array($i, $gaps, true))
                            <rect x="{{ $stairX($i) + 3 }}" y="{{ $stairY($s) }}" width="63" height="{{ 196 - $stairY($s) }}" rx="4" class="cp-gr-gap" />
                        @else
                            <rect x="{{ $stairX($i) + 3 }}" y="{{ $stairY($s) }}" width="63" height="{{ 196 - $stairY($s) }}" rx="4" class="cp-gr-stair" />
                        @endif
                        <text x="{{ $stairX($i) + 34.5 }}" y="222" text-anchor="middle" @class(['cp-gr-stair-name', 'cp-gr-stair-off' => in_array($i, $gaps, true)])>{{ $love['stairs']['names'][$i] }}</text>
                    @endforeach
                    <path d="@foreach ($runIdx as $k => $i){{ $k === 0 ? 'M' : 'L' }}{{ $stairX($i) + 34.5 }} {{ $stairY($steps[$i]) - 14 }} @endforeach" class="cp-gr-run" />
                    @foreach ($runIdx as $i)
                        <circle cx="{{ $stairX($i) + 34.5 }}" cy="{{ $stairY($steps[$i]) - 14 }}" r="6" class="cp-gr-run-dot" />
                    @endforeach
                </svg>
                <figcaption>
                    <ul class="cp-hero-legend">
                        @foreach ((array) $love['stairs']['legend'] as $key)
                            <li><i class="cp-gr-key cp-gr-key-{{ ['stair', 'gap', 'run'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</li>
                        @endforeach
                    </ul>
                    <p class="cp-gr-stairs-note">{{ $love['stairs']['note'] }}@include('site.courses._cites', ['keys' => ['jubilee']])</p>
                </figcaption>
            </figure>
        </div>
    </section>

    <section class="s-wrap cp-dk-demosec cp-gr-demosec" aria-labelledby="demo-h">
        <div class="cp-dk-demo cp-dk-screen" data-gr-demo data-say="{{ $demo['say'] }}" data-after="{{ $demo['after'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['tag'] }}</span></p>
            <div class="cp-dk-stage">
                <div class="cp-dk-roll">
                    <svg viewBox="0 0 600 220" preserveAspectRatio="none" aria-hidden="true">
                        @foreach ($pent as $m)
                            <path d="M8 {{ $runY($m) }} H592" class="cp-dk-staff" />
                        @endforeach
                        @foreach (range(0, 2) as $g)
                            @foreach ($run as $k => $m)
                                @if ($k > 0)
                                    <path d="M{{ round($groupX($g) + $k * $noteW - 3, 1) }} {{ $runY($run[$k - 1]) }} L{{ round($groupX($g) + $k * $noteW + 3, 1) }} {{ $runY($m) }}" class="cp-gr-glide" />
                                @endif
                                <rect x="{{ round($groupX($g) + $k * $noteW + 3, 1) }}" y="{{ $runY($m) - 6 }}" width="{{ round($noteW - 6, 1) }}" height="12" rx="3" class="cp-gr-bar" data-gr-step="{{ $g }}-{{ $k }}" />
                            @endforeach
                        @endforeach
                    </svg>
                    <p class="cp-gr-speeds" aria-hidden="true">
                        @foreach ((array) $demo['speeds'] as $speed)
                            <span data-gr-speed="{{ $loop->index }}">{{ $speed }}</span>
                        @endforeach
                    </p>
                </div>
                <button type="button" class="cp-dk-big" data-gr-play="run" data-play="{{ $demo['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-big-icon" aria-hidden="true"></span><span data-label>{{ $demo['play'] }}</span></button>
            </div>
            <div class="cp-dk-caption">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}</p>
                </div>
                <div>
                    <p class="cp-dk-demo-lead cp-gr-how">{{ $demo['how'] }}@include('site.courses._cites', ['keys' => ['xu']])</p>
                    <p class="cp-dk-say" role="status" data-gr-say>{{ $demo['ready'] }}</p>
                    <p class="cp-dk-key">
                        @foreach ((array) $demo['key'] as $key)
                            <span><i class="cp-gr-dkey cp-gr-dkey-{{ ['note', 'on'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-dk-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-gr-aims" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="cp-gr-kick">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-gr-goals">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-gr-n" aria-hidden="true">0{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-gr-plain" aria-labelledby="plain-h">
        <div class="cp-gr-plain-head">
            <div>
                <p class="cp-gr-kick">{{ $love['kicks']['plain'] }}</p>
                <h2 id="plain-h" class="cp-gr-title">{{ $plain['title'] }}</h2>
            </div>
            <div>
                <p class="cp-gr-body">{{ $plain['lead'] }}@include('site.courses._cites', ['keys' => ['jukebox', 'jubilee']])</p>
                <p class="cp-gr-body">{{ $plain['pent'] }}@include('site.courses._cites', ['keys' => ['gill']])</p>
            </div>
        </div>
        <h3 class="cp-gr-h3 cp-gr-blur-h">{{ $plain['blur_title'] }}</h3>
        <p class="cp-gr-small">{{ $plain['blur'] }}@include('site.courses._cites', ['keys' => ['xu']])</p>
        <ol class="cp-gr-nums">
            @foreach ((array) $plain['numbers'] as [$big, $what, $text])
                <li class="cp-gr-card cp-gr-num">
                    <p class="cp-gr-big">{{ $big }}</p>
                    <h4>{{ $what }}</h4>
                    <p>{{ $text }}@if ($loop->index < 2)@include('site.courses._cites', ['keys' => [['xu', 'dai'][$loop->index]]])@endif</p>
                </li>
            @endforeach
        </ol>
        <div class="cp-gr-pair">
            <div class="cp-dk-demo cp-gr-smear" data-gr-smear data-say-clean="{{ $smear['say_clean'] }}" data-say-smear="{{ $smear['say_smear'] }}" data-after="{{ $smear['after'] }}">
                <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $smear['kick'] }}</span><span>{{ $smear['tag'] }}</span></p>
                <div class="cp-gr-deck-in">
                    <div class="cp-dk-roll cp-gr-smear-roll">
                        <svg viewBox="0 0 600 200" preserveAspectRatio="none" aria-hidden="true">
                            @foreach ($pent as $m)
                                <path d="M8 {{ $smY($m) }} H592" class="cp-dk-staff" />
                            @endforeach
                            @foreach (['clean', 'smear'] as $g => $kind)
                                @foreach ($run as $k => $m)
                                    @php($end = $k === 5 ? 8 : $k + 1)
                                    @if ($k === 0)
                                        @php($d = 'M'.$smX($g, 0).' '.$smY($m).' H'.$smX($g, 1))
                                    @elseif ($kind === 'clean')
                                        @php($d = 'M'.$smX($g, $k).' '.$smY($run[$k - 1]).' L'.$smX($g, $k + 0.16).' '.$smY($m).' H'.$smX($g, $end))
                                    @else
                                        @php($d = 'M'.$smX($g, $k).' '.$smY($run[$k - 1]).' L'.$smX($g, $k + 1).' '.$smY($m).($k === 5 ? ' H'.$smX($g, 8) : ''))
                                    @endif
                                    <path d="{{ $d }}" class="cp-gr-seg" data-gr-seg="{{ $kind }}-{{ $k }}" />
                                @endforeach
                            @endforeach
                        </svg>
                        <p class="cp-gr-speeds cp-gr-speeds-two" aria-hidden="true">
                            @foreach ((array) $smear['labels'] as $name)
                                <span data-gr-pass="{{ ['clean', 'smear'][$loop->index] }}">{{ $name }}</span>
                            @endforeach
                        </p>
                    </div>
                    <p class="cp-dk-btns"><button type="button" class="cp-dk-play" data-gr-play="smear" data-play="{{ $smear['play'] }}" data-stop="{{ $smear['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $smear['play'] }}</span></button></p>
                    <p class="cp-dk-say" role="status" data-gr-smear-say>{{ $smear['ready'] }}</p>
                    <p class="cp-dk-key">
                        @foreach ((array) $smear['key'] as $key)
                            <span><i class="cp-gr-dkey cp-gr-dkey-{{ ['line', 'on'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-dk-note">{{ $smear['note'] }}</p>
                </div>
            </div>
            <div class="cp-gr-pair-text">
                <h3 class="cp-gr-h3">{{ $plain['slow_title'] }}</h3>
                <p class="cp-gr-body">{{ $plain['slow'] }}@include('site.courses._cites', ['keys' => ['furuya']])</p>
                <p class="cp-gr-body">{{ $plain['correct'] }}@include('site.courses._cites', ['keys' => ['duke']])</p>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-gr-blue" aria-labelledby="blue-h">
        <div>
            <p class="cp-gr-kick">{{ $love['kicks']['blue'] }}</p>
            <h2 id="blue-h" class="cp-gr-title">{{ $blue['title'] }}</h2>
            <p class="cp-gr-body">{{ $blue['lead'] }}@include('site.courses._cites', ['keys' => ['rochester', 'temperley']])</p>
            <p class="cp-gr-body">{{ $blue['measured'] }}@include('site.courses._cites', ['keys' => ['cutting']])</p>
        </div>
        <div class="cp-dk-demo cp-gr-bend" data-gr-bend data-cents="{{ $blue['cents'] }}" data-hz="{{ $blue['hz'] }}"
            data-say-bend="{{ $blue['say_bend'] }}" data-say-hold="{{ $blue['say_hold'] }}" data-after="{{ $blue['after'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $blue['kick'] }}</span><span>{{ $blue['tag'] }}</span></p>
            <div class="cp-gr-deck-in">
                <div class="cp-dk-roll cp-gr-bend-roll">
                    <svg viewBox="0 0 600 200" preserveAspectRatio="none" aria-hidden="true">
                        @foreach ((array) $blue['marks'] as [$c, $label])
                            <path d="M8 {{ $bendY($c) }} H592" class="cp-gr-mark cp-gr-mark-{{ $loop->index }}" />
                        @endforeach
                        <path d="M20 {{ $bendY(300) }} H90 L250 {{ $bendY($bendStart) }} H580" class="cp-gr-bendline" data-gr-bendline />
                    </svg>
                </div>
                <p class="cp-gr-meter" aria-hidden="true"><b data-gr-cents>{{ strtr($blue['cents'], [':n' => $bendStart]) }}</b><span data-gr-hz>{{ strtr($blue['hz'], [':n' => $bendHz]) }}</span></p>
                <div class="cp-dk-sliders cp-gr-sliders">
                    <label class="cp-dk-slider"><span>{{ $blue['slider'] }} <output data-gr-bend-out>{{ strtr($blue['cents'], [':n' => $bendStart]) }}</output></span><input type="range" min="300" max="400" step="1" value="{{ $bendStart }}" data-gr-bend-to></label>
                </div>
                <p class="cp-dk-btns"><button type="button" class="cp-dk-play" data-gr-play="bend" data-play="{{ $blue['play'] }}" data-stop="{{ $blue['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $blue['play'] }}</span></button></p>
                <p class="cp-dk-say" role="status" data-gr-bend-say>{{ $blue['ready'] }}</p>
                <ul class="cp-gr-marks">
                    @foreach ((array) $blue['marks'] as [$c, $label])
                        <li><i class="cp-gr-mkey cp-gr-mkey-{{ $loop->index }}" aria-hidden="true"></i><b>{{ $c }}</b><span>{{ $label }}</span></li>
                    @endforeach
                </ul>
                <p class="cp-dk-note">{{ $blue['note'] }}</p>
            </div>
        </div>
    </section>

    <section class="cp-gr-archive" aria-labelledby="archive-h">
        <div class="s-wrap s-sec">
            <p class="cp-gr-kick">{{ $love['kicks']['archive'] }}</p>
            <h2 id="archive-h" class="cp-gr-title">{{ $love['archive']['title'] }}</h2>
            {{-- The first two years, then the rest folded on a phone (open on a wide screen). --}}
            @php($rows = (array) $love['archive']['rows'])
            @foreach ([array_slice($rows, 0, 2), array_slice($rows, 2)] as $part)
                @if ($loop->last && ! $loop->first)
                    <details class="cp-gr-more" data-gr-wide><summary><span>{{ $love['archive']['more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
                @endif
                <ol class="cp-gr-years" start="{{ $loop->first ? 1 : 3 }}">
                    @foreach ($part as [$year, $place, $title, $text, $cites])
                        <li>
                            <p class="cp-gr-year"><span>{{ $year }}</span><span class="cp-gr-place">{{ $place }}</span></p>
                            <div>
                                <h3>{{ $title }}</h3>
                                <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                @if ($loop->last && ! $loop->first)
                    </details>
                @endif
            @endforeach
        </div>
    </section>

    @if ($outline !== null)
        <section class="cp-gr-climbsec" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-gr-kick">{{ $love['kicks']['climb'] }}</p>
                <div class="cp-gr-head">
                    <h2 id="units-h" class="cp-gr-title">{{ $page['titles']['units'] }}</h2>
                    <p class="cp-gr-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <ol class="cp-gr-units">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-gr-card cp-gr-unit" style="--step: {{ $loop->index }}">
                            <p class="cp-gr-unit-n"><span class="cp-gr-unit-dot" aria-hidden="true">{{ $loop->iteration }}</span><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-gr-small">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            <ol class="cp-gr-lessons">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li><span>{{ $lesson }}</span>@if ($open && $loop->parent->first && $loop->first)<a href="{{ $tryUrl }}" class="cp-gr-free">{{ __('pages.site.course_free') }}</a>@endif</li>
                                @endforeach
                            </ol>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="s-wrap s-sec cp-gr-first" aria-labelledby="try-h">
        <div>
            <p class="cp-gr-kick">{{ $love['kicks']['first'] }}</p>
            <h2 id="try-h" class="cp-gr-title">{{ $page['titles']['try'] }}</h2>
            <p class="cp-gr-body">{{ $page['try']['lead'] }}</p>
            <ol class="cp-gr-steps">
                @foreach ((array) $page['try']['steps'] as $step)
                    <li><span class="cp-gr-step-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $step }}</p></li>
                @endforeach
            </ol>
            <p class="cp-gr-note">{{ $page['try']['after'] }}</p>
        </div>
        <div class="cp-gr-card cp-gr-inside">
            <h2 id="inside-h" class="cp-gr-h3">{{ $page['titles']['inside'] }}</h2>
            <p class="cp-gr-small">{{ $page['measures'] }}</p>
            <p class="cp-gr-small"><x-site.say :text="$page['scoring']" /></p>
        </div>
    </section>

    <section class="s-wrap s-sec cp-gr-margin" aria-labelledby="facts-h">
        <p class="cp-gr-kick">{{ $love['kicks']['notes'] }}</p>
        <h2 id="facts-h" class="cp-gr-title">{{ $love['facts_title'] }}</h2>
        @php($facts = (array) $love['facts'])
        @foreach ([array_slice($facts, 0, 2), array_slice($facts, 2)] as $part)
            @if ($loop->last && ! $loop->first)
                <details class="cp-gr-more" data-gr-wide><summary><span>{{ $love['facts_more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
            @endif
            <ul class="cp-gr-facts">
                @foreach ($part as [$text, $source, $cites])
                    <li>
                        <p class="cp-gr-fact">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                        <p class="cp-gr-src">{{ $source }}</p>
                    </li>
                @endforeach
            </ul>
            @if ($loop->last && ! $loop->first)
                </details>
            @endif
        @endforeach
    </section>

    <section class="s-wrap s-sec cp-gr-kit" aria-labelledby="kit-h">
        <h2 id="kit-h" class="cp-gr-kick">{{ $love['kicks']['kit'] }}</h2>
        <div class="cp-gr-kit-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul class="cp-gr-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="cp-gr-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div class="cp-gr-safe"><h3>{{ $page['titles']['safety'] }}</h3><ul class="cp-gr-ticks">@foreach ((array) $page['safety'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-gr-fine" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-gr-title">{{ $page['titles']['faq'] }}</h2>
        @include('site.courses._faq')
    </section>

    <section class="cp-gr-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-gr-close-in">
            <p class="cp-gr-kick">{{ $love['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-gr-title">{{ $page['titles']['join'] }}</h2>
            <p class="cp-gr-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-join-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                @if ($open)
                    <a href="/pricing" class="btn-secondary btn-lg">{{ __('pages.site.course_plus') }}</a>
                @else
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @endif
            </p>
            @if ($open)
                <p class="cp-gr-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-gr-kick cp-gr-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-gr-sources" aria-labelledby="notes-h">
        <details data-gr-sources>
            <summary><h2 id="notes-h" class="cp-gr-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/gospel-runs.js"></script>
</main>
