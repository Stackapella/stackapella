{{-- Musical theatre (/courses/musical-theatre), its own page: every word to the back of the gallery.
     Calm and light, after Vibrato's design (white cards on a warm wash, thin headlines, quiet tracked
     labels), in theatre red and footlight gold. The hero is the words beside a stage in its own card:
     red curtains, footlights along its edge, and the Major-General's first fourteen syllables as lamps
     on two heights, a step apart, with a legend. Then the featured demo, Two notes, fourteen syllables
     (js/public/musical-theatre.js): the patter's first two lines on a stave in E flat, read from the
     Chappell vocal score and played an octave below the treble clef they're printed in, each syllable
     lit as it sounds, with a tempo slider and smeared or crisp. No mic. Then the story (borrowed tunes
     to the Savoy), the outcomes, the units (from the course file), the science in plain words,
     backstage (what the app hears, and the pack's surprising facts) on a dark band, the company,
     before you begin, questions, the close and the sources. Every claim is from the research pack
     (docs/courses/musical-theatre-love-letter.md), cited; all of it public domain. Game skin: the
     arcade's night, square frames with hard shadows, and PRESS START on the demo. No drawn people.
     Words: lang/en/site.php courses.pages.musical-theatre (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($lyric = (array) $demo['lyric'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($counts = [':lessons' => (string) ($outline['lessons'] ?? ''), ':units' => (string) count($outline['units'] ?? [])])
{{-- The hero's lamps: the first fourteen syllables, 1 on C and 0 on B flat. --}}
@php($lamps = [0, 0, 1, 0, 1, 0, 1, 0, 1, 0, 1, 0, 1, 0])
@php($lampX = fn (int $i): float => round(170 + $i * 252 / 13, 1))
@php($lampY = fn (int $high): int => $high === 1 ? 128 : 162)
{{-- The patter as printed (MIDI, written in the treble clef; js/public/musical-theatre.js plays it an
     octave lower), the pickup and four bars, read from the Chappell vocal score, No. 13, p. 61. --}}
@php($patter = [70, 70, 72, 70, 72, 70, 72, 70, 72, 70, 72, 70, 72, 70, 67, 63, 58, 68, 70, 68, 70, 68, 70, 68, 70, 68, 70, 68, 70, 68, 65, 62, 58])
{{-- A note's stave step, counted from the bottom line (E4) up, a line or a space each. --}}
@php($steps = [58 => -3, 62 => -1, 63 => 0, 65 => 1, 67 => 2, 68 => 3, 70 => 4, 72 => 5])
{{-- Four staves: [first note, notes]; the first has the pickup "I" before its bar line. --}}
@php($systems = [[0, 9], [9, 8], [17, 8], [25, 8]])
@php($head = 58)
@php($gap = 34)
@php($noteY = fn (int $midi): int => 70 - $steps[$midi] * 5)
<main class="cp cp-mt">
    <section class="cp-hero cp-mt-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-mt-kick cp-eyebrow">{{ $outline !== null ? strtr($love['eyebrow'], $counts) : $page['eyebrow'] }}</p>
                <h1 id="page-h">{{ $page['hero_title'] }}</h1>
                <p class="s-lede">{{ $page['hero_lead'] }}</p>
                <p class="cp-hero-btns">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    @if ($outline !== null)
                        <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                    @endif
                </p>
                @unless ($open)
                    <p class="cp-mt-quiet">{{ __('pages.site.course_soon') }}</p>
                @endunless
                <ul class="cp-hero-facts">
                    @foreach ((array) $love['chips'] as $chip)
                        @if ($outline !== null || ! str_contains($chip, ':lessons'))
                            <li>{{ strtr($chip, $counts) }}</li>
                        @endif
                    @endforeach
                </ul>
            </div>
            <figure class="cp-hero-card cp-mt-stagecard">
                <svg viewBox="32 12 536 316" class="cp-mt-stage-svg" role="img" aria-label="{{ $love['stage_alt'] }}">
                    <defs>
                        <radialGradient id="mt-spot" cx="50%" cy="38%" r="60%"><stop offset="0" class="cp-mt-spot-1" /><stop offset="1" class="cp-mt-spot-2" /></radialGradient>
                        <linearGradient id="mt-glow" x1="0" y1="1" x2="0" y2="0"><stop offset="0" class="cp-mt-glow-1" /><stop offset="1" class="cp-mt-glow-2" /></linearGradient>
                    </defs>
                    <rect x="56" y="36" width="488" height="262" fill="url(#mt-spot)" />
                    <path d="M100 240 H500 L544 298 H56 Z" class="cp-mt-floor" />
                    <path d="M150 240 L120 298 M200 240 L184 298 M250 240 L242 298 M300 240 V298 M350 240 L358 298 M400 240 L416 298 M450 240 L480 298 M80 268 H520" class="cp-mt-boards" />
                    <rect x="56" y="196" width="488" height="102" fill="url(#mt-glow)" />
                    <path d="M160 {{ $lampY(1) }} H432 M160 {{ $lampY(0) }} H432" class="cp-mt-guide" />
                    <polyline points="{{ implode(' ', array_map(fn (int $high, int $i): string => $lampX($i).','.$lampY($high), $lamps, array_keys($lamps))) }}" class="cp-mt-thread" />
                    @foreach ($lamps as $high)
                        <circle cx="{{ $lampX($loop->index) }}" cy="{{ $lampY($high) }}" r="7" class="cp-mt-lamp" style="--i: {{ $loop->index }}" />
                    @endforeach
                    <text x="440" y="{{ $lampY(1) + 5 }}" class="cp-mt-pitch">{{ $love['stage']['high'] }}</text>
                    <text x="440" y="{{ $lampY(0) + 5 }}" class="cp-mt-pitch">{{ $love['stage']['low'] }}</text>
                    <g class="cp-mt-curtain">
                        <path d="M56 36 H150 C140 100 120 160 96 210 C106 234 92 270 56 298 Z" />
                        <path d="M544 36 H450 C460 100 480 160 504 210 C494 234 508 270 544 298 Z" />
                    </g>
                    <path d="M80 40 C92 110 88 180 74 280 M106 40 C108 100 98 160 86 220 M130 40 C124 96 110 150 96 196 M520 40 C508 110 512 180 526 280 M494 40 C492 100 502 160 514 220 M470 40 C476 96 490 150 504 196" class="cp-mt-folds" />
                    <path d="M56 36 H544 V60 Q526 76 508 60 Q490 76 472 60 Q454 76 436 60 Q418 76 400 60 Q382 76 364 60 Q346 76 328 60 Q310 76 292 60 Q274 76 256 60 Q238 76 220 60 Q202 76 184 60 Q166 76 148 60 Q130 76 112 60 Q94 76 76 60 Q66 70 56 60 Z" class="cp-mt-valance" />
                    <rect x="40" y="298" width="520" height="22" class="cp-mt-apron" />
                    @foreach (range(0, 10) as $i)
                        <circle cx="{{ 90 + $i * 42 }}" cy="298" r="5.5" class="cp-mt-foot" />
                    @endforeach
                    <path d="M40 320 V58 Q40 20 78 20 H522 Q560 20 560 58 V320" class="cp-mt-arch" />
                </svg>
                <figcaption>
                    <ul class="cp-hero-legend">
                        <li><i class="cp-mt-key-lamp" aria-hidden="true"></i>{{ $love['stage']['lamp'] }}</li>
                        <li><i class="cp-mt-key-two" aria-hidden="true"></i>{{ $love['stage']['two'] }}</li>
                    </ul>
                    <p class="cp-mt-stage-note">{{ $love['stage_note'] }}@include('site.courses._cites', ['keys' => ['score']])</p>
                </figcaption>
            </figure>
        </div>
    </section>

    <section class="s-wrap cp-dk-demosec cp-mt-demosec" aria-labelledby="demo-h">
        <div class="cp-dk-demo cp-dk-screen cp-mt-demo" data-mt-demo data-style="legato" data-notes="{{ json_encode($patter) }}"
            data-ready="{{ $demo['ready'] }}" data-legato="{{ $demo['playing_legato'] }}" data-crisp="{{ $demo['playing_crisp'] }}" data-after="{{ $demo['after'] }}" data-rate="{{ $demo['rate'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['lesson'] }}</span></p>
            <div class="cp-mt-stage">
                <p class="cp-mt-keyname">{{ $demo['key'] }}</p>
                <div class="cp-mt-systems" role="group" aria-label="{{ $demo['stave'] }}">
                    @foreach ($systems as [$from, $count])
                        @php($wide = $head + $count * $gap + 8)
                        <div class="cp-mt-system" style="--w: {{ $wide }}">
                            <svg viewBox="0 0 {{ $wide }} 104" aria-hidden="true">
                                <path d="M0 30 H{{ $wide }} M0 40 H{{ $wide }} M0 50 H{{ $wide }} M0 60 H{{ $wide }} M0 70 H{{ $wide }}" class="cp-mt-line" />
                                <path d="M{{ $wide - 1 }} 30 V70" class="cp-mt-barline" />
                                <path d="M10.6 58 C8 60 5 58.6 5.4 55.6 C5.8 53 9 52.4 10 54.6 M10.6 58 C12.4 56 12.2 50 11.6 44 L9.2 4 C8.8 -3 10.2 -11 13.6 -12.4 C16.6 -13.4 16.8 -6 14.2 -1.6 C11 4 3 9.6 2.4 21 C1.8 32 7 40.6 13.4 40.4 C19.4 40.2 21.4 33.6 20.2 28.8 C18.8 23.6 12.6 22.4 10 26 C7.8 29.2 9.4 32.6 12.6 32.6" transform="translate(4 30)" class="cp-mt-clef" />
                                <text x="15" y="101" class="cp-mt-eight">8</text>
                                @foreach ([[30, 50], [38, 35], [46, 55]] as [$fx, $fy])
                                    <path d="M{{ $fx }} {{ $fy - 11 }} V{{ $fy + 3 }} C{{ $fx + 7 }} {{ $fy - 1 }} {{ $fx + 6 }} {{ $fy - 6 }} {{ $fx }} {{ $fy - 2 }}" class="cp-mt-flat" />
                                @endforeach
                                @if ($from === 0)
                                    <path d="M{{ $head + $gap }} 30 V70" class="cp-mt-barline" />
                                @endif
                                {{-- Beamed in fours (the pickup on its own, with a flag), stems up. --}}
                                @php($beams = $from === 0 ? [[0, 1], [1, 4], [5, 4]] : [[0, 4], [4, 4]])
                                @foreach ($beams as [$b, $len])
                                    @php($ys = array_map($noteY, array_slice($patter, $from + $b, $len)))
                                    @php($top = min($ys) - 26)
                                    @foreach (range($b, $b + $len - 1) as $k)
                                        @php($x = $head + $k * $gap + 17)
                                        <path d="M{{ $x + 5 }} {{ $noteY($patter[$from + $k]) - 1 }} V{{ $top }}" class="cp-mt-stem" />
                                    @endforeach
                                    @if ($len > 1)
                                        <path d="M{{ $head + $b * $gap + 21 }} {{ $top }} H{{ $head + ($b + $len - 1) * $gap + 23.4 }}" class="cp-mt-beam" />
                                    @else
                                        <path d="M{{ $head + $b * $gap + 22 }} {{ $top }} C{{ $head + $b * $gap + 26 }} {{ $top + 6 }} {{ $head + $b * $gap + 32 }} {{ $top + 10 }} {{ $head + $b * $gap + 28 }} {{ $top + 20 }}" class="cp-mt-flag" />
                                    @endif
                                @endforeach
                                @foreach (range(0, $count - 1) as $k)
                                    @php($x = $head + $k * $gap + 17)
                                    @php($midi = $patter[$from + $k])
                                    @if ($steps[$midi] < -1)
                                        <path d="M{{ $x - 9 }} 80 H{{ $x + 9 }}" class="cp-mt-line" />
                                    @endif
                                    <ellipse cx="{{ $x }}" cy="{{ $noteY($midi) }}" rx="5.8" ry="4.1" transform="rotate(-20 {{ $x }} {{ $noteY($midi) }})" class="cp-mt-head" data-mt-note="{{ $from + $k }}" />
                                @endforeach
                            </svg>
                            <p class="cp-mt-words" style="grid-template-columns: {{ $head }}fr repeat({{ $count }}, {{ $gap }}fr) 8fr" aria-hidden="true">
                                <span></span>
                                @foreach (range($from, $from + $count - 1) as $n)
                                    <span data-mt-note="{{ $n }}">{{ $lyric[$n] ?? '' }}</span>
                                @endforeach
                            </p>
                        </div>
                    @endforeach
                </div>
                <p class="sr-only">{{ implode(' ', $lyric) }}</p>
                <button type="button" class="cp-dk-big cp-mt-big" data-mt-play aria-pressed="false"><span class="cp-dk-big-icon" aria-hidden="true"></span><span class="cp-mt-go"><x-site.skin :game="__('pages.site.demo_press_start')" :serious="$demo['play']" /></span><span class="cp-mt-stop">{{ $demo['stop'] }}</span></button>
            </div>
            <div class="cp-dk-caption">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}@include('site.courses._cites', ['keys' => ['score']])</p>
                </div>
                <div>
                    <label class="cp-dk-slider cp-mt-tempo">
                        <span>{{ $demo['tempo'] }} <output data-mt-rate-out>{{ str_replace(':n', '3', $demo['rate']) }}</output></span>
                        <input type="range" min="2" max="7" step="0.5" value="3" data-mt-rate>
                        <span class="cp-mt-ends" aria-hidden="true"><span>{{ $demo['slow'] }}</span><span>{{ $demo['fast'] }}</span></span>
                    </label>
                    <fieldset class="cp-dk-pick cp-mt-style">
                        <legend>{{ $demo['style'] }}</legend>
                        @foreach (['legato', 'crisp'] as $style)
                            <button type="button" class="cp-dk-chip cp-mt-chip" data-mt-style="{{ $style }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><span class="cp-mt-tick" aria-hidden="true"></span>{{ $demo[$style] }}</button>
                        @endforeach
                    </fieldset>
                    <p class="cp-dk-say" role="status" data-mt-say>{{ $demo['ready'] }}</p>
                    <p class="cp-dk-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-mt-story" aria-labelledby="story-h">
        <div>
            <p class="cp-mt-kick">{{ $page['kicks']['story'] }}</p>
            <h2 id="story-h" class="cp-mt-title">{{ $page['titles']['story'] }}</h2>
        </div>
        <div>
            @php($years = (array) $love['story'])
            @foreach ([array_slice($years, 0, 2), array_slice($years, 2)] as $part)
                @if (! $loop->first)
                    <details class="cp-mt-more" data-mt-wide><summary>{{ $love['more']['story'] }}</summary>
                @endif
                <ol class="cp-mt-years">
                    @foreach ($part as [$when, $title, $text, $cites])
                        <li>
                            <p class="cp-mt-year">{{ $when }}</p>
                            <div>
                                <h3>{{ $title }}</h3>
                                <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                @if (! $loop->first)
                    </details>
                @endif
            @endforeach
        </div>
    </section>

    <section class="s-wrap s-sec cp-mt-aimsec" aria-labelledby="outcomes-h">
        <p class="cp-mt-kick">{{ $page['kicks']['aims'] }}</p>
        <h2 id="outcomes-h" class="cp-mt-title">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-mt-aims">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-mt-aim-n" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    @if ($outline !== null)
        <section class="cp-mt-unitsec" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-mt-kick">{{ $page['kicks']['units'] }}</p>
                <div class="cp-mt-unithead">
                    <h2 id="units-h" class="cp-mt-title">{{ $page['titles']['units'] }}</h2>
                    <p class="cp-mt-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <ol class="cp-mt-units">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-mt-unit">
                            <p class="cp-mt-unit-n"><span class="cp-mt-unit-dot" aria-hidden="true">{{ $loop->iteration }}</span><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-mt-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            {{-- Unit 1's lessons always show; the others fold on a phone (open on a wide screen). --}}
                            <details class="cp-mt-fold" @if ($loop->first) open data-mt-always @else data-mt-wide @endif>
                                <summary>{{ __('pages.site.course_chip_lessons', ['n' => count($unit['lessons'])]) }}</summary>
                                <ol class="cp-mt-lessons">
                                    @foreach ($unit['lessons'] as $lesson)
                                        <li><span>{{ $lesson }}</span>@if ($loop->parent->first && $loop->first)<span class="cp-mt-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                                    @endforeach
                                </ol>
                            </details>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="s-wrap s-sec cp-mt-science" aria-labelledby="science-h">
        <p class="cp-mt-kick">{{ $page['kicks']['science'] }}</p>
        <h2 id="science-h" class="cp-mt-title">{{ $page['titles']['science'] }}</h2>
        @php($cards = (array) $love['science'])
        @foreach ([array_slice($cards, 0, 2), array_slice($cards, 2)] as $part)
            @if (! $loop->first)
                <details class="cp-mt-more" data-mt-wide><summary>{{ $love['more']['science'] }}</summary>
            @endif
            <ul class="cp-mt-cards">
                @foreach ($part as [$title, $text, $cites])
                    <li class="cp-mt-card">
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    </li>
                @endforeach
                @if (! $loop->first)
                    <li class="cp-mt-card cp-mt-card-ours">
                        <h3>{{ $love['science_course'][0] }}</h3>
                        <p>{{ $love['science_course'][1] }}</p>
                    </li>
                @endif
            </ul>
            @if (! $loop->first)
                </details>
            @endif
        @endforeach
    </section>

    <section class="cp-mt-band" aria-labelledby="margin-h">
        <div class="s-wrap s-sec cp-mt-band-grid">
            <div>
                <p class="cp-mt-kick">{{ $page['kicks']['margin'] }}</p>
                <h2 id="margin-h" class="cp-mt-title">{{ $page['titles']['margin'] }}</h2>
                <h3 class="cp-mt-kick cp-mt-inside-h">{{ $page['titles']['inside'] }}</h3>
                <p class="cp-mt-body">{{ $page['measures'] }}</p>
            </div>
            <div>
                @php($notes = (array) $love['margin'])
                <ul class="cp-mt-margin">
                    @foreach (array_slice($notes, 0, 2) as [$text, $cites])
                        <li><p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p></li>
                    @endforeach
                </ul>
                <details class="cp-mt-more" data-mt-wide><summary>{{ $love['more']['margin'] }}</summary>
                    <ul class="cp-mt-margin">
                        @foreach (array_slice($notes, 2) as [$text, $cites])
                            <li><p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p></li>
                        @endforeach
                    </ul>
                </details>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-mt-companysec" aria-labelledby="company-h">
        <p class="cp-mt-kick">{{ $page['kicks']['company'] }}</p>
        <h2 id="company-h" class="cp-mt-title">{{ $page['titles']['company'] }}</h2>
        @php($cast = (array) $love['company'])
        @foreach ([array_slice($cast, 0, 3), array_slice($cast, 3)] as $part)
            @if (! $loop->first)
                <details class="cp-mt-more" data-mt-wide><summary>{{ $love['more']['company'] }}</summary>
            @endif
            <ol class="cp-mt-cast">
                @foreach ($part as [$name, $years, $what, $cites])
                    <li>
                        <p class="cp-mt-cast-name"><b>{{ $name }}</b><span>{{ $years }}</span></p>
                        <p class="cp-mt-cast-what">{{ $what }}@include('site.courses._cites', ['keys' => $cites])</p>
                    </li>
                @endforeach
            </ol>
            @if (! $loop->first)
                </details>
            @endif
        @endforeach
    </section>

    <section class="s-wrap s-sec cp-mt-begin" aria-labelledby="begin-h">
        <p class="cp-mt-kick">{{ $page['kicks']['begin'] }}</p>
        <h2 id="begin-h" class="cp-mt-title">{{ $page['titles']['who'] }}</h2>
        <div class="cp-mt-begin-grid">
            <ul class="cp-mt-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul>
            <div>
                <h3>{{ $page['titles']['safety'] }}</h3>
                <ul class="cp-mt-ticks">@foreach ((array) $page['safety'] as $item)<li><x-site.icon name="info" size="16" /><span>{{ $item }}</span></li>@endforeach</ul>
            </div>
            {{-- What you need and the songs fold on a phone (open on a wide screen). --}}
            <div>
                <details class="cp-mt-col" data-mt-wide><summary><h3>{{ $page['titles']['need'] }}</h3></summary><ul class="cp-mt-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></details>
                <details class="cp-mt-col cp-mt-songs-h" data-mt-wide><summary><h3>{{ __('pages.site.course_songs') }}</h3></summary>
                    <ul class="cp-mt-songs">
                        @foreach ((array) $page['songs'] as [$song, $credit])
                            <li><b translate="no">{{ $song }}</b><span>{{ $credit }}</span></li>
                        @endforeach
                    </ul>
                </details>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-mt-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-mt-kick">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-mt-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-mt-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-mt-close-in">
            <p class="cp-mt-kick">{{ $page['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-mt-title">{{ $page['titles']['join'] }}</h2>
            <p class="cp-mt-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-join-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                @if ($open)
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @else
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @endif
            </p>
            @if ($open)
                <p class="cp-mt-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-mt-kick cp-mt-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-mt-sources" aria-labelledby="notes-h">
        <details data-mt-sources>
            <summary><h2 id="notes-h" class="cp-mt-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/musical-theatre.js"></script>
</main>
