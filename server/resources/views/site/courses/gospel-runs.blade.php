{{-- Gospel and soul runs (/courses/gospel-runs), its own page, to Hillel's design of 2026-11-18: set like an 1870s
     songbook (Libre Caslon headings with italic second halves, a drop cap, numbers and labels in the system monospace).
     The hero is the major pentatonic as stairs from C4 to C5, F and B left out as dashed gaps: tap a stair to hear it,
     or hear lesson 1's run (la, so, mi, re, do, two a second, over its Amen chords) go down them. Then the featured demo
     (one run, three speeds), the outcomes stepping down, a melisma shown, why fast runs smear with the run sung clean
     and then smeared, the blue note's bend over a drone (a working slider), the archive on music staves (a note-head
     climbing to "A little higher"), the units as a staircase up from I to IV, lesson 1 set out as lining out, margin
     notes, before you begin, questions, a dark close with the stairs coming down beside it, and the sources. Every
     claim keeps its numbered notes (docs/courses/gospel-runs-love-letter.md). The drawings' notes and places come from
     App\Support\Site\GospelRunsArt; every sound only when pressed, on the audio clock (js/public/gospel-runs.js). The
     spirituals and gospel as Black American sacred music: standard words, no imitation of anyone, no drawn people.
     Game skin: the arcade's night, frames and type. Words: lang/en/site.php courses.pages.gospel-runs (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($heads = $love['heads'])
@php($demo = $love['demo'])
@php($plain = $love['plain'])
@php($smear = $love['smear'])
@php($blue = $love['blue'])
@php($stairs = \App\Support\Site\GospelRunsArt::stairs())
@php($run = \App\Support\Site\GospelRunsArt::run())
@php($deck = \App\Support\Site\GospelRunsArt::speeds())
@php($smeared = \App\Support\Site\GospelRunsArt::smear())
@php($bend = \App\Support\Site\GospelRunsArt::bend($blue['marks']))
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
<main class="cp cp-gospel" data-gr>
    <section class="cp-gr-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-gr-hero-in">
            <div class="cp-gr-hero-top">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-gr-chip"><b>{{ __('pages.site.course_chip_free') }}</b>@if ($outline !== null)<span>{{ __('site.courses.pages.gospel-runs.love.hero.size', ['lessons' => $outline['lessons'], 'units' => count($outline['units'])]) }}</span>@endif</p>
            </div>
            <div class="cp-gr-stage">
                <div class="cp-gr-hero-copy">
                    <p class="cp-gr-eyebrow">{{ $love['hero']['kick'] }}</p>
                    <h1 id="page-h" class="cp-gr-h1">{{ $love['hero']['title'][0] }} <em>{{ $love['hero']['title'][1] }}</em></h1>
                </div>
                <div class="cp-gr-stairs" data-gr-stairs>
                    @foreach ($stairs as $stair)
                        @if ($stair['gap'])
                            <div class="cp-gr-gap" style="--rise: {{ $stair['rise'] }}" role="img" aria-label="{{ __('site.courses.pages.gospel-runs.love.stairs.gap', ['sol' => $love['stairs']['sol'][$loop->index], 'note' => $love['stairs']['notes'][$loop->index]]) }}">
                                <i aria-hidden="true">{{ $love['stairs']['sol'][$loop->index] }}</i><span aria-hidden="true">{{ $love['stairs']['notes'][$loop->index] }}<span class="cp-gr-off">{{ $love['stairs']['left_out'] }}</span></span>
                            </div>
                        @else
                            <button type="button" class="cp-gr-stair" style="--rise: {{ $stair['rise'] }}" data-gr-stair="{{ $stair['midi'] }}" aria-label="{{ __('site.courses.pages.gospel-runs.love.stairs.label', ['sol' => $love['stairs']['sol'][$loop->index], 'note' => $love['stairs']['notes'][$loop->index]]) }}">
                                <i aria-hidden="true">{{ $love['stairs']['sol'][$loop->index] }}</i><span aria-hidden="true">{{ $love['stairs']['notes'][$loop->index] }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
        <div class="cp-gr-band">
            <div class="s-wrap cp-gr-band-in">
                <button type="button" class="cp-gr-btn cp-gr-btn-gold" data-gr-play="stairs" data-gr-run="{{ $run['data'] }}" data-play="{{ $love['hero']['play'] }}" data-stop="{{ $love['hero']['stop'] }}" aria-pressed="false"><span class="cp-gr-btn-icon" aria-hidden="true"></span><span data-label>{{ $love['hero']['play'] }}</span></button>
                <p class="cp-gr-band-note">{{ $love['hero']['tap'] }} {{ $love['stairs']['note'] }}@include('site.courses._cites', ['keys' => ['jubilee']]) <span class="cp-gr-band-key">{{ $love['hero']['key'] }}</span></p>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-gr-intro" aria-label="{{ $page['name'] }}">
        <p class="cp-gr-lead">{{ $page['hero_lead'] }}</p>
        <div class="cp-gr-intro-side">
            <ul class="cp-gr-diamonds">
                @foreach ((array) $page['hero_facts'] as $fact)
                    <li>{{ $fact }}</li>
                @endforeach
            </ul>
            <p class="cp-gr-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <a href="{{ $outline !== null ? '#units-h' : '#outcomes-h' }}" class="btn-secondary btn-lg">{{ $outline !== null ? $page['see_lessons'] : $page['see_aims'] }}</a>
            </p>
            @unless ($open)
                <p class="cp-gr-note">{{ __('pages.site.course_soon') }}</p>
            @endunless
        </div>
    </section>

    <section class="s-wrap cp-gr-demosec" aria-labelledby="demo-h">
        <div class="cp-gr-deck" data-gr-demo data-say="{{ $demo['say'] }}" data-after="{{ $demo['after'] }}">
            <p class="cp-gr-deck-bar"><span class="cp-gr-live"><i aria-hidden="true"></i>{{ $demo['live'] }}</span><span>{{ $demo['tag'] }}</span></p>
            <div class="cp-gr-panels">
                @foreach ((array) $demo['speeds'] as $g => $speed)
                    <div class="cp-gr-panel" data-gr-speed="{{ $g }}">
                        <svg viewBox="0 0 300 176" aria-hidden="true">
                            @foreach ($deck['rows'] as $y)
                                <path d="M0 {{ $y }} H300" class="cp-gr-row" />
                            @endforeach
                            <polyline points="{{ $deck['line'] }}" class="cp-gr-through" />
                            @foreach ($deck['notes'] as $k => $note)
                                <rect x="{{ $note['x'] }}" y="{{ $note['y'] }}" width="36" height="16" rx="4" class="cp-gr-note" data-gr-step="{{ $g }}-{{ $k }}" />
                            @endforeach
                        </svg>
                        <p class="cp-gr-panel-foot"><span>{{ $speed }}</span><span>{{ $demo['ms'][$g] }}</span></p>
                    </div>
                @endforeach
            </div>
            <div class="cp-gr-deck-cap">
                <div>
                    <h2 id="demo-h" class="cp-gr-h cp-gr-h-deck">{{ $heads['demo'][0] }} <em>{{ $heads['demo'][1] }}</em></h2>
                    <p class="cp-gr-deck-lead">{{ $demo['lead'] }}</p>
                    <button type="button" class="cp-gr-btn cp-gr-btn-gold" data-gr-play="run" data-play="{{ $demo['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-gr-btn-icon" aria-hidden="true"></span><span data-label>{{ $demo['play'] }}</span></button>
                    <p class="cp-gr-say" role="status" data-gr-say>{{ $demo['ready'] }}</p>
                </div>
                <div>
                    <p class="cp-gr-deck-text">{{ $demo['how'] }}@include('site.courses._cites', ['keys' => ['xu']])</p>
                    <p class="cp-gr-key">
                        @foreach ((array) $demo['key'] as $key)
                            <span><i class="cp-gr-swatch cp-gr-swatch-{{ $loop->index }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-gr-deck-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-gr-aims" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="cp-gr-kick">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-gr-goals">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-gr-plain" aria-labelledby="plain-h">
        <p class="cp-gr-kick">{{ $love['kicks']['plain'] }}</p>
        <h2 id="plain-h" class="cp-gr-h">{{ $heads['plain'][0] }} <em>{{ $heads['plain'][1] }}</em></h2>
        <figure class="cp-gr-melisma">
            <p class="cp-gr-mel" aria-hidden="true"><span class="cp-gr-mel-words">{{ $love['melisma']['words'] }}</span> <span>{{ $love['melisma']['syllables'] }}</span>@foreach ($run['steps'] as $step)<b style="--step: {{ $step }}">{{ $love['melisma']['vowel'] }}</b>@endforeach<span class="cp-gr-mel-end">{{ $love['melisma']['end'] }}</span></p>
            <figcaption>{{ $love['melisma']['note'] }}</figcaption>
        </figure>
        <div class="cp-gr-two">
            <p class="cp-gr-body">{{ $plain['lead'] }}@include('site.courses._cites', ['keys' => ['jukebox', 'jubilee']])</p>
            <p class="cp-gr-body">{{ $plain['pent'] }}@include('site.courses._cites', ['keys' => ['gill']])</p>
        </div>

        <div class="cp-gr-pair">
            {{-- Why fast runs smear beside the smear deck (the page's length pass, 2026-11-19); the words first on a phone. --}}
            <div class="cp-gr-pair-text">
                <h3 class="cp-gr-h3">{{ $plain['blur_title'] }}</h3>
                <p class="cp-gr-small">{{ $plain['blur'] }}@include('site.courses._cites', ['keys' => ['xu']])</p>
                <ol class="cp-gr-nums">
                    @foreach ((array) $plain['numbers'] as [$big, $unit, $what, $text, $cites])
                        <li class="cp-gr-num">
                            <p class="cp-gr-big">{{ $big }}<small>{{ $unit }}</small></p>
                            <h4>{{ $what }}</h4>
                            <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                        </li>
                    @endforeach
                </ol>
            </div>
            <div class="cp-gr-deck cp-gr-smear" data-gr-smear data-say-clean="{{ $smear['say_clean'] }}" data-say-smear="{{ $smear['say_smear'] }}" data-after="{{ $smear['after'] }}">
                <p class="cp-gr-deck-bar"><span class="cp-gr-live"><i aria-hidden="true"></i>{{ $smear['kick'] }}</span><span>{{ $smear['tag'] }}</span></p>
                <div class="cp-gr-panels cp-gr-panels-two">
                    @foreach ($smeared['passes'] as $kind => $segments)
                        <div class="cp-gr-panel" data-gr-pass="{{ $kind }}">
                            <svg viewBox="0 0 240 200" aria-hidden="true">
                                @foreach ($smeared['rows'] as $y)
                                    <path d="M0 {{ $y }} H240" class="cp-gr-row" />
                                @endforeach
                                @foreach ($segments as $k => $d)
                                    <path d="{{ $d }}" class="cp-gr-seg" data-gr-seg="{{ $kind }}-{{ $k }}" />
                                @endforeach
                            </svg>
                            <p class="cp-gr-panel-name">{{ $smear['labels'][$loop->index] }}</p>
                        </div>
                    @endforeach
                </div>
                <div class="cp-gr-deck-foot">
                    <button type="button" class="cp-gr-btn cp-gr-btn-line" data-gr-play="smear" data-play="{{ $smear['play'] }}" data-stop="{{ $smear['stop'] }}" aria-pressed="false"><span class="cp-gr-btn-icon" aria-hidden="true"></span><span data-label>{{ $smear['play'] }}</span></button>
                    <p class="cp-gr-say" role="status" data-gr-smear-say>{{ $smear['ready'] }}</p>
                    <p class="cp-gr-key">
                        @foreach ((array) $smear['key'] as $key)
                            <span><i class="cp-gr-swatch cp-gr-swatch-line{{ $loop->index }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-gr-deck-note">{{ $smear['note'] }}</p>
                </div>
            </div>
        </div>
        <h3 class="cp-gr-h3 cp-gr-slow-h">{{ $heads['slow'][0] }} <em>{{ $heads['slow'][1] }}</em></h3>
        <div class="cp-gr-two cp-gr-slow">
            <p class="cp-gr-body">{{ $plain['slow'] }}@include('site.courses._cites', ['keys' => ['furuya']])</p>
            <p class="cp-gr-body">{{ $plain['correct'] }}@include('site.courses._cites', ['keys' => ['duke']])</p>
        </div>
    </section>

    <section class="cp-gr-blue" aria-labelledby="blue-h">
        <div class="s-wrap cp-gr-blue-in">
            <div>
                <p class="cp-gr-kick">{{ $love['kicks']['blue'] }}</p>
                <h2 id="blue-h" class="cp-gr-h">{{ $heads['blue'][0] }} <em>{{ $heads['blue'][1] }}</em></h2>
                <p class="cp-gr-body">{{ $blue['lead'] }}@include('site.courses._cites', ['keys' => ['rochester', 'temperley']])</p>
                <p class="cp-gr-body">{{ $blue['measured'] }}@include('site.courses._cites', ['keys' => ['cutting']])</p>
            </div>
            <div class="cp-gr-deck cp-gr-bend" data-gr-bend data-cents="{{ $blue['cents'] }}" data-hz="{{ $blue['hz'] }}"
                data-say-bend="{{ $blue['say_bend'] }}" data-say-hold="{{ $blue['say_hold'] }}" data-after="{{ $blue['after'] }}">
                <p class="cp-gr-deck-bar"><span class="cp-gr-live"><i aria-hidden="true"></i>{{ $blue['kick'] }}</span><span>{{ $blue['tag'] }}</span></p>
                <div class="cp-gr-bend-in">
                    <svg viewBox="0 0 500 220" class="cp-gr-lane" aria-hidden="true">
                        @foreach ($bend['marks'] as $y)
                            <path d="M16 {{ $y }} H484" class="cp-gr-mark cp-gr-mark-{{ $loop->index }}" />
                        @endforeach
                        <path d="{{ $bend['path'] }}" class="cp-gr-bendline" data-gr-bendline />
                    </svg>
                    <p class="cp-gr-meter" aria-hidden="true"><b data-gr-cents>{{ __('site.courses.pages.gospel-runs.love.blue.cents', ['n' => $bend['cents']]) }}</b><span data-gr-hz>{{ __('site.courses.pages.gospel-runs.love.blue.hz', ['n' => $bend['hz']]) }}</span></p>
                    <label class="cp-gr-slider"><span>{{ $blue['slider'] }} <output data-gr-bend-out>{{ __('site.courses.pages.gospel-runs.love.blue.cents', ['n' => $bend['cents']]) }}</output></span><input type="range" min="300" max="400" step="1" value="{{ $bend['cents'] }}" data-gr-bend-to></label>
                    <button type="button" class="cp-gr-btn cp-gr-btn-line" data-gr-play="bend" data-play="{{ $blue['play'] }}" data-stop="{{ $blue['stop'] }}" aria-pressed="false"><span class="cp-gr-btn-icon" aria-hidden="true"></span><span data-label>{{ $blue['play'] }}</span></button>
                    <p class="cp-gr-say" role="status" data-gr-bend-say>{{ $blue['ready'] }}</p>
                    <ul class="cp-gr-marks">
                        @foreach ((array) $blue['marks'] as [$cents, $label])
                            <li><i class="cp-gr-mkey cp-gr-mkey-{{ $loop->index }}" aria-hidden="true"></i><b>{{ $cents }}</b><span>{{ $label }}</span></li>
                        @endforeach
                    </ul>
                    <p class="cp-gr-deck-note">{{ $blue['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cp-gr-archive" aria-labelledby="archive-h">
        <div class="s-wrap s-sec">
            <p class="cp-gr-kick">{{ $love['kicks']['archive'] }}</p>
            <h2 id="archive-h" class="cp-gr-h">{{ $heads['archive'][0] }} <em>{{ $heads['archive'][1] }}</em></h2>
            <p class="cp-gr-sub">{{ $love['archive']['lead'] }}</p>
            {{-- The first two years, then the rest folded on a phone (open on a wide screen); each on a staff, its
                 note-head a little higher than the last. --}}
            @php($rows = (array) $love['archive']['rows'])
            @foreach ([array_slice($rows, 0, 2, true), array_slice($rows, 2, null, true)] as $part)
                @if ($loop->last)
                    <details class="cp-gr-more" data-gr-wide><summary><span>{{ $love['archive']['more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
                @endif
                <ol class="cp-gr-years">
                    @foreach ($part as $k => [$year, $place, $title, $text, $cites])
                        <li style="--k: {{ $k }}">
                            <span class="cp-gr-staff" aria-hidden="true"><i></i></span>
                            <p class="cp-gr-year"><span>{{ $year }}</span><span class="cp-gr-place">{{ $place }}</span></p>
                            <div>
                                <h3>{{ $title }}</h3>
                                <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                @if ($loop->last)
                    </details>
                @endif
            @endforeach
        </div>
    </section>

    @if ($outline !== null)
        <section class="s-wrap s-sec cp-gr-climb" aria-labelledby="units-h">
            <div class="cp-gr-head">
                <div>
                    <p class="cp-gr-kick">{{ $love['kicks']['climb'] }}</p>
                    <h2 id="units-h" class="cp-gr-h">{{ $heads['units'][0] }} <em>{{ $heads['units'][1] }}</em></h2>
                </div>
                <p class="cp-gr-count">{{ __('site.courses.pages.gospel-runs.love.units_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-gr-units">
                @foreach ($outline['units'] as $unit)
                    {{-- Unit III, the blue note, in the blue note section's night. --}}
                    <li @class(['cp-gr-unit', 'cp-gr-unit-night' => $loop->iteration === 3])>
                        <p class="cp-gr-unit-n"><span class="cp-gr-unit-label"><x-site.skin :game="__('pages.site.course_stage', ['n' => $loop->iteration])" :serious="__('pages.site.course_unit', ['n' => $loop->iteration])" /></span>@if ($open && $loop->first)<a href="{{ $tryUrl }}" class="cp-gr-free">{{ __('pages.site.course_free') }}</a>@endif</p>
                        <h3>{{ $unit['title'] }}</h3>
                        @isset($page['units_say'][$loop->index])
                            <p class="cp-gr-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                        @endisset
                        <ul class="cp-gr-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li>{{ $lesson }}</li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ol>
            <p class="cp-gr-tail">{{ $love['climb_tail'] }}</p>
        </section>
    @endif

    <section class="cp-gr-firstsec" aria-labelledby="try-h">
        <div class="s-wrap s-sec cp-gr-first">
            <div>
                <p class="cp-gr-kick">{{ $love['kicks']['first'] }}</p>
                <h2 id="try-h" class="cp-gr-h">{{ $heads['try'][0] }} <em>{{ $heads['try'][1] }}</em></h2>
                <p class="cp-gr-sub">{{ $page['try']['lead'] }}</p>
                <ol class="cp-gr-lining">
                    @foreach ((array) $love['lining']['rows'] as [$who, $line])
                        <li><span class="cp-gr-who">{{ $love['lining']['who'][$who] }}</span><p>{{ $line }}</p></li>
                    @endforeach
                </ol>
                <p class="cp-gr-note">{{ $page['try']['after'] }}</p>
            </div>
            <div class="cp-gr-inside">
                <h3 class="cp-gr-h3">{{ $heads['inside'][0] }} <em>{{ $heads['inside'][1] }}</em></h3>
                <p>{{ $page['measures'] }}</p>
                <p><x-site.say :text="$page['scoring']" /></p>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-gr-margin" aria-labelledby="facts-h">
        <p class="cp-gr-kick">{{ $love['kicks']['notes'] }}</p>
        <h2 id="facts-h" class="cp-gr-h">{{ $heads['facts'][0] }} <em>{{ $heads['facts'][1] }}</em></h2>
        @php($facts = (array) $love['facts'])
        {{-- Three notes, then the rest folded at every width (secondary detail; the page's length pass, 2026-11-19). --}}
        @foreach ([array_slice($facts, 0, 3), array_slice($facts, 3)] as $part)
            @if ($loop->last)
                <details class="cp-gr-more cp-gr-more-all"><summary><span>{{ $love['facts_more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
            @endif
            <ul class="cp-gr-facts">
                @foreach ($part as [$text, $source, $cites])
                    <li>
                        <p class="cp-gr-fact">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                        <p class="cp-gr-src">{{ $source }}</p>
                    </li>
                @endforeach
            </ul>
            @if ($loop->last)
                </details>
            @endif
        @endforeach
    </section>

    <section class="s-wrap cp-gr-kit" aria-labelledby="kit-h">
        <h2 id="kit-h" class="cp-gr-kick">{{ $love['kicks']['kit'] }}</h2>
        <div class="cp-gr-kit-grid">
            @foreach (['who', 'need', 'safety'] as $list)
                <div @class(['cp-gr-safe' => $loop->last])>
                    <h3>{{ $page['titles'][$list] }}</h3>
                    <ul class="cp-gr-ticks">
                        @foreach ((array) $page[$list] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    <section class="s-wrap s-sec cp-gr-fine" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-gr-h cp-gr-faq-h">{{ $heads['faq'][0] }} <em>{{ $heads['faq'][1] }}</em></h2>
        @include('site.courses._faq')
    </section>

    <section class="cp-gr-close" aria-labelledby="join-h">
        <div class="s-wrap cp-gr-close-in">
            <div class="cp-gr-close-copy">
                <p class="cp-gr-kick">{{ $love['kicks']['close'] }}</p>
                <h2 id="join-h" class="cp-gr-h cp-gr-h-close">{{ $heads['join'][0] }} <em>{{ $heads['join'][1] }}</em></h2>
                <p class="cp-gr-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
                <p class="cp-gr-btns">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                </p>
                @if ($open)
                    <p class="cp-gr-note">{{ __('pages.site.course_join_note') }}</p>
                @endif
                <h3 class="cp-gr-kick cp-gr-related-h">{{ $page['titles']['related'] }}</h3>
                @include('site.courses._related')
            </div>
            <div class="cp-gr-close-stairs" aria-hidden="true">
                @foreach ($run['steps'] as $k => $step)
                    <span style="--step: {{ $step }}">{{ $love['stairs']['sol'][$run['stairs'][$k]] }}</span>
                @endforeach
            </div>
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
