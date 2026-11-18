{{-- Counterpoint (/courses/counterpoint): "Point against point", after Hillel's design of 2026-11-18
     (course-designs/counterpoint): the look of old printed music, hand-copied choir books and teaching
     books. Square notes on red four-line staves in every chart, a voice a colour (I ink, II red, III
     ochre, and IV slate, only in the counting demo); sections headed by a red Roman numeral and a
     short staff; lead paragraphs opening on a large red capital; serif display type. Game skin:
     "Point vs point", neon lanes on a dot grid, the notes as glowing blocks, the history a high-score
     table. Sections: the hero with Fig. 1 (the course's own first-species exercise, its interval
     figures counted from the notes); I the round (with a voice to follow); what you'll be able to do;
     II two streams (split the tune); III one voice or two; IV the books (Liber I to V, from the
     course's outline); V a thousand years (with count the voices); VI seek and you shall find; VII
     what the app hears; before you begin; questions; finis; the sources. Every sound is made here
     (js/public/counterpoint.js), only when pressed. Words: lang/en/site.php courses.pages.counterpoint
     (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
{{-- The drawings' notes and places: App\Support\Pages\CounterpointPage (Fig. 1 is the course's own
     first-species exercise, its figures counted from the notes). --}}
@php($cp = \App\Support\Pages\CounterpointPage::class)
@php($species = $cp::species())
@php($run = $cp::run())
@php($fusion = $cp::fusion())
@php($seek = $cp::seek())
@php($count = $cp::count())
{{-- The section heads' numerals, in order, and the books'. --}}
@php($roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'])
{{-- The count of lessons and books, from the course itself, in the eyebrow and the first answer. --}}
@php($lessons = $outline['lessons'] ?? null)
@php($books = $outline !== null ? count($outline['units']) : null)
@if ($lessons !== null)
    @php($page['faq'][0][1] = str_replace(':n', (string) $lessons, $love['faq_long']))
@endif
@php($beats = (array) $love['history']['beats'])
<main class="cp cp-counterpoint cp-cp2" data-cp>
    <section class="cp-cp2-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-cp2-hero-in">
            <div class="cp-cp2-hero-words">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-cp2-label cp-cp2-eyebrow">
                    <span>{{ $love['eyebrow_free'] }}</span>
                    @if ($lessons !== null)
                        <span>{{ str_replace(':n', (string) $lessons, $love['eyebrow_lessons']) }}</span>
                        <span>{{ str_replace(':n', (string) $books, $love['eyebrow_books']) }}</span>
                    @endif
                </p>
                <h1 id="page-h" class="cp-cp2-h1">{{ $cp::heading($page['hero_title'], $love['hero_em']) }}</h1>
                <p class="cp-cp2-kicker"><x-site.skin :game="$love['kicker_game']" :serious="$love['kicker']" />@include('site.courses._cites', ['keys' => ['catholic', 'ucsb']])</p>
                <p class="cp-cp2-lede">{{ $page['hero_lead'] }}</p>
                <p class="cp-cp2-ctas"><a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a></p>
                @unless ($open)
                    <p class="cp-cp2-quiet">{{ __('pages.site.course_soon') }}</p>
                @endunless
            </div>
            @php($fig = $love['species'])
            <figure class="cp-cp2-demo cp-cp2-fig1" data-cp-species data-top="{{ json_encode($species['top']) }}" data-low="{{ json_encode($species['low']) }}" data-step="{{ $cp::SPECIES_STEP }}">
                <div class="cp-cp2-fighead"><p class="cp-cp2-figtitle">{{ $fig['title'] }}</p><span class="cp-cp2-fign">{{ str_replace(':n', '1', $love['fig']) }}</span></div>
                <svg viewBox="0 0 570 212" aria-hidden="true" class="cp-cp2-species">
                    @foreach ($species['staves'] as $staff)
                        @foreach ($staff['lines'] as $y)
                            <path d="M52 {{ $y }} H560" class="cp-cp2-staff" />
                        @endforeach
                        <text x="2" y="{{ $staff['label'] }}" class="cp-cp2-vn {{ $staff['voice'] }}">{{ $fig[$staff['name']] }}</text>
                    @endforeach
                    @foreach ($species['pairs'] as $pair)
                        <g class="cp-cp2-pair" data-at="{{ $pair['at'] }}" data-len="{{ $cp::SPECIES_STEP }}">
                            <path d="{{ $pair['tie'] }}" class="cp-cp2-tie" />
                            <text x="{{ $pair['x'] }}" y="121" class="cp-cp2-figure">{{ $pair['figure'] }}</text>
                            <rect x="{{ $pair['x'] - 6 }}" y="{{ $pair['top'] - 6 }}" width="12" height="12" rx="1" class="cp-cp2-dot cp-cp2-v2" />
                            <rect x="{{ $pair['x'] - 6 }}" y="{{ $pair['low'] - 6 }}" width="12" height="12" rx="1" class="cp-cp2-dot cp-cp2-v1" />
                        </g>
                    @endforeach
                </svg>
                <figcaption class="sr-only">{{ str_replace(':list', implode(', ', array_column($species['pairs'], 'figure')), $fig['art']) }}</figcaption>
                <div class="cp-cp2-figfoot">
                    <p class="cp-cp2-small">{{ $fig['caption'] }}</p>
                    <button type="button" class="cp-cp2-play" data-cp-species-play data-play="{{ $fig['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $fig['play'] }}</span></button>
                </div>
            </figure>
        </div>
    </section>

    <section class="cp-cp2-stage" aria-labelledby="stage-h" data-cp-stage>
        <div class="s-wrap">
            <p class="cp-cp2-sec"><span class="cp-cp2-num">{{ $roman[0] }}</span><span class="cp-cp2-label">{{ $love['round_kick'] }}</span><span class="cp-cp2-rule" aria-hidden="true"></span></p>
            <div class="cp-cp2-stage-head">
                <h2 id="stage-h" class="cp-cp2-stage-title">{{ $love['card'] }}</h2>
                <button type="button" class="cp-cp2-play" data-cp-round data-play="{{ $love['round'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $love['round'] }}</span></button>
            </div>
            <figure class="cp-cp2-scroll" data-cp-canon data-cp-scroll>
                @include('site.courses._cp-canon')
                <figcaption class="sr-only">{{ $love['art_note'] }}</figcaption>
            </figure>
            <div class="cp-cp2-follow">
                <p class="cp-cp2-small">{{ $love['follow_lead'] }}</p>
                <p class="cp-cp2-btns" role="group" aria-label="{{ $love['follow_label'] }}">
                    @foreach ([1, 2, 3] as $n)
                        <button type="button" class="cp-cp2-pick cp-cp2-v{{ $n }}" data-cp-follow="{{ $n }}" aria-pressed="false"><span class="cp-cp2-swatch" aria-hidden="true"></span>{{ str_replace(':n', $love['voices'][$n - 1], $love['follow']) }}</button>
                    @endforeach
                </p>
            </div>
        </div>
    </section>

    <section class="cp-cp2-alt cp-cp2-aims" aria-labelledby="aims-h">
        <div class="s-wrap">
            <h2 id="aims-h" class="cp-cp2-label">{{ $page['titles']['outcomes'] }}</h2>
            <ol class="cp-cp2-outcomes">
                @foreach ((array) $page['outcomes'] as $outcome)
                    <li>{{ $outcome }}</li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="cp-cp2-alt cp-cp2-deep" aria-labelledby="streams-h">
        <div class="s-wrap s-sec">
            <p class="cp-cp2-sec"><span class="cp-cp2-num">{{ $roman[1] }}</span><span class="cp-cp2-label">{{ $love['streams']['kick'] }}</span><span class="cp-cp2-rule" aria-hidden="true"></span></p>
            <div class="cp-cp2-split">
                <div>
                    <h2 id="streams-h" class="cp-cp2-h2">{{ $cp::heading($love['streams']['title'], $love['streams']['em']) }}</h2>
                    <p class="cp-cp2-lede cp-cp2-initial">{{ $love['streams']['lead'] }}@include('site.courses._cites', ['keys' => ['mcgill', 'jep']])</p>
                    <p class="cp-cp2-body">{{ $love['streams']['body'] }}@include('site.courses._cites', ['keys' => ['jep', 'asa']])</p>
                </div>
                <div class="cp-cp2-demo" data-cp-run data-high="{{ json_encode($cp::HIGH) }}" data-low="{{ json_encode($cp::LOW) }}">
                    <div class="cp-cp2-fighead"><h3 class="cp-cp2-figtitle">{{ $love['streams']['demo'] }}</h3><span class="cp-cp2-fign">{{ str_replace(':n', '2', $love['fig']) }}</span></div>
                    <p class="cp-cp2-small">{{ $love['streams']['demo_lead'] }}</p>
                    <svg viewBox="0 0 590 130" class="cp-cp2-run" aria-hidden="true">
                        <path d="{{ $run['zig'] }}" class="cp-cp2-zig" />
                        <path d="{{ $run['high'] }}" class="cp-cp2-strand cp-cp2-v2" />
                        <path d="{{ $run['low'] }}" class="cp-cp2-strand cp-cp2-v1" />
                        @foreach ($run['dots'] as $dot)
                            <rect x="{{ $dot['x'] - 6 }}" y="{{ $dot['y'] - 6 }}" width="12" height="12" rx="1" @class(['cp-cp2-dot', 'cp-cp2-high' => $dot['high'], 'cp-cp2-v1' => ! $dot['high']]) />
                        @endforeach
                    </svg>
                    <label class="cp-cp2-slider"><span class="cp-cp2-slider-top"><span>{{ $love['streams']['speed'] }}</span><output data-cp-rate-out data-say="{{ $love['streams']['rate'] }}">{{ str_replace(':n', '4', $love['streams']['rate']) }}</output></span><input type="range" min="2" max="12" step="1" value="4" data-cp-rate><span class="cp-cp2-ends" aria-hidden="true"><span>{{ $love['streams']['slow'] }}</span><span>{{ $love['streams']['fast'] }}</span></span></label>
                    <p class="cp-cp2-btns">
                        <button type="button" class="cp-cp2-play" data-cp-run-play data-play="{{ $love['streams']['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $love['streams']['play'] }}</span></button>
                        <span class="cp-cp2-readout" aria-live="polite" data-cp-streams data-one="{{ $love['streams']['one'] }}" data-two="{{ $love['streams']['two'] }}" data-one-game="{{ $love['streams']['one_game'] }}" data-two-game="{{ $love['streams']['two_game'] }}"><span class="s-skin-g"></span><span class="s-skin-s"></span></span>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section aria-labelledby="fusion-h">
        <div class="s-wrap s-sec">
            <p class="cp-cp2-sec"><span class="cp-cp2-num">{{ $roman[2] }}</span><span class="cp-cp2-label">{{ $love['fusion']['kick'] }}</span><span class="cp-cp2-rule" aria-hidden="true"></span></p>
            <h2 id="fusion-h" class="cp-cp2-h2">{{ $cp::heading($love['fusion']['title'], $love['fusion']['em']) }}</h2>
            <div class="cp-cp2-cols">
                <p class="cp-cp2-lede">{{ $love['fusion']['lead'] }}@include('site.courses._cites', ['keys' => ['hr']])</p>
                <p class="cp-cp2-body">{{ $love['fusion']['motion'] }}@include('site.courses._cites', ['keys' => ['sheffield', 'asa']])</p>
            </div>
            <div class="cp-cp2-demo cp-cp2-fusecard" data-cp-fuse data-kind="same" data-lines="{{ json_encode($cp::FUSE) }}">
                <div class="cp-cp2-fuse-left">
                    <p class="cp-cp2-seg" role="group" aria-label="{{ $love['fusion']['tabs'] }}">
                        @foreach (['same', 'opposite', 'melt'] as $tab)
                            <button type="button" data-cp-tab="{{ $tab }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $love['fusion'][$tab] }}</button>
                        @endforeach
                    </p>
                    <svg viewBox="0 0 330 140" class="cp-cp2-fuse" aria-hidden="true" data-cp-art="motion">
                        @foreach ($fusion['lines'] as $y)
                            <path d="M10 {{ $y }} H320" class="cp-cp2-staff" />
                        @endforeach
                        @foreach ($fusion['kinds'] as $kind => $lines)
                            <g data-show="{{ $kind }}">
                                @foreach ($lines as $line)
                                    <g class="{{ $line['voice'] }}">
                                        <polyline points="{{ $line['points'] }}" class="cp-cp2-thread" />
                                        @foreach ($line['notes'] as $j => $note)
                                            <rect x="{{ $note['x'] - 7 }}" y="{{ $note['y'] - 7 }}" width="14" height="14" rx="1" class="cp-cp2-dot" data-cp-note="{{ $j }}" />
                                        @endforeach
                                    </g>
                                @endforeach
                            </g>
                        @endforeach
                    </svg>
                    <svg viewBox="0 0 300 64" class="cp-cp2-octave" aria-hidden="true" data-cp-art="melt" hidden>
                        <g class="cp-cp2-v1"><path d="M10 44 H262" class="cp-cp2-thread" /><rect x="270" y="36" width="16" height="16" rx="1" class="cp-cp2-dot" /></g>
                        <g class="cp-cp2-v2"><path d="M10 44 H262" class="cp-cp2-thread" data-cp-melt-thread /><rect x="273" y="39" width="10" height="10" rx="1" class="cp-cp2-dot" data-cp-melt-dot /></g>
                    </svg>
                </div>
                <div class="cp-cp2-panel" data-cp-panel="motion">
                    <p class="cp-cp2-how"><span data-show="same">{{ $love['fusion']['same_how'] }}</span><span data-show="opposite">{{ $love['fusion']['opposite_how'] }}</span></p>
                    <div class="cp-cp2-fuse-acts">
                        <button type="button" class="cp-cp2-play" data-cp-fuse-play data-play="{{ $love['fusion']['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $love['fusion']['play'] }}</span></button>
                        <span class="cp-cp2-btns" role="group" aria-label="{{ $love['fusion']['pick'] }}">
                            @foreach (['one', 'two'] as $heard)
                                <button type="button" class="cp-cp2-pick" data-cp-pick="{{ $heard }}" aria-pressed="false">{{ $love['fusion'][$heard] }}</button>
                            @endforeach
                        </span>
                    </div>
                    <p class="cp-cp2-answer" aria-live="polite" data-cp-answer data-same-one="{{ $love['fusion']['same_one'] }}" data-same-two="{{ $love['fusion']['same_two'] }}" data-opposite-one="{{ $love['fusion']['opposite_one'] }}" data-opposite-two="{{ $love['fusion']['opposite_two'] }}"></p>
                </div>
                <div class="cp-cp2-panel cp-cp2-melt" data-cp-panel="melt" data-cp-melt hidden>
                    <p class="cp-cp2-how">{{ $love['fusion']['melt_lead'] }}</p>
                    <label class="cp-cp2-slider"><span>{{ $love['fusion']['cents'] }} <output data-cp-cents>0</output></span><input type="range" min="-50" max="50" step="1" value="0" data-cp-detune></label>
                    <p class="cp-cp2-btns">
                        <button type="button" class="cp-cp2-play" data-cp-melt-play data-play="{{ $love['fusion']['hold'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $love['fusion']['hold'] }}</span></button>
                        <span class="cp-cp2-readout" aria-live="polite" data-cp-melted data-one="{{ $love['fusion']['merged'] }}" data-two="{{ $love['fusion']['apart'] }}" data-one-game="{{ $love['fusion']['merged_game'] }}" data-two-game="{{ $love['fusion']['apart_game'] }}"><span class="s-skin-g"></span><span class="s-skin-s"></span></span>
                    </p>
                </div>
            </div>
            <p class="cp-cp2-body">{{ $love['fusion']['bach'] }}@include('site.courses._cites', ['keys' => ['hr', 'mp91', 'mp01']])</p>
        </div>
    </section>

    @if ($outline !== null)
        <section class="cp-cp2-alt cp-cp2-deep" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-cp2-sec"><span class="cp-cp2-num">{{ $roman[3] }}</span><span class="cp-cp2-label">{{ $love['books']['kick'] }}</span><span class="cp-cp2-rule" aria-hidden="true"></span></p>
                <div class="cp-cp2-books-head">
                    <h2 id="units-h" class="cp-cp2-h2">{{ $cp::heading($love['books']['title'], $love['books']['em']) }}</h2>
                    <p class="cp-cp2-small">{{ str_replace([':u', ':n'], [(string) $books, (string) $lessons], $love['books']['side']) }}</p>
                </div>
                <ol class="cp-cp2-books">
                    @foreach ($outline['units'] as $unit)
                        <li class="cp-cp2-book cp-cp2-v{{ $loop->index % 3 + 1 }}" style="--i: {{ $loop->index }}">
                            <details data-cp-open @if ($loop->first) open @endif>
                                <summary>
                                    <span class="cp-cp2-liber"><span class="cp-cp2-label">{{ $love['books']['liber'] }}</span> <span class="cp-cp2-bookn">{{ $roman[$loop->index] ?? $loop->iteration }}</span></span>
                                    <span class="cp-cp2-booktop">
                                        <h3>{{ $unit['title'] }}</h3>
                                        @isset($page['units_say'][$loop->index])
                                            <span class="cp-cp2-say">{{ $page['units_say'][$loop->index] }}</span>
                                        @endisset
                                    </span>
                                </summary>
                                <ol class="cp-cp2-lessons">
                                    @foreach ($unit['lessons'] as $lesson)
                                        <li>{{ $lesson }}@if ($loop->parent->first && $loop->first) <a href="{{ $tryUrl }}" class="cp-cp2-free">{{ $love['books']['free'] }}</a>@endif</li>
                                    @endforeach
                                </ol>
                            </details>
                        </li>
                    @endforeach
                </ol>
                <p class="cp-cp2-ctas"><a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
        </section>
    @endif

    <section class="cp-cp2-history" aria-labelledby="history-h">
        <div class="s-wrap s-sec">
            <p class="cp-cp2-sec"><span class="cp-cp2-num">{{ $roman[4] }}</span><span class="cp-cp2-label">{{ $love['history']['kick'] }}</span><span class="cp-cp2-rule" aria-hidden="true"></span></p>
            <h2 id="history-h" class="cp-cp2-h2">{{ $cp::heading($love['history']['title'], $love['history']['em']) }}</h2>
            <p class="cp-cp2-small">{{ $love['history']['lead'] }}</p>
            @foreach ([array_slice($beats, 0, 5), array_slice($beats, 5)] as $part)
                @if (! $loop->first)
                    <details class="cp-cp2-more" data-cp-open><summary>{{ $love['history']['more'] }}</summary>
                @endif
                <ol class="cp-cp2-years">
                    @foreach ($part as [$when, $text, $cites, $players])
                        @if ($players === 40)
                            {{-- Forty lines, a voice each, in eight choirs of five, each coming in a step after the
                                 last: a picture of the scoring, and said so under it, not the score's order. --}}
                            <li class="cp-cp2-forty" aria-hidden="true">
                                <svg viewBox="0 0 1000 206" preserveAspectRatio="none">
                                    @foreach ($cp::forty() as $hair)
                                        <g class="{{ $hair['voice'] }}"><path d="M0 {{ $hair['y'] }} H{{ $hair['x'] }}" class="cp-cp2-hair cp-cp2-hair-before" /><path d="M{{ $hair['x'] }} {{ $hair['y'] }} H1000" class="cp-cp2-hair" /></g>
                                    @endforeach
                                </svg>
                            </li>
                        @endif
                        <li @class(['cp-cp2-year', 'cp-cp2-year-forty' => $players === 40])>
                            <p class="cp-cp2-when">{{ $when }}</p>
                            <div class="cp-cp2-year-in">
                                <p class="cp-cp2-band-row">
                                    @if ($players !== 40)
                                        <svg viewBox="0 0 240 {{ $players * 6 + 4 }}" class="cp-cp2-band" aria-hidden="true">
                                            @foreach ($cp::band($players) as $voice)
                                                <g class="{{ $voice['voice'] }}"><path d="M{{ $voice['x'] }} {{ $voice['y'] }} H240" class="cp-cp2-thread" /><rect x="{{ $voice['x'] - 2.5 }}" y="{{ $voice['y'] - 2.5 }}" width="5" height="5" class="cp-cp2-dot" /></g>
                                            @endforeach
                                        </svg>
                                    @endif
                                    <span class="cp-cp2-players"><x-site.skin :game="str_replace(':n', (string) $players, $love['history']['players'])" :serious="str_replace(':n', (string) $players, $love['history']['voices_n'])" /></span>
                                </p>
                                <p class="cp-cp2-what">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                                @if ($players === 40)
                                    <p class="cp-cp2-small">{{ $love['history']['forty'] }}</p>
                                @endif
                            </div>
                        </li>
                        @if ($players === 40)
                            @php($words = $love['history']['count'])
                            <li class="cp-cp2-demo cp-cp2-count" data-cp-count>
                                <div class="cp-cp2-fighead"><h3 class="cp-cp2-figtitle">{{ $words['title'] }}</h3><span class="cp-cp2-fign">{{ str_replace(':n', '3', $love['fig']) }}</span></div>
                                <p class="cp-cp2-small">{{ $words['lead'] }}</p>
                                <svg viewBox="0 0 700 216" class="cp-cp2-lanes" aria-hidden="true" data-x0="44" data-lane="{{ $count['lane'] }}">
                                    @foreach ($count['voices'] as $voice)
                                        <g class="cp-cp2-v{{ $voice['n'] }}">
                                            @foreach ($voice['lines'] as $y)
                                                <path d="M44 {{ $y }} H690" class="cp-cp2-staff" />
                                            @endforeach
                                            <text x="6" y="{{ $voice['label'] }}" class="cp-cp2-vn">{{ $love['voices'][$voice['n'] - 1] }}</text>
                                            <g class="cp-cp2-entry" data-cp-entry="{{ $voice['n'] - 1 }}">
                                                @foreach ($voice['notes'] as $note)
                                                    <rect x="{{ $note['x'] }}" y="{{ $note['y'] }}" width="6" height="6" class="cp-cp2-dot" data-at="{{ $note['at'] }}" data-len="{{ $note['len'] }}" />
                                                @endforeach
                                            </g>
                                        </g>
                                    @endforeach
                                    <path d="M44 190 H690" class="cp-cp2-base" />
                                    <g class="cp-cp2-real" data-cp-real>
                                        @foreach ($count['voices'] as $voice)
                                            <g class="cp-cp2-v{{ $voice['n'] }}"><path d="M{{ $voice['entry'] }} 193 v8" class="cp-cp2-realmark" /><text x="{{ $voice['entry'] }}" y="214" class="cp-cp2-vn cp-cp2-realn">{{ $love['voices'][$voice['n'] - 1] }}</text></g>
                                        @endforeach
                                    </g>
                                    <g data-cp-taps></g>
                                    <path d="M44 6 V192" class="cp-cp2-head" data-cp-count-head />
                                </svg>
                                <p class="cp-cp2-legend" data-cp-count-legend hidden><span><span class="cp-cp2-key cp-cp2-key-tap" aria-hidden="true"></span> {{ $words['yours'] }}</span><span><span class="cp-cp2-key cp-cp2-key-real" aria-hidden="true"></span> {{ $words['real'] }}</span></p>
                                <p class="cp-cp2-btns">
                                    <button type="button" class="cp-cp2-play" data-cp-count-play data-play="{{ $words['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $words['play'] }}</span></button>
                                    <button type="button" class="cp-cp2-pick cp-cp2-tap" data-cp-tap disabled>{{ $words['tap'] }}</button>
                                </p>
                                <div aria-live="polite">
                                    <p class="cp-cp2-answer cp-cp2-caught" data-cp-caught data-say="{{ $words['result'] }}" data-say-game="{{ $words['result_game'] }}" hidden><span class="s-skin-g"></span><span class="s-skin-s"></span></p>
                                    <p class="cp-cp2-small" data-cp-count-note hidden>{{ $words['note'] }}@include('site.courses._cites', ['keys' => ['mp89']])</p>
                                </div>
                            </li>
                        @endif
                    @endforeach
                </ol>
                @if (! $loop->first)
                    </details>
                @endif
            @endforeach
        </div>
    </section>

    <section class="cp-cp2-alt cp-cp2-deep" aria-labelledby="seek-h">
        <div class="s-wrap s-sec">
            <p class="cp-cp2-sec"><span class="cp-cp2-num">{{ $roman[5] }}</span><span class="cp-cp2-label" lang="la">{{ $love['seek']['kick'] }}</span><span class="cp-cp2-rule" aria-hidden="true"></span></p>
            <div class="cp-cp2-split">
                <div>
                    <h2 id="seek-h" class="cp-cp2-h2">{{ $cp::heading($love['seek']['title'], $love['seek']['em']) }}</h2>
                    <p class="cp-cp2-lede cp-cp2-initial">{{ $love['seek']['lead'] }}@include('site.courses._cites', ['keys' => ['bachnl', 'cmslc']])</p>
                    <p class="cp-cp2-body">{{ $page['move']['smooth'] }}</p>
                </div>
                <div class="cp-cp2-demo" data-cp-seek data-line="{{ json_encode($cp::SEEK) }}">
                    <div class="cp-cp2-fighead"><h3 class="cp-cp2-figtitle">{{ $love['seek']['riddle'] }}</h3><span class="cp-cp2-fign">{{ str_replace(':n', '4', $love['fig']) }}</span></div>
                    <p class="cp-cp2-small">{{ $love['seek']['demo_lead'] }}</p>
                    <svg viewBox="0 0 520 150" class="cp-cp2-seek" aria-hidden="true">
                        @foreach ($seek['lines'] as $y)
                            <path d="M8 {{ $y }} H512" class="cp-cp2-staff" />
                        @endforeach
                        @foreach ($seek['voices'] as $voice)
                            <g class="{{ $voice['voice'] }}" @if ($voice['copy']) data-cp-copy @endif>
                                <polyline points="{{ $voice['points'] }}" class="cp-cp2-thread" />
                                @foreach ($voice['notes'] as $j => $note)
                                    <rect x="{{ $note['x'] - $voice['size'] / 2 }}" y="{{ $note['y'] - $voice['size'] / 2 }}" width="{{ $voice['size'] }}" height="{{ $voice['size'] }}" rx="1" class="cp-cp2-dot" data-cp-note="{{ $j }}" />
                                @endforeach
                            </g>
                        @endforeach
                        <g data-cp-links></g>
                    </svg>
                    <p class="cp-cp2-legend" data-cp-legend hidden><span><span class="cp-cp2-key" aria-hidden="true"></span> {{ $love['seek']['fits'] }}</span><span><span class="cp-cp2-key cp-cp2-key-rub" aria-hidden="true"></span> {{ $love['seek']['rubs'] }}</span></p>
                    <label class="cp-cp2-slider"><span>{{ $love['seek']['delay'] }} <output data-cp-beats>1</output></span><input type="range" min="1" max="6" step="1" value="1" data-cp-delay></label>
                    <p class="cp-cp2-ticks" aria-hidden="true">@foreach (range(1, 6) as $n)<span data-cp-tick="{{ $n }}">{{ $n }}</span>@endforeach</p>
                    <p class="cp-cp2-btns">
                        <button type="button" class="cp-cp2-play" data-cp-seek-play data-play="{{ $love['seek']['play'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $love['seek']['play'] }}</span></button>
                        <span class="cp-cp2-readout" aria-live="polite" data-cp-fit data-smooth="{{ $love['seek']['smooth'] }}" data-one="{{ $love['seek']['rub_one'] }}" data-many="{{ $love['seek']['rub_many'] }}" data-smooth-game="{{ $love['seek']['smooth_game'] }}" data-rub-game="{{ $love['seek']['rub_game'] }}" hidden><span class="s-skin-g"></span><span class="s-skin-s"></span></span>
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- What the app listens for: the course's measure and scoring beside two real screens of it
         (web/icons/help, course-counterpoint--1 and --2, made by web/tool/course_shots.py). --}}
    <section class="cp-cp2-app" aria-labelledby="inside-h">
        <div class="s-wrap s-sec cp-cp2-split">
            <div>
                <p class="cp-cp2-sec"><span class="cp-cp2-num">{{ $roman[6] }}</span><span class="cp-cp2-label">{{ $love['app_kick'] }}</span></p>
                <h2 id="inside-h" class="cp-cp2-h2">{{ $cp::heading($page['titles']['inside'], $love['app_em']) }}</h2>
                <p class="cp-cp2-lede">{{ $page['measures'] }}</p>
                <p class="cp-cp2-small"><x-site.say :text="$page['scoring']" /></p>
            </div>
            <div class="cp-cp2-shots s-course-shots">
                @foreach ((array) ($page['shots'] ?? []) as $alt)
                    <x-site.appshot :id="'course-'.$page['slug'].'--'.$loop->iteration" :alt="$alt" />
                @endforeach
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-cp2-pack" aria-labelledby="pack-h">
        <h2 class="cp-cp2-sec"><span class="cp-cp2-label" id="pack-h">{{ $page['titles']['reader'] }}</span><span class="cp-cp2-rule" aria-hidden="true"></span></h2>
        <div class="cp-cp2-pack-grid">
            @foreach (['who', 'need'] as $part)
                <details class="cp-cp2-fold" data-cp-open><summary><h3>{{ $page['titles'][$part] }}</h3></summary><ul>@foreach ((array) $page[$part] as $line)<li>{{ $line }}</li>@endforeach</ul></details>
            @endforeach
            <div><h3>{{ $page['titles']['safety'] }}</h3><ul>@foreach ((array) $page['safety'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
            <div class="cp-cp2-songs-box"><h3>{{ __('pages.site.course_songs') }}</h3><ul class="cp-cp2-songs">@foreach ((array) $page['songs'] as [$song, $credit])<li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>@endforeach</ul></div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-cp2-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-cp2-label">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-cp2-h2"><em>{{ $page['titles']['faq'] }}</em></h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-cp2-finis" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-cp2-finis-in">
            <figure class="cp-cp2-card" data-cp-canon>
                @include('site.courses._cp-canon')
            </figure>
            <div>
                <p class="cp-cp2-finis-kick">{{ $page['kicks']['finis'] }}</p>
                <h2 id="join-h" class="cp-cp2-h2">{{ $cp::heading($page['titles']['join'], $love['join_em']) }}</h2>
                <p class="cp-cp2-lede">{{ $open ? $page['finis'] : __('pages.site.course_soon') }}</p>
                <p class="cp-cp2-ctas">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <button type="button" class="cp-cp2-play" data-cp-round data-play="{{ $love['again'] }}" data-stop="{{ $love['stop'] }}" aria-pressed="false"><span class="cp-cp2-play-icon" aria-hidden="true"></span><span data-cp-label>{{ $love['again'] }}</span></button>
                </p>
                @if ($open)
                    <p class="cp-cp2-quiet">{{ __('pages.site.course_join_note') }}</p>
                @endif
            </div>
        </div>
    </section>

    <section class="cp-cp2-also" aria-labelledby="also-h">
        <div class="s-wrap">
            <h2 id="also-h" class="cp-cp2-label">{{ $page['titles']['related'] }}</h2>
            @include('site.courses._related')
            <details class="cp-cp2-notes" data-cp-open>
                <summary><h2 class="cp-cp2-label">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
                <ol>
                    @foreach ($sources as [$label, $url])
                        <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                    @endforeach
                </ol>
            </details>
        </div>
    </section>
    <script type="module" src="/js/public/counterpoint.js"></script>
</main>
