{{-- Barbershop and tags (/courses/barbershop): "The Fifth Voice", a love letter to barbershop
     harmony (docs/course-pages-polish.md; the facts: docs/courses/barbershop-love-letter.md). Laid out
     like a barbershop song: an intro (four voices and the fifth nobody sings, on one deep note's
     overtone ladder, evenly spaced as the overtones are), verse one (the tuner: slide the third until
     the wobble stops), the chorus (the cornerstone chord, its seventh gliding 31 cents low, straight or
     wavy), the bridge (whose sound this is: the Black origins and who was shut out of the revival),
     verse two (the course's own phrase of Down by the Old Mill Stream, a seat at a time), tonight's
     bill (the lessons), what the app hears, house rules, questions, the tag (the swipe, and your open
     seat), and the sources, numbered. Every sound is made here (js/public/barbershop.js, on our own
     Web Audio), only when pressed. Both skins. Words: lang/en/site.php courses.pages.barbershop
     (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($parts = [4 => 'bass', 5 => 'baritone', 6 => 'lead', 7 => 'tenor'])
@php($names = ['tenor' => $page['seats'][0][0], 'lead' => $page['seats'][1][0], 'baritone' => $page['seats'][2][0], 'bass' => $page['seats'][3][0]])
@php($keys = ['tenor', 'lead', 'baritone', 'bass'])
{{-- The course's phrase (bb-3-lead, from beat 14): [beat, length, semitones from A3, cents], a voice a part. --}}
@php($phrase = ['tenor' => [[0, .5, 2, 0], [.5, .5, 5, 0], [1, .5, 5, 0], [1.5, 3, 10, 0], [4.5, 3, 7, 0], [7.5, 4, 12, 2]], 'lead' => [[0, .5, -7, 0], [.5, .5, -2, 0], [1, .5, 2, 0], [1.5, 3, 5, 2], [4.5, 3, 4, 0], [7.5, 4, 3, -31]], 'baritone' => [[0, .5, 2, 0], [.5, .5, 2, 0], [1, .5, -2, 0], [1.5, 3, 2, -14], [4.5, 3, -2, 0], [7.5, 4, -3, -14]], 'bass' => [[0, .5, -14, 0], [.5, .5, -14, 0], [1, .5, -14, 0], [1.5, 3, -14, 0], [4.5, 3, -11, 0], [7.5, 4, -7, 0]]])
@php($py = fn (float $step): float => round(84 + (12 - $step) * 11.5, 1))
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($num = 0)
<main class="cp cp-barbershop cp-bb2" data-bb>
    <section class="cp-bb2-intro" aria-labelledby="page-h">
        <div class="s-wrap cp-bb2-intro-grid">
            <div class="cp-bb2-intro-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-bb2-label">{{ $page['eyebrow'] }}</p>
                <h1 id="page-h" class="cp-bb2-h1">{{ $love['hero_title'] }} <em>{{ $love['hero_title_2'] }}</em></h1>
                @include('site.courses._bb-ladder', ['size' => 'mini', 'box' => '0 -20 560 340'])
                <p class="cp-bb2-lede">{{ $love['hero_lead'] }}</p>
                <p class="cp-bb2-ctas cp-bb2-hero-ctas">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <button type="button" class="cp-bb2-play" data-bb-ring data-play="{{ $love['hear'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-bb2-play-icon" aria-hidden="true"></span><span data-bb-label>{{ $love['hear'] }}</span></button>
                </p>
                <p class="cp-bb2-quiet">@unless ($open){{ __('pages.site.course_soon') }} @endunless{{ $love['quiet'] }}</p>
            </div>
            @include('site.courses._bb-ladder', ['size' => 'big', 'box' => '0 0 560 560'])
        </div>
    </section>


    <section class="cp-bb2-verse" aria-labelledby="wobble-h" data-bb-tuner>
        <div class="s-wrap s-sec">
            <p class="cp-bb2-label">{{ $love['wobble']['kick'] }}</p>
            <h2 id="wobble-h" class="cp-bb2-h2">{{ $love['wobble']['title'] }}</h2>
            <p class="cp-bb2-lede">{{ $love['wobble']['lead'] }}</p>
            <p class="cp-bb2-why">{{ $love['wobble']['why'] }}@include('site.courses._cites', ['keys' => ['unsw', 'about']])</p>
            <div class="cp-bb2-tuner-row">
                <div>
                    <p class="cp-bb2-tuner-try">{{ $love['wobble']['try'] }}</p>
                    <p class="cp-bb2-ctas"><button type="button" class="cp-bb2-play" data-bb-wobble data-play="{{ $love['wobble']['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-bb2-play-icon" aria-hidden="true"></span><span data-bb-label>{{ $love['wobble']['play'] }}</span></button></p>
                </div>
                <div class="cp-bb2-meter">
                    <p class="cp-bb2-readout" aria-live="polite"><span class="cp-bb2-readout-game">{{ $love['wobble']['beats_game'] }}</span> <b data-bb-beats>5.2</b> <span class="cp-bb2-readout-unit">{{ $love['wobble']['beats'] }}</span> <strong class="cp-bb2-rings" data-bb-rings hidden>{{ $love['wobble']['ring'] }}</strong></p>
                    <p class="cp-bb2-stars" aria-hidden="true" data-bb-stars="0"><span></span><span></span><span></span></p>
                    <p class="cp-bb2-burst" aria-hidden="true">{{ $love['wobble']['ring_game'] }}</p>
                </div>
            </div>
            <label class="cp-bb2-slider">
                <span class="sr-only">{{ $love['wobble']['label'] }}</span>
                <span class="cp-bb2-thumbtag" aria-hidden="true" data-bb-tag data-ring="{{ $love['wobble']['ticks']['ring'] }}">0¢</span>
                <input type="range" min="-30" max="10" step="1" value="0" data-bb-third>
            </label>
            <p class="cp-bb2-ticks" aria-hidden="true"><span class="cp-bb2-tick-low">−30</span><span class="cp-bb2-tick-ring">−14 <b>{{ $love['wobble']['ticks']['ring'] }}</b></span><span class="cp-bb2-tick-piano">0 <b>{{ $love['wobble']['ticks']['piano'] }}</b></span><span class="cp-bb2-tick-high">+10</span></p>
        </div>
        <svg viewBox="0 0 600 160" class="cp-bb2-beatwave" aria-hidden="true" preserveAspectRatio="none"><path data-bb-wave d="M0 80 H600" /></svg>
        <div class="s-wrap cp-bb2-verse-end">
            <p class="cp-bb2-note-small">{{ $page['try']['after'] }}</p>
        </div>
    </section>

    <section class="s-wrap s-sec cp-bb2-chorus" aria-labelledby="chord-h">
        <div class="cp-bb2-chorus-grid">
            <div class="cp-bb2-chorus-head">
                <p class="cp-bb2-label">{{ $love['chord']['kick'] }}</p>
                <h2 id="chord-h" class="cp-bb2-h2">{{ $love['chord']['title'] }}</h2>
                <p class="cp-bb2-lede">{{ $love['chord']['lead'] }}@include('site.courses._cites', ['keys' => ['language', 'about']])</p>
            </div>
            {{-- Drawn true: the piano's B flat is 31 cents above rung 7, 13% of the way to rung 8 (231 cents
                 away); the loupe shows the same glide ten times larger. --}}
            <figure class="cp-bb2-glide" data-bb-glide>
                <p class="cp-bb2-glide-n" aria-hidden="true">−31¢</p>
                <svg viewBox="0 0 560 400" aria-hidden="true">
                    <defs><clipPath id="bb-loupe"><circle cx="470" cy="128" r="78" /></clipPath></defs>
                    @foreach ([4, 5, 6, 7, 8] as $n)
                        @php($y = 380 - ($n - 4) * 84)
                        <g class="cp-bb2-rung{{ isset($parts[$n]) ? ' cp-bb2-rung-'.$parts[$n] : '' }}">
                            <text x="30" y="{{ $y + 8 }}" class="cp-bb2-rung-n">{{ $n }}</text>
                            @isset($parts[$n])
                                <path d="M64 {{ $y }} H260" class="cp-bb2-rung-line" />
                                @if ($n !== 7)
                                    <circle cx="260" cy="{{ $y }}" r="14" class="cp-bb2-note" />
                                @endif
                                <text x="290" y="{{ $y + 5 }}" class="cp-bb2-rung-part">{{ $names[$parts[$n]] }}</text>
                            @else
                                <path d="M64 {{ $y }} H540" class="cp-bb2-rung-off" />
                            @endisset
                        </g>
                    @endforeach
                    <path d="M64 117 H260" class="cp-bb2-piano-line" />
                    <g class="cp-bb2-rung-tenor"><circle cx="260" cy="117" r="14" class="cp-bb2-note cp-bb2-glider cp-bb2-glider-true" /></g>
                    <circle cx="470" cy="128" r="80" class="cp-bb2-loupe" />
                    <g clip-path="url(#bb-loupe)" class="cp-bb2-rung-tenor">
                        <path d="M380 78 H560" class="cp-bb2-piano-line" />
                        <path d="M380 188 H560" class="cp-bb2-rung-line" />
                        <circle cx="470" cy="78" r="16" class="cp-bb2-note cp-bb2-glider cp-bb2-glider-big" />
                    </g>
                    <text x="470" y="236" class="cp-bb2-loupe-n">×10</text>
                    <text x="384" y="70" class="cp-bb2-piano-n">{{ $love['chord']['piano'] }}</text>
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
    <section class="cp-bb2-bridge" aria-labelledby="story-h">
        <div class="s-wrap s-sec">
            <p class="cp-bb2-label">{{ $love['story']['kick'] }}</p>
            <h2 id="story-h" class="cp-bb2-h2">{{ $love['story']['title'] }}</h2>
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
                    <div class="cp-bb2-bar-end" aria-hidden="true"></div>
                </li>
                @foreach (array_filter($beats, fn (array $b): bool => (int) $b[0] > 1963) as [$year, $text, $cites])
                    @include('site.courses._bb-year')
                @endforeach
            </ol>
        </div>
    </section>

    <section class="s-wrap s-sec cp-bb2-inside" aria-labelledby="seats-h">
        <p class="cp-bb2-label">{{ $love['inside']['kick'] }}</p>
        <h2 id="seats-h" class="cp-bb2-h2">{{ $love['inside']['title'] }}</h2>
        <p class="cp-bb2-lede">{{ $love['inside']['lead'] }}</p>
        <figure class="cp-bb2-phrase" data-bb-phrase="{{ json_encode($phrase) }}">
            <div class="cp-bb2-phrase-plot">
                <svg viewBox="0 0 552 400" preserveAspectRatio="none" aria-hidden="true">
                    @foreach (range(-12, 12, 3) as $step)
                        <path d="M0 {{ $py($step) }} H552" class="cp-bb2-guide" />
                    @endforeach
                    @foreach ($keys as $key)
                        <polyline class="cp-bb2-vline cp-bb2-v-{{ $key }}" data-bb-line="{{ $key }}" points="@foreach ($phrase[$key] as [$beat, $length, $step]){{ round($beat * 48, 1) }},{{ $py($step) }} {{ round(($beat + $length) * 48, 1) }},{{ $py($step) }} @endforeach" />
                    @endforeach
                </svg>
                <span class="cp-bb2-head" aria-hidden="true"></span>
                <p class="cp-bb2-lyric" aria-hidden="true">@foreach ($love['inside']['lyric'] as $words)<span style="flex-grow: {{ [1.5, 3, 3, 4][$loop->index] }}">{{ $words }}</span>@endforeach</p>
                <div class="cp-bb2-seats" role="group" aria-label="{{ $love['inside']['title'] }}">
                    <button type="button" class="cp-bb2-seat cp-bb2-seat-all" style="top: 7%" data-bb-seat="all" data-job="{{ $love['inside']['all_job'] }}" aria-pressed="false">{{ $love['inside']['all'] }}</button>
                    @foreach ($keys as $key)
                        <button type="button" class="cp-bb2-seat cp-bb2-v-{{ $key }}" style="top: {{ round($py(end($phrase[$key])[2]) / 400 * 100, 2) }}%" data-bb-seat="{{ $key }}" data-job="{{ $love['inside']['jobs'][$loop->index] }}" aria-pressed="false">{{ $names[$key] }}</button>
                    @endforeach
                </div>
            </div>
            <p class="cp-bb2-job" aria-live="polite" data-bb-job></p>
        </figure>
    </section>

    @if ($outline !== null)
        <section class="cp-bb2-billsec" aria-labelledby="units-h">
            <div class="s-wrap s-sec cp-bb2-bill">
                <p class="cp-bb2-label">{{ $love['bill']['kick'] }}</p>
                <h2 id="units-h" class="cp-bb2-h2">{{ $love['bill']['title'] }}</h2>
                <ul class="cp-bb2-promises">
                    @foreach ((array) $page['outcomes'] as $outcome)
                        <li>{{ $outcome }}</li>
                    @endforeach
                </ul>
                <div class="cp-bb2-acts">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($act = str_replace(':n', (string) $loop->iteration, $love['bill']['act']))
                        <details class="cp-bb2-act" data-bb-open @if ($loop->first) open @endif>
                            <summary>
                                <span class="cp-bb2-act-n"><x-site.skin :game="$stage" :serious="$act" /></span>
                                <h3>{{ $unit['title'] }}</h3>
                                <span class="cp-bb2-act-more">{{ str_replace(':n', (string) count($unit['lessons']), $love['bill']['more']) }}</span>
                            </summary>
                            <ol class="cp-bb2-turns">
                                @foreach ($unit['lessons'] as $lesson)
                                    @php($num++)
                                    <li><span class="cp-bb2-turn">{{ $lesson }}</span><span class="cp-bb2-dots" aria-hidden="true"></span>@if ($num === 1)<a href="{{ $tryUrl }}" class="cp-bb2-free">{{ $love['bill']['free'] }}</a>@else<span class="cp-bb2-no">{{ str_replace(':n', (string) $num, $love['bill']['no']) }}</span>@endif</li>
                                @endforeach
                            </ol>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="s-wrap s-sec cp-bb2-hears" aria-labelledby="inside-h">
        <h2 id="inside-h" class="cp-bb2-label">{{ $page['titles']['inside'] }}</h2>
        <p class="cp-bb2-big">{{ $love['hears']['readout'] }}</p>
        <p class="cp-bb2-lede">{{ $page['measures'] }}</p>
        <p class="cp-bb2-note-small"><x-site.say :text="$page['scoring']" /></p>
    </section>

    <section class="s-wrap s-sec cp-bb2-rules" aria-labelledby="rules-h">
        <p class="cp-bb2-label">{{ $love['rules']['kick'] }}</p>
        <h2 id="rules-h" class="cp-bb2-h2">{{ $love['rules']['title'] }}</h2>
        <div class="cp-bb2-rules-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><p>{{ $love['rules']['who'] }}</p><h3>{{ $page['titles']['need'] }}</h3><p>{{ $love['rules']['need'] }}</p></div>
            <div><h3>{{ $page['titles']['safety'] }}</h3><ul>@foreach ((array) $page['safety'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-bb2-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-bb2-label">{{ $love['fine'] }}</p>
            <h2 id="faq-h" class="cp-bb2-h2">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
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
