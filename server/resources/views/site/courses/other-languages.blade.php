{{-- Singing in another language (/courses/other-languages), its own page: a love letter in Vibrato's calm, light
     design (white cards, thin headlines, the hero's picture in its own card with a legend), from the research pack
     (docs/courses/other-languages-love-letter.md), every claim with its numbered notes. The hero is a mora map:
     Tōkyō, Ōsaka and Nagasaki, four morae each, bracketed into two, three and four syllables. Then the featured
     demo, One mora, one note: Sakura's "ka-gi-ri" as three morae or merged, and obasan against obāsan, each
     syllable lit as it sounds (js/public/other-languages.js). By the end (the outcomes), in plain words (glides,
     schwa, and three things a new language asks for), from the archive (1894 to 1961), the course (the units, from
     the course file), Shchedryk's figure up the speed ladder, in tune or drifting flat, lesson 1 beside what the app
     hears (pitch and timing only: it never hears which vowel or consonant you made), margin notes (the facts),
     before you begin, questions, the close and the sources. No drawn people; every sound only when pressed, on the
     audio clock. Its calm layout is Diction's own (the cp-di-* classes); only its palette, pictures and decks are its own (.cp-ol-*). Game skin: square arcade frames on the night. Words: lang/en/site.php
     courses.pages.other-languages (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($map = $love['map'])
@php($demo = $love['demo'])
@php($plain = $love['plain'])
@php($ladder = $love['ladder'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
{{-- The mora map: four cells a word, 102 units apart, each word's syllables bracketed under its cells. --}}
@php($cellX = fn (int $i): int => 20 + $i * 102)
{{-- The mora deck: 3.25 beats across 70 to 620 (169 units a beat); G4, E♭4 and D4 at 80, 200 and 230 (30 a semitone). --}}
@php($bx = fn (float $beat): int => (int) round(70 + $beat * 169))
{{-- Obasan and obāsan: five half-beat slots across 70 to 620. --}}
@php($sx = fn (int $slot): int => 70 + $slot * 110)
{{-- The ladder deck: the figure (3 beats) across 40 to 560, and ten rounds' cells in three groups. --}}
@php($fx = fn (float $beat): int => (int) round(40 + $beat * 173))
@php($figure = [[0, 1, 40], [1, 0.5, 55], [1.5, 0.5, 40], [2, 1, 85]])
@php($roundX = fn (int $k): int => 42 + $k * 48 + ($k >= 2 ? 30 : 0) + ($k >= 6 ? 30 : 0))
<main class="cp cp-diction cp-ol" data-ol>
    <section class="cp-hero cp-di-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-hero-chip"><b>{{ __('pages.site.course_chip_free') }}</b>@if ($outline !== null)<span>{{ __('pages.site.course_chip_lessons', ['n' => $outline['lessons']]) }}</span>@endif</p>
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
                    <p class="cp-di-quiet">{{ __('pages.site.course_soon') }}</p>
                @endunless
            </div>
            <figure class="cp-hero-card cp-di-map">
                <svg viewBox="0 0 440 296" class="cp-di-map-art" aria-hidden="true">
                    @foreach ((array) $map['words'] as [$word, $kana, $morae, $syllables])
                        @php($y = 18 + $loop->index * 94)
                        <text x="20" y="{{ $y + 10 }}" class="cp-ol-mword">{{ $word }}<tspan dx="10" class="cp-ol-mkana">{{ $kana }}</tspan></text>
                        <text x="420" y="{{ $y + 10 }}" text-anchor="end" class="cp-ol-mcount">{{ str_replace(':n', (string) count($syllables), $map['count']) }}</text>
                        @foreach ($morae as $mora)
                            <rect x="{{ $cellX($loop->index) }}" y="{{ $y + 22 }}" width="94" height="40" rx="8" class="cp-ol-cell" />
                            <text x="{{ $cellX($loop->index) + 47 }}" y="{{ $y + 48 }}" text-anchor="middle" class="cp-ol-mora">{{ $mora }}</text>
                        @endforeach
                        @php($at = 0)
                        @foreach ($syllables as $span)
                            <path d="M{{ $cellX($at) + 6 }} {{ $y + 66 }} V{{ $y + 74 }} H{{ $cellX($at + $span - 1) + 88 }} V{{ $y + 66 }}" class="cp-ol-brace" />
                            @php($at += $span)
                        @endforeach
                    @endforeach
                </svg>
                <figcaption>
                    <ul class="cp-hero-legend">
                        @foreach ((array) $map['legend'] as $key)
                            <li><i class="cp-di-key cp-ol-key-{{ ['cell', 'brace'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</li>
                        @endforeach
                    </ul>
                    <p class="cp-di-map-note">{{ $map['note'] }}@include('site.courses._cites', ['keys' => ['mora']])</p>
                </figcaption>
            </figure>
        </div>
    </section>

    <section class="s-wrap cp-dk-demosec cp-ol-demosec" aria-labelledby="demo-h">
        <div class="cp-dk-demo cp-dk-screen" data-ol-demo data-ready="{{ $demo['ready'] }}" data-after="{{ $demo['after'] }}"
            data-say-three="{{ $demo['say']['three'] }}" data-say-merged="{{ $demo['say']['merged'] }}" data-say-obasan="{{ $demo['say']['obasan'] }}"
            data-name-three="{{ $demo['buttons']['three'] }}" data-name-merged="{{ $demo['buttons']['merged'] }}" data-name-obasan="{{ $demo['buttons']['obasan'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['tag'] }}</span></p>
            <div class="cp-dk-stage">
                <div class="cp-dk-roll cp-ol-roll">
                    <p class="cp-ol-read" aria-hidden="true"><b data-ol-mode>{{ $demo['buttons']['three'] }}</b></p>
                    <svg viewBox="0 0 640 300" aria-hidden="true">
                        {{-- Three morae and merged: the notes on their pitches, each fresh start a dot. --}}
                        @foreach (['three', 'merged'] as $view)
                            <g data-ol-view="{{ $view }}" @if ($view !== 'three') hidden @endif>
                                @foreach (['G4' => 80, 'Eb4' => 200, 'D4' => 230] as $pitch => $y)
                                    <path d="M64 {{ $y }} H626" class="cp-dk-staff" />
                                    <text x="54" y="{{ $y + 5 }}" text-anchor="end" class="cp-ol-pitch">{{ $demo['notes'][$pitch] }}</text>
                                @endforeach
                                @if ($view === 'three')
                                    <path d="M{{ $bx(0) + 6 }} 80 H{{ $bx(1) - 8 }}" class="cp-ol-tone" data-ol-on="three-0" />
                                    <path d="M{{ $bx(1) + 6 }} 80 H{{ $bx(1.5) }} V200 H{{ $bx(2) - 8 }}" class="cp-ol-tone" data-ol-on="three-1" />
                                    <path d="M{{ $bx(2) + 6 }} 230 H{{ $bx(3.25) - 6 }}" class="cp-ol-tone" data-ol-on="three-2" />
                                    @foreach ([[0, 80], [1, 80], [2, 230]] as [$beat, $y])
                                        <circle cx="{{ $bx($beat) + 6 }}" cy="{{ $y }}" r="9" class="cp-ol-start" data-ol-on="three-{{ $loop->index }}" />
                                        <text x="{{ $bx($beat) + 6 }}" y="{{ $y + 38 }}" class="cp-ol-syl" data-ol-on="three-{{ $loop->index }}">{{ $demo['syllables']['three'][$loop->index] }}</text>
                                    @endforeach
                                @else
                                    <path d="M{{ $bx(0) + 6 }} 80 H{{ $bx(1.5) }} V200 H{{ $bx(2) - 8 }}" class="cp-ol-tone" data-ol-on="merged-0" />
                                    <path d="M{{ $bx(2) + 6 }} 230 H{{ $bx(3.25) - 6 }}" class="cp-ol-tone" data-ol-on="merged-1" />
                                    <circle cx="{{ $bx(1) + 6 }}" cy="80" r="9" class="cp-ol-ghost" />
                                    @foreach ([[0, 80], [2, 230]] as [$beat, $y])
                                        <circle cx="{{ $bx($beat) + 6 }}" cy="{{ $y }}" r="9" class="cp-ol-start" data-ol-on="merged-{{ $loop->index }}" />
                                        <text x="{{ $bx($beat) + 6 }}" y="{{ $y + 38 }}" class="cp-ol-syl" data-ol-on="merged-{{ $loop->index }}">{{ $demo['syllables']['merged'][$loop->index] }}</text>
                                    @endforeach
                                @endif
                            </g>
                        @endforeach
                        {{-- Obasan against obāsan: half-beat slots on one note, the ā over two, the n a hum. --}}
                        <g data-ol-view="obasan" hidden>
                            @foreach (['obasan' => [[0, 1], [1, 1], [2, 1], [3, 1]], 'obaasan' => [[0, 1], [1, 2], [3, 1], [4, 1]]] as $word => $cells)
                                @php($y = $loop->first ? 52 : 184)
                                <text x="70" y="{{ $y }}" class="cp-ol-wordname">{{ $demo['words'][$word] }}</text>
                                @foreach ($cells as [$slot, $span])
                                    <rect x="{{ $sx($slot) + 4 }}" y="{{ $y + 14 }}" width="{{ $span * 110 - 8 }}" height="50" rx="10" class="cp-ol-slot {{ $loop->last ? 'cp-ol-slot-n' : '' }}" data-ol-on="{{ $word }}-{{ $loop->index }}" />
                                    <text x="{{ $sx($slot) + $span * 55 }}" y="{{ $y + 47 }}" text-anchor="middle" class="cp-ol-slotname" data-ol-on="{{ $word }}-{{ $loop->index }}">{{ $demo['syllables'][$word][$loop->index] }}</text>
                                @endforeach
                            @endforeach
                        </g>
                    </svg>
                </div>
                <button type="button" class="cp-dk-big" data-ol-play="tour" data-play="{{ $demo['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-big-icon" aria-hidden="true"></span><span data-label>{{ $demo['play'] }}</span></button>
            </div>
            <div class="cp-dk-caption">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}</p>
                    <p class="cp-dk-demo-lead">{{ $demo['how'] }}@include('site.courses._cites', ['keys' => ['mora', 'jphon']])</p>
                </div>
                <div>
                    <fieldset class="cp-dk-pick cp-ol-pick">
                        <legend>{{ $demo['one_label'] }}</legend>
                        @foreach ((array) $demo['buttons'] as $kind => $name)
                            <button type="button" class="cp-dk-play {{ $loop->last ? 'cp-dk-play-b' : '' }}" data-ol-play="{{ $kind }}" data-play="{{ $name }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $name }}</span></button>
                        @endforeach
                    </fieldset>
                    <p class="cp-dk-say" role="status" data-ol-say>{{ $demo['ready'] }}</p>
                    <p class="cp-dk-key">
                        @foreach ((array) $demo['key'] as $key)
                            <span><i class="cp-ol-dkey cp-ol-dkey-{{ ['note', 'start', 'hum'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-dk-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-ol-aims" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="cp-di-kick">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-di-goals">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-di-n" aria-hidden="true">0{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-ol-plain" aria-labelledby="plain-h">
        <div class="cp-di-plain-head">
            <div>
                <p class="cp-di-kick">{{ $love['kicks']['plain'] }}</p>
                <h2 id="plain-h" class="cp-di-title">{{ $plain['title'] }}</h2>
            </div>
            <div>
                <p class="cp-di-body">{{ $plain['glides'] }}@include('site.courses._cites', ['keys' => ['diph', 'jphon', 'sphon', 'uphon']])</p>
                <p class="cp-di-body">{{ $plain['schwa'] }}@include('site.courses._cites', ['keys' => ['stress', 'sphon']])</p>
            </div>
        </div>
        {{-- Folded on a phone (for length), open on a wide screen. --}}
        <details class="cp-di-more cp-ol-asks-more" data-ol-wide><summary><span>{{ $plain['asks_title'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
        <h3 class="cp-di-h3 cp-di-blur-h">{{ $plain['asks_title'] }}</h3>
        <ol class="cp-di-nums">
            @foreach ((array) $plain['asks'] as [$big, $what, $text, $cites])
                <li class="cp-di-card cp-di-num">
                    <p class="cp-di-big">{{ $big }}</p>
                    <h4>{{ $what }}</h4>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ol>
        </details>
    </section>

    <section class="cp-di-archive" aria-labelledby="archive-h">
        <div class="s-wrap s-sec">
            <p class="cp-di-kick">{{ $love['kicks']['archive'] }}</p>
            <h2 id="archive-h" class="cp-di-title">{{ $love['archive']['title'] }}</h2>
            {{-- The first two rows, then the rest folded on a phone (open on a wide screen). --}}
            @php($rows = (array) $love['archive']['rows'])
            @foreach ([array_slice($rows, 0, 2), array_slice($rows, 2)] as $part)
                @if ($loop->last && ! $loop->first)
                    <details class="cp-di-more" data-ol-wide><summary><span>{{ $love['archive']['more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
                @endif
                <ol class="cp-di-years" start="{{ $loop->first ? 1 : 3 }}">
                    @foreach ($part as [$year, $place, $title, $text, $cites])
                        <li>
                            <p class="cp-di-year"><span>{{ $year }}</span><span class="cp-di-place">{{ $place }}</span></p>
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
        <section class="cp-di-climbsec" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-di-kick">{{ $love['kicks']['climb'] }}</p>
                <div class="cp-di-head">
                    <h2 id="units-h" class="cp-di-title">{{ $page['titles']['units'] }}</h2>
                    <p class="cp-di-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <ol class="cp-di-units">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-di-card cp-di-unit" style="--step: {{ $loop->index }}">
                            <p class="cp-di-unit-n"><span class="cp-di-unit-dot" aria-hidden="true">{{ $loop->iteration }}</span><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-di-small">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            <ol class="cp-di-lessons">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li><span>{{ $lesson }}</span>@if ($open && $loop->parent->first && $loop->first)<a href="{{ $tryUrl }}" class="cp-di-free">{{ __('pages.site.course_free') }}</a>@endif</li>
                                @endforeach
                            </ol>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="s-wrap s-sec cp-ol-laddersec" aria-labelledby="ladder-h">
        <div class="cp-ol-ladder-text">
            <p class="cp-di-kick">{{ $love['kicks']['ladder'] }}</p>
            <h2 id="ladder-h" class="cp-di-title">{{ $ladder['title'] }}</h2>
            <p class="cp-di-body">{{ $ladder['lead'] }}</p>
            <p class="cp-di-note">{{ $ladder['more'] }}</p>
        </div>
        <div class="cp-dk-demo cp-ol-ladder" data-ol-ladder data-ready="{{ $ladder['ready'] }}" data-round="{{ $ladder['round'] }}" data-low="{{ $ladder['low'] }}"
            data-end-true="{{ $ladder['end_true'] }}" data-end-flat="{{ $ladder['end_flat'] }}" data-say-fast="{{ $ladder['say_fast'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $ladder['kick'] }}</span><span>{{ $ladder['tag'] }}</span></p>
            <div class="cp-di-deck-in">
                <h3 class="cp-di-beat-h">{{ $ladder['deck_title'] }}</h3>
                <p class="cp-di-beat-lead">{{ $ladder['deck_lead'] }}</p>
                <div class="cp-dk-roll cp-ol-ladder-roll">
                    <svg viewBox="0 0 600 230" aria-hidden="true">
                        @foreach ([40, 55, 85] as $y)
                            <path d="M30 {{ $y }} H570" class="cp-dk-staff" />
                        @endforeach
                        @foreach ($figure as [$beat, $length, $y])
                            <path d="M{{ $fx($beat) + 8 }} {{ $y }} H{{ $fx($beat + $length) - 10 }}" class="cp-ol-tone" data-ol-fig="{{ $loop->index }}" />
                        @endforeach
                        <path d="M30 145 H570" class="cp-ol-true" />
                        @foreach (range(0, 9) as $k)
                            <rect x="{{ $roundX($k) }}" y="138" width="40" height="14" rx="4" class="cp-ol-round" data-ol-round="{{ $k }}" />
                        @endforeach
                        @foreach ((array) $ladder['tempos'] as $tempo)
                            @php($first = [0, 2, 6][$loop->index])
                            @php($last = [1, 5, 9][$loop->index])
                            <text x="{{ ($roundX($first) + $roundX($last) + 40) / 2 }}" y="222" text-anchor="middle" class="cp-ol-tempo">{{ $tempo }}</text>
                        @endforeach
                    </svg>
                    <p class="cp-dk-words" aria-hidden="true">
                        @foreach ((array) $ladder['syllables'] as $syllable)
                            <span data-ol-fig="{{ $loop->index }}">{{ $syllable }}</span>
                        @endforeach
                    </p>
                </div>
                <fieldset class="cp-dk-pick">
                    <legend>{{ $ladder['pick_label'] }}</legend>
                    @foreach ((array) $ladder['picks'] as $pick => $name)
                        <button type="button" class="cp-dk-chip" data-ol-drift="{{ $pick }}" aria-pressed="{{ $pick === 'true' ? 'true' : 'false' }}"><span class="cp-dk-tick" aria-hidden="true"></span>{{ $name }}</button>
                    @endforeach
                </fieldset>
                <p class="cp-dk-btns">
                    <button type="button" class="cp-dk-play" data-ol-play="ladder" data-play="{{ $ladder['play'] }}" data-stop="{{ $ladder['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $ladder['play'] }}</span></button>
                    <button type="button" class="cp-dk-play cp-dk-play-b" data-ol-play="fast" data-play="{{ $ladder['fast'] }}" data-stop="{{ $ladder['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $ladder['fast'] }}</span></button>
                </p>
                <p class="cp-dk-say" role="status" data-ol-ladder-say>{{ $ladder['ready'] }}</p>
                <p class="cp-dk-key">
                    @foreach ((array) $ladder['key'] as $key)
                        <span><i class="cp-ol-dkey cp-ol-dkey-{{ ['round', 'true'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                    @endforeach
                </p>
                <p class="cp-dk-note">{{ $ladder['note'] }}</p>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-di-first" aria-labelledby="try-h">
        <div>
            <p class="cp-di-kick">{{ $love['kicks']['first'] }}</p>
            <h2 id="try-h" class="cp-di-title">{{ $page['titles']['try'] }}</h2>
            <p class="cp-di-body">{{ $page['try']['lead'] }}</p>
            <ol class="cp-di-steps">
                @foreach ((array) $page['try']['steps'] as $step)
                    <li><span class="cp-di-step-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $step }}</p></li>
                @endforeach
            </ol>
            <p class="cp-di-note">{{ $page['try']['after'] }}</p>
        </div>
        <div class="cp-di-card cp-di-inside">
            <h2 id="inside-h" class="cp-di-h3">{{ $page['titles']['inside'] }}</h2>
            <p class="cp-di-small">{{ $page['measures'] }}</p>
            <p class="cp-di-small"><x-site.say :text="$page['scoring']" /></p>
        </div>
    </section>

    <section class="s-wrap s-sec cp-ol-margin" aria-labelledby="facts-h">
        <p class="cp-di-kick">{{ $love['kicks']['notes'] }}</p>
        <h2 id="facts-h" class="cp-di-title">{{ $love['facts_title'] }}</h2>
        @php($facts = (array) $love['facts'])
        @foreach ([array_slice($facts, 0, 2), array_slice($facts, 2)] as $part)
            @if ($loop->last && ! $loop->first)
                <details class="cp-di-more" data-ol-wide><summary><span>{{ $love['facts_more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
            @endif
            <ul class="cp-di-facts">
                @foreach ($part as [$text, $source, $cites])
                    <li>
                        <p class="cp-di-fact">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                        <p class="cp-di-src">{{ $source }}</p>
                    </li>
                @endforeach
            </ul>
            @if ($loop->last && ! $loop->first)
                </details>
            @endif
        @endforeach
    </section>

    <section class="s-wrap s-sec cp-ol-kit" aria-labelledby="kit-h">
        <h2 id="kit-h" class="cp-di-kick">{{ $love['kicks']['kit'] }}</h2>
        <div class="cp-di-kit-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul class="cp-di-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="cp-di-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div class="cp-di-safe"><h3>{{ $page['titles']['safety'] }}</h3><ul class="cp-di-ticks">@foreach ((array) $page['safety'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-di-fine" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-di-title">{{ $page['titles']['faq'] }}</h2>
        @include('site.courses._faq')
    </section>

    <section class="cp-di-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-di-close-in">
            <p class="cp-di-kick">{{ $love['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-di-title">{{ $page['titles']['join'] }}</h2>
            <p class="cp-di-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-join-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                @if ($open)
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @else
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @endif
            </p>
            @if ($open)
                <p class="cp-di-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-di-kick cp-di-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-di-sources" aria-labelledby="notes-h">
        <details data-ol-sources>
            <summary><h2 id="notes-h" class="cp-di-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/other-languages.js"></script>
</main>
