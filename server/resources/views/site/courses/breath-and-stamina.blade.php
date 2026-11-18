{{-- Breath and stamina (/courses/breath-and-stamina), its own page, after Hillel's design of 2026-11-18: built around
     breath itself. The hero's held note breathes in, holds 9.4 steady seconds on a 0 to 15 second scale and stops with a
     clean end mark; the singer's comma is the course's breath mark (on the eyebrow and between the units on the lesson
     map); each section's label carries a bar that fills a little more down the page, and the close has a breathing guide
     that grows and shrinks. The demo, End on purpose (lesson br-2-end), plays on voices.js; the lesson map is the course's
     units with every lesson a link to its page, so it replaces the separate list of lesson pages. Motion is CSS or
     requestAnimationFrame, visuals only, and still under reduced motion (the end state shows). Words:
     lang/en/site.php courses.pages.breath-and-stamina; script: js/public/breath-and-stamina.js. --}}
@php($demo = $page['demo'])
@php($held = $page['held'])
@php($lessonCount = $outline['lessons'] ?? 0)
{{-- The held note and the sample result: 9.4 steady seconds on a 15-second scale; the lesson map, every lesson a link to
     its page; and the map's count line (App\Support\Pages\BreathAndStaminaPage). --}}
@php($hold = \App\Support\Pages\BreathAndStaminaPage::tenths(\App\Support\Pages\BreathAndStaminaPage::HOLD))
@php($holdAt = \App\Support\Pages\BreathAndStaminaPage::holdAt())
@php($units = \App\Support\Pages\BreathAndStaminaPage::units($page, $outline))
@php($mapMeta = \App\Support\Pages\BreathAndStaminaPage::mapMeta($page, $outline))
<main class="cp cp-br">
    <section class="cp-br-hero" aria-labelledby="page-h">
        <div class="s-wrap">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <div class="cp-br-hero-grid">
                <div>
                    <p class="cp-br-eyebrow"><span class="cp-br-comma" aria-hidden="true">,</span>{{ str_replace(':lessons', (string) $lessonCount, $page['eyebrow']) }}</p>
                    <h1 id="page-h" class="cp-br-h1">{{ $page['hero_title'] }}</h1>
                </div>
                <div class="cp-br-hero-side">
                    <p class="cp-br-lead">{{ $page['hero_lead'] }}</p>
                    <p class="cp-br-btns">
                        @if ($open)
                            <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                        @else
                            <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                        @endif
                        <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                    </p>
                </div>
            </div>

            {{-- The held note, drawn at its end (9.4 s, stopped clean); the script breathes it in and holds it again
                 while it's in view, unless motion is reduced. --}}
            <div class="cp-br-held" data-br-held data-hold="{{ \App\Support\Pages\BreathAndStaminaPage::HOLD }}" data-in="{{ $held['phases']['in'] }}" data-holding="{{ $held['phases']['hold'] }}" data-done="{{ $held['phases']['done'] }}">
                <p class="cp-br-held-top" aria-hidden="true">
                    <span class="cp-br-held-phase" data-br-held-phase>{{ $held['phases']['done'] }}</span>
                    <span><b data-br-held-count>{{ $hold }}</b> {{ $held['unit'] }}</span>
                </p>
                <div class="cp-br-held-track" aria-hidden="true" style="--at: {{ $holdAt }}%">
                    <span class="cp-br-held-fill" data-br-held-fill></span>
                    <span class="cp-br-held-cap"></span>
                    <span class="cp-br-held-ring" data-br-held-ring></span>
                </div>
                <ol class="cp-br-held-ticks" aria-hidden="true">
                    @foreach (\App\Support\Pages\BreathAndStaminaPage::seconds($held['second']) as $second)
                        <li>{{ $second }}</li>
                    @endforeach
                </ol>
                <p class="cp-br-held-foot"><span>{{ $held['cap'] }}</span><b>{{ $held['verdict'] }}</b></p>
            </div>
        </div>
    </section>

    {{-- The featured demo, End on purpose (lesson br-2-end; js/public/breath-and-stamina.js): a listen first, A
         steady and B running out of air, then a held note over a drone to let go on the beat. The roll counts beats 0
         to 12 across x(b) = 20 + 560b/12: two count dots, the note from beat 3 to 9 (its six beats numbered), the
         on-time band (0.12 s either side of 9) and the line at its end. --}}
    <section class="s-wrap cp-br-demosec" aria-labelledby="demo-h">
        <div class="cp-br-demo" data-br-demo
            data-ready="{{ $demo['ready'] }}" data-count="{{ $demo['count'] }}" data-holding="{{ $demo['holding'] }}" data-too-soon="{{ $demo['too_soon'] }}"
            data-on-time="{{ $demo['on_time'] }}" data-early-near="{{ $demo['early_near'] }}" data-early-far="{{ $demo['early_far'] }}"
            data-late-near="{{ $demo['late_near'] }}" data-late-far="{{ $demo['late_far'] }}" data-ran-out="{{ $demo['ran_out'] }}"
            data-right="{{ $demo['right'] }}" data-wrong="{{ $demo['wrong'] }}"
            data-phase-ready="{{ $demo['phases']['ready'] }}" data-phase-count="{{ $demo['phases']['count'] }}" data-phase-hold="{{ $demo['phases']['hold'] }}"
            data-phase-sag="{{ $demo['phases']['sag'] }}" data-phase-done="{{ $demo['phases']['done'] }}" data-second="{{ $held['second'] }}">
            <p class="cp-br-demo-bar"><span class="cp-br-demo-live"><span class="cp-br-dot" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['lesson'] }}</span></p>
            <div class="cp-br-demo-in">
                <div>
                    <h2 id="demo-h" class="cp-br-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-br-demo-lead">{{ $demo['lead'] }}</p>
                    <fieldset class="cp-br-pick">
                        <legend><span aria-hidden="true">1 · </span>{{ $demo['listen_h'] }}</legend>
                        <div class="cp-br-pick-row">
                            @foreach ((array) $demo['clips'] as $clip => $name)
                                <button type="button" class="cp-br-hear" data-br-clip="{{ $clip }}" data-play="{{ $name }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-br-hear-icon" aria-hidden="true"></span><span data-label>{{ $name }}</span></button>
                            @endforeach
                        </div>
                    </fieldset>
                    <fieldset class="cp-br-pick">
                        <legend><span aria-hidden="true">2 · </span>{{ $demo['ask'] }}</legend>
                        <div class="cp-br-pick-row">
                            {{-- The clips' own letters, A and B, never translated (in Spanish they'd read as the notes La and Si). --}}
                            @foreach (array_keys((array) $demo['clips']) as $clip)
                                <button type="button" class="cp-br-guess" data-br-pick="{{ $clip }}" translate="no">{{ strtoupper($clip) }}</button>
                            @endforeach
                        </div>
                    </fieldset>
                    <p class="cp-br-heard" role="status" data-br-heard></p>
                </div>
                <div>
                    <div class="cp-br-roll">
                        <p class="cp-br-roll-top" aria-hidden="true"><span data-br-phase>{{ $demo['phases']['ready'] }}</span><span data-br-readout></span></p>
                        <svg viewBox="0 30 600 170" aria-hidden="true">
                            <path d="M20 100 H580" class="cp-br-roll-staff" />
                            <rect x="{{ 20 + 9 * 560 / 12 - 5.6 }}" y="56" width="11.2" height="104" class="cp-br-roll-band" data-br-band />
                            <rect x="{{ 20 + 3 * 560 / 12 }}" y="93" width="280" height="14" rx="7" class="cp-br-roll-note" data-br-bar />
                            <text x="{{ 20 + 3 * 560 / 12 + 10 }}" y="84" class="cp-br-roll-vowel">{{ $demo['vowel'] }}</text>
                            <path d="M440 50 V164" class="cp-br-roll-line" />
                            <text x="440" y="42" class="cp-br-roll-letgo">{{ $demo['let_go'] }}</text>
                            <circle cx="{{ 20 + 560 / 12 }}" cy="100" r="7" class="cp-br-roll-count" data-br-count />
                            <circle cx="{{ 20 + 2 * 560 / 12 }}" cy="100" r="7" class="cp-br-roll-count" data-br-count />
                            @foreach (range(1, 6) as $beat)
                                <path d="M{{ 20 + (2 + $beat) * 560 / 12 }} 168 V180" class="cp-br-roll-tick" />
                                <text x="{{ 20 + (2 + $beat) * 560 / 12 + 6 }}" y="192" class="cp-br-roll-n">{{ $beat }}</text>
                            @endforeach
                            <path d="" class="cp-br-roll-trace" data-br-trace />
                            <circle cx="20" cy="100" r="8" class="cp-br-roll-head" data-br-head />
                        </svg>
                    </div>
                    <div class="cp-br-hold-row">
                        <button type="button" class="cp-br-press" data-br-hold aria-pressed="false"><span class="cp-br-press-icon" aria-hidden="true"></span>{{ $demo['hold'] }}</button>
                        <p class="cp-br-say" role="status" data-br-say>{{ $demo['ready'] }}</p>
                    </div>
                    <p class="cp-br-demo-note"><b>{{ $demo['motto'] }}</b> {{ $demo['fact'] }} <a href="{{ $demo['url'] }}" rel="noopener">{{ $demo['source'] }}</a></p>
                    <p class="cp-br-demo-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-br-sec" aria-labelledby="ending-h">
        <p class="cp-br-kick"><span>01</span><span class="cp-br-kbar" style="--k: 10%" aria-hidden="true"></span><span>{{ $page['kicks']['ending'] }}</span></p>
        <div class="cp-br-headrow">
            <h2 id="ending-h" class="cp-br-title">{{ $page['titles']['ending'] }}</h2>
            <p class="cp-br-sidelead">{{ $page['ending']['lead'] }}</p>
        </div>
        <div class="cp-br-ends">
            <figure class="cp-br-end">
                <svg viewBox="0 0 420 140" aria-hidden="true">
                    <path d="M10 60 H410" class="cp-br-end-ref" />
                    <path d="M10 60 L250 60 Q290 60 310 82" class="cp-br-end-sag" />
                    <path d="M318 92 Q330 104 338 100 T356 116 T372 122" class="cp-br-end-crack" />
                    <text x="410" y="48" class="cp-br-end-label">{{ $page['ending']['pitch'] }}</text>
                </svg>
                <figcaption>
                    <span class="cp-br-end-head"><b>{{ $page['ending']['out'][0] }}</b><span class="cp-br-end-tag cp-br-end-tag-out">{{ $page['ending']['out_tag'] }}</span></span>
                    {{ $page['ending']['out'][1] }}
                </figcaption>
            </figure>
            <figure class="cp-br-end cp-br-end-good">
                <svg viewBox="0 0 420 140" aria-hidden="true">
                    <path d="M10 60 H410" class="cp-br-end-ref" />
                    <path d="M306 60 H410" class="cp-br-end-air" />
                    <path d="M10 60 L290 60" class="cp-br-end-steady" />
                    <path d="M306 34 V86" class="cp-br-end-stop" />
                    <text x="358" y="106" class="cp-br-end-label cp-br-end-label-air">{{ $page['ending']['air_left'] }}</text>
                </svg>
                <figcaption>
                    <span class="cp-br-end-head"><b>{{ $page['ending']['purpose'][0] }}</b><span class="cp-br-end-tag">{{ $page['ending']['purpose_tag'] }}</span></span>
                    {{ $page['ending']['purpose'][1] }}
                </figcaption>
            </figure>
        </div>
        <p class="cp-br-small">{{ $page['ending']['body'] }}</p>
    </section>

    <section class="s-wrap cp-br-sec" aria-labelledby="outcomes-h">
        <p class="cp-br-kick"><span>02</span><span class="cp-br-kbar" style="--k: 20%" aria-hidden="true"></span><span>{{ $page['kicks']['outcomes'] }}</span></p>
        <h2 id="outcomes-h" class="cp-br-title">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-br-aims">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-br-aim-n" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span><p>{{ $outcome }}</p><span class="cp-br-aim-bar" style="--k: {{ $loop->iteration * 100 / $loop->count }}%" aria-hidden="true"></span></li>
            @endforeach
        </ol>
    </section>

    @if ($units !== [])
        <section class="s-wrap cp-br-sec" aria-labelledby="units-h">
            <p class="cp-br-kick"><span>03</span><span class="cp-br-kbar" style="--k: 30%" aria-hidden="true"></span><span>{{ $page['kicks']['course'] }}</span></p>
            <div class="cp-br-headrow cp-br-headrow-map">
                <h2 id="units-h" class="cp-br-title">{{ $page['titles']['units'] }}</h2>
                <p class="cp-br-meta">{{ $mapMeta }}</p>
            </div>
            @php([$leadBefore, $leadAfter] = \App\Support\Pages\BreathAndStaminaPage::aroundMark($page['units_lead']))
            <p class="cp-br-small cp-br-map-lead">{{ $leadBefore }}<span class="cp-br-comma">,</span>{{ $leadAfter }}</p>
            <ol class="cp-br-map">
                @foreach ($units as $unit)
                    @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                    @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                    <li class="cp-br-unit cp-br-unit-{{ $loop->iteration }}">
                        <p class="cp-br-phrase" aria-hidden="true">@foreach ($unit['lessons'] as $lesson)<span></span>@endforeach<b class="cp-br-comma">,</b></p>
                        <p class="cp-br-unit-n"><x-site.skin :game="$stage" :serious="$unitName" />@if ($loop->first) · {{ __('pages.site.course_free') }}@endif</p>
                        <h3>{{ $unit['title'] }}</h3>
                        @if ($unit['say'] !== '')
                            <p class="cp-br-unit-say">{{ $unit['say'] }}</p>
                        @endif
                        <ul class="cp-br-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li>
                                    @if ($lesson['url'])
                                        <a href="{{ $lesson['url'] }}"><b>{{ $lesson['name'] }}</b>@if ($lesson['line'] !== '')<span>{{ $lesson['line'] }}</span>@endif</a>
                                    @else
                                        <b>{{ $lesson['name'] }}</b>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="cp-br-band" aria-labelledby="easy-h">
        <div class="s-wrap">
            <p class="cp-br-kick"><span>04</span><span class="cp-br-kbar" style="--k: 40%" aria-hidden="true"></span><span>{{ $page['kicks']['pacing'] }}</span></p>
            <div class="cp-br-headrow">
                <h2 id="easy-h" class="cp-br-title">{{ $page['titles']['easy'] }}</h2>
                <p class="cp-br-sidelead">{{ $page['easy']['lead'] }}</p>
            </div>
            <figure class="cp-br-pace">
                <figcaption>{{ $page['easy']['set'] }}</figcaption>
                <p class="cp-br-pace-row" aria-hidden="true">
                    @foreach (range(1, 3) as $round)
                        <span class="cp-br-pace-breath"></span><span class="cp-br-pace-breath"></span><span class="cp-br-pace-breath"></span><span class="cp-br-pace-hold"></span>
                    @endforeach
                    <span class="cp-br-pace-rest"></span>
                </p>
                <ul class="cp-br-pace-key">
                    <li><span class="cp-br-pace-breath" aria-hidden="true"></span>{{ $page['easy']['legend']['breath'] }}</li>
                    <li><span class="cp-br-pace-hold" aria-hidden="true"></span>{{ $page['easy']['legend']['hold'] }}</li>
                    <li><span class="cp-br-pace-rest" aria-hidden="true"></span>{{ $page['easy']['legend']['rest'] }}</li>
                </ul>
            </figure>
            <ol class="cp-br-rules">
                @foreach ((array) $page['easy']['points'] as $point)
                    <li><span aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span><p>{{ $point }}</p></li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="s-wrap cp-br-sec" aria-labelledby="try-h">
        <p class="cp-br-kick"><span>05</span><span class="cp-br-kbar" style="--k: 50%" aria-hidden="true"></span><span>{{ $page['kicks']['try'] }}</span></p>
        <div class="cp-br-headrow">
            <h2 id="try-h" class="cp-br-title">{{ $page['titles']['try'] }}</h2>
            <p class="cp-br-sidelead">{{ $page['try']['lead'] }}</p>
        </div>
        <ol class="cp-br-steps">
            @foreach ((array) $page['try']['steps'] as $step)
                <li class="cp-br-step-{{ $loop->iteration }}">
                    <span class="cp-br-step-mark" aria-hidden="true"></span>
                    <p class="cp-br-step-n">{{ sprintf('%02d', $loop->iteration) }} · {{ $page['try']['verbs'][$loop->index] ?? '' }}</p>
                    <p>{{ $step }}</p>
                </li>
            @endforeach
        </ol>
        <p class="cp-br-small">{{ $page['try']['after'] }}</p>
    </section>

    <section class="s-wrap cp-br-sec" aria-labelledby="inside-h">
        <div class="cp-br-listen">
            <div>
                <p class="cp-br-kick"><span>06</span><span class="cp-br-kbar" style="--k: 60%" aria-hidden="true"></span><span>{{ $page['kicks']['listening'] }}</span></p>
                <h2 id="inside-h" class="cp-br-title">{{ $page['titles']['inside'] }}</h2>
                <ul class="cp-br-hears">
                    @foreach ((array) $page['hears'] as $by => $lines)
                        @foreach ((array) $lines as $line)
                            <li><span>{{ $line }}</span><span @class(['cp-br-by', 'cp-br-by-you' => $by === 'you'])>{{ $page['hears_by'][$by] }}</span></li>
                        @endforeach
                    @endforeach
                </ul>
                <p class="cp-br-small"><x-site.say :text="$page['scoring']" /></p>
            </div>
            {{-- A sample of lesson 1's result, labelled as one: no real singer's numbers. --}}
            <figure class="cp-br-sample" aria-label="{{ $page['sample']['label'] }}">
                <p class="cp-br-sample-top"><span>{{ $page['sample']['lesson'] }}</span><span class="cp-br-sample-tag">{{ $page['sample']['tag'] }}</span></p>
                <p class="cp-br-sample-title">{{ $page['sample']['title'] }}</p>
                <p class="cp-br-sample-n"><b>{{ $hold }}</b> {{ $page['sample']['unit'] }}</p>
                <span class="cp-br-sample-bar" style="--at: {{ $holdAt }}%" aria-hidden="true"></span>
                <p class="cp-br-sample-scale" aria-hidden="true"><span>{{ $page['sample']['from'] }}</span><span>{{ $page['sample']['cap'] }}</span></p>
                <p class="cp-br-sample-end">{{ $page['sample']['ended'] }}</p>
            </figure>
        </div>
    </section>

    <section class="s-wrap cp-br-sec" aria-labelledby="facts-h">
        <p class="cp-br-kick"><span>07</span><span class="cp-br-kbar" style="--k: 70%" aria-hidden="true"></span><span>{{ $page['kicks']['facts'] }}</span></p>
        <h2 id="facts-h" class="cp-br-title">{{ $page['titles']['facts'] }}</h2>
        <ul class="cp-br-facts">
            @foreach ((array) $page['facts'] as [$text, $source, $url])
                <li>
                    <h3>{{ $page['fact_heads'][$loop->index] ?? '' }}</h3>
                    <p>{{ $text }}</p>
                    <p class="cp-br-src">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $source }}</a>@else{{ $source }}@endif</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap cp-br-sec" aria-labelledby="who-h">
        <p class="cp-br-kick"><span>08</span><span class="cp-br-kbar" style="--k: 80%" aria-hidden="true"></span><span>{{ $page['kicks']['who'] }}</span></p>
        <h2 id="who-h" class="cp-br-title">{{ $page['titles']['who'] }}</h2>
        <div class="cp-br-who">
            <div><h3 class="cp-br-label">{{ $page['titles']['for'] }}</h3><ul class="cp-br-lines">@foreach ((array) $page['who'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
            <div><h3 class="cp-br-label">{{ $page['titles']['need'] }}</h3><ul class="cp-br-lines">@foreach ((array) $page['need'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
            <div class="cp-br-songs">
                <h3 class="cp-br-label">{{ __('pages.site.course_songs') }}</h3>
                <ul>
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b><span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-br-sec cp-br-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-br-kick" aria-hidden="true"><span>09</span><span class="cp-br-kbar" style="--k: 90%"></span></p>
            <h2 id="faq-h" class="cp-br-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="s-wrap cp-br-closesec" aria-labelledby="join-h">
        <div class="cp-br-close">
            <div>
                <p class="cp-br-kick cp-br-kick-close">{{ $page['kicks']['close'] }}</p>
                <h2 id="join-h" class="cp-br-close-h">{{ $page['titles']['join'] }}</h2>
                <p class="cp-br-close-lead">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
                <p class="cp-br-btns">
                    @if ($open)
                        <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                    @else
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                    @endif
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                </p>
                @if ($open)
                    <p class="cp-br-close-note">{{ __('pages.site.course_join_note') }}</p>
                @endif
            </div>
            {{-- The breathing guide: grows as you breathe in, holds, and shrinks as you let it out (CSS); still, with its
                 three cues in a row, under reduced motion. --}}
            <div class="cp-br-guide" role="img" aria-label="{{ implode(', ', (array) $page['guide']) }}">
                <span class="cp-br-guide-ring"><span class="cp-br-guide-orb"></span></span>
                <p class="cp-br-guide-cues">@foreach ((array) $page['guide'] as $cue)<span>{{ $cue }}</span>@endforeach</p>
            </div>
        </div>
        <div class="cp-br-more">
            <h3 class="cp-br-label">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
    <script type="module" src="/js/public/breath-and-stamina.js"></script>
</main>
