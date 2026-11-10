{{-- Yodeling (/courses/yodeling): "Two Voices, One Valley", a love letter to the yodel
     (docs/course-pages-polish.md; the facts: docs/courses/yodel-love-letter.md). Built in two registers,
     as a yodel is: head sections high and light (sky, an airy italic serif), chest sections low and
     warm (a dense serif), meeting at hard seams, never blended. Serious skin: Swiss cut paper, an
     alphorn on the ridge calling across a valley in one colour (no drawn people, ever). Game skin:
     "Echo Valley", an 8-bit platformer where chest voice is the ground lane, head voice the upper
     lane and a flip is a jump for a pixel note. Sections: the
     valley and its call (with its echoes), two voices in one throat (the same leap two ways, flip or
     slide), calls from alp to alp (the story), not only the Alps, the alphorn's odd note, wordless by
     heart, the trail (the lessons), your flip (first steps and a siren whose break you move), what
     the app hears, your pack, questions, the summit, and the sources. Every sound is made here
     (js/public/yodeling.js), only when pressed. Words: lang/en/site.php courses.pages.yodeling (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
{{-- The call: [start, length, y, head] for each note of the hook, drawn at 200px a second from the alphorn. --}}
@php($hook = [[0, .3, 205, false], [.3, .3, 140, true], [.6, .3, 190, false], [.9, .8, 125, true]])
{{-- Each note's run and the jump into it, coloured by the voice it lands in. --}}
@php($call = array_map(fn (array $n, int $i): array => [$n, $i > 0 ? $hook[$i - 1][2] : null], $hook, array_keys($hook)))
<main class="cp cp-yodel cp-yo2" data-yo>
    <section class="cp-yo2-hero" aria-labelledby="page-h">
        <figure class="cp-yo2-valley" data-yo-valley>
            <svg viewBox="0 0 800 420" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
                <path d="M0 300 L120 222 L200 250 L330 142 L420 200 L520 112 L640 190 L720 152 L800 206 V420 H0 Z" class="cp-yo2-far" />
                <path d="M300 168 L330 142 L356 166 L340 160 L330 168 L316 160 Z M494 136 L520 112 L548 140 L530 132 L520 142 L506 132 Z" class="cp-yo2-snow" />
                <path d="M0 420 V256 C 60 236, 120 240, 170 262 C 230 290, 300 340, 380 372 C 480 400, 640 382, 800 358 V420 Z" class="cp-yo2-near" />
                @foreach ([[26, 262], [48, 252], [176, 284], [700, 374], [730, 366], [760, 362]] as [$x, $y])
                    <path d="M{{ $x }} {{ $y - 46 }} l-13 22 h7 l-11 16 h8 l-10 14 h38 l-10 -14 h8 l-11 -16 h7 Z" class="cp-yo2-near" />
                @endforeach
                <path d="M584 372 V350 L602 334 L620 350 V372 Z M578 352 L602 330 L626 352 L622 356 L602 338 L582 356 Z" class="cp-yo2-near" />
                @foreach ([[470, 384], [516, 380]] as [$x, $y])
                    <path d="M{{ $x }} {{ $y }} h26 v-12 q0 -6 -6 -6 h-16 q-4 0 -6 -4 l-4 2 l2 6 v14 Z M{{ $x + 2 }} {{ $y }} v6 h3 v-6 Z M{{ $x + 20 }} {{ $y }} v6 h3 v-6 Z" class="cp-yo2-near" />
                @endforeach
                <g class="cp-yo2-horn"><path d="M70 215 L104 234 Q 112 238 116 230 L120 218 L134 224 L126 236 Q 118 246 104 241 L70 221 Z M65 213 h6 v9 h-6 Z M73 220 L67 245 h3 L76 222 Z M76 222 L82 245 h3 L80 224 Z" /></g>
                <g class="cp-yo2-sprite"><g transform="translate(110 244) scale(2) translate(-110 -244)"><path d="M102 234 h8 v2 h2 v6 h-2 v2 h-8 v-2 h-2 v-6 h2 Z M110 208 h2 v28 h-2 Z M112 208 h4 v2 h2 v2 h2 v8 h-2 v-4 h-2 v-2 h-4 Z" /><path d="M102 236 h2 v2 h-2 Z" class="cp-yo2-sprite-shine" /></g></g>
                <path d="M14 132 H800 M14 198 H800" class="cp-yo2-lanes" />
                <text x="14" y="124" class="cp-yo2-lane cp-yo2-head">{{ $love['lanes']['head'] }}</text>
                <text x="14" y="190" class="cp-yo2-lane cp-yo2-chest">{{ $love['lanes']['chest'] }}</text>
                @foreach ([['', 'translate(0 0)'], ['cp-yo2-echo-1', 'translate(312 104) scale(.6)'], ['cp-yo2-echo-2', 'translate(560 70) scale(.35)']] as [$echo, $move])
                    <g class="cp-yo2-call {{ $echo }}" transform="{{ $move }}">
                        @foreach ($call as [[$at, $length, $y, $head], $from])
                            @if ($from !== null)
                                <path d="M{{ 130 + $at * 200 }} {{ $from }} V{{ $y }}" pathLength="1" class="cp-yo2-seg {{ $head ? 'cp-yo2-head' : 'cp-yo2-chest' }}" style="--at: {{ $at }}s; --len: .02s" />
                            @endif
                            <path d="M{{ 130 + $at * 200 }} {{ $y }} H{{ 130 + ($at + $length) * 200 }}" pathLength="1" class="cp-yo2-seg {{ $head ? 'cp-yo2-head' : 'cp-yo2-chest' }}" style="--at: {{ $at }}s; --len: {{ $length }}s" />
                        @endforeach
                    </g>
                @endforeach
            </svg>
            <figcaption class="sr-only">{{ $love['art_note'] }}</figcaption>
        </figure>
        <div class="s-wrap cp-yo2-hero-copy">
            <p class="cp-yo2-hud" aria-hidden="true"><span>{{ $love['hud']['up'] }}</span><span>{{ $love['hud']['flips'] }} <b class="cp-yo2-hud-flips"></b></span><span>{{ $love['hud']['world'] }}</span><span class="cp-yo2-hud-echo">{{ $love['hud']['echo'] }}</span></p>
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <p class="cp-yo2-label">{{ $page['eyebrow'] }}</p>
            <h1 id="page-h" class="cp-yo2-h1">{{ $page['hero_title'] }}</h1>
            <p class="cp-yo2-lede">{{ $page['hero_lead'] }}</p>
            <p class="cp-yo2-ctas">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <button type="button" class="cp-yo2-play" data-yo-call data-play="{{ $love['call'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo2-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['call'] }}</span></button>
            </p>
            <p class="cp-yo2-quiet">@unless ($open){{ __('pages.site.course_soon') }} @endunless{{ $love['quiet'] }}</p>
            <h2 class="cp-yo2-label cp-yo2-outcomes-h">{{ $page['titles']['outcomes'] }}</h2>
            <ul class="cp-yo2-outcomes">
                @foreach ((array) $page['outcomes'] as $outcome)
                    <li>{{ $outcome }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="cp-yo2-high" aria-labelledby="throat-h">
        <div class="s-wrap s-sec">
            <p class="cp-yo2-label">{{ $love['throat']['kick'] }}</p>
            <h2 id="throat-h" class="cp-yo2-h2 cp-yo2-h2-head">{{ $love['throat']['title'] }}</h2>
            <div class="cp-yo2-cols">
                <p class="cp-yo2-lede">{{ $love['throat']['lead'] }}@include('site.courses._cites', ['keys' => ['jasa']])</p>
                <p class="cp-yo2-body">{{ $love['throat']['break'] }}@include('site.courses._cites', ['keys' => ['ncvs', 'jasa']])</p>
            </div>
            <div class="cp-yo2-demos">
                <div class="cp-yo2-demo">
                    <h3>{{ $love['throat']['same'] }}</h3>
                    <svg viewBox="0 0 260 110" class="cp-yo2-leap" aria-hidden="true">
                        <path d="M0 58 H260" class="cp-yo2-break" /><text x="258" y="52" class="cp-yo2-break-n">{{ $love['throat']['the_break'] }}</text>
                        <path d="M14 86 H60" class="cp-yo2-chest" /><path d="M72 30 H118" class="cp-yo2-chest" />
                        <path d="M142 86 H188" class="cp-yo2-chest" /><path d="M200 30 H246" class="cp-yo2-head" />
                    </svg>
                    <p class="cp-yo2-btns">
                        <button type="button" class="cp-yo2-play" data-yo-leap="one" data-play="{{ $love['throat']['one_voice'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo2-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['throat']['one_voice'] }}</span></button>
                        <button type="button" class="cp-yo2-play" data-yo-leap="flip" data-play="{{ $love['throat']['flipped'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo2-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['throat']['flipped'] }}</span></button>
                    </p>
                    <p class="cp-yo2-small">{{ $love['throat']['same_note'] }}</p>
                </div>
                <div class="cp-yo2-demo" data-yo-quiz>
                    <h3>{{ $love['throat']['quiz'] }}</h3>
                    <p class="cp-yo2-small">{{ $love['throat']['quiz_lead'] }}</p>
                    <p class="cp-yo2-btns">
                        @foreach (['a', 'b'] as $clip)
                            <button type="button" class="cp-yo2-play" data-yo-clip="{{ $clip }}" data-play="{{ $love['throat']['play_'.$clip] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo2-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['throat']['play_'.$clip] }}</span></button>
                        @endforeach
                    </p>
                    <p class="cp-yo2-btns" role="group" aria-label="{{ $love['throat']['pick'] }}">
                        @foreach (['a', 'b'] as $clip)
                            <button type="button" class="cp-yo2-pick" data-yo-pick="{{ $clip }}" aria-pressed="false">{{ $love['throat']['pick_'.$clip] }}</button>
                        @endforeach
                    </p>
                    <p class="cp-yo2-answer" aria-live="polite"><span data-yo-answer data-right="{{ $love['throat']['right'] }}" data-wrong="{{ $love['throat']['wrong'] }}"></span> <b class="cp-yo2-bonus" data-yo-bonus hidden>{{ $love['throat']['bonus'] }}</b></p>
                    <svg viewBox="0 0 270 90" class="cp-yo2-traces" aria-hidden="true" data-yo-traces>
                        <text x="0" y="22" class="cp-yo2-trace-n">A</text><path data-yo-trace="a" d="M20 70 H120" />
                        <text x="140" y="22" class="cp-yo2-trace-n">B</text><path data-yo-trace="b" d="M160 70 H260" />
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <section class="cp-yo2-low" aria-labelledby="story-h">
        <div class="s-wrap s-sec">
            <p class="cp-yo2-label">{{ $love['story']['kick'] }}</p>
            <h2 id="story-h" class="cp-yo2-h2">{{ $love['story']['title'] }}</h2>
            <p class="cp-yo2-lede">{{ $love['story']['lead'] }}@include('site.courses._cites', ['keys' => ['innsbruck', 'myswitzerland']])</p>
            <ol class="cp-yo2-story">
                @foreach ((array) $love['story']['beats'] as [$year, $text, $cites])
                    <li @class(['cp-yo2-up' => $loop->odd, 'cp-yo2-down' => $loop->even])>
                        <p class="cp-yo2-year">{{ $year }}</p>
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                        @if ($loop->last)
                            <p class="cp-yo2-bignums">@foreach ($love['story']['big'] as [$n, $what])<span><b>{{ $n }}</b> {{ $what }}</span>@endforeach</p>
                            <p class="cp-yo2-small">{{ $love['story']['big_note'] }}@include('site.courses._cites', ['keys' => $love['story']['big_cites']])</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="cp-yo2-high" aria-labelledby="world-h">
        <div class="s-wrap s-sec cp-yo2-world">
            <h2 id="world-h" class="cp-yo2-h2 cp-yo2-h2-head">{{ $love['world']['kick'] }}</h2>
            <ul>
                @foreach ((array) $love['world']['lines'] as [$text, $cites])
                    <li>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="cp-yo2-low" aria-labelledby="horn-h">
        <div class="s-wrap s-sec">
            <p class="cp-yo2-label">{{ $love['horn']['kick'] }}</p>
            <h2 id="horn-h" class="cp-yo2-h2">{{ $love['horn']['title'] }}</h2>
            <p class="cp-yo2-lede">{{ $love['horn']['lead'] }}@include('site.courses._cites', ['keys' => ['myswitzerland']])</p>
            <div class="cp-yo2-horn-row">
            <figure class="cp-yo2-horn" data-yo-horn>
                {{-- The natural notes 8 to 12 of one low C, placed by their cents above the 8th
                     (1200 × log2(n / 8)), over the piano's keys from C to G. --}}
                <svg viewBox="0 0 760 170" aria-hidden="true">
                    <path d="M30 120 H730" class="cp-yo2-horn-axis" />
                    @foreach (['C' => 0, 'D' => 200, 'E' => 400, 'F' => 500, 'F♯' => 600, 'G' => 700] as $key => $cents)
                        <path d="M{{ 30 + $cents }} 112 V128" class="cp-yo2-horn-tick" /><text x="{{ 30 + $cents }}" y="154" class="cp-yo2-horn-key">{{ $key }}</text>
                    @endforeach
                    @foreach ([8 => 0, 9 => 204, 10 => 386, 11 => 551, 12 => 702] as $n => $cents)
                        <g @class(['cp-yo2-horn-note', 'cp-yo2-horn-odd' => $n === 11]) style="--i: {{ $n - 8 }}"><circle cx="{{ 30 + $cents }}" cy="76" r="{{ $n === 11 ? 16 : 11 }}" /><text x="{{ 30 + $cents }}" y="44">{{ $n }}</text><text x="{{ 30 + $cents }}" y="104" class="cp-yo2-horn-cents">{{ $cents }}</text></g>
                    @endforeach
                </svg>
                <figcaption>{{ $love['horn']['between'] }}</figcaption>
            </figure>
            <div>
            <p class="cp-yo2-ctas"><button type="button" class="cp-yo2-play" data-yo-horn-play data-play="{{ $love['horn']['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo2-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['horn']['play'] }}</span></button></p>
            <p class="cp-yo2-body">{{ $love['horn']['note'] }}@include('site.courses._cites', ['keys' => ['hslu19']])</p>
            </div>
            </div>
        </div>
    </section>

    <section class="cp-yo2-high cp-yo2-heart" aria-labelledby="heart-h">
        <div class="s-wrap s-sec">
            <h2 id="heart-h" class="cp-yo2-label">{{ $love['heart']['kick'] }}</h2>
            <p class="cp-yo2-bignum"><b>{{ $love['heart']['big'] }}</b> <span>{{ $love['heart']['big_line'] }}</span></p>
            <p class="cp-yo2-lede">{{ $love['heart']['lead'] }}@include('site.courses._cites', ['keys' => ['hslu21']])</p>
            <p class="cp-yo2-body">{{ $love['heart']['loba'] }}@include('site.courses._cites', ['keys' => ['ranz']])</p>
        </div>
    </section>

    @if ($outline !== null)
        <section class="cp-yo2-low cp-yo2-trailsec" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-yo2-label">{{ $love['trail']['kick'] }}</p>
                <h2 id="units-h" class="cp-yo2-h2">{{ $page['titles']['units'] }}</h2>
                <ol class="cp-yo2-trail">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li @class(['cp-yo2-up' => $loop->even, 'cp-yo2-down' => $loop->odd])>
                            <p class="cp-yo2-label"><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-yo2-small">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            <details class="cp-yo2-more" data-yo-open @if ($loop->first) open @endif>
                                <summary>{{ str_replace(':n', (string) count($unit['lessons']), $love['trail']['more']) }}</summary>
                                <ol class="cp-yo2-lessons">
                                    @foreach ($unit['lessons'] as $lesson)
                                        <li>{{ $lesson }}@if ($loop->parent->first && $loop->first) <a href="{{ $tryUrl }}" class="cp-yo2-free">{{ $love['trail']['free'] }}</a>@endif</li>
                                    @endforeach
                                </ol>
                            </details>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="cp-yo2-high" aria-labelledby="try-h">
        <div class="s-wrap s-sec cp-yo2-flip">
            <div>
                <p class="cp-yo2-label">{{ $love['flip']['kick'] }}</p>
                <h2 id="try-h" class="cp-yo2-h2 cp-yo2-h2-head">{{ $page['titles']['try'] }}</h2>
                <p class="cp-yo2-small">{{ $page['try']['lead'] }}</p>
                <ol class="cp-yo2-steps">
                    @foreach ((array) $page['try']['steps'] as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
                <p class="cp-yo2-small">{{ $page['try']['after'] }}</p>
            </div>
            <div class="cp-yo2-demo cp-yo2-siren" data-yo-siren>
                <h3>{{ $love['flip']['demo'] }}</h3>
                <p class="cp-yo2-small">{{ $love['flip']['demo_lead'] }}</p>
                <div class="cp-yo2-siren-row">
                    <div class="cp-yo2-strip" aria-hidden="true"><svg viewBox="0 0 300 160" preserveAspectRatio="none"><path data-yo-curve="chest" class="cp-yo2-chest" d="" /><path data-yo-curve="head" class="cp-yo2-head" d="" /><path data-yo-curve="break" class="cp-yo2-break" d="" /></svg><span data-yo-note></span></div>
                    <div class="cp-yo2-siren-ctl">
                        <label class="cp-yo2-slider"><span>{{ $love['flip']['label'] }}</span><input type="range" min="0" max="100" value="50" data-yo-break></label>
                        <p class="cp-yo2-btns"><button type="button" class="cp-yo2-play" data-yo-siren-play data-play="{{ $love['flip']['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo2-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['flip']['play'] }}</span></button></p>
                    </div>
                </div>
            </div>
            <div class="cp-yo2-rules">
                <h3 class="cp-yo2-label">{{ $page['titles']['safety'] }}</h3>
                <p class="cp-yo2-safe">@foreach ((array) $page['light']['points'] as $point)<span>{{ $point }}</span>@endforeach</p>
            </div>
        </div>
    </section>

    @include('site.courses._inside')

    <section class="s-wrap s-sec cp-yo2-pack" aria-labelledby="pack-h">
        <h2 id="pack-h" class="cp-yo2-label">{{ $love['pack'] }}</h2>
        <div class="cp-yo2-pack-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul>@foreach ((array) $page['who'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
            <div><h3>{{ __('pages.site.course_songs') }}</h3><p class="cp-yo2-songs">@foreach ((array) $page['songs'] as [$song, $credit])<span><b translate="no">{{ $song }}</b> {{ $credit }}</span>@endforeach</p></div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-yo2-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-yo2-label">{{ $love['fine'] }}</p>
            <h2 id="faq-h" class="cp-yo2-h2">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-yo2-summit" aria-labelledby="join-h">
        <svg class="cp-yo2-summit-range" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true"><path d="M0 120 V34 L180 66 L340 18 L500 80 L660 26 L820 70 L970 8 L1140 76 L1280 30 L1440 70 V120 Z" /></svg>
        <svg class="cp-yo2-answer-horn" viewBox="42 208 76 40" aria-hidden="true"><g transform="translate(180 0) scale(-1 1)"><path d="M70 215 L104 234 Q 112 238 116 230 L120 218 L134 224 L126 236 Q 118 246 104 241 L70 221 Z M65 213 h6 v9 h-6 Z M73 220 L67 245 h3 L76 222 Z M76 222 L82 245 h3 L80 224 Z" /></g></svg>
        <div class="s-wrap s-sec cp-yo2-summit-in">
            <p class="cp-yo2-label">{{ $love['summit']['kick'] }}</p>
            <h2 id="join-h" class="cp-yo2-h2">{{ $page['titles']['join'] }}<span class="cp-yo2-echo" aria-hidden="true">{{ $love['summit']['echo'] }}</span><span class="cp-yo2-echo cp-yo2-echo-far" aria-hidden="true">{{ $love['summit']['echo'] }}</span></h2>
            <p class="cp-yo2-lede">{{ $open ? $love['summit']['line'] : __('pages.site.course_soon') }}</p>
            <p class="cp-yo2-ctas cp-yo2-center">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg cp-yo2-cta">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <button type="button" class="cp-yo2-play" data-yo-call data-play="{{ $love['call'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo2-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['call'] }}</span></button>
            </p>
            @if ($open)
                <p class="cp-yo2-quiet">{{ __('pages.site.course_join_note') }}</p>
            @endif
        </div>
    </section>

    <section class="s-wrap cp-yo2-also" aria-labelledby="also-h">
        <h2 id="also-h" class="cp-yo2-label">{{ $page['titles']['related'] }}</h2>
        @include('site.courses._related')
    </section>

    <section class="s-wrap cp-yo2-notes" aria-labelledby="notes-h">
        <details data-yo-open>
            <summary><h2 id="notes-h" class="cp-yo2-label">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/yodeling.js"></script>
</main>
