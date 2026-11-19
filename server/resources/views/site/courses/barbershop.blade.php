{{-- Barbershop and tags (/courses/barbershop): "The Fifth Voice", a love letter to barbershop
     harmony (docs/course-pages-polish.md; the facts: docs/courses/barbershop-love-letter.md), set as
     an old barbershop and a quartet's playbill (Hillel's design, 2026-11-18). Laid out like a
     barbershop song: an intro (a ticket, four voices and the fifth nobody sings, and one deep note's
     overtone ladder in an arched shop window, rung 8 lighting when the chord rings), verse one (the
     tuner: slide the third until the wobble stops), the chorus (the cornerstone chord, its seventh
     gliding 31 cents low, straight or wavy), the bridge (whose sound this is: the Black origins, and
     1941 to 1963 on black, who was shut out of the revival), verse two (the course's own phrase of
     Down by the Old Mill Stream, a seat at a time), tonight's bill (the lessons, a framed playbill in
     its acts), what the app hears (a held note on a meter), the house rules (a green sign),
     questions, the tag (the swipe, and your open seat), and the sources, numbered. No barber pole
     (Hillel: "remove the vertical barber pole at the top"), and no pole stripes between sections.
     Every sound is made here (js/public/barbershop.js, on our own Web Audio), only when pressed. Both
     skins. Words: lang/en/site.php courses.pages.barbershop (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($parts = [4 => 'bass', 5 => 'baritone', 6 => 'lead', 7 => 'tenor'])
@php($names = ['tenor' => $page['seats'][0][0], 'lead' => $page['seats'][1][0], 'baritone' => $page['seats'][2][0], 'bass' => $page['seats'][3][0]])
@php($keys = ['tenor', 'lead', 'baritone', 'bass'])
{{-- The course's phrase as unit 3 sings it (App\Support\Site\BarbershopPage::PHRASE). --}}
@php($phrase = \App\Support\Site\BarbershopPage::PHRASE)
@php($py = fn (float $step): float => round(84 + (12 - $step) * 11.5, 1))
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
{{-- Counts from the course itself, in words where the language has them ("Seventeen short lessons"). --}}
@php($count = \App\Support\Site\BarbershopPage::counts($outline, app()->getLocale()))
@php($lessonCount = $count['lessons'])
@php($num = 0)
<main class="cp cp-barbershop cp-bb2" data-bb>
    <section class="cp-bb2-intro" aria-labelledby="page-h">
        <div class="s-wrap cp-bb2-intro-grid">
            <div class="cp-bb2-intro-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-bb2-ticket"><span>{{ $love['ticket']['admit'] }}</span><span>{{ $lessonCount > 0 ? str_replace(':n', (string) $lessonCount, $love['ticket']['line']) : $page['eyebrow'] }}</span></p>
                <h1 id="page-h" class="cp-bb2-h1">{{ $love['hero_title'] }} <em>{{ $love['hero_title_2'] }}</em></h1>
                <p class="cp-bb2-lede">{{ $love['hero_lead'] }}</p>
                <p class="cp-bb2-ctas cp-bb2-hero-ctas">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <button type="button" class="cp-bb2-play" data-bb-ring data-play="{{ $love['hear'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-bb2-play-icon" aria-hidden="true"></span><span data-bb-label>{{ $love['hear'] }}</span></button>
                </p>
                <p class="cp-bb2-quiet">@unless ($open){{ __('pages.site.course_soon') }} @endunless{{ $love['quiet'] }}</p>
            </div>
            @include('site.courses._bb-ladder')
        </div>
    </section>

    @php($wobbleH = \App\Support\Site\BarbershopPage::title($love['wobble']['title'], $love['wobble']['title_em'] ?? ''))
    <section class="cp-bb2-verse" aria-labelledby="wobble-h" data-bb-tuner>
        <div class="s-wrap s-sec">
            <p class="cp-bb2-label">{{ $love['wobble']['kick'] }}</p>
            <h2 id="wobble-h" class="cp-bb2-h2">{{ $wobbleH[0] }}@if ($wobbleH[1] !== '') <em>{{ $wobbleH[1] }}</em>@endif</h2>
            <div class="cp-bb2-tuner-row">
                <div>
                    <p class="cp-bb2-lede">{{ $love['wobble']['lead'] }}</p>
                    <p class="cp-bb2-why">{{ $love['wobble']['why'] }}@include('site.courses._cites', ['keys' => ['unsw', 'about']])</p>
                    <p class="cp-bb2-tuner-try">{{ $love['wobble']['try'] }}</p>
                    <p class="cp-bb2-ctas"><button type="button" class="cp-bb2-play" data-bb-wobble data-play="{{ $love['wobble']['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-bb2-play-icon" aria-hidden="true"></span><span data-bb-label>{{ $love['wobble']['play'] }}</span></button></p>
                </div>
                <div class="cp-bb2-meter">
                    <p class="cp-bb2-readout" aria-live="polite"><span class="cp-bb2-readout-game">{{ $love['wobble']['beats_game'] }}</span> <b data-bb-beats>5.2</b> <span class="cp-bb2-readout-unit">{{ $love['wobble']['beats'] }}</span> <strong class="cp-bb2-rings" data-bb-rings hidden>{{ $love['wobble']['ring'] }}</strong></p>
                    <p class="cp-bb2-stars" aria-hidden="true" data-bb-stars="0"><span></span><span></span><span></span></p>
                    <p class="cp-bb2-burst" aria-hidden="true">{{ $love['wobble']['ring_game'] }}</p>
                </div>
            </div>
            <label class="cp-bb2-slider" data-bb-slider>
                <span class="cp-bb2-slider-head" aria-hidden="true"><span>{{ $love['wobble']['third'] }}</span><span class="cp-bb2-cents" data-bb-tag data-ring="{{ $love['wobble']['ticks']['ring'] }}">0¢</span></span>
                <span class="sr-only">{{ $love['wobble']['label'] }}</span>
                <input type="range" min="-30" max="10" step="1" value="0" data-bb-third>
            </label>
            <p class="cp-bb2-ticks" aria-hidden="true"><span class="cp-bb2-tick-low">−30</span><span class="cp-bb2-tick-ring">−14 <b>{{ $love['wobble']['ticks']['ring'] }}</b></span><span class="cp-bb2-tick-piano">0 <b>{{ $love['wobble']['ticks']['piano'] }}</b></span><span class="cp-bb2-tick-high">+10</span></p>
        </div>
        <svg viewBox="0 0 600 160" class="cp-bb2-beatwave" aria-hidden="true" preserveAspectRatio="none"><path data-bb-wave d="M0 80 H600" /></svg>
        <div class="s-wrap cp-bb2-verse-end">
            <p class="cp-bb2-note-small">{{ $page['try']['after'] }}</p>
        </div>
    </section>

    @php($chordH = \App\Support\Site\BarbershopPage::title($love['chord']['title'], $love['chord']['title_em'] ?? ''))
    <section class="s-wrap s-sec cp-bb2-chorus" aria-labelledby="chord-h">
        <div class="cp-bb2-chorus-grid">
            <div class="cp-bb2-chorus-head">
                <p class="cp-bb2-label">{{ $love['chord']['kick'] }}</p>
                <h2 id="chord-h" class="cp-bb2-h2">{{ $chordH[0] }}@if ($chordH[1] !== '') <em>{{ $chordH[1] }}</em>@endif</h2>
                <p class="cp-bb2-lede">{{ $love['chord']['lead'] }}@include('site.courses._cites', ['keys' => ['language', 'about']])</p>
            </div>
            {{-- Drawn true: rungs 4 to 8 evenly spaced, and the piano's B flat 31 cents above rung 7, 13% of the way to
                 rung 8 (231 cents away). At rest the seventh sits tuned on rung 7; Tune the seventh plays it at the
                 piano's pitch first, then glides it down onto the rung, and the cents and their line follow. --}}
            {{-- Wavy draws each voice's line with the wave it sings (Straight / Wavy below). --}}
            <figure class="cp-bb2-glide" data-bb-glide>
                <p class="cp-bb2-glide-n" aria-hidden="true"><span class="cp-bb2-glide-sung">−31¢</span><span class="cp-bb2-glide-piano">0¢</span></p>
                <p class="cp-bb2-glide-cap"><span class="cp-bb2-glide-sung">{{ $love['chord']['sung'] }}</span><span class="cp-bb2-glide-piano" aria-hidden="true">{{ $love['chord']['played'] }}</span></p>
                <svg viewBox="0 0 520 380" aria-hidden="true">
                    @foreach ([8, 7, 6, 5, 4] as $n)
                        @php($y = 356 - ($n - 4) * 84)
                        <g class="cp-bb2-rung{{ isset($parts[$n]) ? ' cp-bb2-rung-'.$parts[$n] : '' }}">
                            <text x="14" y="{{ $y + 10 }}" class="cp-bb2-rung-n">{{ $n }}</text>
                            @isset($parts[$n])
                                <path d="M54 {{ $y }} H330" class="cp-bb2-rung-line cp-bb2-rung-straight" />
                                <path d="{{ \App\Support\Site\BarbershopPage::wavy($y) }}" class="cp-bb2-rung-line cp-bb2-rung-wavy" />
                                @if ($n !== 7)
                                    <circle cx="330" cy="{{ $y }}" r="13" class="cp-bb2-note" />
                                @endif
                                <text x="356" y="{{ $y + 5 }}" class="cp-bb2-rung-part">{{ $names[$parts[$n]] }}</text>
                            @else
                                <path d="M54 {{ $y }} H500" class="cp-bb2-rung-off" />
                            @endisset
                        </g>
                    @endforeach
                    <path d="M54 93 H330" class="cp-bb2-piano-line" />
                    <text x="312" y="80" class="cp-bb2-piano-n">{{ $love['chord']['piano'] }}</text>
                    <g class="cp-bb2-rung-tenor"><circle cx="330" cy="104" r="13" class="cp-bb2-note cp-bb2-glider" /></g>
                </svg>
            </figure>
            <div class="cp-bb2-chorus-play">
                <p class="cp-bb2-ctas">
                    <button type="button" class="cp-bb2-play" data-bb-seventh data-play="{{ $love['chord']['tune'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-bb2-play-icon" aria-hidden="true"></span><span data-bb-label>{{ $love['chord']['tune'] }}</span></button>
                    <span class="cp-bb2-segs" role="group" aria-label="{{ $love['chord']['how'] }}">
                        <button type="button" data-bb-tone="straight" aria-pressed="true">{{ $love['chord']['straight'] }}</button><button type="button" data-bb-tone="wavy" aria-pressed="false">{{ $love['chord']['wavy'] }}</button>
                    </span>
                </p>
                <p class="cp-bb2-note-small">{{ $love['chord']['wavy_note'] }}</p>
            </div>
        </div>
        <aside class="cp-bb2-margin">
            <p>{{ $love['chord']['record'] }}@include('site.courses._cites', ['keys' => ['roots']])</p>
        </aside>
    </section>

    @php($beats = (array) $love['story']['beats'])
    @php($during = fn (array $b): bool => (int) $b[0] >= 1941 && (int) $b[0] <= 1963)
    @php($storyH = \App\Support\Site\BarbershopPage::title($love['story']['title'], $love['story']['title_em'] ?? ''))
    <section class="cp-bb2-bridge" aria-labelledby="story-h">
        <div class="s-wrap s-sec">
            <p class="cp-bb2-label">{{ $love['story']['kick'] }}</p>
            <h2 id="story-h" class="cp-bb2-h2">{{ $storyH[0] }}@if ($storyH[1] !== '') <em>{{ $storyH[1] }}</em>@endif</h2>
            <p class="cp-bb2-lede">{{ $love['story']['lead'] }}</p>
            <ol class="cp-bb2-timeline">
                @foreach (array_filter($beats, fn (array $b): bool => (int) $b[0] < 1941) as [$year, $text, $cites])
                    @include('site.courses._bb-year')
                @endforeach
                <li class="cp-bb2-span">
                    <div class="cp-bb2-bar"><p class="cp-bb2-bar-years">{{ $love['story']['shut']['years'] }}</p><p class="cp-bb2-bar-line">{{ $love['story']['shut']['line'] }}</p></div>
                    <ol>
                        @foreach (array_filter($beats, $during) as [$year, $text, $cites])
                            @include('site.courses._bb-year')
                        @endforeach
                    </ol>
                </li>
                @foreach (array_filter($beats, fn (array $b): bool => (int) $b[0] > 1963) as [$year, $text, $cites])
                    @include('site.courses._bb-year')
                @endforeach
            </ol>
        </div>
    </section>

    @php($insideH = \App\Support\Site\BarbershopPage::title($love['inside']['title'], $love['inside']['title_em'] ?? ''))
    <section class="s-wrap s-sec cp-bb2-inside" aria-labelledby="seats-h">
        <p class="cp-bb2-label">{{ $love['inside']['kick'] }}</p>
        <h2 id="seats-h" class="cp-bb2-h2">{{ $insideH[0] }}@if ($insideH[1] !== '') <em>{{ $insideH[1] }}</em>@endif</h2>
        <p class="cp-bb2-lede">{{ $love['inside']['lead'] }}</p>
        <figure class="cp-bb2-phrase" data-bb-phrase="{{ json_encode($phrase) }}">
            <div class="cp-bb2-phrase-row">
                <div class="cp-bb2-phrase-plot">
                    <svg viewBox="0 0 552 400" preserveAspectRatio="none" aria-hidden="true">
                        @foreach (range(-12, 12, 6) as $step)
                            <path d="M0 {{ $py($step) }} H552" class="cp-bb2-guide" />
                        @endforeach
                        @foreach ($keys as $key)
                            <polyline class="cp-bb2-vline cp-bb2-v-{{ $key }}" data-bb-line="{{ $key }}" points="@foreach ($phrase[$key] as [$beat, $length, $step]){{ round($beat * 48, 1) }},{{ $py($step) }} {{ round(($beat + $length) * 48, 1) }},{{ $py($step) }} @endforeach" />
                        @endforeach
                    </svg>
                    <span class="cp-bb2-head" aria-hidden="true"></span>
                    <p class="cp-bb2-lyric" aria-hidden="true">@foreach ($love['inside']['lyric'] as $words)<span style="flex-grow: {{ [1.5, 3, 3, 4][$loop->index] }}">{{ $words }}</span>@endforeach</p>
                </div>
                <div class="cp-bb2-seats" role="group" aria-label="{{ $love['inside']['title'] }}">
                    <button type="button" class="cp-bb2-seat cp-bb2-seat-all" data-bb-seat="all" data-job="{{ $love['inside']['all_job'] }}" aria-pressed="false">{{ $love['inside']['all'] }}</button>
                    @foreach ($keys as $key)
                        <button type="button" class="cp-bb2-seat cp-bb2-v-{{ $key }}" data-bb-seat="{{ $key }}" data-job="{{ $love['inside']['jobs'][$loop->index] }}" aria-pressed="false">{{ $names[$key] }}</button>
                    @endforeach
                </div>
            </div>
            <p class="cp-bb2-job" aria-live="polite" data-bb-job></p>
        </figure>
    </section>

    @if ($outline !== null)
        <section class="s-wrap s-sec cp-bb2-billsec" aria-labelledby="units-h">
            <div class="cp-bb2-bill">
                <div class="cp-bb2-bill-head">
                    <p class="cp-bb2-presents">{{ $love['bill']['presents'] }}</p>
                    <p class="cp-bb2-label"><span aria-hidden="true">★ </span>{{ $love['bill']['kick'] }}<span aria-hidden="true"> ★</span></p>
                    <h2 id="units-h" class="cp-bb2-h2">{{ str_replace(':count', $count['lessonsWords'], $love['bill']['title']) }} <em>{{ str_replace(':acts', $count['unitsWords'], $love['bill']['title_em']) }}</em></h2>
                    <ul class="cp-bb2-promises">
                        @foreach ((array) $page['outcomes'] as $outcome)
                            <li>{{ $outcome }}</li>
                        @endforeach
                    </ul>
                </div>
                {{-- An act about the seats marks each seat's lesson with its colour, and the open seat hollow. --}}
                <div class="cp-bb2-acts">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($act = str_replace(':n', \App\Support\Site\BarbershopPage::roman($loop->iteration), $love['bill']['act']))
                        <details class="cp-bb2-act" data-bb-open @if ($loop->first) open @endif>
                            <summary>
                                <span class="cp-bb2-act-n"><x-site.skin :game="$stage" :serious="$act" /></span>
                                <h3>{{ $unit['title'] }}</h3>
                                <span class="cp-bb2-act-more">{{ str_replace(':n', (string) count($unit['lessons']), $love['bill']['more']) }}</span>
                            </summary>
                            <ol class="cp-bb2-turns">
                                @foreach (\App\Support\Site\BarbershopPage::seats($unit['lessons'], $names) as [$lesson, $seat])
                                    @php($num++)
                                    <li>@if ($seat !== null)<span class="cp-bb2-seatdot{{ $seat !== '' ? ' cp-bb2-v-'.$seat : '' }}" aria-hidden="true"></span>@endif<span class="cp-bb2-turn">{{ $lesson }}</span><span class="cp-bb2-dots" aria-hidden="true"></span>@if ($num === 1)<a href="{{ $tryUrl }}" class="cp-bb2-free">{{ $love['bill']['free'] }}</a>@else<span class="cp-bb2-no">{{ str_replace(':n', (string) $num, $love['bill']['no']) }}</span>@endif</li>
                                @endforeach
                            </ol>
                        </details>
                    @endforeach
                </div>
                <p class="cp-bb2-bill-foot">{{ $love['bill']['foot'] }}</p>
            </div>
        </section>
    @endif

    <section class="s-wrap s-sec cp-bb2-hears" aria-labelledby="inside-h">
        <div>
            <h2 id="inside-h" class="cp-bb2-label">{{ $page['titles']['inside'] }}</h2>
            <p class="cp-bb2-big">{{ $love['hears']['readout'] }}</p>
            <p class="cp-bb2-lede">{{ $page['measures'] }}</p>
            <p class="cp-bb2-note-small"><x-site.say :text="$page['scoring']" /></p>
        </div>
        {{-- The readout as the meter shows it: a ±30 cent scale, the band where a held note rings (within 6 cents of
             pure, js/just.js RING.locked), and a note just above it, which the take calls 6 cents high. --}}
        @php($cx = fn (float $c): float => round(($c + 30) / 60 * 100, 2))
        <figure class="cp-bb2-dial" aria-hidden="true">
            <p class="cp-bb2-dial-top"><span>{{ $love['hears']['note'] }}</span><b>{{ $love['hears']['holding'] }}</b></p>
            <div class="cp-bb2-dial-scale">
                <svg viewBox="0 0 100 60" preserveAspectRatio="none">
                    <rect x="{{ $cx(-6) }}" y="14" width="{{ $cx(6) - $cx(-6) }}" height="32" class="cp-bb2-dial-band" />
                    <path d="M{{ $cx(0) }} 8 V52" class="cp-bb2-dial-pure" />
                    @foreach (range(-30, 30, 6) as $c)
                        @if (abs($c) > 6)
                            <path d="M{{ $cx($c) }} 20 V40" class="cp-bb2-dial-tick" />
                        @endif
                    @endforeach
                    <path d="M{{ $cx(6.4) }} 4 V58" class="cp-bb2-dial-mark" />
                </svg>
                <span class="cp-bb2-dial-pill" style="left: {{ $cx(6.4) }}%">+6¢</span>
            </div>
            <p class="cp-bb2-dial-ends"><span>−30</span><b>{{ $love['hears']['rings'] }}</b><span>+30</span></p>
            <figcaption class="cp-bb2-dial-fall"><span>←</span> {{ $love['hears']['fall'] }}</figcaption>
        </figure>
    </section>

    <section class="s-wrap s-sec cp-bb2-rules" aria-labelledby="rules-h">
        <div class="cp-bb2-sign">
            <p class="cp-bb2-label">{{ $love['rules']['kick'] }}</p>
            <h2 id="rules-h" class="cp-bb2-h2">{{ $love['rules']['title'] }}</h2>
            <div class="cp-bb2-rules-grid">
                <div><h3>{{ $page['titles']['who'] }}</h3><p>{{ $love['rules']['who'] }}</p><h3>{{ $page['titles']['need'] }}</h3><p>{{ $love['rules']['need'] }}</p></div>
                <div><h3>{{ $page['titles']['safety'] }}</h3><ol class="cp-bb2-safety">@foreach ((array) $page['safety'] as $line)<li><span class="cp-bb2-safety-n" aria-hidden="true">{{ \App\Support\Site\BarbershopPage::roman($loop->iteration) }}.</span><span>{{ $line }}</span></li>@endforeach</ol></div>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-bb2-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-bb2-label">{{ $love['fine'] }}</p>
            <h2 id="faq-h" class="cp-bb2-h2">{{ $page['titles']['faq'] }}</h2>
        </div>
        {{-- The answers say the course's own count of lessons. --}}
        @php($faqPage = [...$page, 'faq' => \App\Support\Site\BarbershopPage::faq((array) $page['faq'], $lessonCount)])
        @include('site.courses._faq', ['page' => $faqPage])
    </section>

    <section class="cp-bb2-tag" aria-labelledby="join-h" data-bb-tag-stage>
        <div class="s-wrap s-sec cp-bb2-tag-in">
            <p class="cp-bb2-label">{{ $love['tag']['kick'] }}</p>
            <h2 id="join-h" class="cp-bb2-h2">{{ $love['tag']['title'] }}</h2>
            <p class="cp-bb2-lede">{{ $love['tag']['lead'] }}</p>
            {{-- The swipe in time: x = 20 + 88 a second; the baritone bends G to B flat (1.1 to 2.3 s), and
                 the chord lands on F (3.2 s), where the spark lights again, as in the intro. --}}
            <svg viewBox="0 0 640 236" class="cp-bb2-swipe" aria-hidden="true">
                <path d="M20 24 H513" class="cp-bb2-sw cp-bb2-v-tenor" />
                <path d="M20 64 H117 C 170 64, 170 40, 222 40 H302 C 314 40, 312 48, 324 48 H513" class="cp-bb2-sw cp-bb2-v-baritone" />
                <path d="M20 88 H302 C 314 88, 312 80, 324 80 H513" class="cp-bb2-sw cp-bb2-v-lead" />
                <path d="M20 120 H302 C 314 120, 312 176, 324 176 H513" class="cp-bb2-sw cp-bb2-v-bass" />
                @foreach (['tenor' => 24, 'baritone' => 48, 'lead' => 80, 'bass' => 176] as $key => $y)
                    <text x="528" y="{{ $y + 5 }}" class="cp-bb2-sw-n cp-bb2-v-{{ $key }}">{{ $names[$key] }}</text>
                @endforeach
                <text x="170" y="226" class="cp-bb2-sw-word">{{ $love['tag']['word'] }}</text>
                <g class="cp-bb2-spark cp-bb2-sw-spark"><circle cx="470" cy="-6" r="22" /><path d="M470 -40 L475 -11 L504 -6 L475 -1 L470 28 L465 -1 L436 -6 L465 -11 Z" /></g>
                <path d="M20 8 V196" class="cp-bb2-sw-head" />
            </svg>
            <p class="cp-bb2-ctas cp-bb2-center cp-bb2-tag-ctas">
                <button type="button" class="cp-bb2-play" data-bb-swipe data-play="{{ $love['tag']['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-bb2-play-icon" aria-hidden="true"></span><span data-bb-label>{{ $love['tag']['play'] }}</span></button>
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg cp-bb2-cta">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
            </p>
            @unless ($open)
                <p class="cp-bb2-soon">{{ __('pages.site.course_soon') }}</p>
            @endunless
        </div>
    </section>

    <section class="s-wrap cp-bb2-also" aria-labelledby="also-h">
        <h2 id="also-h" class="cp-bb2-label">{{ $page['titles']['related'] }}</h2>
        @include('site.courses._related')
    </section>

    <section class="s-wrap cp-bb2-notes" aria-labelledby="notes-h">
        <details data-bb-open>
            <summary><h2 id="notes-h" class="cp-bb2-label">{{ __('pages.site.sources') }} <span class="cp-bb2-notes-n">({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
            <h3 class="cp-bb2-label">{{ __('pages.site.course_songs') }}</h3>
            <ul>
                @foreach ((array) $page['songs'] as [$song, $credit])
                    <li><b translate="no">{{ $song }}</b> {{ $credit }}</li>
                @endforeach
            </ul>
        </details>
    </section>
    <script type="module" src="/js/public/barbershop.js"></script>
</main>
