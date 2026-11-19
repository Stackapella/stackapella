{{-- Yodeling (/courses/yodeling): Hillel's design of 2026-11-18 (course-designs/yodeling), a climb up a Swiss hiking
     trail; the facts: docs/courses/yodel-love-letter.md. Register as altitude: chest voice on the valley floor, head voice
     above the treeline, and the course's own hook drawn jumping between them, red for chest and blue for head (always
     named too), its echoes fading off to the right. The units are yellow Swiss hiking signs, the harder two with a
     mountain path's white-red-white tip; every section's label is a trail marker, and the page climbs from Gonten to
     the Säntis summit at its close, through real places on the way, each height with its note (`love.alt`). Sections:
     the hero with its signpost and panorama, two voices in one throat (same leap, two voices; flip or slide?), calls from
     alp to alp (a dashed trail of dates), not only the Alps, the alphorn's odd note, wordless by heart, the trail (each
     unit higher than the last), your flip (first steps, a siren whose break you move, singing it safely), what the app
     listens for (two real screens), your pack and questions, the summit, then the related courses and the sources.
     Every sound is made here (js/public/yodeling.js, voices.js on the audio clock), only when pressed. Its drawings:
     App\Support\Pages\YodelingPage. Words: lang/en/site.php courses.pages.yodeling (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($yo = \App\Support\Pages\YodelingPage::class)
<main class="cp cp-yodel cp-yo3" data-yo>
    <section class="cp-yo3-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-yo3-hero-grid">
            <div class="cp-yo3-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                @include('site.courses._yodeling-mark', ['kick' => $page['eyebrow'], 'at' => 'hero'])
                <h1 id="page-h" class="cp-yo3-h1">{{ $page['hero_title'] }}</h1>
                <p class="cp-yo3-lede">{{ $page['hero_lead'] }}</p>
                <p class="cp-yo3-ctas">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <button type="button" class="cp-yo3-play" data-yo-call data-play="{{ $love['call'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo3-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['call'] }}</span></button>
                </p>
                <p class="cp-yo3-quiet">@unless ($open){{ __('pages.site.course_soon') }} @endunless{{ $love['quiet'] }}</p>
            </div>

            @if ($outline !== null)
                {{-- The signpost: the course's board, then a yellow sign per unit pointing up the trail to it. --}}
                <div class="cp-yo3-post">
                    <span class="cp-yo3-pole" aria-hidden="true"></span>
                    <p class="cp-yo3-board"><b>{{ $page['name'] }}</b> <span>{{ $yo::size($outline, $love['sign']['size'], $love['trail']['more']) }}</span></p>
                    <ul class="cp-yo3-signs">
                        @foreach ($outline['units'] as $unit)
                            @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                            <li>
                                <a href="#unit-{{ $loop->iteration }}" @class(['cp-yo3-sign', 'cp-yo3-sign-mountain' => $yo::mountain($loop->iteration)])>
                                    <span><small>{{ $loop->last ? str_replace(':unit', $unitName, $love['sign']['summit']) : $unitName }}</small> <b>{{ $unit['title'] }}</b></span>
                                    <span class="cp-yo3-sign-n">{{ str_replace(':n', (string) count($unit['lessons']), $love['trail']['more']) }}</span>
                                    @if ($yo::mountain($loop->iteration))
                                        <span class="cp-yo3-tip" role="img" aria-label="{{ $love['sign']['mountain'] }}" title="{{ $love['sign']['mountain'] }}"></span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <p class="cp-yo3-key">{{ $love['sign']['key'] }}@include('site.courses._cites', ['keys' => ['trails']])</p>
                </div>
            @endif
        </div>

        <div class="s-wrap cp-yo3-outcomes-wrap">
            <h2 class="cp-yo3-kick">{{ $page['titles']['outcomes'] }}</h2>
            <ol class="cp-yo3-outcomes">
                @foreach ((array) $page['outcomes'] as $outcome)
                    <li>{{ $outcome }}</li>
                @endforeach
            </ol>
        </div>

        {{-- The panorama: head voice's line above the treeline, chest voice's on the valley floor, the call jumping
             between them and the valley's two answers, smaller and further off. Drawn as it sounds when Call is pressed. --}}
        <figure class="cp-yo3-pano" data-yo-valley>
            <svg viewBox="0 0 1440 320" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0 320 V215 L170 160 L330 195 L520 95 L690 175 L860 62 L1040 150 L1210 104 L1440 172 V320 Z" class="cp-yo3-far" />
                <path d="M496 103 L520 95 L540 104 L528 108 L520 104 L508 110 Z M836 70 L860 62 L884 72 L870 77 L860 72 L848 78 Z" class="cp-yo3-snow" />
                <path d="M0 320 V252 L180 220 L380 246 L580 288 L820 300 L1020 282 L1220 240 L1440 206 V320 Z" class="cp-yo3-near" />
                <path d="M0 {{ $yo::HEAD_Y }} H1440" class="cp-yo3-line cp-yo3-head" />
                <path d="M0 {{ $yo::CHEST_Y }} H1440" class="cp-yo3-line cp-yo3-chest" />
                @foreach ([[230, 110, 1.0, 0], [860, 64, .75, 1], [1190, 40, .5, 2]] as [$x, $beat, $rise, $echo])
                    <g class="cp-yo3-call cp-yo3-echo-{{ $echo }}">
                        @foreach ($yo::call($x, $beat, $rise, $echo) as [$d, $voice, $at])
                            <path d="{{ $d }}" class="cp-yo3-seg cp-yo3-{{ $voice }}" style="--at: {{ $at }}s" />
                        @endforeach
                    </g>
                @endforeach
            </svg>
            <span class="cp-yo3-pano-lane cp-yo3-pano-head" aria-hidden="true">{{ $love['pano']['head'] }}<span class="cp-yo3-pano-where"> · {{ $love['pano']['head_where'] }}</span></span>
            <span class="cp-yo3-pano-lane cp-yo3-pano-chest" aria-hidden="true">{{ $love['pano']['chest'] }}<span class="cp-yo3-pano-where"> · {{ $love['pano']['chest_where'] }}</span></span>
            <span class="cp-yo3-pano-note cp-yo3-pano-call" aria-hidden="true">{{ $love['pano']['call'] }}</span>
            <span class="cp-yo3-pano-note cp-yo3-pano-echo" aria-hidden="true">{{ $love['pano']['echo'] }}</span>
            <figcaption class="sr-only">{{ $love['art_note'] }}</figcaption>
        </figure>
    </section>

    <section class="cp-yo3-sky" aria-labelledby="throat-h">
        <div class="s-wrap cp-yo3-sec">
            @include('site.courses._yodeling-mark', ['kick' => $love['throat']['kick'], 'at' => 'throat', 'tone' => 'head'])
            <h2 id="throat-h" class="cp-yo3-h2 cp-yo3-h2-head">{{ $love['throat']['title'] }}</h2>
            <div class="cp-yo3-cols">
                <p class="cp-yo3-lede">{{ $love['throat']['lead'] }}@include('site.courses._cites', ['keys' => ['jasa']])</p>
                <p class="cp-yo3-body">{{ $love['throat']['break'] }}@include('site.courses._cites', ['keys' => ['ncvs', 'jasa']])</p>
            </div>
            <div class="cp-yo3-demos">
                <div class="cp-yo3-demo" data-yo-leaps data-top="chest">
                    <h3>{{ $love['throat']['same'] }}</h3>
                    <svg viewBox="0 0 300 124" class="cp-yo3-leap" aria-hidden="true">
                        <path d="M0 66 H300" class="cp-yo3-break" /><text x="298" y="58" class="cp-yo3-break-n">{{ $love['throat']['the_break'] }}</text>
                        <path d="M40 98 H120" class="cp-yo3-bar cp-yo3-chest" /><text x="40" y="120" class="cp-yo3-bar-n cp-yo3-chest">{{ $love['throat']['top']['chest'] }}</text>
                        <path d="M160 30 H240" class="cp-yo3-bar cp-yo3-top" />
                        <text x="160" y="16" class="cp-yo3-bar-n cp-yo3-top-n cp-yo3-top-chest">{{ $love['throat']['top']['chest'] }}</text>
                        <text x="160" y="16" class="cp-yo3-bar-n cp-yo3-top-n cp-yo3-top-head">{{ $love['throat']['top']['head'] }}</text>
                    </svg>
                    <p class="cp-yo3-btns">
                        <button type="button" class="cp-yo3-play" data-yo-leap="one" data-play="{{ $love['throat']['one_voice'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo3-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['throat']['one_voice'] }}</span></button>
                        <button type="button" class="cp-yo3-play" data-yo-leap="flip" data-play="{{ $love['throat']['flipped'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo3-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['throat']['flipped'] }}</span></button>
                    </p>
                    <p class="cp-yo3-small">{{ $love['throat']['same_note'] }}</p>
                </div>
                <div class="cp-yo3-demo" data-yo-quiz>
                    <h3>{{ $love['throat']['quiz'] }}</h3>
                    <p class="cp-yo3-small">{{ $love['throat']['quiz_lead'] }}</p>
                    <p class="cp-yo3-btns">
                        @foreach (['a', 'b'] as $clip)
                            <button type="button" class="cp-yo3-play" data-yo-clip="{{ $clip }}" data-play="{{ $love['throat']['play_'.$clip] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo3-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['throat']['play_'.$clip] }}</span></button>
                        @endforeach
                    </p>
                    <p class="cp-yo3-btns" role="group" aria-label="{{ $love['throat']['pick'] }}">
                        @foreach (['a', 'b'] as $clip)
                            <button type="button" class="cp-yo3-pick" data-yo-pick="{{ $clip }}" aria-pressed="false">{{ $love['throat']['pick_'.$clip] }}</button>
                        @endforeach
                    </p>
                    <p class="cp-yo3-answer" aria-live="polite"><span data-yo-answer data-right="{{ $love['throat']['right'] }}" data-wrong="{{ $love['throat']['wrong'] }}"></span> <b class="cp-yo3-bonus" data-yo-bonus hidden>{{ $love['throat']['bonus'] }}</b></p>
                    <svg viewBox="0 0 270 80" class="cp-yo3-traces" aria-hidden="true" data-yo-traces>
                        <text x="0" y="20" class="cp-yo3-trace-n">A</text><path data-yo-trace="a" d="M20 62 H120" />
                        <text x="140" y="20" class="cp-yo3-trace-n">B</text><path data-yo-trace="b" d="M160 62 H260" />
                    </svg>
                </div>
            </div>
        </div>
    </section>

    <section class="cp-yo3-warm" aria-labelledby="story-h">
        <div class="s-wrap cp-yo3-sec">
            @include('site.courses._yodeling-mark', ['kick' => $love['story']['kick'], 'at' => 'story'])
            <h2 id="story-h" class="cp-yo3-h2">{{ $love['story']['title'] }}</h2>
            <p class="cp-yo3-lede">{{ $love['story']['lead'] }}@include('site.courses._cites', ['keys' => ['innsbruck', 'myswitzerland']])</p>
            <ol class="cp-yo3-story">
                @foreach ((array) $love['story']['beats'] as [$year, $text, $cites])
                    <li style="--i: {{ $loop->iteration }}">
                        <span class="cp-yo3-diamond" aria-hidden="true"></span>
                        <p class="cp-yo3-year">{{ $year }}</p>
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                        @if ($loop->last)
                            <p class="cp-yo3-bignums">@foreach ($love['story']['big'] as [$n, $what])<span><b>{{ $n }}</b> {{ $what }}</span>@endforeach</p>
                            <p class="cp-yo3-small">{{ $love['story']['big_note'] }}@include('site.courses._cites', ['keys' => $love['story']['big_cites']])</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="cp-yo3-sky" aria-labelledby="world-h">
        <div class="s-wrap cp-yo3-sec">
            <h2 id="world-h" class="cp-yo3-h2 cp-yo3-h2-head">{{ $love['world']['kick'] }}</h2>
            <ul class="cp-yo3-world">
                @foreach ((array) $love['world']['lines'] as [$where, $text, $cites])
                    <li><p class="cp-yo3-where">{{ $where }}</p><p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p></li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="cp-yo3-warm" aria-labelledby="horn-h">
        <div class="s-wrap cp-yo3-sec">
            @include('site.courses._yodeling-mark', ['kick' => $love['horn']['kick'], 'at' => 'horn'])
            <h2 id="horn-h" class="cp-yo3-h2">{{ $love['horn']['title'] }}</h2>
            <p class="cp-yo3-lede">{{ $love['horn']['lead'] }}@include('site.courses._cites', ['keys' => ['myswitzerland']])</p>
            <div class="cp-yo3-horn-row">
                <figure class="cp-yo3-horn" data-yo-horn>
                    <figcaption class="cp-yo3-kick">{{ $love['horn']['scale'] }}</figcaption>
                    <svg viewBox="0 0 760 196" aria-hidden="true">
                        <path d="M30 128 H730" class="cp-yo3-horn-axis" />
                        @foreach ($yo::keys() as $key => $cents)
                            <path d="M{{ 30 + $cents }} 120 V136" class="cp-yo3-horn-tick" /><text x="{{ 30 + $cents }}" y="160" class="cp-yo3-horn-key">{{ $key }}</text>
                        @endforeach
                        @foreach ($yo::hornNotes() as $n => $cents)
                            <g @class(['cp-yo3-horn-note', 'cp-yo3-horn-odd' => $n === 11]) style="--i: {{ $n - 8 }}"><circle cx="{{ 30 + $cents }}" cy="80" r="{{ $n === 11 ? 17 : 12 }}" /><text x="{{ 30 + $cents }}" y="46">{{ $n }}</text><text x="{{ 30 + $cents }}" y="110" class="cp-yo3-horn-cents">{{ $cents }}</text></g>
                        @endforeach
                        <path d="M{{ 30 + $yo::hornNotes()[11] }} 140 V172" class="cp-yo3-horn-gap" />
                        <text x="{{ 30 + $yo::hornNotes()[11] }}" y="190" class="cp-yo3-horn-nokey">{{ $love['horn']['nokey'] }}</text>
                    </svg>
                    <p class="sr-only">{{ $love['horn']['between'] }}</p>
                </figure>
                <div>
                    <p class="cp-yo3-ctas"><button type="button" class="cp-yo3-play" data-yo-horn-play data-play="{{ $love['horn']['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo3-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['horn']['play'] }}</span></button></p>
                    <p class="cp-yo3-body">{{ $love['horn']['note'] }}@include('site.courses._cites', ['keys' => ['hslu19']])</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cp-yo3-sky" aria-labelledby="heart-h">
        <div class="s-wrap cp-yo3-sec cp-yo3-heart">
            <div>
                @include('site.courses._yodeling-mark', ['kick' => $love['heart']['kick'], 'at' => 'heart', 'tone' => 'head', 'id' => 'heart-h'])
                <p class="cp-yo3-big">{{ $love['heart']['big'] }}</p>
                <p class="cp-yo3-big-line">{{ $love['heart']['big_line'] }}@include('site.courses._cites', ['keys' => ['hslu21']])</p>
                <p class="cp-yo3-body">{{ $love['heart']['lead'] }}@include('site.courses._cites', ['keys' => ['hslu21']])</p>
                <p class="cp-yo3-small">{{ $love['heart']['loba'] }}@include('site.courses._cites', ['keys' => ['ranz']])</p>
            </div>
            <p class="cp-yo3-loba" aria-hidden="true">@foreach ((array) $love['heart']['echo'] as $word)<span style="--i: {{ $loop->index }}">{{ $word }}</span>@endforeach</p>
        </div>
    </section>

    @if ($outline !== null)
        <section class="cp-yo3-warm" id="trail" aria-labelledby="units-h">
            <div class="s-wrap cp-yo3-sec">
                @include('site.courses._yodeling-mark', ['kick' => $love['trail']['kick'], 'at' => 'trail'])
                <h2 id="units-h" class="cp-yo3-h2">{{ $page['titles']['units'] }}</h2>
                <ol class="cp-yo3-trail">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li id="unit-{{ $loop->iteration }}" style="--i: {{ $loop->index }}">
                            <p @class(['cp-yo3-sign', 'cp-yo3-sign-mountain' => $yo::mountain($loop->iteration)])>
                                <span><b><x-site.skin :game="$stage" :serious="$loop->last ? str_replace(':unit', $unitName, $love['sign']['summit']) : $unitName" /></b></span>
                                <span class="cp-yo3-sign-n">{{ str_replace(':n', (string) count($unit['lessons']), $love['trail']['more']) }}</span>
                                @if ($yo::mountain($loop->iteration))
                                    <span class="cp-yo3-tip" role="img" aria-label="{{ $love['sign']['mountain'] }}" title="{{ $love['sign']['mountain'] }}"></span>
                                @endif
                            </p>
                            <div class="cp-yo3-unit">
                                <h3>{{ $unit['title'] }}</h3>
                                @isset($page['units_say'][$loop->index])
                                    <p class="cp-yo3-small">{{ $page['units_say'][$loop->index] }}</p>
                                @endisset
                                <ol class="cp-yo3-lessons">
                                    @foreach ($unit['lessons'] as $lesson)
                                        <li>{{ $lesson }}@if ($loop->parent->first && $loop->first) <a href="{{ $tryUrl }}" class="cp-yo3-free">{{ $love['trail']['free'] }}</a>@endif</li>
                                    @endforeach
                                </ol>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="cp-yo3-sky" aria-labelledby="try-h">
        <div class="s-wrap cp-yo3-sec">
            <div class="cp-yo3-flip">
                <div>
                    @include('site.courses._yodeling-mark', ['kick' => $love['flip']['kick'], 'at' => 'flip', 'tone' => 'head'])
                    <h2 id="try-h" class="cp-yo3-h2 cp-yo3-h2-head">{{ $page['titles']['try'] }}</h2>
                    <p class="cp-yo3-small">{{ $page['try']['lead'] }}</p>
                    <ol class="cp-yo3-steps">
                        @foreach ((array) $page['try']['steps'] as $step)
                            <li>{{ $step }}</li>
                        @endforeach
                    </ol>
                    <p class="cp-yo3-small">{{ $page['try']['after'] }}</p>
                </div>
                <div class="cp-yo3-demo cp-yo3-siren" data-yo-siren>
                    <h3>{{ $love['flip']['demo'] }}</h3>
                    <p class="cp-yo3-small">{{ $love['flip']['demo_lead'] }}</p>
                    <div class="cp-yo3-strip" aria-hidden="true"><svg viewBox="0 0 300 160" preserveAspectRatio="none"><path data-yo-curve="break" class="cp-yo3-break" d="" /><path data-yo-curve="chest" class="cp-yo3-chest" d="" /><path data-yo-curve="head" class="cp-yo3-head" d="" /></svg><span data-yo-note></span></div>
                    <p class="cp-yo3-legend" aria-hidden="true"><span class="cp-yo3-chest">{{ $love['lanes']['chest'] }}</span><span class="cp-yo3-head">{{ $love['lanes']['head'] }}</span></p>
                    <label class="cp-yo3-slider"><span>{{ $love['flip']['label'] }}</span><input type="range" min="0" max="100" value="50" data-yo-break></label>
                    <p class="cp-yo3-btns"><button type="button" class="cp-yo3-play" data-yo-siren-play data-play="{{ $love['flip']['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo3-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['flip']['play'] }}</span></button></p>
                </div>
            </div>
            <div class="cp-yo3-rules">
                <h3 class="cp-yo3-kick">{{ $page['titles']['safety'] }}</h3>
                <ul>@foreach ((array) $page['light']['points'] as $point)<li>{{ $point }}</li>@endforeach</ul>
            </div>
        </div>
    </section>

    <section class="cp-yo3-night" aria-labelledby="inside-h">
        <div class="s-wrap cp-yo3-sec cp-yo3-app">
            <div>
                <h2 id="inside-h" class="cp-yo3-h2">{{ $page['titles']['inside'] }}</h2>
                <p class="cp-yo3-lede">{{ $page['measures'] }}</p>
                <p class="cp-yo3-body"><x-site.say :text="$page['scoring']" /></p>
                <p class="cp-yo3-meter" aria-hidden="true">@foreach (range(1, 8) as $flip)<span @class(['cp-yo3-meter-on' => $flip <= 6])></span>@endforeach</p>
            </div>
            <div class="cp-yo3-shots">
                @foreach ((array) ($page['shots'] ?? []) as $alt)
                    @php($shotId = 'course-'.$page['slug'].'--'.$loop->iteration)
                    <x-site.appshot :id="$shotId" :alt="$alt" />
                @endforeach
            </div>
        </div>
    </section>

    <section class="cp-yo3-paper" aria-labelledby="pack-h">
        <div class="s-wrap cp-yo3-sec">
            @include('site.courses._yodeling-mark', ['kick' => $love['pack'], 'at' => 'pack', 'id' => 'pack-h'])
            <div class="cp-yo3-pack">
                <div><h3>{{ $page['titles']['who'] }}</h3><ul class="cp-yo3-dash">@foreach ((array) $page['who'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
                <div><h3>{{ __('pages.site.course_songs') }}</h3><ul class="cp-yo3-songs">@foreach ((array) $page['songs'] as [$song, $credit])<li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>@endforeach</ul></div>
            </div>
            <div class="cp-yo3-fine">
                <div>
                    <p class="cp-yo3-kick">{{ $love['fine'] }}</p>
                    <h2 id="faq-h" class="cp-yo3-h2">{{ $page['titles']['faq'] }}</h2>
                </div>
                @include('site.courses._faq')
            </div>
        </div>
    </section>

    <section class="cp-yo3-night cp-yo3-summit" aria-labelledby="join-h">
        <svg class="cp-yo3-summit-range" viewBox="0 0 1440 60" preserveAspectRatio="none" aria-hidden="true"><path d="M0 0 H1440 V18 L1300 40 L1150 22 L980 58 L860 30 L720 4 L560 46 L390 30 L260 52 L120 34 L0 60 Z" /></svg>
        <div class="s-wrap cp-yo3-summit-in">
            {{-- The summit cross, as the Säntis's top has one. --}}
            <svg class="cp-yo3-cross" viewBox="0 0 40 64" aria-hidden="true"><path d="M17 0 H23 V16 H38 V22 H23 V64 H17 V22 H2 V16 H17 Z" /></svg>
            @include('site.courses._yodeling-mark', ['kick' => $love['summit']['kick'], 'at' => 'summit'])
            <h2 id="join-h" class="cp-yo3-h2">{{ $page['titles']['join'] }}</h2>
            <p class="cp-yo3-echo" aria-hidden="true"><span>{{ $love['summit']['echo'] }}</span><span>{{ $love['summit']['echo'] }}</span></p>
            <p class="cp-yo3-lede">{{ $open ? $love['summit']['line'] : __('pages.site.course_soon') }}</p>
            <p class="cp-yo3-ctas cp-yo3-center">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <button type="button" class="cp-yo3-play" data-yo-call data-play="{{ $love['call'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-yo3-play-icon" aria-hidden="true"></span><span data-yo-label>{{ $love['call'] }}</span></button>
            </p>
            @if ($open)
                <p class="cp-yo3-quiet">{{ __('pages.site.course_join_note') }}</p>
            @endif
        </div>
    </section>

    <section class="cp-yo3-paper cp-yo3-end" aria-labelledby="also-h">
        <div class="s-wrap">
            <h2 id="also-h" class="cp-yo3-kick">{{ $page['titles']['related'] }}</h2>
            @include('site.courses._related')
            <details class="cp-yo3-notes" data-yo-open>
                <summary><h2 id="notes-h" class="cp-yo3-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
                <ol>
                    @foreach ($sources as [$label, $url])
                        <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                    @endforeach
                </ol>
            </details>
        </div>
    </section>
    <script type="module" src="/js/public/yodeling.js"></script>
</main>
