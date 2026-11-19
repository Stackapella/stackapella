{{-- Sight-singing (/courses/sight-singing), after Hillel's design (2026-11-18, ~/Code/course-designs/sight-singing): the
     music itself is the design, and the page climbs a scale. Each section is labelled with the next note, do, re, mi, up
     to ti, and the close is the high do at the top. The hero's sol-fa ladder sounds a rung when it's tapped (its rungs at
     their real semitone heights, the half steps mi to fa and ti to do closer), over a strip of stave where the scale
     steps up to do. Find do, the featured demo (js/public/sight-singing.js), is a page of sheet music: the course's own
     line (lesson 4's, App\Support\Pages\SightSingingPage::demo) on a treble stave in C, D, F or G with its real key
     signature, Guido's red F line and yellow C line, a drone on do under it, each note a button that says whether it's
     do, and the names under the notes that switch off. The story is six bars of notation, one way of writing each age;
     the outcomes rise like steps, do to so, the half step at fa; the lessons are each unit a line of bars 01 to 20, every
     one a link to its lesson's page, marked by a shape-note head; the margin notes are in red ink; the songs are on a
     hymn board whose numbers come from the course file. Every claim is from the research pack
     (docs/courses/sight-singing-love-letter.md), with its numbered notes. The music is drawn in the song pages' own
     engraved outlines (SightSingingPage::glyphDefs). Game skin: the arcade's night. No drawn people. Words: lang/en/site.php
     courses.pages.sight-singing. --}}
@section('lesson-pages-inline', '1')
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($names = (array) $love['names'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($counts = [':lessons' => (string) ($outline['lessons'] ?? ''), ':units' => (string) count($outline['units'] ?? [])])
@php($line = \App\Support\Pages\SightSingingPage::demo())
@php($lineLabel = strtr($demo['lesson'], [':n' => (string) $line['number'], ':name' => $line['name']]))
@php($staves = \App\Support\Pages\SightSingingPage::staves($line['notes']))
@php($strip = \App\Support\Pages\SightSingingPage::strip())
@php($units = \App\Support\Pages\SightSingingPage::units($page, $outline))
@php($board = \App\Support\Pages\SightSingingPage::hymnBoard((array) $page['songs']))
@php($headX = \App\Support\Pages\SightSingingPage::headX())
<main class="cp cp-ss">
    {{ \App\Support\Pages\SightSingingPage::glyphDefs(['gClef', 'noteheadBlack', 'accidentalSharp', 'accidentalFlat']) }}

    {{-- do: the hero. --}}
    <section class="cp-ss-hero" aria-labelledby="page-h">
        <div class="s-wrap">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <div class="cp-ss-hero-grid">
                <div class="cp-ss-hero-copy">
                    <p class="cp-ss-step"><span class="cp-ss-sol">{{ $names[0] }}</span><span>{{ $outline !== null ? strtr($love['eyebrow'], $counts) : $page['eyebrow'] }}</span></p>
                    <h1 id="page-h">{{ \App\Support\Pages\SightSingingPage::heading($page['hero_title'], $love['hero_em']) }}</h1>
                    <p class="cp-ss-lead">{{ $page['hero_lead'] }}</p>
                    <p class="cp-ss-btns">
                        <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                        <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                    </p>
                    @unless ($open)
                        <p class="cp-ss-quiet">{{ __('pages.site.course_soon') }}</p>
                    @endunless
                    <ul class="cp-ss-facts">
                        @foreach ((array) $love['chips'] as $chip)
                            @if ($outline !== null || ! str_contains($chip, ':lessons'))
                                <li>{{ strtr($chip, $counts) }}</li>
                            @endif
                        @endforeach
                    </ul>
                </div>
                <figure class="cp-ss-ladder" data-ss-ladder>
                    <p class="cp-ss-ladder-head"><span class="cp-ss-label">{{ $love['ladder']['title'] }}</span><span>{{ $love['ladder']['tap'] }}</span></p>
                    <div class="cp-ss-rungs" role="group" aria-label="{{ $love['ladder_alt'] }}">
                        @foreach (\App\Support\Pages\SightSingingPage::RUNGS as $semis)
                            <button type="button" @class(['cp-ss-rung', 'cp-ss-rung-home' => $loop->first || $loop->last]) data-ss-rung="{{ $semis }}" style="--at: {{ round((12 - $semis) / 12, 4) }}" aria-label="{{ strtr($love['ladder']['rung'], [':name' => $names[$loop->index % 7]]) }}" aria-pressed="false"><span class="cp-ss-rung-name" aria-hidden="true">{{ $names[$loop->index % 7] }}</span><span class="cp-ss-rung-line" aria-hidden="true"></span></button>
                        @endforeach
                        {{-- The two half steps, mi to fa and ti to do, bracketed. --}}
                        <span class="cp-ss-half" style="--from: {{ round(8 / 12, 4) }}; --to: {{ round(7 / 12, 4) }}" aria-hidden="true"></span>
                        <span class="cp-ss-half" style="--from: {{ round(1 / 12, 4) }}; --to: 0" aria-hidden="true"></span>
                        <span class="cp-ss-dot" aria-hidden="true"></span>
                    </div>
                    <figcaption>
                        <ul class="cp-ss-legend">
                            <li><i class="cp-ss-key-home" aria-hidden="true"></i>{{ $love['ladder']['home'] }}</li>
                            <li><i class="cp-ss-key-half" aria-hidden="true"></i>{{ $love['ladder']['half'] }}</li>
                        </ul>
                        <p>{{ $love['ladder_note'] }}@include('site.courses._cites', ['keys' => ['glover']])</p>
                    </figcaption>
                </figure>
            </div>
        </div>
        {{-- The scale stepping up a stave to do, middle C to the C above, the half steps marked. --}}
        <div class="cp-ss-strip" aria-hidden="true">
            <div class="s-wrap">
                <svg class="cp-ss-strip-svg" width="100%" height="104" focusable="false">
                    <use href="#ss-gClef" transform="translate(2 56) scale(1.2)" class="cp-ss-ink" />
                    @foreach ($strip['halves'] as $half)
                        <g class="cp-ss-strip-half">
                            <line x1="{{ $half['from'] }}%" x2="{{ $half['to'] }}%" y1="{{ $half['y'] }}" y2="{{ $half['y'] }}" />
                            <line x1="{{ $half['from'] }}%" x2="{{ $half['from'] }}%" y1="{{ $half['y'] }}" y2="{{ $half['y'] + 5 }}" />
                            <line x1="{{ $half['to'] }}%" x2="{{ $half['to'] }}%" y1="{{ $half['y'] }}" y2="{{ $half['y'] + 5 }}" />
                        </g>
                    @endforeach
                    @foreach ($strip['notes'] as $note)
                        <svg x="{{ $note['x'] }}%" y="0" overflow="visible">
                            @if ($note['ledger'])
                                <rect x="-12" y="{{ $note['y'] - 0.6 }}" width="24" height="1.2" class="cp-ss-ink" />
                            @endif
                            <use href="#ss-noteheadBlack" transform="translate(-7.08 {{ $note['y'] }}) scale(1.2)" @class(['cp-ss-ink' => ! $note['home'], 'cp-ss-blue' => $note['home']]) />
                        </svg>
                    @endforeach
                    <svg x="100%" y="0" overflow="visible">
                        <rect x="-11" y="20" width="1.5" height="49" class="cp-ss-ink" />
                        <rect x="-6" y="20" width="5" height="49" class="cp-ss-ink" />
                    </svg>
                </svg>
            </div>
        </div>
    </section>

    {{-- re: Find do. --}}
    <section class="s-wrap cp-ss-sec cp-ss-demosec" aria-labelledby="demo-h">
        <div class="cp-ss-head-split">
            <div>
                <p class="cp-ss-step"><span class="cp-ss-sol">{{ $names[1] }}</span><span>{{ $page['kicks']['demo'] }} · @if ($line['url'] !== null)<a href="{{ $line['url'] }}">{{ $lineLabel }}</a>@else{{ $lineLabel }}@endif</span></p>
                <h2 id="demo-h" class="cp-ss-h2">{{ \App\Support\Pages\SightSingingPage::heading($demo['title'], $demo['title_em']) }}</h2>
            </div>
            <p class="cp-ss-aside">{{ $demo['lead'] }}</p>
        </div>
        <div class="cp-ss-demo" data-ss-demo data-key="c" data-names="on" data-tempo="{{ $line['tempo'] }}"
            data-ready="{{ $demo['ready'] }}" data-playing="{{ $demo['playing'] }}" data-after="{{ $demo['after'] }}" data-wrong="{{ $demo['wrong'] }}"
            data-right-c="{{ $demo['right_c'] }}" data-right-d="{{ $demo['right_d'] }}" data-right-f="{{ $demo['right_f'] }}" data-right-g="{{ $demo['right_g'] }}"
            data-moved-c="{{ $demo['moved_c'] }}" data-moved-d="{{ $demo['moved_d'] }}" data-moved-f="{{ $demo['moved_f'] }}" data-moved-g="{{ $demo['moved_g'] }}">
            <p class="cp-ss-demo-bar"><span class="cp-ss-live"><i aria-hidden="true"></i>{{ $demo['live'] }}</span><span class="cp-ss-keyname">@foreach ($demo['keys'] as $key => $keyName)<span data-for="{{ $key }}">{{ $keyName }}</span>@endforeach</span></p>
            <div class="cp-ss-sheet">
                <div class="cp-ss-stave" role="group" aria-label="{{ $demo['stave'] }}">
                    {{-- The clef and each key's signature; on a phone the line takes two systems, so the second starts with
                         its own clef and signature, as printed music does. --}}
                    @foreach ([1, 2] as $system)
                        <div @class(['cp-ss-clef', 'cp-ss-clef-again' => $system === 2]) aria-hidden="true">
                            <svg viewBox="0 20 104 140" focusable="false">
                                <use href="#ss-gClef" transform="translate(8 104) scale(1.6)" class="cp-ss-ink" />
                                @foreach ($staves['signatures'] as $key => $signs)
                                    <g class="cp-ss-for" data-for="{{ $key }}">
                                        @foreach ($signs as $sign)
                                            <use href="#ss-{{ $sign['glyph'] }}" transform="translate({{ $sign['x'] }} {{ $sign['y'] }}) scale(1.6)" class="cp-ss-ink" />
                                        @endforeach
                                    </g>
                                @endforeach
                            </svg>
                        </div>
                        @foreach ($staves['notes'] as $note)
                            @if (($system === 1) === ($loop->iteration <= 4))
                                @include('site.courses._sight-singing-note', ['i' => $loop->index])
                            @endif
                        @endforeach
                        @if ($system === 1)
                            <div class="cp-ss-break" aria-hidden="true"></div>
                        @endif
                    @endforeach
                </div>
                <p class="cp-ss-guido"><i class="cp-ss-key-f" aria-hidden="true"></i><i class="cp-ss-key-c" aria-hidden="true"></i><span>{{ $demo['guido'] }}@include('site.courses._cites', ['keys' => ['guido', 'ebsco']])</span></p>
            </div>
            <div class="cp-ss-demo-foot">
                <p class="cp-ss-controls">
                    <button type="button" class="cp-ss-play" data-ss-play aria-pressed="false"><span class="cp-ss-play-icon" aria-hidden="true"></span><span class="cp-ss-go"><x-site.skin :game="__('pages.site.demo_press_start')" :serious="$demo['play']" /></span><span class="cp-ss-stop">{{ $demo['stop'] }}</span></button>
                    <button type="button" class="cp-ss-newkey" data-ss-key>{{ $demo['new_key'] }}</button>
                    <button type="button" class="cp-ss-switch" role="switch" aria-checked="true" data-ss-names><span class="switch-track" aria-hidden="true"><span class="switch-knob"></span></span><span>{{ $demo['names'] }}</span></button>
                </p>
                <div class="cp-ss-said">
                    <p class="cp-ss-say" role="status" data-ss-say>{{ $demo['ready'] }}</p>
                    <p class="cp-ss-fine">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- mi: the story, six bars. --}}
    <section class="s-wrap cp-ss-sec cp-ss-story" aria-labelledby="story-h">
        <p class="cp-ss-step"><span class="cp-ss-sol">{{ $names[2] }}</span><span>{{ $page['kicks']['story'] }}</span></p>
        <div class="cp-ss-head-split">
            <h2 id="story-h" class="cp-ss-h2">{{ $page['titles']['story'] }}</h2>
            <p class="cp-ss-sub">{{ $love['story_sub'] }}</p>
        </div>
        <ol class="cp-ss-bars">
            @foreach ((array) $love['story'] as [$when, $title, $text, $cites])
                <li class="cp-ss-bar">
                    <p class="cp-ss-bar-top"><span class="cp-ss-year">{{ $when }}</span><span class="cp-ss-bar-n">{{ strtr($love['bar'], [':n' => (string) $loop->iteration]) }}</span></p>
                    @include('site.courses._sight-singing-figure', ['figure' => $loop->index, 'figures' => $love['figures']])
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- fa: the outcomes, rising do to so. --}}
    <section class="s-wrap cp-ss-sec cp-ss-aimsec" aria-labelledby="outcomes-h">
        <p class="cp-ss-step"><span class="cp-ss-sol">{{ $names[3] }}</span><span>{{ $page['kicks']['aims'] }}</span></p>
        <h2 id="outcomes-h" class="cp-ss-h2">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-ss-aims">
            @foreach ((array) $page['outcomes'] as $outcome)
                @php($rise = \App\Support\Pages\SightSingingPage::OUTCOME_STEPS[$loop->index] ?? 0)
                <li @class(['cp-ss-aim', 'cp-ss-aim-home' => $loop->first, 'cp-ss-aim-half' => $rise === 5]) style="--rise: {{ $rise }}">
                    @if ($rise === 5)
                        <span class="cp-ss-aim-tag">{{ $love['half_step'] }}</span>
                    @endif
                    <span class="cp-ss-aim-n" aria-hidden="true">{{ $names[$loop->index] ?? '' }}</span>
                    <p>{{ $outcome }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- so: the lessons, each unit a line of bars. --}}
    @if ($outline !== null)
        <section class="cp-ss-unitsec" aria-labelledby="units-h">
            <div class="s-wrap cp-ss-sec">
                <p class="cp-ss-step"><span class="cp-ss-sol">{{ $names[4] }}</span><span>{{ $page['kicks']['units'] }} · {{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</span></p>
                <h2 id="units-h" class="cp-ss-h2 cp-ss-h2-wide">{{ \App\Support\Pages\SightSingingPage::heading($page['titles']['units'], $love['units_em']) }}</h2>
                <ol class="cp-ss-units">
                    @foreach ($units as $unit)
                        <li class="cp-ss-unit" data-unit="{{ $unit['n'] }}">
                            <div class="cp-ss-unit-head">
                                <span class="cp-ss-shape cp-ss-shape-{{ $unit['shape'] }}" aria-hidden="true"><i></i><i></i><i></i></span>
                                <p class="cp-ss-unit-n"><x-site.skin :game="__('pages.site.course_stage', ['n' => $unit['n']])" :serious="__('pages.site.course_unit', ['n' => $unit['n']])" />@if ($unit['range'] !== '') · <em>{{ $unit['range'] }}</em>@endif</p>
                                <h3>{{ $unit['title'] }}</h3>
                                @if ($unit['say'] !== '')
                                    <p class="cp-ss-unit-say">{{ $unit['say'] }}</p>
                                @endif
                            </div>
                            <ol class="cp-ss-lessons">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li>
                                        @if ($lesson['url'] !== null)
                                            <a href="{{ $lesson['url'] }}" class="cp-ss-lesson">@include('site.courses._sight-singing-lesson', ['start' => $loop->parent->first && $loop->first])</a>
                                        @else
                                            <span class="cp-ss-lesson">@include('site.courses._sight-singing-lesson', ['start' => $loop->parent->first && $loop->first])</span>
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

    {{-- la: the science. --}}
    <section class="s-wrap cp-ss-sec cp-ss-science" aria-labelledby="science-h">
        <p class="cp-ss-step"><span class="cp-ss-sol">{{ $names[5] }}</span><span>{{ $page['kicks']['science'] }}</span></p>
        <h2 id="science-h" class="cp-ss-h2">{{ $page['titles']['science'] }}</h2>
        <ol class="cp-ss-cards">
            @foreach ((array) $love['science'] as [$title, $text, $cites])
                <li>
                    <span class="cp-ss-roman" aria-hidden="true">{{ \App\Support\Pages\SightSingingPage::roman($loop->iteration) }}</span>
                    <div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- ti: what the app listens for, and the notes in the margin, in red ink. --}}
    <section class="cp-ss-band" aria-labelledby="margin-h">
        <div class="s-wrap cp-ss-sec cp-ss-band-grid">
            <div>
                <p class="cp-ss-step"><span class="cp-ss-sol">{{ $names[6] }}</span><span>{{ $page['kicks']['margin'] }}</span></p>
                <h2 id="margin-h" class="cp-ss-h2">{{ $page['titles']['margin'] }}</h2>
                <p class="cp-ss-measures">{{ $page['measures'] }}</p>
            </div>
            <ul class="cp-ss-margin">
                @foreach ((array) $love['margin'] as [$text, $cites])
                    <li><span class="cp-ss-margin-n">@include('site.courses._cites', ['keys' => $cites])</span><p>{{ $text }}</p></li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Before you begin, and the songs on the hymn board. --}}
    <section class="s-wrap cp-ss-sec cp-ss-begin" aria-labelledby="begin-h">
        <p class="cp-ss-step"><span>{{ $page['kicks']['begin'] }}</span></p>
        <h2 id="begin-h" class="cp-ss-h2">{{ $page['titles']['who'] }}</h2>
        <div class="cp-ss-begin-grid">
            <div>
                <h3 class="cp-ss-col-h">{{ $love['for_you'] }}</h3>
                <ul class="cp-ss-rows">@foreach ((array) $page['who'] as $item)<li>{{ $item }}</li>@endforeach</ul>
            </div>
            <div>
                <h3 class="cp-ss-col-h">{{ $page['titles']['need'] }}</h3>
                <ul class="cp-ss-rows">@foreach ((array) $page['need'] as $item)<li>{{ $item }}</li>@endforeach</ul>
                <h3 class="cp-ss-col-h cp-ss-col-h-next">{{ $page['titles']['safety'] }}</h3>
                <ul class="cp-ss-rows">@foreach ((array) $page['safety'] as $item)<li>{{ $item }}</li>@endforeach</ul>
            </div>
            <div class="cp-ss-board">
                <h3 class="cp-ss-col-h"><span>{{ __('pages.site.course_songs') }}</span><span>{{ $love['board']['name'] }}</span></h3>
                <ul>
                    @foreach ($board as $song)
                        <li><span class="cp-ss-board-n">{{ $song['unit'] ?? '' }}</span><div><b translate="no">{{ $song['title'] }}</b><span>{{ $song['credit'] }}</span></div></li>
                    @endforeach
                </ul>
                <p>{{ $love['board']['note'] }}</p>
            </div>
        </div>
    </section>

    {{-- Questions. --}}
    <section class="s-wrap cp-ss-sec cp-ss-faqsec" aria-labelledby="faq-h">
        <div>
            <p class="cp-ss-step"><span>{{ $page['kicks']['questions'] }}</span></p>
            <h2 id="faq-h" class="cp-ss-h2">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    {{-- do, high: the close. --}}
    <section class="cp-ss-close" aria-labelledby="join-h">
        <div class="s-wrap cp-ss-sec cp-ss-close-in">
            <p class="cp-ss-step"><span class="cp-ss-sol">{{ $names[0] }}</span><span>{{ $page['kicks']['close'] }}</span></p>
            <h2 id="join-h" class="cp-ss-join">{{ \App\Support\Pages\SightSingingPage::heading($page['titles']['join'], $love['join_em']) }}</h2>
            <p class="cp-ss-lead">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-ss-btns cp-ss-close-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <button type="button" class="btn-secondary btn-lg" data-ss-hear-do aria-pressed="false">{{ $love['hear_do'] }}</button>
                <a href="/courses" class="cp-ss-all">{{ __('pages.site.course_all') }} <x-site.icon name="arrow" size="16" /></a>
            </p>
            @if ($open)
                <p class="cp-ss-fine">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-ss-label cp-ss-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-ss-sources" aria-labelledby="notes-h">
        <details data-ss-sources>
            <summary><h2 id="notes-h" class="cp-ss-label">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/sight-singing.js"></script>
</main>
