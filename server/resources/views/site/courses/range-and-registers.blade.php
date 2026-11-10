{{-- Range and registers (/courses/range-and-registers), its own page: a love letter in Vibrato's calm,
     light design (white cards, thin headlines, the hero's picture in its own card with a legend), from
     the research pack (docs/courses/range-love-letter.md), every claim with its numbered notes. The hero
     is a keyboard from the basses' B flat 1 to Agujari's C7, the famous notes pinned. Then the featured
     demo, Muller's two series heard: a synth voice up a scale in chest and back down in head, the overlap
     a band the visitor moves (js/public/range-and-registers.js). By the end (the outcomes), in plain words
     (a register, M0 to M3, Ferrein's cord, playable, and why some voices seem short), from the archive (1741 to 1883), the two
     ends (the low notes built from their harmonics, the high softer as it climbs, a hertz meter), the
     climb (the units, from the course file), lesson 1 beside what the app hears, margin notes (the
     facts), before you begin, questions, the close and the sources. No drawn people; every sound only
     when pressed, on the audio clock. Game skin: square arcade frames on the night. Words: lang/en/site.php
     courses.pages.range-and-registers (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($ends = $love['ends'])
@php($plain = $love['plain'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
{{-- The hero's keyboard, A1 to C7: its white keys in order, and where each key sits. --}}
@php($whites = [])
@foreach (range(33, 96) as $m)
    @if (in_array($m % 12, [0, 2, 4, 5, 7, 9, 11], true))
        @php($whites[$m] = count($whites))
    @endif
@endforeach
@php($kw = 600 / count($whites))
@php($keyX = fn (int $m): float => isset($whites[$m]) ? ($whites[$m] + 0.5) * $kw : ($whites[$m - 1] + 1) * $kw)
@php($lit = [34 => 'low', 38 => 'low', 60 => 'mid', 89 => 'high', 96 => 'high'])
{{-- The demo's scale, C3 to C5 on the white keys, up then back down; the band (four notes where either
     voice will do) starts at C4. Up, chest to the band's top; down, head to its bottom (as the JS). --}}
@php($noteName = fn (int $i): string => ['C', 'D', 'E', 'F', 'G', 'A', 'B'][$i % 7].(3 + intdiv($i, 7)))
@php($path = array_merge(range(0, 14), range(13, 0)))
@php($lo = 7)
@php($rowY = fn (int $row): float => 216 - $row * 13.5)
@php($colW = 560 / count($path))
{{-- The two ends, as the JS plays them: [MIDI, start, seconds]; their height by pitch. --}}
@php($lowEnd = [[38, 0, 3.2], [46, 3.7, 0.6], [45, 4.3, 0.6], [43, 4.9, 0.6], [41, 5.5, 0.6], [39, 6.1, 0.6], [38, 6.7, 0.6], [36, 7.3, 0.6], [34, 7.9, 2.2]])
@php($highEnd = [[72, 0, 0.5], [76, 0.5, 0.5], [79, 1, 0.5], [84, 1.5, 0.5], [88, 2, 0.5], [91, 2.5, 0.5], [96, 3, 2]])
@php($endY = fn (int $m): float => round(222 - ($m - 31) / 67 * 204, 1))
<main class="cp cp-range" data-rg>
    <section class="cp-hero cp-rg-hero" aria-labelledby="page-h">
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
                    <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                </p>
            </div>
            <figure class="cp-hero-card cp-rg-span">
                <svg viewBox="0 0 600 150" class="cp-rg-keys" aria-hidden="true">
                    <path d="M{{ $keyX(34) }} 18 H{{ $keyX(96) }}" class="cp-rg-span-line" />
                    @foreach ($whites as $m => $i)
                        <rect x="{{ round($i * $kw, 2) }}" y="40" width="{{ round($kw - 1.5, 2) }}" height="108" rx="2" class="cp-rg-white @isset($lit[$m]) cp-rg-lit-{{ $lit[$m] }} @endisset" />
                    @endforeach
                    @foreach (range(33, 96) as $m)
                        @unless (isset($whites[$m]))
                            <rect x="{{ round($keyX($m) - $kw * 0.32, 2) }}" y="40" width="{{ round($kw * 0.64, 2) }}" height="66" rx="1.5" @class(['cp-rg-black', 'cp-rg-lit-low' => $m === 34]) />
                        @endunless
                    @endforeach
                    @foreach ($lit as $m => $kind)
                        <path d="M{{ round($keyX($m), 2) }} 24 V{{ isset($whites[$m]) ? 112 : 84 }}" class="cp-rg-stem cp-rg-stem-{{ $kind }}" />
                        <circle cx="{{ round($keyX($m), 2) }}" cy="18" r="7" class="cp-rg-pin cp-rg-pin-{{ $kind }}" />
                    @endforeach
                </svg>
                <figcaption>
                    <p class="cp-rg-span-ends"><span>{{ $love['span']['low'] }}</span><span>{{ $love['span']['high'] }}</span></p>
                    <ul class="cp-hero-legend">
                        @foreach ((array) $love['span']['legend'] as $key)
                            <li><i class="cp-rg-key cp-rg-key-{{ ['low', 'mid', 'high'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</li>
                        @endforeach
                    </ul>
                    <p class="cp-rg-span-note">{{ $love['span']['note'] }}@include('site.courses._cites', ['keys' => ['nunc', 'grove-agujari']])</p>
                </figcaption>
            </figure>
        </div>
    </section>

    <section class="s-wrap cp-dk-demosec cp-rg-demosec" aria-labelledby="demo-h">
        <div class="cp-dk-demo cp-dk-screen" data-rg-demo data-lo="{{ $lo }}"
            data-say-chest="{{ $demo['say_chest'] }}" data-say-head="{{ $demo['say_head'] }}" data-say-two="{{ $demo['say_two'] }}" data-after="{{ $demo['after'] }}" data-band="{{ $demo['band'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['tag'] }}</span></p>
            <div class="cp-dk-stage">
                <div class="cp-dk-roll">
                    <svg viewBox="0 0 600 240" preserveAspectRatio="none" aria-hidden="true">
                        <rect x="0" y="{{ $rowY($lo + 3) - 8 }}" width="600" height="{{ $rowY($lo) - $rowY($lo + 3) + 16 }}" class="cp-rg-band" data-rg-band-box />
                        @foreach (range(0, 14) as $row)
                            <path d="M8 {{ $rowY($row) }} H592" class="cp-dk-staff" />
                        @endforeach
                        @foreach ($path as $k => $row)
                            <rect x="{{ round(20 + $k * $colW + 2, 1) }}" y="{{ $rowY($row) - 4.5 }}" width="{{ round($colW - 4, 1) }}" height="9" rx="3" class="cp-rg-bar" data-rg-step="{{ $k }}" @if ($k < 15 ? $row >= $lo + 3 : $row > $lo) data-head @endif />
                        @endforeach
                    </svg>
                </div>
                <button type="button" class="cp-dk-big" data-rg-play="climb" data-play="{{ $demo['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-big-icon" aria-hidden="true"></span><span data-label>{{ $demo['play'] }}</span></button>
            </div>
            <div class="cp-dk-caption">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}@include('site.courses._cites', ['keys' => ['muller', 'mackinlay']])</p>
                    <p class="cp-dk-demo-lead">{{ $demo['how'] }}@include('site.courses._cites', ['keys' => ['hints']])</p>
                </div>
                <div>
                    <p class="cp-dk-btns"><button type="button" class="cp-dk-play" data-rg-play="both" data-play="{{ $demo['both'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['both'] }}</span></button></p>
                    <div class="cp-dk-sliders cp-rg-sliders">
                        <label class="cp-dk-slider"><span>{{ $demo['slider'] }} <output data-rg-band-out>{{ strtr($demo['band'], [':low' => $noteName($lo), ':high' => $noteName($lo + 3)]) }}</output></span><input type="range" min="1" max="10" step="1" value="{{ $lo }}" data-rg-band></label>
                    </div>
                    <p class="cp-dk-say" role="status" data-rg-say>{{ $demo['ready'] }}</p>
                    <p class="cp-dk-key">
                        @foreach ((array) $demo['key'] as $key)
                            <span><i class="cp-rg-dkey cp-rg-dkey-{{ ['chest', 'head', 'band'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-dk-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-rg-aims" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="cp-rg-kick">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-rg-goals">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-rg-n" aria-hidden="true">0{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-rg-plain" aria-labelledby="plain-h">
        <div class="cp-rg-plain-head">
            <div>
                <p class="cp-rg-kick">{{ $love['kicks']['plain'] }}</p>
                <h2 id="plain-h" class="cp-rg-title">{{ $plain['title'] }}</h2>
            </div>
            <div>
                <p class="cp-rg-body">{{ $plain['lead'] }}@include('site.courses._cites', ['keys' => ['hints', 'henrich']])</p>
                <p class="cp-rg-body">{{ $plain['four'] }}@include('site.courses._cites', ['keys' => ['jvoice', 'rstb']])</p>
            </div>
        </div>
        <ol class="cp-rg-mechs">
            @foreach ((array) $plain['mechanisms'] as [$code, $name, $what])
                <li class="cp-rg-card cp-rg-mech">
                    <p class="cp-rg-mech-code" translate="no">{{ $code }}</p>
                    <span class="cp-rg-mech-bar cp-rg-mech-bar-{{ $loop->index }}" aria-hidden="true"></span>
                    <h3>{{ $name }}</h3>
                    <p>{{ $what }}@include('site.courses._cites', ['keys' => $loop->last ? ['jvoice'] : ['rstb']])</p>
                </li>
            @endforeach
        </ol>
        <div class="cp-rg-pair">
            <figure class="cp-rg-card cp-rg-cords" data-rg-cords>
                <h3 class="cp-rg-h3">{{ $plain['cord_title'] }}</h3>
                <p class="cp-rg-small">{{ $plain['cord'] }}@include('site.courses._cites', ['keys' => ['bjorl', 'muller']])</p>
                <ol class="cp-rg-cord-list">
                    @foreach ((array) $plain['cords'] as $cord)
                        <li data-rg-cord="{{ $loop->index }}"><span class="cp-rg-cord cp-rg-cord-{{ $loop->index }}" aria-hidden="true"></span><span>{{ $cord }}</span></li>
                    @endforeach
                </ol>
                <p class="cp-rg-btns"><button type="button" class="cp-rg-hear" data-rg-play="cords" data-play="{{ $plain['hear'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-rg-hear-icon" aria-hidden="true"></span><span data-label>{{ $plain['hear'] }}</span></button></p>
            </figure>
            <div class="cp-rg-pair-text">
                <h3 class="cp-rg-h3">{{ $plain['short_title'] }}</h3>
                <p class="cp-rg-body">{{ $plain['short'] }}@include('site.courses._cites', ['keys' => ['rstb']])</p>
            </div>
        </div>
    </section>

    <section class="cp-rg-archive" aria-labelledby="archive-h">
        <div class="s-wrap s-sec">
            <p class="cp-rg-kick">{{ $love['kicks']['archive'] }}</p>
            <h2 id="archive-h" class="cp-rg-title">{{ $love['archive']['title'] }}</h2>
            {{-- The first two years, then the rest folded on a phone (open on a wide screen). --}}
            @php($rows = (array) $love['archive']['rows'])
            @foreach ([array_slice($rows, 0, 2), array_slice($rows, 2)] as $part)
                @if ($loop->last && ! $loop->first)
                    <details class="cp-rg-more" data-rg-wide><summary><span>{{ $love['archive']['more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
                @endif
                <ol class="cp-rg-years" start="{{ $loop->first ? 1 : 3 }}">
                    @foreach ($part as [$year, $place, $title, $text, $cites])
                        <li>
                            <p class="cp-rg-year"><span>{{ $year }}</span><span class="cp-rg-place">{{ $place }}</span></p>
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

    <section class="s-wrap s-sec cp-rg-ends" aria-labelledby="ends-h">
        <div>
            <p class="cp-rg-kick">{{ $love['kicks']['ends'] }}</p>
            <h2 id="ends-h" class="cp-rg-title">{{ $ends['title'] }}</h2>
            <p class="cp-rg-body">{{ $ends['lead'] }}@include('site.courses._cites', ['keys' => ['nunc', 'grove-agujari']])</p>
            <blockquote class="cp-rg-quote"><p>{{ $ends['quote'] }}@include('site.courses._cites', ['keys' => ['jahn']])</p></blockquote>
        </div>
        <div class="cp-dk-demo cp-rg-ends-deck" data-rg-ends data-hz="{{ $ends['hz'] }}"
            data-say-hold="{{ $ends['say_hold'] }}" data-say-walk="{{ $ends['say_walk'] }}" data-say-climb="{{ $ends['say_climb'] }}" data-say-top="{{ $ends['say_top'] }}" data-after="{{ $ends['after'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $ends['kick'] }}</span><span>{{ $ends['tag'] }}</span></p>
            <div class="cp-rg-ends-in">
                <div class="cp-dk-roll cp-rg-ends-roll">
                    <svg viewBox="0 0 600 240" preserveAspectRatio="none" aria-hidden="true">
                        <path d="M8 {{ $endY(60) }} H592" class="cp-dk-staff cp-dk-centre" />
                        @foreach ($lowEnd as [$m, $at, $long])
                            <rect x="{{ round(20 + $at * 26.5, 1) }}" y="{{ $endY($m) - 5 }}" width="{{ round($long * 26.5 - 3, 1) }}" height="10" rx="3" class="cp-rg-bar" data-rg-end="low-{{ $loop->index }}" />
                        @endforeach
                        @foreach ($highEnd as [$m, $at, $long])
                            <rect x="{{ round(330 + $at * 50, 1) }}" y="{{ $endY($m) - 5 }}" width="{{ round($long * 50 - 3, 1) }}" height="10" rx="3" class="cp-rg-bar" data-head data-rg-end="high-{{ $loop->index }}" />
                        @endforeach
                    </svg>
                </div>
                <p class="cp-rg-meter" aria-hidden="true"><b data-rg-note>{{ $ends['span'] }}</b><span data-rg-hz>{{ $ends['span_hz'] }}</span></p>
                <p class="cp-dk-btns">
                    @foreach (['low', 'high'] as $end)
                        <button type="button" class="cp-dk-play @if ($end === 'high') cp-dk-play-b @endif" data-rg-play="{{ $end }}" data-play="{{ $ends[$end] }}" data-stop="{{ $ends['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $ends[$end] }}</span></button>
                    @endforeach
                </p>
                <p class="cp-dk-say" role="status" data-rg-ends-say>{{ $ends['ready'] }}</p>
                <p class="cp-dk-key">
                    @foreach ((array) $ends['key'] as $key)
                        <span><i class="cp-rg-dkey cp-rg-dkey-{{ ['mid', 'chest', 'head'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                    @endforeach
                </p>
                <p class="cp-dk-note">{{ $ends['note'] }}</p>
            </div>
        </div>
    </section>

    @if ($outline !== null)
        <section class="cp-rg-climbsec" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-rg-kick">{{ $love['kicks']['climb'] }}</p>
                <div class="cp-rg-head">
                    <h2 id="units-h" class="cp-rg-title">{{ $page['titles']['units'] }}</h2>
                    <p class="cp-rg-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <ol class="cp-rg-units">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-rg-card cp-rg-unit" style="--rise: {{ $loop->index }}">
                            <p class="cp-rg-unit-n"><span class="cp-rg-unit-dot" aria-hidden="true">{{ $loop->iteration }}</span><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-rg-small">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            <ol class="cp-rg-lessons">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li><span>{{ $lesson }}</span>@if ($loop->parent->first && $loop->first)<a href="{{ $tryUrl }}" class="cp-rg-free">{{ __('pages.site.course_free') }}</a>@endif</li>
                                @endforeach
                            </ol>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="s-wrap s-sec cp-rg-first" aria-labelledby="try-h">
        <div>
            <p class="cp-rg-kick">{{ $love['kicks']['first'] }}</p>
            <h2 id="try-h" class="cp-rg-title">{{ $page['titles']['try'] }}</h2>
            <p class="cp-rg-body">{{ $page['try']['lead'] }}</p>
            <ol class="cp-rg-steps">
                @foreach ((array) $page['try']['steps'] as $step)
                    <li><span class="cp-rg-step-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $step }}</p></li>
                @endforeach
            </ol>
            <p class="cp-rg-note">{{ $page['try']['after'] }}</p>
        </div>
        <div class="cp-rg-card cp-rg-inside">
            <h2 id="inside-h" class="cp-rg-h3">{{ $page['titles']['inside'] }}</h2>
            <p class="cp-rg-small">{{ $page['measures'] }}</p>
            <p class="cp-rg-small"><x-site.say :text="$page['scoring']" /></p>
        </div>
    </section>

    <section class="s-wrap s-sec cp-rg-margin" aria-labelledby="facts-h">
        <p class="cp-rg-kick">{{ $love['kicks']['notes'] }}</p>
        <h2 id="facts-h" class="cp-rg-title">{{ $love['facts_title'] }}</h2>
        @php($facts = (array) $love['facts'])
        @foreach ([array_slice($facts, 0, 2), array_slice($facts, 2)] as $part)
            @if ($loop->last && ! $loop->first)
                <details class="cp-rg-more" data-rg-wide><summary><span>{{ $love['facts_more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
            @endif
            <ul class="cp-rg-facts">
                @foreach ($part as [$text, $source, $cites])
                    <li>
                        <p class="cp-rg-fact">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                        <p class="cp-rg-src">{{ $source }}</p>
                    </li>
                @endforeach
            </ul>
            @if ($loop->last && ! $loop->first)
                </details>
            @endif
        @endforeach
    </section>

    <section class="s-wrap s-sec cp-rg-kit" aria-labelledby="kit-h">
        <h2 id="kit-h" class="cp-rg-kick">{{ $love['kicks']['kit'] }}</h2>
        <div class="cp-rg-kit-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul class="cp-rg-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="cp-rg-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div class="cp-rg-safe"><h3>{{ $page['titles']['safety'] }}</h3><ul class="cp-rg-ticks">@foreach ((array) $page['safety'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-rg-fine" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-rg-title">{{ $page['titles']['faq'] }}</h2>
        @include('site.courses._faq')
    </section>

    <section class="cp-rg-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-rg-close-in">
            <p class="cp-rg-kick">{{ $love['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-rg-title">{{ $page['titles']['join'] }}</h2>
            <p class="cp-rg-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-join-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                @if ($open)
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @else
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @endif
            </p>
            @if ($open)
                <p class="cp-rg-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-rg-kick cp-rg-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-rg-sources" aria-labelledby="notes-h">
        <details data-rg-sources>
            <summary><h2 id="notes-h" class="cp-rg-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/range-and-registers.js"></script>
</main>
