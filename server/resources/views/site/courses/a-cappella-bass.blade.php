{{-- A cappella bass (/courses/a-cappella-bass), its own page: be the floor. Calm and light, after
     Vibrato's and Sight-singing's design (white cards on a stone wash, thin headlines, quiet tracked
     labels), in a deep teal with amber for a chord's notes. The hero is the words beside a bass C's
     first six harmonics drawn as rungs, spaced as the ear hears them: the widest at the foot is the
     floor, and harmonics 3 and 5 are the G and E that make the rest of its chord. Then the featured
     demo, Same notes, new root (js/public/a-cappella-bass.js): C4 and E4 held, alone, then over a bass
     C3, then A2, each chord lit as it sounds and each one a button. No mic. Then the story (the bass
     from Rameau to the Ravens), the outcomes, the units (from the course file), the science in plain
     words (with The note that isn't there: G2 from six harmonics, the 98 Hz taken away), notes in the
     margin and what the app listens for, before you begin, questions, the close and the sources. Every
     claim is from the research pack (docs/courses/a-cappella-bass-love-letter.md), cited. Low notes
     are built from their harmonics so a phone speaker sounds them. Game skin: the arcade's night,
     square frames with hard shadows, and PRESS START on the demo. No drawn people. Words:
     lang/en/site.php courses.pages.a-cappella-bass (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($tones = $love['overtones'])
@php($missing = $love['missing'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($counts = [':lessons' => (string) ($outline['lessons'] ?? ''), ':units' => (string) count($outline['units'] ?? [])])
{{-- The hero's rungs: harmonic n at a log height (an octave 100 high), its width falling as it
     weakens; 1 is the floor, 2 and 4 are C again, 3, 5 and 6 are the rest of the chord. --}}
@php($rungY = fn (int $n): float => round(286 - log($n, 2) * 100, 1))
@php($kinds = [1 => 'floor', 2 => 'octave', 3 => 'chord', 4 => 'octave', 5 => 'chord', 6 => 'chord'])
{{-- The demo's notes as MIDI, placed from A2 (45) at the foot to E4 (64) at the top. --}}
@php($place = fn (int $midi): float => round(($midi - 45) / 19, 3))
@php($basses = [null, 48, 45])
<main class="cp cp-acb">
    <section class="cp-hero cp-acb-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-acb-kick cp-eyebrow">{{ $outline !== null ? strtr($love['eyebrow'], $counts) : $page['eyebrow'] }}</p>
                <h1 id="page-h">{{ $page['hero_title'] }}</h1>
                <p class="s-lede">{{ $page['hero_lead'] }}</p>
                <p class="cp-hero-btns">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                </p>
                @unless ($open)
                    <p class="cp-acb-quiet">{{ __('pages.site.course_soon') }}</p>
                @endunless
                <ul class="cp-hero-facts">
                    @foreach ((array) $love['chips'] as $chip)
                        @if ($outline !== null || ! str_contains($chip, ':lessons'))
                            <li>{{ strtr($chip, $counts) }}</li>
                        @endif
                    @endforeach
                </ul>
            </div>
            <figure class="cp-hero-card cp-acb-tones">
                <p class="cp-acb-kick cp-acb-tones-h">{{ $tones['title'] }}</p>
                <svg viewBox="0 0 320 310" role="img" aria-label="{{ $tones['alt'] }}">
                    @foreach ($kinds as $n => $kind)
                        @php($y = $rungY($n))
                        @php($w = round(220 / sqrt($n)))
                        <text x="30" y="{{ $y + 5 }}" class="cp-acb-tone-n">{{ $n }}</text>
                        <path d="M58 {{ $y }} H{{ 58 + $w }}" class="cp-acb-rung cp-acb-rung-{{ $kind }}" />
                        <text x="{{ 58 + $w + 14 }}" y="{{ $y + 6 }}" class="cp-acb-tone-name cp-acb-tone-{{ $kind }}">{{ $tones['names'][$n - 1] }}</text>
                    @endforeach
                    <path d="M44 {{ $rungY(1) + 18 }} H300" class="cp-acb-ground" />
                </svg>
                <figcaption>
                    <ul class="cp-hero-legend">
                        <li><i class="cp-acb-key-floor" aria-hidden="true"></i>{{ $tones['floor'] }}</li>
                        <li><i class="cp-acb-key-octave" aria-hidden="true"></i>{{ $tones['octave'] }}</li>
                        <li><i class="cp-acb-key-chord" aria-hidden="true"></i>{{ $tones['chord'] }}</li>
                    </ul>
                    <p class="cp-acb-tones-note">{{ $tones['note'] }}@include('site.courses._cites', ['keys' => ['unsw']])</p>
                </figcaption>
            </figure>
        </div>
    </section>

    <section class="s-wrap cp-dk-demosec cp-acb-demosec" aria-labelledby="demo-h">
        <div class="cp-dk-demo cp-dk-screen cp-acb-demo" data-acb-demo
            data-ready="{{ $demo['ready'] }}" data-after="{{ $demo['after'] }}"
            data-say-0="{{ $demo['says'][0] }}" data-say-1="{{ $demo['says'][1] }}" data-say-2="{{ $demo['says'][2] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['lesson'] }}</span></p>
            <div class="cp-acb-stage">
                <ol class="cp-acb-chords" role="group" aria-label="{{ $demo['stage'] }}">
                    @foreach ((array) $demo['chords'] as [$name, $mood])
                        @php($bass = $basses[$loop->index])
                        <li>
                            <button type="button" class="cp-acb-chord" data-acb-chord="{{ $loop->index }}" aria-pressed="false">
                                <span class="cp-acb-col" aria-hidden="true">
                                    <span class="cp-acb-dot cp-acb-top" style="--p: {{ $place(64) }}">{{ $demo['top'][1] }}</span>
                                    <span class="cp-acb-dot cp-acb-top" style="--p: {{ $place(60) }}">{{ $demo['top'][0] }}</span>
                                    @if ($bass === null)
                                        <span class="cp-acb-dot cp-acb-none" style="--p: {{ $place(48) }}">{{ $demo['empty'] }}</span>
                                    @else
                                        <span class="cp-acb-dot cp-acb-bass" style="--p: {{ $place($bass) }}">{{ $demo['bass'][$loop->index] }}</span>
                                    @endif
                                </span>
                                <span class="cp-acb-name">{{ $name }}</span>
                                <span class="cp-acb-mood">{{ $mood }}</span>
                            </button>
                        </li>
                    @endforeach
                </ol>
                <button type="button" class="cp-dk-big cp-acb-big" data-acb-play aria-pressed="false"><span class="cp-dk-big-icon" aria-hidden="true"></span><span class="cp-acb-go"><x-site.skin :game="__('pages.site.demo_press_start')" :serious="$demo['play']" /></span><span class="cp-acb-stop">{{ $demo['stop'] }}</span></button>
            </div>
            <div class="cp-dk-caption">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}</p>
                </div>
                <div>
                    <p class="cp-dk-say cp-acb-say" role="status" data-acb-say>{{ $demo['ready'] }}</p>
                    <p class="cp-dk-note cp-acb-hz">{{ $demo['hz'] }}</p>
                    <p class="cp-dk-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-acb-story" aria-labelledby="story-h">
        <p class="cp-acb-kick">{{ $page['kicks']['story'] }}</p>
        <h2 id="story-h" class="cp-acb-title">{{ $page['titles']['story'] }}</h2>
        <ol class="cp-acb-years">
            @foreach ((array) $love['story'] as [$when, $title, $text, $cites])
                <li>
                    <p class="cp-acb-year">{{ $when }}</p>
                    <div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-acb-aimsec" aria-labelledby="outcomes-h">
        <p class="cp-acb-kick">{{ $page['kicks']['aims'] }}</p>
        <h2 id="outcomes-h" class="cp-acb-title">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-acb-aims">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-acb-aim-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    @if ($outline !== null)
        <section class="cp-acb-unitsec" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-acb-kick">{{ $page['kicks']['units'] }}</p>
                <div class="cp-acb-unithead">
                    <h2 id="units-h" class="cp-acb-title">{{ $page['titles']['units'] }}</h2>
                    <p class="cp-acb-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <ol class="cp-acb-units">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-acb-unit">
                            <p class="cp-acb-unit-n"><span class="cp-acb-unit-dot" aria-hidden="true">{{ $loop->iteration }}</span><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-acb-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            {{-- Unit 1's lessons always show; the others fold on a phone (open on a wide screen). --}}
                            <details class="cp-acb-fold" @if ($loop->first) open data-acb-always @else data-acb-wide @endif>
                                <summary>{{ __('pages.site.course_chip_lessons', ['n' => count($unit['lessons'])]) }}</summary>
                                <ol class="cp-acb-lessons">
                                    @foreach ($unit['lessons'] as $lesson)
                                        <li><span>{{ $lesson }}</span>@if ($loop->parent->first && $loop->first)<span class="cp-acb-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                                    @endforeach
                                </ol>
                            </details>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="s-wrap s-sec cp-acb-science" aria-labelledby="science-h">
        <p class="cp-acb-kick">{{ $page['kicks']['science'] }}</p>
        <h2 id="science-h" class="cp-acb-title">{{ $page['titles']['science'] }}</h2>
        <ul class="cp-acb-cards">
            @foreach ((array) $love['science'] as [$title, $text, $cites])
                @if ($loop->index === 2)
                    <li class="cp-acb-card cp-acb-missing" data-acb-missing data-after="{{ $missing['after'] }}">
                        <div>
                            <h3>{{ $missing['title'] }}</h3>
                            <p>{{ $missing['lead'] }}@include('site.courses._cites', ['keys' => ['jasa', 'libre']])</p>
                        </div>
                        <div class="cp-acb-missing-play">
                            <svg viewBox="0 0 300 130" class="cp-acb-spectrum" aria-hidden="true">
                                <path d="M10 100 H290" class="cp-acb-axis" />
                                @foreach (range(1, 6) as $n)
                                    @php($h = round(84 / sqrt($n)))
                                    <rect x="{{ $n * 44 - 14 }}" y="{{ 100 - $h }}" width="28" height="{{ $h }}" rx="4" @class(['cp-acb-bar', 'cp-acb-bar-fund' => $n === 1]) @if ($n === 1) data-acb-fund @endif />
                                    <text x="{{ $n * 44 }}" y="122" class="cp-acb-bar-hz">{{ $n * 98 }}</text>
                                @endforeach
                            </svg>
                            <p class="cp-acb-parts" aria-hidden="true"><span data-acb-part="with">{{ $missing['with'] }}</span><span data-acb-part="without">{{ $missing['without'] }}</span></p>
                            <p class="cp-acb-body cp-acb-try">{{ $missing['try'] }}</p>
                            <p class="cp-acb-btns"><button type="button" class="cp-acb-hear" data-acb-missing-play data-play="{{ $missing['play'] }}" data-stop="{{ $missing['stop'] }}" aria-pressed="false"><span class="cp-acb-hear-icon" aria-hidden="true"></span><span data-label>{{ $missing['play'] }}</span></button></p>
                            <p class="cp-acb-missing-say" role="status" data-acb-missing-say></p>
                        </div>
                    </li>
                @endif
                <li class="cp-acb-card">
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="cp-acb-band" aria-labelledby="margin-h">
        <div class="s-wrap s-sec cp-acb-band-grid">
            <div>
                <p class="cp-acb-kick">{{ $page['kicks']['margin'] }}</p>
                <h2 id="margin-h" class="cp-acb-title">{{ $page['titles']['margin'] }}</h2>
                <h3 class="cp-acb-kick cp-acb-inside-h">{{ $page['titles']['inside'] }}</h3>
                <p class="cp-acb-body">{{ $page['measures'] }}</p>
            </div>
            <ul class="cp-acb-margin">
                @foreach ((array) $love['margin'] as [$text, $cites])
                    <li><p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p></li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="s-wrap s-sec cp-acb-begin" aria-labelledby="begin-h">
        <p class="cp-acb-kick">{{ $page['kicks']['begin'] }}</p>
        <h2 id="begin-h" class="cp-acb-title">{{ $page['titles']['who'] }}</h2>
        <div class="cp-acb-begin-grid">
            <ul class="cp-acb-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="cp-acb-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div>
                <h3>{{ $page['titles']['safety'] }}</h3>
                <ul class="cp-acb-ticks">@foreach ((array) $page['safety'] as $item)<li><x-site.icon name="info" size="16" /><span>{{ $item }}</span></li>@endforeach</ul>
                <h3 class="cp-acb-songs-h">{{ __('pages.site.course_songs') }}</h3>
                <ul class="cp-acb-songs">
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b><span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-acb-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-acb-kick">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-acb-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-acb-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-acb-close-in">
            <p class="cp-acb-kick">{{ $page['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-acb-title">{{ $page['titles']['join'] }}</h2>
            <p class="cp-acb-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-join-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                @if ($open)
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @else
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @endif
            </p>
            @if ($open)
                <p class="cp-acb-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-acb-kick cp-acb-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-acb-sources" aria-labelledby="notes-h">
        <details data-acb-sources>
            <summary><h2 id="notes-h" class="cp-acb-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/a-cappella-bass.js"></script>
</main>
