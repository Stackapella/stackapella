{{-- Range and registers (/courses/range-and-registers), its own page, after Hillel's design of 2026-11-18: reading down the
     page is like singing up a scale. Chest voice is rust and head voice blue, the same way throughout, and striped in both
     where either will do (the break), always named in words too. Each section's label carries a note and its hertz,
     rising from D2 to F6 (App\Support\Pages\RangeAndRegistersPage works them out at A4 = 440), square marks below the
     break and round ones above it, with a full-width striped band for the break between them. The hero's 63-key ruler
     runs from the basses' B flat 1 to Agujari's C7, its famous notes marked; the break zone on it is labelled as varying
     by voice. The demos (js/public/range-and-registers.js, on voices.js, only when pressed, on the audio clock): Up in
     chest, down in head with its band, One note, two ways, Ferrein's cord drawn as three strings, and the two ends with a
     hertz meter. The four mechanisms are one chart of overlapping regions, approximate and labelled so (no source gives
     their ranges). The archive is an old printed record (serif, a timeline, plate numbers); the climb is the course's
     units as rising steps, every lesson a link to its page, so the shared list of lesson pages isn't shown again. Words:
     lang/en/site.php courses.pages.range-and-registers (love); sources numbered as they're listed. --}}
@section('lesson-pages-inline', '1')
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($ends = $love['ends'])
@php($plain = $love['plain'])
@php($span = $love['span'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($rr = \App\Support\Pages\RangeAndRegistersPage::class)
@php($letters = $love['letters'])
@php($markers = $rr::markers($love['marker'], $letters))
@php($units = $rr::units($page, $outline))
@php($keys = $rr::keys())
@php($zone = $rr::breakZone())
@php($bandBox = $rr::bandBox())
<main class="cp cp-rg" data-rg>
    <section class="cp-rg-hero" aria-labelledby="page-h">
        <div class="s-wrap">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <div class="cp-rg-hero-grid">
                <div>
                    <p class="cp-hero-chip"><b>{{ __('pages.site.course_chip_free') }}</b>@if ($outline !== null)<span>{{ __('pages.site.course_chip_lessons', ['n' => $outline['lessons']]) }}</span>@endif</p>
                    <h1 id="page-h" class="cp-rg-h1">@foreach ($rr::parts($love['hero']) as [$text, $mark])<span @class(['cp-rg-t-'.$mark => $mark !== null])>{{ $text }}</span>@endforeach</h1>
                </div>
                <div class="cp-rg-hero-side">
                    <p class="cp-rg-lead">@foreach ($rr::parts($love['hero_lead']) as [$text, $mark])<span @class(['cp-rg-t-'.$mark => $mark !== null])>{{ $text }}</span>@endforeach</p>
                    <ul class="cp-rg-facts-row">
                        @foreach ((array) $page['hero_facts'] as $fact)
                            <li>{{ $fact }}</li>
                        @endforeach
                    </ul>
                    <p class="cp-rg-btns">
                        <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                        <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                    </p>
                </div>
            </div>

            {{-- The ruler: 63 keys, B flat 1 to C7 (a C tallest, a black key shortest), rust below the break zone, blue
                 above it and striped in it; the marked notes as stems, middle C dashed. --}}
            <figure class="cp-rg-ruler">
                <div class="cp-rg-ruler-ends">
                    <p class="cp-rg-ruler-low"><b>{{ $rr::label($love['marker'], $rr::LOW, $letters) }}</b>{{ $span['low_say'] }}@include('site.courses._cites', ['keys' => ['nunc', 'osmin']])</p>
                    <p class="cp-rg-ruler-high"><b>{{ $rr::label($love['marker'], $rr::HIGH, $letters) }}</b>{{ $span['high_say'] }}@include('site.courses._cites', ['keys' => ['zauberflote', 'grove-agujari']])</p>
                </div>
                <div class="cp-rg-ruler-art" aria-hidden="true">
                    <span class="cp-rg-zone" style="--from: {{ $zone['from'] }}%; --width: {{ $zone['width'] }}%"><span>{{ $span['break'] }}</span></span>
                    @foreach ($rr::marks() as $mark)
                        <span @class(['cp-rg-stem', 'cp-rg-stem-'.$mark['kind'], 'cp-rg-stem-short' => $mark['short']]) style="--at: {{ $mark['at'] }}%"></span>
                    @endforeach
                    <span class="cp-rg-midc" style="--at: {{ $rr::at(60) }}%">{{ $span['middle'] }}</span>
                    <span class="cp-rg-keyrow">
                        @foreach ($keys as $key)
                            <i class="cp-rg-k cp-rg-k-{{ $key['kind'] }} cp-rg-k-{{ $key['zone'] }}"></i>
                        @endforeach
                    </span>
                    <span class="cp-rg-octs">
                        @foreach ($rr::octaves($letters) as $octave)
                            <span style="--at: {{ $octave['at'] }}%">{{ $octave['name'] }}</span>
                        @endforeach
                    </span>
                </div>
                <figcaption class="cp-rg-ruler-cap">{{ $span['note'] }}@include('site.courses._cites', ['keys' => ['nunc', 'grove-agujari']])</figcaption>
            </figure>
        </div>
    </section>

    {{-- D2: the featured demo, Up in chest, down in head (Muller's two series). The stage draws the 29 steps, C3 to C5 and
         back, chest steps solid and head steps outlined, and the band where either will do, striped; the script moves the
         band and lights each step as it sounds. --}}
    <section class="s-wrap cp-rg-sec cp-rg-demosec" aria-labelledby="demo-h">
        @include('site.courses._range-and-registers-note', ['marker' => $markers['demo']])
        <div class="cp-dk-demo cp-rg-deck" data-rg-demo data-lo="{{ $rr::BAND }}" data-letters="{{ $letters }}"
            data-say-chest="{{ $demo['say_chest'] }}" data-say-head="{{ $demo['say_head'] }}" data-say-two="{{ $demo['say_two'] }}" data-after="{{ $demo['after'] }}" data-band="{{ $demo['band'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['tag'] }}</span></p>
            <div class="cp-dk-stage">
                <div class="cp-dk-roll cp-rg-roll">
                    <svg viewBox="0 0 600 240" preserveAspectRatio="none" aria-hidden="true">
                        <defs>
                            <pattern id="rg-hatch" width="12" height="12" patternUnits="userSpaceOnUse" patternTransform="rotate(45)"><rect width="6" height="12" class="cp-rg-hatch-a" /><rect x="6" width="6" height="12" class="cp-rg-hatch-b" /></pattern>
                        </defs>
                        @foreach (range(0, $rr::ROWS - 1) as $row)
                            <path d="M8 {{ $rr::rowY($row) }} H592" class="cp-dk-staff" />
                        @endforeach
                        <rect x="10" y="{{ $bandBox['y'] }}" width="580" height="{{ $bandBox['h'] }}" rx="6" class="cp-rg-band" data-rg-band-box />
                        @foreach ($rr::steps() as $step)
                            <rect x="{{ $step['x'] }}" y="{{ $step['y'] }}" width="{{ $step['w'] }}" height="10" rx="3" class="cp-rg-bar" data-rg-step="{{ $loop->index }}" @if ($step['head']) data-head @endif />
                        @endforeach
                    </svg>
                    <p class="cp-rg-corner cp-rg-corner-up" aria-hidden="true"><span>↗</span> {{ $demo['up'] }}</p>
                    <p class="cp-rg-corner cp-rg-corner-down" aria-hidden="true">{{ $demo['down'] }} <span>↘</span></p>
                </div>
                <button type="button" class="cp-dk-big" data-rg-play="climb" data-play="{{ $demo['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-big-icon" aria-hidden="true"></span><span data-label>{{ $demo['play'] }}</span></button>
            </div>
            <div class="cp-dk-caption">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h">@foreach ($rr::parts($demo['title_h']) as [$text, $mark])<span @class(['cp-rg-t-'.$mark => $mark !== null])>{{ $text }}</span>@endforeach</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}@include('site.courses._cites', ['keys' => ['muller', 'mackinlay']])</p>
                    <p class="cp-dk-demo-lead">{{ $demo['how'] }}@include('site.courses._cites', ['keys' => ['hints']])</p>
                </div>
                <div>
                    <p class="cp-dk-btns"><button type="button" class="cp-dk-play cp-rg-two" data-rg-play="both" data-play="{{ $demo['both'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['both'] }}</span></button></p>
                    <label class="cp-dk-slider cp-rg-slider"><span>{{ $demo['slider'] }} <output data-rg-band-out>{{ $rr::band($demo['band'], $letters) }}</output></span><input type="range" min="1" max="10" step="1" value="{{ $rr::BAND }}" data-rg-band></label>
                    <p class="cp-dk-say" role="status" data-rg-say>{{ $demo['ready'] }}</p>
                    <p class="cp-rg-key">
                        @foreach ((array) $demo['key'] as $key)
                            <span><i class="cp-rg-sw cp-rg-sw-{{ ['chest', 'head', 'band'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-dk-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- G2: by the end, as rising steps. --}}
    <section class="s-wrap cp-rg-sec" aria-labelledby="outcomes-h">
        @include('site.courses._range-and-registers-note', ['marker' => $markers['outcomes'], 'kick' => $page['titles']['outcomes'], 'id' => 'outcomes-h'])
        <ol class="cp-rg-stairs">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-rg-stair-n" aria-hidden="true">0{{ $loop->iteration }}</span><p>{{ $outcome }}</p><span class="cp-rg-stair cp-rg-stair-{{ $loop->index }}" aria-hidden="true"></span></li>
            @endforeach
        </ol>
    </section>

    {{-- C3: in plain words, the four mechanisms on one chart, and Ferrein's cord. --}}
    <section class="cp-rg-plain" aria-labelledby="plain-h">
        <div class="s-wrap cp-rg-sec">
            <div class="cp-rg-two-col">
                <div>
                    @include('site.courses._range-and-registers-note', ['marker' => $markers['plain'], 'kick' => $love['kicks']['plain']])
                    <h2 id="plain-h" class="cp-rg-title">{{ $plain['title'] }}</h2>
                </div>
                <div class="cp-rg-body">
                    <p>{{ $plain['lead'] }}@include('site.courses._cites', ['keys' => ['hints', 'henrich']])</p>
                    <p>{{ $plain['four'] }}@include('site.courses._cites', ['keys' => ['jvoice', 'rstb']])</p>
                </div>
            </div>

            {{-- The four mechanisms as overlapping regions from low to high, with soft ends: their order and overlap are
                 the sources', their places only a sketch, and the figure says so. --}}
            <figure class="cp-rg-fig cp-rg-map">
                <p class="cp-rg-fig-top"><span>{{ $plain['fig1'] }}</span><span class="cp-rg-approx">{{ $plain['approx'] }}</span></p>
                <div class="cp-rg-map-rows">
                    <span class="cp-rg-map-over" aria-hidden="true"><span class="cp-rg-map-zone" style="--from: {{ $zone['from'] }}%; --width: {{ $zone['width'] }}%"><span>{{ $span['break'] }}</span></span></span>
                    @foreach ((array) $plain['mechanisms'] as [$code, $name, $what])
                        <p class="cp-rg-map-row cp-rg-m{{ $loop->index }}"><b translate="no">{{ $code }}</b><span class="cp-rg-map-track"><span class="cp-rg-map-bar"><span>{{ $name }}</span></span></span></p>
                    @endforeach
                    <p class="cp-rg-map-axis" aria-hidden="true"><span>{{ $plain['low'] }}</span><span>{{ $plain['high'] }}</span></p>
                </div>
                <figcaption class="cp-rg-fig-cap">{{ $plain['fig1_note'] }}@include('site.courses._cites', ['keys' => ['hints', 'jvoice']])</figcaption>
            </figure>

            <ol class="cp-rg-mechs">
                @foreach ((array) $plain['mechanisms'] as [$code, $name, $what])
                    <li class="cp-rg-m{{ $loop->index }}">
                        <h3><span translate="no">{{ $code }}</span> · {{ $name }}</h3>
                        <p>{{ $what }}@include('site.courses._cites', ['keys' => $loop->last ? ['jvoice'] : ['rstb']])</p>
                    </li>
                @endforeach
            </ol>

            <div class="cp-rg-pair">
                <figure class="cp-rg-cords" data-rg-cords>
                    <p class="cp-rg-fig-top"><span>{{ $plain['fig2'] }}</span></p>
                    <h3 class="cp-rg-serif-h">{{ $plain['cord_title'] }}</h3>
                    <p class="cp-rg-small">{{ $plain['cord'] }}@include('site.courses._cites', ['keys' => ['muller', 'bjorl']])</p>
                    <ol class="cp-rg-strings">
                        @foreach ((array) $plain['cords'] as $cord)
                            <li data-rg-cord="{{ $loop->index }}" class="cp-rg-string-{{ $loop->index }}">
                                <span class="cp-rg-string" aria-hidden="true"></span>
                                <span class="cp-rg-string-say"><span>{{ $cord }}</span><b aria-hidden="true">{{ $plain['cord_parts'][$loop->index] ?? '' }}</b></span>
                            </li>
                        @endforeach
                    </ol>
                    <p class="cp-rg-btns-s"><button type="button" class="cp-rg-hear" data-rg-play="cords" data-play="{{ $plain['hear'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-rg-hear-icon" aria-hidden="true"></span><span data-label>{{ $plain['hear'] }}</span></button></p>
                </figure>
                <div class="cp-rg-short">
                    <h3>{{ $plain['short_title'] }}</h3>
                    <p class="cp-rg-serif-p">{{ $plain['short'] }}@include('site.courses._cites', ['keys' => ['rstb']])</p>
                </div>
            </div>
        </div>
    </section>

    {{-- F3: from the archive, an old printed record: a timeline of the six years, and each entry with its plate. --}}
    <section class="cp-rg-archive" aria-labelledby="archive-h">
        <div class="s-wrap cp-rg-sec">
            @include('site.courses._range-and-registers-note', ['marker' => $markers['archive'], 'kick' => $love['kicks']['archive']])
            <h2 id="archive-h" class="cp-rg-title cp-rg-serif">@foreach ($rr::parts($love['archive']['title']) as [$text, $mark])<span @class(['cp-rg-t-'.$mark => $mark !== null])>{{ $text }}</span>@endforeach</h2>
            <ol class="cp-rg-timeline" aria-hidden="true">
                @foreach ($rr::timeline((array) $love['archive']['rows']) as $point)
                    <li style="--at: {{ $point['at'] }}%" @if ($point['up']) data-up @endif><span>{{ $point['year'] }}</span></li>
                @endforeach
            </ol>
            <ol class="cp-rg-years">
                @foreach ((array) $love['archive']['rows'] as [$year, $place, $title, $text, $cites])
                    <li>
                        <p class="cp-rg-year"><span class="cp-rg-year-n">{{ $year }}</span><span class="cp-rg-plate">{{ $rr::plate(str_replace(':place', $place, $love['archive']['plate']), $loop->iteration) }}</span></p>
                        <div>
                            <h3>{{ $title }}</h3>
                            <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- The break: chest below, head above. --}}
    <div class="cp-rg-break">
        <p class="cp-rg-break-card"><span class="cp-rg-break-ends"><b class="cp-rg-chest-t">{{ $love['break']['chest'] }}</b><span aria-hidden="true">⟷</span><b class="cp-rg-head-t">{{ $love['break']['head'] }}</b></span><span class="cp-rg-break-line">{{ $love['break']['line'] }}</span></p>
    </div>

    {{-- A4: the two ends, and a hertz meter (B flat 1 to C7 by semitones, so the hertz on a log scale). --}}
    <section class="s-wrap cp-rg-sec cp-rg-ends" aria-labelledby="ends-h">
        <div>
            @include('site.courses._range-and-registers-note', ['marker' => $markers['ends'], 'kick' => $love['kicks']['ends']])
            <h2 id="ends-h" class="cp-rg-title">{{ $ends['title'] }}</h2>
            <p class="cp-rg-body-p">{{ $ends['lead'] }}@include('site.courses._cites', ['keys' => ['nunc', 'grove-agujari']])</p>
            <blockquote class="cp-rg-quote"><p>{{ $ends['quote'] }}@include('site.courses._cites', ['keys' => ['jahn']])</p></blockquote>
        </div>
        <div class="cp-dk-demo cp-rg-deck cp-rg-ends-deck" data-rg-ends data-hz="{{ $ends['hz'] }}" data-marker="{{ $love['marker'] }}" data-letters="{{ $letters }}" data-low="{{ $rr::LOW }}" data-high="{{ $rr::HIGH }}"
            data-say-hold="{{ $ends['say_hold'] }}" data-say-walk="{{ $ends['say_walk'] }}" data-say-climb="{{ $ends['say_climb'] }}" data-say-top="{{ $ends['say_top'] }}" data-after="{{ $ends['after'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $ends['kick'] }}</span><span>{{ $ends['tag'] }}</span></p>
            <div class="cp-rg-ends-in">
                <div class="cp-rg-meter" aria-hidden="true">
                    <div class="cp-rg-meter-track" style="--at: {{ $rr::meterAt(60) }}%; --mid: {{ $rr::meterAt(60) }}%" data-rg-meter>
                        <span class="cp-rg-meter-bar"></span>
                        <span class="cp-rg-meter-mid"></span>
                        <span class="cp-rg-needle"><b data-rg-needle>{{ $rr::label($love['marker'], 60, $letters) }}</b></span>
                    </div>
                    <p class="cp-rg-meter-axis" style="--mid: {{ $rr::meterAt(60) }}%"><span>{{ $rr::hz($rr::LOW) }}</span><span>{{ $rr::hz(60) }}</span><span>{{ str_replace(':n', $rr::hz($rr::HIGH), $ends['hz']) }}</span></p>
                </div>
                <p class="cp-rg-readout" aria-hidden="true"><b data-rg-note>{{ $ends['span'] }}</b><span data-rg-hz>{{ str_replace([':low', ':high'], [$rr::hz($rr::LOW), $rr::hz($rr::HIGH)], $ends['span_hz']) }}</span></p>
                <p class="cp-dk-btns">
                    @foreach (['low', 'high'] as $end)
                        <button type="button" class="cp-dk-play cp-rg-end-{{ $end }}" data-rg-play="{{ $end }}" data-play="{{ $ends[$end] }}" data-stop="{{ $ends['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $ends[$end] }}</span></button>
                    @endforeach
                </p>
                <p class="cp-dk-say" role="status" data-rg-ends-say>{{ $ends['ready'] }}</p>
                <p class="cp-dk-note">{{ $ends['note'] }}</p>
            </div>
        </div>
    </section>

    {{-- C5: the climb, the units as rising steps (chest, the break, head, the blend), every lesson a link to its page. --}}
    @if ($units !== [])
        <section class="cp-rg-climb" aria-labelledby="units-h">
            <div class="s-wrap cp-rg-sec">
                @include('site.courses._range-and-registers-note', ['marker' => $markers['climb'], 'kick' => $love['kicks']['climb']])
                <div class="cp-rg-headrow">
                    <h2 id="units-h" class="cp-rg-title">{{ $page['titles']['units'] }}</h2>
                    <p class="cp-rg-count">{{ __('pages.site.course_size', ['units' => count($units), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <ol class="cp-rg-units">
                    @foreach ($units as $unit)
                        <li class="cp-rg-unit" data-unit="{{ $loop->iteration }}" style="--unit: {{ $loop->iteration }}">
                            <p class="cp-rg-unit-top"><x-site.skin :game="__('pages.site.course_stage', ['n' => $loop->iteration])" :serious="__('pages.site.course_unit', ['n' => $loop->iteration])" /><span>{{ $love['unit_kinds'][$loop->index] ?? '' }}</span></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @if ($unit['say'] !== '')
                                <p class="cp-rg-small">{{ $unit['say'] }}</p>
                            @endif
                            <ol class="cp-rg-lessons">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li>
                                        @if ($lesson['url'])
                                            <a href="{{ $lesson['url'] }}">{{ $lesson['name'] }}</a>
                                        @else
                                            <span>{{ $lesson['name'] }}</span>
                                        @endif
                                        @if ($loop->parent->first && $loop->first)
                                            <a href="{{ $tryUrl }}" class="cp-rg-start">{{ __('pages.site.course_free') }}</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- E5: lesson 1, beside what the app hears. --}}
    <section class="s-wrap cp-rg-sec cp-rg-first" aria-labelledby="try-h">
        <div>
            @include('site.courses._range-and-registers-note', ['marker' => $markers['first'], 'kick' => $love['kicks']['first']])
            <h2 id="try-h" class="cp-rg-title">{{ $page['titles']['try'] }}</h2>
            <p class="cp-rg-body-p">{{ $page['try']['lead'] }}</p>
            <ol class="cp-rg-steps">
                @foreach ((array) $page['try']['steps'] as $step)
                    <li class="cp-rg-step-{{ $loop->index }}"><span class="cp-rg-step-n" aria-hidden="true">{{ $love['step_marks'][$loop->index] ?? $loop->iteration }}</span><p>{{ $step }}</p></li>
                @endforeach
            </ol>
            <p class="cp-rg-note-p">{{ $page['try']['after'] }}</p>
        </div>
        <div class="cp-rg-inside">
            <h2 id="inside-h" class="cp-rg-h3">{{ $page['titles']['inside'] }}</h2>
            <p class="cp-rg-small">{{ $page['measures'] }}</p>
            <p class="cp-rg-small"><x-site.say :text="$page['scoring']" /></p>
        </div>
    </section>

    {{-- A5: margin notes, in their own words. --}}
    <section class="s-wrap cp-rg-sec" aria-labelledby="facts-h">
        @include('site.courses._range-and-registers-note', ['marker' => $markers['notes'], 'kick' => $love['kicks']['notes']])
        <h2 id="facts-h" class="cp-rg-title cp-rg-serif">@foreach ($rr::parts($love['facts_title']) as [$text, $mark])<span @class(['cp-rg-t-'.$mark => $mark !== null])>{{ $text }}</span>@endforeach</h2>
        <ul class="cp-rg-words">
            @foreach ((array) $love['facts'] as [$text, $source, $cites])
                <li>
                    <p class="cp-rg-word">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    <p class="cp-rg-src">{{ $source }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- C6: before you begin. --}}
    <section class="s-wrap cp-rg-sec" aria-labelledby="kit-h">
        @include('site.courses._range-and-registers-note', ['marker' => $markers['kit'], 'kick' => $love['kicks']['kit'], 'id' => 'kit-h'])
        <div class="cp-rg-kit">
            @foreach (['who', 'need', 'safety'] as $list)
                <div @class(['cp-rg-safe' => $list === 'safety'])>
                    <h3>{{ $page['titles'][$list] }}</h3>
                    <ul>
                        @foreach ((array) $page[$list] as $item)
                            <li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    {{-- F6: questions, beside their heading. --}}
    <section class="s-wrap cp-rg-sec cp-rg-fine" aria-labelledby="faq-h">
        <div>
            @include('site.courses._range-and-registers-note', ['marker' => $markers['faq']])
            <h2 id="faq-h" class="cp-rg-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    {{-- Back to the bottom: the close on the night, the ruler again at its foot. --}}
    <section class="cp-rg-close" aria-labelledby="join-h">
        <div class="s-wrap cp-rg-close-in">
            <p class="cp-rg-close-kick"><i aria-hidden="true"></i>{{ $love['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-rg-close-h">@foreach ($rr::parts($love['join']) as [$text, $mark])<span @class(['cp-rg-t-'.$mark => $mark !== null])>{{ $text }}</span>@endforeach</h2>
            <p class="cp-rg-close-lead">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-rg-btns cp-rg-close-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </p>
            @if ($open)
                <p class="cp-rg-close-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-rg-close-rel">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
        <div class="s-wrap cp-rg-close-keys" aria-hidden="true">
            @foreach ($keys as $key)
                <i class="cp-rg-k cp-rg-k-{{ $key['kind'] }} cp-rg-k-{{ $key['zone'] }}"></i>
            @endforeach
        </div>
    </section>

    <section class="s-wrap cp-rg-sources" aria-labelledby="notes-h">
        <details data-rg-sources>
            <summary><h2 id="notes-h">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/range-and-registers.js"></script>
</main>
