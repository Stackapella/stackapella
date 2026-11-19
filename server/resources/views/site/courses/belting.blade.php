{{-- First steps to belting (/courses/belting), its own page, to Hillel's design of 2026-11-18 (course-designs/belting): the
     calling contour all the way down, two level notes with the second about three semitones lower, drawn as faint pitch
     lines and red note bars. The headline steps down like a call ("Bright," high, "not loud." lower, with its "step
     down" bracket); the hero's card plays the call and the same call held flat; the featured demo, Same note, two
     colours, synthesizes the veiled and the bright note with its harmonics drawn as they sound (js/public/belting.js,
     on voices.js and the audio clock, only when pressed). Then the care as a printed notice, the outcomes as notes
     stepping down, in plain words with Garcia's lit-match line as a pull quote, why loud is the risk on a loudness bar
     (this course is speaking volume, never louder), the archive on a dark ground (each entry a year, a place, a stop
     and a pull quote of its own sourced words, cited), the units with their lessons as notes falling across the
     lines, lesson 1 beside what the app hears, margin notes, before you begin, questions, the close on red, and the
     sources. Still Diction's calm love-letter layout underneath (its cp-di-* classes); the rest is .cp-fb. Bound by the
     voice-health review (docs/courses/belting-voice-health.md): no loudness ever asked for, no promise of power, range,
     high notes or safety for everyone, and the app hears pitch and timing only. Hey, Ho and Haul Away are named, never
     linked. No drawn people. Words: lang/en/site.php courses.pages.belting (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($call = $love['call'])
@php($demo = $love['demo'])
@php($plain = $love['plain'])
@php($ems = $love['em'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($rangeName = __('site.courses.pages.range-and-registers.name'))
{{-- The headline's two steps, and each heading's phrase in italics: App\Support\Pages\BeltingPage. --}}
@php($steps = \App\Support\Pages\BeltingPage::steps((string) $page['hero_title']))
@php($semi = \App\Support\Pages\BeltingPage::semitoneY(...))
@php($bx = \App\Support\Pages\BeltingPage::beatX(...))
@php($fx = \App\Support\Pages\BeltingPage::hzX(...))
<main class="cp cp-diction cp-fb" data-fb>
    <section class="cp-hero cp-di-hero cp-fb-hero" aria-labelledby="page-h">
        <div class="s-wrap">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <p class="cp-fb-chips"><span class="cp-hero-chip"><b>{{ __('pages.site.course_chip_free') }}</b>@if ($outline !== null)<span>{{ __('pages.site.course_chip_lessons', ['n' => $outline['lessons']]) }}</span>@endif</span><span class="cp-fb-tag">{{ $love['tag'] }}</span></p>
            <div class="cp-fb-head cp-fb-lines">
                <h1 id="page-h" class="cp-fb-h1">
                    <span class="cp-fb-h1-hi"><span class="cp-fb-word">{{ $steps[0] }}<i class="cp-fb-bar" aria-hidden="true"></i></span></span>
                    @isset($steps[1])
                        <span class="cp-fb-h1-lo"><span class="cp-fb-step" aria-hidden="true"><span>{{ $love['step'][0] }}</span><span>{{ $love['step'][1] }}</span></span><span class="cp-fb-word">{{ $steps[1] }}<i class="cp-fb-bar" aria-hidden="true"></i></span></span>
                    @endisset
                </h1>
            </div>
            <div class="cp-fb-hero-grid">
                <div class="cp-hero-copy">
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
                    <p class="cp-di-quiet">{{ $page['hero_care'] }}</p>
                    @unless ($open)
                        <p class="cp-di-quiet">{{ __('pages.site.course_soon') }}</p>
                    @endunless
                </div>
                <figure class="cp-hero-card cp-di-map cp-fb-call" data-fb-call>
                    <p class="cp-fb-call-top"><span class="cp-fb-mono cp-fb-red">{{ $call['kick'] }}</span><span class="cp-fb-mono">{{ $call['pitch'] }} <span aria-hidden="true">&uarr;</span> &middot; {{ $call['time'] }} <span aria-hidden="true">&rarr;</span></span></p>
                    <svg viewBox="40 0 400 220" class="cp-di-map-art" aria-hidden="true">
                        @foreach (range(3, -2) as $s)
                            <path d="M48 {{ $semi($s) }} H436" class="cp-di-grid" />
                        @endforeach
                        {{-- Held flat: the second note left on the first's pitch, an outline. --}}
                        <rect x="{{ $bx(1) + 4 }}" y="{{ $semi(2) - 10 }}" width="{{ $bx(2.5) - $bx(1) - 8 }}" height="20" rx="10" class="cp-di-ring" data-fb-note="flat" />
                        {{-- The call: two level notes, the second lower. --}}
                        <rect x="{{ $bx(0) + 4 }}" y="{{ $semi(2) - 10 }}" width="{{ $bx(1) - $bx(0) - 8 }}" height="20" rx="10" class="cp-di-dot" data-fb-note="hi" />
                        <rect x="{{ $bx(1) + 4 }}" y="{{ $semi(-1) - 10 }}" width="{{ $bx(2.5) - $bx(1) - 8 }}" height="20" rx="10" class="cp-di-dot" data-fb-note="lo" />
                        <text x="{{ $bx(0) + 4 }}" y="{{ $semi(2) - 22 }}" class="cp-di-vname cp-fb-syl">{{ $call['syllables'][0] }}</text>
                        <text x="{{ $bx(1) + 4 }}" y="{{ $semi(-1) + 42 }}" class="cp-di-vname cp-fb-syl">{{ $call['syllables'][1] }}</text>
                        {{-- The step between them. --}}
                        <path d="M{{ $bx(2.5) + 22 }} {{ $semi(2) }} V{{ $semi(-1) }}" class="cp-di-tri cp-fb-stepline" />
                        @foreach ((array) $call['step'] as $line)
                            <text x="{{ $bx(2.5) + 32 }}" y="{{ $semi(1) + 10 + $loop->index * 20 }}" class="cp-di-tick">{{ $line }}</text>
                        @endforeach
                    </svg>
                    <p class="cp-fb-call-btns">
                        <button type="button" class="cp-fb-callbtn" data-fb-play="call" data-play="{{ $call['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-fb-callbtn-icon" aria-hidden="true"></span><span data-label>{{ $call['play'] }}</span></button>
                        <button type="button" class="cp-fb-callbtn cp-fb-callbtn-flat" data-fb-play="flat" data-play="{{ $call['flat'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-fb-callbtn-icon" aria-hidden="true"></span><span data-label>{{ $call['flat'] }}</span></button>
                    </p>
                    <figcaption>
                        <ul class="cp-hero-legend">
                            @foreach ((array) $call['legend'] as $key)
                                <li><i class="cp-di-key cp-di-key-{{ ['dot', 'ring'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</li>
                            @endforeach
                        </ul>
                        <p class="cp-di-map-note">{{ $call['note'] }}@include('site.courses._cites', ['keys' => ['ladd', 'dayoc']])</p>
                    </figcaption>
                </figure>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-dk-demosec cp-fb-demosec" aria-labelledby="demo-h">
        <div class="cp-dk-demo cp-dk-screen" data-fb-demo data-ready="{{ $demo['ready'] }}" data-after="{{ $demo['after'] }}" data-say-veiled="{{ $demo['say']['veiled'] }}" data-say-bright="{{ $demo['say']['bright'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['tag'] }}</span></p>
            <div class="cp-dk-stage">
                <div class="cp-dk-roll cp-di-roll">
                    <p class="cp-di-read" aria-hidden="true">
                        @foreach (['veiled', 'bright'] as $colour)
                            <b data-fb-colour="{{ $colour }}" @if ($colour !== 'veiled') hidden @endif>{{ $demo['colours'][$colour] }}</b>
                        @endforeach
                        @foreach ((array) $demo['homes'] as $home => $name)
                            <span data-fb-home-name="{{ $home }}" @if ($home !== 'A3') hidden @endif>{{ $name }}</span>
                        @endforeach
                        @foreach ((array) $demo['filters'] as $colour => $filter)
                            <span data-fb-filter="{{ $colour }}" @if ($colour !== 'veiled') hidden @endif>{{ $filter }}</span>
                        @endforeach
                    </p>
                    <svg viewBox="0 0 600 200" preserveAspectRatio="none" aria-hidden="true" data-fb-spectrum>
                        <rect x="{{ $fx(1000) }}" y="8" width="{{ $fx(3000) }}" height="182" class="cp-fb-band" />
                        @foreach ([2000, 3000] as $f)
                            <path d="M{{ $fx($f) }} 8 V190" class="cp-di-hz" />
                        @endforeach
                        <path d="M0 190 H600" class="cp-dk-staff" />
                        <g data-fb-harmonics></g>
                    </svg>
                    <p class="cp-di-scale" aria-hidden="true">
                        @foreach ((array) $demo['ticks'] as $tick)
                            <span style="--at: {{ $loop->index / 5 }}">{{ $tick }}</span>
                        @endforeach
                    </p>
                    <p class="cp-dk-words" aria-hidden="true">
                        @foreach ((array) $demo['colours'] as $colour => $name)
                            <span data-fb-step="{{ $colour }}">{{ $loop->iteration }}. {{ $name }}</span>
                        @endforeach
                    </p>
                </div>
            </div>
            <div class="cp-dk-caption">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h cp-fb-em">{{ \App\Support\Pages\BeltingPage::em($demo['title'], $ems['demo']) }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}</p>
                    <p class="cp-dk-demo-lead">{{ $demo['how'] }}@include('site.courses._cites', ['keys' => ['unsw']])</p>
                </div>
                <div>
                    <button type="button" class="cp-dk-big cp-fb-big" data-fb-play="both" data-play="{{ $demo['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-big-icon" aria-hidden="true"></span><span data-label>{{ $demo['play'] }}</span></button>
                    <fieldset class="cp-dk-pick cp-di-pick">
                        <legend>{{ $demo['voice_label'] }}</legend>
                        @foreach ((array) $demo['homes'] as $home => $name)
                            <button type="button" class="cp-dk-chip" data-fb-home="{{ $home }}" aria-pressed="{{ $home === 'A3' ? 'true' : 'false' }}"><span class="cp-dk-tick" aria-hidden="true"></span>{{ $name }}</button>
                        @endforeach
                    </fieldset>
                    <fieldset class="cp-dk-pick cp-di-pick">
                        <legend>{{ $demo['one_label'] }}</legend>
                        @foreach ((array) $demo['one'] as $colour => $name)
                            <button type="button" class="cp-dk-play {{ $loop->last ? 'cp-dk-play-b' : '' }}" data-fb-play="{{ $colour }}" data-play="{{ $name }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $name }}</span></button>
                        @endforeach
                    </fieldset>
                    <p class="cp-dk-say" role="status" data-fb-say>{{ $demo['ready'] }}</p>
                    <p class="cp-dk-key">
                        @foreach ((array) $demo['key'] as $key)
                            <span><i class="cp-di-dkey {{ ['cp-di-dkey-line', 'cp-fb-dkey-band'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-dk-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    {{-- The care, boxed like a printed notice. --}}
    <section class="s-wrap s-sec cp-fb-care" aria-labelledby="care-h">
        <div class="cp-fb-notice">
            <div>
                <p class="cp-di-kick">{{ $love['kicks']['care'] }}</p>
                <h2 id="care-h" class="cp-di-title cp-fb-em">{{ \App\Support\Pages\BeltingPage::em($page['titles']['care'], $ems['care']) }}</h2>
                <p class="cp-fb-across" aria-hidden="true"><span>{{ $love['across'][0] }}</span><i></i><span>{{ $love['across'][1] }}</span><b>{{ $love['across'][2] }}</b></p>
            </div>
            <div>
                <p class="cp-fb-care-lead">{{ $page['care']['lead'] }}</p>
                <ol class="cp-fb-roman">
                    @foreach ((array) $page['care']['points'] as $point)
                        <li>{{ $point }}</li>
                    @endforeach
                </ol>
                <p class="cp-di-note"><a href="/courses/range-and-registers" class="s-arrow">{{ $rangeName }} <x-site.icon name="arrow" size="16" /></a></p>
            </div>
        </div>
    </section>

    {{-- By the end: each outcome under a note bar, the four stepping down like a call. --}}
    <section class="s-wrap s-sec cp-fb-aims" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="cp-di-kick">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-fb-goals">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li style="--i: {{ $loop->index }}"><i class="cp-fb-note" aria-hidden="true"></i><span class="cp-di-n" aria-hidden="true">0{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-fb-plain" aria-labelledby="plain-h">
        <div class="cp-di-plain-head">
            <div>
                <p class="cp-di-kick">{{ $love['kicks']['plain'] }}</p>
                <h2 id="plain-h" class="cp-di-title cp-fb-em">{{ \App\Support\Pages\BeltingPage::em($plain['title'], $ems['plain']) }}</h2>
                <figure class="cp-fb-pull">
                    <blockquote><p>"{{ $plain['quote'] }}"</p></blockquote>
                    <figcaption class="cp-fb-mono">{{ $plain['quote_by'] }}@include('site.courses._cites', ['keys' => ['garcia']])</figcaption>
                </figure>
            </div>
            <div>
                <p class="cp-di-body">{{ $plain['lead'] }}@include('site.courses._cites', ['keys' => ['unsw', 'bele']])</p>
                <p class="cp-di-body">{{ $plain['speech'] }}@include('site.courses._cites', ['keys' => ['henrich', 'dayoc']])</p>
            </div>
        </div>
        <div class="cp-fb-risk">
            <h3 class="cp-di-h3">{{ $plain['risk_title'] }}</h3>
            <p class="cp-di-small">{{ $plain['risk'] }}</p>
            {{-- The loudness bar: this course is speaking volume, never louder. --}}
            @php($loud = $plain['loud'])
            <div class="cp-fb-loud" role="img" aria-label="{{ $loud['say'] }}">
                <span class="cp-fb-loud-tag cp-fb-loud-this">{{ $loud['this'] }}</span>
                <span class="cp-fb-loud-tag cp-fb-loud-not">{{ $loud['not'] }}</span>
                @foreach ((array) $loud['levels'] as $level)
                    <span class="cp-fb-loud-bar cp-fb-loud-{{ $loop->iteration }}" style="--c: {{ $loop->iteration }}"></span>
                    <span class="cp-fb-loud-name {{ $loop->iteration === 2 ? 'cp-fb-loud-on' : '' }}" style="--c: {{ $loop->iteration }}">{{ $level }}</span>
                @endforeach
            </div>
            {{-- The three numbers, folded on a phone (for length), open on a wide screen. --}}
            <details class="cp-di-more cp-fb-nums" data-fb-wide><summary><span>{{ $plain['numbers_more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
                <ol class="cp-di-nums">
                    @foreach ((array) $plain['numbers'] as [$big, $what, $text, $cites])
                        <li class="cp-di-card cp-di-num">
                            <p class="cp-di-big">{{ $big }}</p>
                            <h4>{{ $what }}</h4>
                            <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                        </li>
                    @endforeach
                </ol>
            </details>
        </div>
    </section>

    {{-- From the archive, on a dark ground: a year, a place and its stop, then the entry's own words pulled out. --}}
    <section class="cp-di-archive cp-fb-archive" aria-labelledby="archive-h">
        <div class="s-wrap s-sec">
            <p class="cp-di-kick">{{ $love['kicks']['archive'] }}</p>
            <h2 id="archive-h" class="cp-di-title cp-fb-em">{{ \App\Support\Pages\BeltingPage::em($love['archive']['title'], $ems['archive']) }}</h2>
            <p class="cp-fb-route cp-fb-mono" aria-hidden="true">{{ implode(' → ', (array) $love['archive']['route']) }}</p>
            {{-- The first two rows, then the rest folded on a phone (open on a wide screen). --}}
            @php($rows = (array) $love['archive']['rows'])
            @php($pulls = (array) $love['archive']['pulls'])
            @php($stops = (array) $love['archive']['stops'])
            @foreach ([array_slice($rows, 0, 2, true), array_slice($rows, 2, null, true)] as $part)
                @if ($loop->last && ! $loop->first)
                    <details class="cp-di-more" data-fb-wide><summary><span>{{ $love['archive']['more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
                @endif
                <ol class="cp-di-years" start="{{ $loop->first ? 1 : 3 }}">
                    @foreach ($part as $i => [$year, $place, $title, $text, $cites])
                        <li>
                            <p class="cp-di-year"><span>{{ $year }}</span><span class="cp-di-place">{{ $place }}@isset($stops[$i]) <span aria-hidden="true">&middot;</span> {{ $stops[$i] }}@endisset</span></p>
                            <div>
                                <h3>{{ $title }}</h3>
                                @isset($pulls[$i])
                                    <blockquote class="cp-fb-pullq"><p>"{{ $pulls[$i][0] }}"@include('site.courses._cites', ['keys' => $pulls[$i][1]])</p></blockquote>
                                @endisset
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
        {{-- The course: each unit's lessons as notes falling across the pitch lines. --}}
        <section class="cp-di-climbsec cp-fb-climb" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-di-kick">{{ $love['kicks']['climb'] }}</p>
                <h2 id="units-h" class="cp-di-title cp-fb-em">{{ \App\Support\Pages\BeltingPage::em($page['titles']['units'], $ems['units']) }}</h2>
                <p class="cp-fb-count cp-fb-mono">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                <ol class="cp-di-units cp-fb-units">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-di-card cp-di-unit">
                            <p class="cp-di-unit-n"><span class="cp-di-unit-dot" aria-hidden="true">{{ $loop->iteration }}</span><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-di-small">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            <ol class="cp-fb-falls">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li style="--i: {{ $loop->index }}">
                                        @if ($open && $loop->parent->first && $loop->first)
                                            <a href="{{ $tryUrl }}" class="cp-fb-pill cp-fb-pill-go" title="{{ $lesson }}"><span class="cp-fb-pill-t">{{ $lesson }}</span><b>{{ __('pages.site.course_free') }}</b></a>
                                        @else
                                            <span class="cp-fb-pill" title="{{ $lesson }}"><span class="cp-fb-pill-t">{{ $lesson }}</span></span>
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

    <section class="s-wrap s-sec cp-di-first cp-fb-first" aria-labelledby="try-h">
        <div>
            <p class="cp-di-kick">{{ $love['kicks']['first'] }}</p>
            <h2 id="try-h" class="cp-di-title cp-fb-em">{{ \App\Support\Pages\BeltingPage::em($page['titles']['try'], $ems['try']) }}</h2>
            <p class="cp-di-body">{{ $page['try']['lead'] }}</p>
            <ol class="cp-fb-steps">
                @foreach ((array) $page['try']['steps'] as $step)
                    <li><span class="cp-di-n" aria-hidden="true">0{{ $loop->iteration }}</span><p>{{ $step }}</p></li>
                @endforeach
            </ol>
            <p class="cp-di-note">{{ $page['try']['after'] }}</p>
        </div>
        <div class="cp-di-card cp-di-inside cp-fb-inside">
            <h2 id="inside-h" class="cp-di-h3">{{ $page['titles']['inside'] }}</h2>
            <dl class="cp-fb-hears">
                @foreach (['hears', 'ear'] as $side)
                    <div class="cp-fb-hears-{{ $side }}"><dt class="cp-fb-mono">{{ $love['listens'][$side][0] }}</dt><dd>{{ $love['listens'][$side][1] }}</dd></div>
                @endforeach
            </dl>
            <p class="cp-di-small">{{ $page['measures'] }}</p>
            <p class="cp-di-small"><x-site.say :text="$page['scoring']" /></p>
        </div>
    </section>

    <section class="s-wrap s-sec cp-fb-margin" aria-labelledby="facts-h">
        <p class="cp-di-kick">{{ $love['kicks']['notes'] }}</p>
        <h2 id="facts-h" class="cp-di-title cp-fb-em">{{ \App\Support\Pages\BeltingPage::em($love['facts_title'], $ems['facts']) }}</h2>
        @php($facts = (array) $love['facts'])
        @foreach ([array_slice($facts, 0, 2), array_slice($facts, 2)] as $part)
            @if ($loop->last && ! $loop->first)
                <details class="cp-di-more" data-fb-wide><summary><span>{{ $love['facts_more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
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

    <section class="s-wrap s-sec cp-fb-kit" aria-labelledby="kit-h">
        <h2 id="kit-h" class="cp-di-kick">{{ $love['kicks']['kit'] }}</h2>
        <div class="cp-di-kit-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul class="cp-di-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="cp-di-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div class="cp-di-safe"><h3>{{ $page['titles']['safety'] }}</h3><ul class="cp-di-ticks">@foreach ((array) $page['safety'] as $item)<li><x-site.icon name="info" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-di-fine" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-di-title">{{ $page['titles']['faq'] }}</h2>
        @include('site.courses._faq')
    </section>

    {{-- The close on red, the page's one accent block, with the pitch lines behind it. --}}
    <section class="cp-di-close cp-fb-close cp-fb-lines" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-di-close-in">
            <p class="cp-di-kick">{{ $love['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-di-title cp-fb-em">{{ \App\Support\Pages\BeltingPage::em($page['titles']['join'], $ems['join']) }}</h2>
            <p class="cp-di-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-di-note">{{ $page['close_care'] }}</p>
            <p class="cp-join-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </p>
            @if ($open)
                <p class="cp-di-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-di-kick cp-di-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-di-sources" aria-labelledby="notes-h">
        <details data-fb-sources>
            <summary><h2 id="notes-h" class="cp-di-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/belting.js"></script>
</main>
