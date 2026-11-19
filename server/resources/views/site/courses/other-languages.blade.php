{{-- Singing in another language (/courses/other-languages), its own page after Hillel's design (2026-11-18,
     ~/Code/course-designs/other-languages): the look of a diction primer and a chant book. A book serif with italics
     (EB Garamond, the site's serif behind it), the site's monospace for syllables and respellings, the device's own
     Japanese serif for kana; the labels in one red ink; Japanese, Spanish, church Latin and Ukrainian each a hue,
     carried through the four first lines, the outcomes, the units, the archive's tags and the margin notes. The
     syllable boxes run through the page: the hero's mora map (Tōkyō, Ōsaka, Nagasaki, four morae each), each song's
     real opening line from its template (a box a sung syllable, as long as its notes), Murphy's DOE-MEE-NAY and the
     close's "Sa ku ra". Two demos on voices.js, only when pressed and on the audio clock (js/public/other-languages.js):
     One mora, one note (ka-gi-ri as three morae or merged, obasan against obāsan) and Shchedryk's figure up the
     course's speed ladder, in tune or drifting flat. Every fact keeps its numbered notes and the one Sources list.
     Every lesson is listed in its unit, linked to its lesson page once the course has them (then the shared list
     below the page is left out). Game skin: the same pieces in the arcade's frames and neon. Words: lang/en/site.php
     courses.pages.other-languages (`love`); what the view reads from elsewhere or measures:
     App\Support\Pages\OtherLanguagesPage. --}}
@php($ol = \App\Support\Pages\OtherLanguagesPage::class)
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($names = $love['names'])
@php($map = $love['map'])
@php($demo = $love['demo'])
@php($plain = $love['plain'])
@php($ladder = $love['ladder'])
@php($lines = $ol::firstLines((array) $love['lines']['rows']))
@php($units = $outline !== null ? $ol::units($outline) : [])
@php($linked = $ol::linksEveryLesson($units))
@php($rungs = $ol::ladder())
@php($glide = $ol::glide((string) $plain['glide']))
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@if ($linked)
    @section('lesson-pages-inline', '1')
@endif
<main class="cp-ol" data-ol>
    <section class="cp-ol-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-ol-hero-grid">
            <div class="cp-ol-hero-copy">
                <x-site.crumbs :trail="[[__('pages.site.courses'), '/courses'], [$page['name']]]" />
                <p class="cp-ol-pills">
                    <span class="cp-ol-chip"><b>{{ __('pages.site.course_chip_free') }}</b>@if ($outline !== null)<span>{{ __('pages.site.course_chip_lessons', ['n' => $outline['lessons']]) }}</span>@endif</span>
                    <span class="cp-ol-codes" role="img" aria-label="{{ $love['codes_label'] }}">
                        @foreach ((array) $love['codes'] as $lang => $code)
                            <span class="cp-ol-{{ $lang }}" aria-hidden="true">{{ $code }}</span>
                        @endforeach
                    </span>
                </p>
                <h1 id="page-h" class="cp-ol-h1">@foreach ($ol::marked((string) $love['title']) as $piece)<span class="cp-ol-mk-{{ $piece['kind'] }}">{{ $piece['text'] }}</span>@endforeach</h1>
                <p class="cp-ol-lede">{{ $page['hero_lead'] }}</p>
                <ul class="cp-ol-facts">
                    @foreach ((array) $page['hero_facts'] as $fact)
                        <li>{{ $fact }}</li>
                    @endforeach
                </ul>
                <p class="cp-ol-btns">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="{{ $outline !== null ? '#units-h' : '#outcomes-h' }}" class="btn-secondary btn-lg">{{ $outline !== null ? $page['see_lessons'] : $page['see_aims'] }}</a>
                </p>
                @unless ($open)
                    <p class="cp-ol-quiet">{{ __('pages.site.course_soon') }}</p>
                @endunless
            </div>
            <figure class="cp-ol-fig">
                <p class="cp-ol-fig-head"><span>{{ $map['fig'] }}</span><span class="cp-ol-ja">{{ $love['codes']['ja'] }}</span></p>
                <ol class="cp-ol-words">
                    @foreach ((array) $map['words'] as [$word, $kana, $morae, $syllables])
                        <li>
                            <p class="cp-ol-word-name"><span>{{ $word }}</span><span class="cp-ol-kana" lang="ja">{{ $kana }}</span><span class="cp-ol-word-count">{{ str_replace(':n', (string) count($syllables), $map['count']) }}</span></p>
                            <span class="cp-ol-morae" aria-hidden="true">
                                @foreach ($morae as $mora)
                                    <span class="cp-ol-mora">{{ $mora }}</span>
                                @endforeach
                                @foreach ($syllables as $span)
                                    <i class="cp-ol-brace" style="--span: {{ $span }}"></i>
                                @endforeach
                            </span>
                        </li>
                    @endforeach
                </ol>
                <figcaption>
                    <ul class="cp-ol-legend">
                        <li><i class="cp-ol-key-mora" aria-hidden="true"></i>{{ $map['legend'][0] }}</li>
                        <li><i class="cp-ol-key-brace" aria-hidden="true"></i>{{ $map['legend'][1] }}</li>
                    </ul>
                    <p class="cp-ol-fig-note">{{ $map['note'] }}@include('site.courses._cites', ['keys' => ['mora']])</p>
                </figcaption>
            </figure>
        </div>
    </section>

    @if ($lines !== [])
        <section class="s-wrap cp-ol-sec cp-ol-lines" aria-labelledby="lines-h">
            <div class="cp-ol-lines-head">
                <div>
                    <p class="cp-ol-kick">{{ $love['lines']['kick'] }}</p>
                    <h2 id="lines-h" class="cp-ol-h2">{{ $love['lines']['title'] }}</h2>
                </div>
                <p class="cp-ol-small">{{ $love['lines']['lead'] }}</p>
            </div>
            <ol class="cp-ol-linelist">
                @foreach ($lines as $line)
                    <li class="cp-ol-line cp-ol-{{ $line['lang'] }}">
                        <p class="cp-ol-line-name"><span class="cp-ol-tag">0{{ $loop->iteration }} · {{ $names[$line['lang']] }}</span><span class="cp-ol-song">{{ $line['song'] }}</span></p>
                        {{-- No lang="la" on the Latin: EB Garamond's Latin forms would write its u as v ("rvm"). --}}
                        <ol class="cp-ol-boxes" @if ($line['lang'] !== 'la') lang="{{ $line['lang'] }}" @endif>
                            @foreach ($line['items'] as $item)
                                @if ($item['text'] === null)
                                    <li class="cp-ol-rest" style="--len: {{ $item['len'] }}" aria-hidden="true"></li>
                                @else
                                    <li class="cp-ol-box{{ $item['tie'] ? ' cp-ol-tie' : '' }}" style="--len: {{ $item['len'] }}">
                                        <span class="cp-ol-box-in">{{ $item['text'] }}@foreach ($item['marks'] as $mark)<i style="--at: {{ round($mark / $item['len'], 4) }}" aria-hidden="true"></i>@endforeach</span>
                                        <small lang="{{ app()->getLocale() }}">{{ $item['hint'] }}</small>
                                    </li>
                                @endif
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="s-wrap cp-ol-demosec" aria-labelledby="demo-h">
        <div class="cp-ol-deck" data-ol-demo data-ready="{{ $demo['ready'] }}" data-after="{{ $demo['after'] }}"
            data-say-three="{{ $demo['say']['three'] }}" data-say-merged="{{ $demo['say']['merged'] }}" data-say-obasan="{{ $demo['say']['obasan'] }}"
            data-name-three="{{ $demo['buttons']['three'] }}" data-name-merged="{{ $demo['buttons']['merged'] }}" data-name-obasan="{{ $demo['buttons']['obasan'] }}"
            data-kana-three="{{ $demo['kana']['three'] }}" data-kana-merged="{{ $demo['kana']['merged'] }}" data-kana-obasan="{{ $demo['kana']['obasan'] }}">
            <p class="cp-ol-deck-bar"><span class="cp-ol-live"><i aria-hidden="true"></i>{{ $demo['live'] }}</span><span>{{ $demo['tag'] }}</span></p>
            <div class="cp-ol-stage">
                <p class="cp-ol-read" aria-hidden="true"><b data-ol-mode>{{ $demo['buttons']['three'] }}</b><span data-ol-kana lang="ja">{{ $demo['kana']['three'] }}</span></p>
                <svg viewBox="0 0 640 300" aria-hidden="true">
                    {{-- Three morae and merged: the notes on their pitches, each fresh start a dot. --}}
                    @foreach (['three', 'merged'] as $view)
                        <g data-ol-view="{{ $view }}" @if ($view !== 'three') hidden @endif>
                            @foreach (['G4' => 80, 'Eb4' => 200, 'D4' => 230] as $pitch => $y)
                                <path d="M64 {{ $y }} H626" class="cp-ol-staff" />
                                <text x="54" y="{{ $y + 5 }}" text-anchor="end" class="cp-ol-pitch">{{ $demo['notes'][$pitch] }}</text>
                            @endforeach
                            @if ($view === 'three')
                                <path d="M{{ $ol::beatX(0) + 6 }} 80 H{{ $ol::beatX(1) - 8 }}" class="cp-ol-tone" data-ol-on="three-0" />
                                <path d="M{{ $ol::beatX(1) + 6 }} 80 H{{ $ol::beatX(1.5) }} V200 H{{ $ol::beatX(2) - 8 }}" class="cp-ol-tone" data-ol-on="three-1" />
                                <path d="M{{ $ol::beatX(2) + 6 }} 230 H{{ $ol::beatX(3.25) - 6 }}" class="cp-ol-tone" data-ol-on="three-2" />
                                @foreach ([[0, 80], [1, 80], [2, 230]] as [$beat, $y])
                                    <circle cx="{{ $ol::beatX($beat) + 6 }}" cy="{{ $y }}" r="9" class="cp-ol-start" data-ol-on="three-{{ $loop->index }}" />
                                    <text x="{{ $ol::beatX($beat) + 6 }}" y="{{ $y + 42 }}" text-anchor="middle" class="cp-ol-syl" data-ol-on="three-{{ $loop->index }}">{{ $demo['syllables']['three'][$loop->index] }}</text>
                                @endforeach
                            @else
                                <path d="M{{ $ol::beatX(0) + 6 }} 80 H{{ $ol::beatX(1.5) }} V200 H{{ $ol::beatX(2) - 8 }}" class="cp-ol-tone" data-ol-on="merged-0" />
                                <path d="M{{ $ol::beatX(2) + 6 }} 230 H{{ $ol::beatX(3.25) - 6 }}" class="cp-ol-tone" data-ol-on="merged-1" />
                                <circle cx="{{ $ol::beatX(1) + 6 }}" cy="80" r="9" class="cp-ol-ghost" />
                                @foreach ([[0, 80], [2, 230]] as [$beat, $y])
                                    <circle cx="{{ $ol::beatX($beat) + 6 }}" cy="{{ $y }}" r="9" class="cp-ol-start" data-ol-on="merged-{{ $loop->index }}" />
                                    <text x="{{ $ol::beatX($beat) + 6 }}" y="{{ $y + 42 }}" text-anchor="middle" class="cp-ol-syl" data-ol-on="merged-{{ $loop->index }}">{{ $demo['syllables']['merged'][$loop->index] }}</text>
                                @endforeach
                            @endif
                        </g>
                    @endforeach
                    {{-- Obasan against obāsan: half-beat slots on one note, the ā over two, the n a hum. --}}
                    <g data-ol-view="obasan" hidden>
                        @foreach (['obasan' => [[0, 1], [1, 1], [2, 1], [3, 1]], 'obaasan' => [[0, 1], [1, 2], [3, 1], [4, 1]]] as $word => $cells)
                            @php($y = $loop->first ? 52 : 184)
                            <text x="70" y="{{ $y }}" class="cp-ol-wordname">{{ $demo['words'][$word] }}</text>
                            @foreach ($cells as [$slot, $span])
                                <rect x="{{ $ol::slotX($slot) + 4 }}" y="{{ $y + 14 }}" width="{{ $span * 110 - 8 }}" height="50" rx="6" class="cp-ol-slot {{ $loop->last ? 'cp-ol-slot-n' : '' }}" data-ol-on="{{ $word }}-{{ $loop->index }}" />
                                <text x="{{ $ol::slotX($slot) + $span * 55 }}" y="{{ $y + 47 }}" text-anchor="middle" class="cp-ol-slotname" data-ol-on="{{ $word }}-{{ $loop->index }}">{{ $demo['syllables'][$word][$loop->index] }}</text>
                            @endforeach
                        @endforeach
                    </g>
                </svg>
                <button type="button" class="cp-ol-big" data-ol-play="tour" data-play="{{ $demo['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-ol-big-icon" aria-hidden="true"></span><span data-label>{{ $demo['play'] }}</span></button>
            </div>
            <div class="cp-ol-deck-foot">
                <div>
                    <h2 id="demo-h" class="cp-ol-deck-h">{{ $demo['title'] }}</h2>
                    <p class="cp-ol-deck-lead">{{ $demo['lead'] }}</p>
                    <p class="cp-ol-deck-lead">{{ $demo['how'] }}@include('site.courses._cites', ['keys' => ['mora', 'jphon']])</p>
                </div>
                <div>
                    <fieldset class="cp-ol-pick">
                        <legend>{{ $demo['one_label'] }}</legend>
                        @foreach ((array) $demo['buttons'] as $kind => $name)
                            <button type="button" class="cp-ol-play {{ $loop->last ? 'cp-ol-play-b' : '' }}" data-ol-play="{{ $kind }}" data-play="{{ $name }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-ol-play-icon" aria-hidden="true"></span><span data-label>{{ $name }}</span></button>
                        @endforeach
                    </fieldset>
                    <p class="cp-ol-say" role="status" data-ol-say>{{ $demo['ready'] }}</p>
                    <p class="cp-ol-dkeys">
                        @foreach ((array) $demo['key'] as $key)
                            <span><i class="cp-ol-dkey-{{ ['note', 'start', 'hum'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-ol-deck-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-ol-sec" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="cp-ol-kick">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-ol-goals">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li class="cp-ol-{{ $ol::LANGUAGES[$loop->index] ?? 'red' }}"><span class="cp-ol-goal-n" aria-hidden="true">{{ $ol::NUMERALS[$loop->index] ?? $loop->iteration }}.</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap cp-ol-sec cp-ol-plain" aria-labelledby="plain-h">
        <div class="cp-ol-plain-head">
            <div>
                <p class="cp-ol-kick">{{ $love['kicks']['plain'] }}</p>
                @php($plainTitle = $ol::tail((string) $plain['title']))
                <h2 id="plain-h" class="cp-ol-h2 cp-ol-h2-big">{{ $plainTitle[0] }} <em>{{ $plainTitle[1] }}</em></h2>
                @if ($glide !== null)
                    <p class="cp-ol-glide" aria-hidden="true">
                        <span><b>{{ $glide['from'] }}</b><small>{{ $glide['word'] }}</small></span>
                        <i></i>
                        <span><b>{{ $glide['to'] }}</b><small>{{ $plain['glide_label'] }}</small></span>
                    </p>
                @endif
            </div>
            <div>
                <p class="cp-ol-body">{{ $plain['glides'] }}@include('site.courses._cites', ['keys' => ['diph', 'jphon', 'sphon', 'uphon']])</p>
                <p class="cp-ol-body">{{ $plain['schwa'] }}@include('site.courses._cites', ['keys' => ['stress', 'sphon']])</p>
            </div>
        </div>
        <h3 class="cp-ol-h3">{{ $plain['asks_title'] }}</h3>
        <ol class="cp-ol-asks">
            @foreach ((array) $plain['asks'] as [$big, $what, $text, $cites])
                <li class="cp-ol-{{ $ol::hue($ol::ASKS[$loop->index] ?? 'red') }}">
                    <p class="cp-ol-ask-big">{{ $big }}</p>
                    <h4><b>{{ $what }}</b><span>{{ $plain['asks_tags'][$loop->index] ?? '' }}</span></h4>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="cp-ol-archive" aria-labelledby="archive-h">
        <div class="s-wrap cp-ol-sec">
            <p class="cp-ol-kick cp-ol-center">{{ $love['kicks']['archive'] }}</p>
            <h2 id="archive-h" class="cp-ol-h2 cp-ol-h2-big cp-ol-center">@foreach ($ol::years((string) $love['archive']['title']) as [$bit, $isYear])<span @class(['cp-ol-year' => $isYear])>{{ $bit }}</span>@endforeach</h2>
            <div class="cp-ol-page">
                {{-- The first two entries, then the rest folded on a phone (open on a wide screen). --}}
                @php($rows = (array) $love['archive']['rows'])
                @foreach ([array_slice($rows, 0, 2, true), array_slice($rows, 2, null, true)] as $part)
                    @if ($loop->last && ! $loop->first)
                        <details class="cp-ol-more" data-ol-wide><summary><span>{{ $love['archive']['more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
                    @endif
                    <ol class="cp-ol-years" start="{{ $loop->first ? 1 : 3 }}">
                        @foreach ($part as $i => [$year, $place, $title, $text, $cites])
                            @php($tag = $ol::ARCHIVE[$i] ?? 'en')
                            <li>
                                <p class="cp-ol-when"><span class="cp-ol-year">{{ $year }}</span><b>{{ $place }}</b><span class="cp-ol-tag cp-ol-{{ $ol::hue($tag) }}">{{ $names[$tag] ?? '' }}</span></p>
                                <div>
                                    <h3>{{ $title }}</h3>
                                    @if ($year === '1912')
                                        <ol class="cp-ol-boxes cp-ol-murphy cp-ol-la" aria-hidden="true">
                                            @foreach ($ol::pairs((string) $love['archive']['murphy'][0], (string) $love['archive']['murphy'][1]) as [$sung, $spelled])
                                                <li class="cp-ol-box" style="--len: 1"><small>{{ $sung }}</small><span class="cp-ol-box-in">{{ $spelled }}</span></li>
                                            @endforeach
                                        </ol>
                                    @endif
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
        </div>
    </section>

    @if ($units !== [])
        <section class="s-wrap cp-ol-sec cp-ol-course" aria-labelledby="units-h">
            <p class="cp-ol-kick">{{ $love['kicks']['climb'] }}</p>
            @php($unitsTitle = $ol::tail((string) $page['titles']['units']))
            <h2 id="units-h" class="cp-ol-h2">{{ $unitsTitle[0] }} <em>{{ $unitsTitle[1] }}</em></h2>
            <p class="cp-ol-count">{{ __('pages.site.course_size', ['units' => count($units), 'lessons' => $outline['lessons']]) }}</p>
            <ol class="cp-ol-units">
                @foreach ($units as $unit)
                    @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                    @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                    <li class="cp-ol-unit cp-ol-{{ $unit['lang'] }}" data-unit="{{ $loop->iteration }}" style="--unit: {{ $loop->iteration }}">
                        <p class="cp-ol-unit-head"><span class="cp-ol-unit-script" @if ($unit['lang'] !== 'la') lang="{{ $unit['lang'] }}" @endif>{{ $love['scripts'][$loop->index] ?? '' }}</span><span class="cp-ol-unit-n"><x-site.skin :game="$stage" :serious="$unitName" /></span></p>
                        <div class="cp-ol-unit-body">
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-ol-small">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            <ol class="cp-ol-lessons">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li>
                                        @if ($lesson['url'] !== null)
                                            <a href="{{ $lesson['url'] }}"><span class="cp-ol-lesson-n">{{ $lesson['n'] }}</span><span>{{ $lesson['name'] }}</span></a>
                                        @else
                                            <span class="cp-ol-lesson-n">{{ $lesson['n'] }}</span><span>{{ $lesson['name'] }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="cp-ol-laddersec" aria-labelledby="ladder-h">
        <div class="s-wrap cp-ol-sec cp-ol-ladder-grid">
            <div class="cp-ol-ladder-text">
                <p class="cp-ol-kick cp-ol-uk">{{ $love['kicks']['ladder'] }} · {{ $names['uk'] }}</p>
                @php($ladderTitle = $ol::tail((string) $ladder['title']))
                <h2 id="ladder-h" class="cp-ol-h2 cp-ol-h2-big">{{ $ladderTitle[0] }} <em>{{ $ladderTitle[1] }}</em></h2>
                <p class="cp-ol-body">{{ $ladder['lead'] }}</p>
                <p class="cp-ol-small">{{ $ladder['more'] }}</p>
            </div>
            <div class="cp-ol-deck cp-ol-ladder" data-ol-ladder data-drift="true" data-figure="{{ json_encode($ol::figureNotes()) }}" data-ladder="{{ json_encode($ol::LADDER) }}" data-ready="{{ $ladder['ready'] }}" data-round="{{ $ladder['round'] }}" data-low="{{ $ladder['low'] }}"
                data-end-true="{{ $ladder['end_true'] }}" data-end-flat="{{ $ladder['end_flat'] }}" data-say-fast="{{ $ladder['say_fast'] }}">
                <p class="cp-ol-deck-bar"><span class="cp-ol-live"><i aria-hidden="true"></i>{{ $ladder['kick'] }}</span><span>{{ $ladder['tag'] }}</span></p>
                <div class="cp-ol-ladder-in">
                    <h3 class="cp-ol-deck-h cp-ol-deck-h-s">{{ $ladder['deck_title'] }}</h3>
                    <p class="cp-ol-deck-lead">{{ $ladder['deck_lead'] }}</p>
                    <div class="cp-ol-stage cp-ol-ladder-stage">
                        <svg viewBox="0 0 600 120" aria-hidden="true">
                            <path d="M8 {{ $ol::ladderY(67) }} H592" class="cp-ol-true" />
                            @foreach ($rungs['rounds'] as $k => $round)
                                <g class="cp-ol-rnd" data-ol-round="{{ $k }}" style="--k: {{ $k }}">
                                    @foreach ($round['notes'] as $i => $note)
                                        <rect x="{{ $note['x'] }}" y="{{ $ol::ladderY($note['midi']) - 3 }}" width="{{ $note['w'] }}" height="6" rx="3" class="cp-ol-note" data-ol-note="{{ $k }}-{{ $i }}" />
                                    @endforeach
                                </g>
                            @endforeach
                            @foreach ($rungs['tempos'] as $tempo)
                                <text x="{{ $tempo['x'] }}" y="112" class="cp-ol-tempo">{{ $tempo['bpm'] }}</text>
                            @endforeach
                        </svg>
                        <p class="cp-ol-figwords" lang="uk" aria-hidden="true">
                            @foreach ($ol::figureWords() as $word)
                                <span data-ol-fig="{{ $loop->index }}">{{ $word }}</span>
                            @endforeach
                        </p>
                    </div>
                    <fieldset class="cp-ol-pick">
                        <legend>{{ $ladder['pick_label'] }}</legend>
                        @foreach ((array) $ladder['picks'] as $pick => $name)
                            <button type="button" class="cp-ol-pickb" data-ol-drift="{{ $pick }}" aria-pressed="{{ $pick === 'true' ? 'true' : 'false' }}"><span class="cp-ol-tick" aria-hidden="true"></span>{{ $name }}</button>
                        @endforeach
                    </fieldset>
                    <p class="cp-ol-pick cp-ol-pick-row">
                        <button type="button" class="cp-ol-play" data-ol-play="ladder" data-play="{{ $ladder['play'] }}" data-stop="{{ $ladder['stop'] }}" aria-pressed="false"><span class="cp-ol-play-icon" aria-hidden="true"></span><span data-label>{{ $ladder['play'] }}</span></button>
                        <button type="button" class="cp-ol-play" data-ol-play="fast" data-play="{{ $ladder['fast'] }}" data-stop="{{ $ladder['stop'] }}" aria-pressed="false"><span class="cp-ol-play-icon" aria-hidden="true"></span><span data-label>{{ $ladder['fast'] }}</span></button>
                    </p>
                    <p class="cp-ol-say" role="status" data-ol-ladder-say>{{ $ladder['ready'] }}</p>
                    <p class="cp-ol-dkeys">
                        @foreach ((array) $ladder['key'] as $key)
                            <span><i class="cp-ol-dkey-{{ ['round', 'true'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-ol-deck-note">{{ $ladder['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-ol-sec cp-ol-first" aria-labelledby="try-h">
        <div>
            <p class="cp-ol-kick cp-ol-ja">{{ $love['kicks']['first'] }} · {{ $names['ja'] }}</p>
            @php($tryTitle = $ol::tail((string) $page['titles']['try']))
            <h2 id="try-h" class="cp-ol-h2">{{ $tryTitle[0] }} <em>{{ $tryTitle[1] }}</em></h2>
            <p class="cp-ol-body">{{ $page['try']['lead'] }}</p>
            <ol class="cp-ol-steps">
                @foreach ((array) $page['try']['steps'] as $step)
                    <li @class(['cp-ol-ja' => $loop->first])><span class="cp-ol-step-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $step }}</p></li>
                @endforeach
            </ol>
            <p class="cp-ol-small">{{ $page['try']['after'] }}</p>
        </div>
        <div class="cp-ol-inside">
            <h2 id="inside-h" class="cp-ol-h3">{{ $page['titles']['inside'] }}</h2>
            <p class="cp-ol-body">{{ $page['measures'] }}</p>
            <p class="cp-ol-body"><x-site.say :text="$page['scoring']" /></p>
        </div>
    </section>

    <section class="s-wrap cp-ol-sec" aria-labelledby="facts-h">
        <p class="cp-ol-kick">{{ $love['kicks']['notes'] }}</p>
        <h2 id="facts-h" class="cp-ol-h2 cp-ol-h2-big">{{ $love['facts_title'] }}</h2>
        @php($facts = (array) $love['facts'])
        @foreach ([array_slice($facts, 0, 2, true), array_slice($facts, 2, null, true)] as $part)
            @if ($loop->last && ! $loop->first)
                <details class="cp-ol-more" data-ol-wide><summary><span>{{ $love['facts_more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
            @endif
            <ul class="cp-ol-notes">
                @foreach ($part as $i => [$text, $source, $cites])
                    <li class="cp-ol-{{ $ol::hue($ol::FACTS[$i] ?? 'red') }}">
                        <span class="cp-ol-pilcrow" aria-hidden="true">¶</span>
                        <div>
                            <p class="cp-ol-note-text">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                            <p class="cp-ol-note-src">{{ $source }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($loop->last && ! $loop->first)
                </details>
            @endif
        @endforeach
    </section>

    <section class="s-wrap cp-ol-sec" aria-labelledby="kit-h">
        <h2 id="kit-h" class="cp-ol-kick">{{ $love['kicks']['kit'] }}</h2>
        <div class="cp-ol-kit">
            @foreach (['who', 'need', 'safety'] as $list)
                <div>
                    <h3>{{ $page['titles'][$list] }}</h3>
                    <ul>
                        @foreach ((array) $page[$list] as $item)
                            <li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    <section class="s-wrap cp-ol-sec cp-ol-faq" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-ol-h2 cp-ol-h2-big">{{ $page['titles']['faq'] }}</h2>
        @include('site.courses._faq')
    </section>

    <section class="cp-ol-close" aria-labelledby="join-h">
        <div class="s-wrap cp-ol-sec cp-ol-close-in">
            <p class="cp-ol-kick">{{ $love['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-ol-join">
                <span class="sr-only">{{ $page['titles']['join'] }}</span>
                <span class="cp-ol-join-boxes cp-ol-ja" aria-hidden="true">
                    @foreach (explode(' ', (string) $love['close_boxes']) as $box)
                        <span>{{ $box }}</span>
                    @endforeach
                </span>
                <span class="cp-ol-join-rest" aria-hidden="true">@foreach ($ol::marked((string) $love['close_rest']) as $piece)<span class="cp-ol-mk-{{ $piece['kind'] }}">{{ $piece['text'] }}</span>@endforeach</span>
            </h2>
            <p class="cp-ol-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-ol-btns cp-ol-btns-c">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </p>
            @if ($open)
                <p class="cp-ol-small">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-ol-kick cp-ol-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-ol-sources" aria-labelledby="notes-h">
        <details data-ol-sources>
            <summary><h2 id="notes-h" class="cp-ol-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/other-languages.js"></script>
</main>
