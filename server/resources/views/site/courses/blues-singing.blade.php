{{-- Blues singing (/courses/blues-singing), its own page, after Hillel's design of 2026-11-18: the look comes from the
     course's own material, 1920s Chicago sheet music, the three-line verse and the blue note. Ink on paper with a
     sheet-music navy for the answer line and the blue note; the display face is the system serif (no new fonts).
     The hero sets the words beside our own typographic sheet-music panel for Shorty George Blues (Chicago, 1924;
     no period cover art is shown, docs/courses/blues-singing.md), its first verse's three lines on it. Then what you'll
     learn as the song's twelve bars (each line's bars and the band's turn, its chords as printed), the history as the
     courses lead wrote it with its dates on a line, the units as cards (unit 2, the blue note, the one blue card),
     what the app listens for beside a sample take labelled as one, singing safely and the songs with their years,
     the beginner course (site._course-today, with its real app screen) and the group's other courses. No drawn
     people. Game skin: the arcade's night, square frames with hard shadows. Words: lang/en/site.php
     courses.pages.blues-singing (love).
     The length pass (2026-11-19) added, in the same sheet-music look: the demo under the hero, Lean, then land
     (js/public/blues-singing.js: lesson 1's line as printed or without its blue notes, on voices.js, only when pressed),
     how a blues works in plain words, the archive (the course's lesson facts by year), lesson 1's steps from the course,
     who it's for and what you need beside safety and the songs, the questions, a close, and the numbered Sources. --}}
@php($love = $page['love'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
{{-- What the page draws, worked out in App\Support\Pages\BluesSingingPage: the chart's bars (Shorty George Blues as
     the course teaches it), the sample take, and the words cut where the design sets a part apart. --}}
@php($blues = \App\Support\Pages\BluesSingingPage::class)
@php($rows = $blues::rows((array) $love['chart']))
@php($take = $blues::take())
@php($takeCount = $blues::takeCount())
@php($cover = $blues::title((string) $love['cover']['title']))
@php($heard = $blues::measures((string) ($page['measures'] ?? '')))
@php($said = $blues::ending((string) ($page['history']['body'][1] ?? ''), (string) $love['said_em']))
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($roll = $blues::demo())
@php($firstSteps = $blues::firstLesson())
<main class="cp cp-bl">
    <section class="cp-hero cp-bl-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid cp-bl-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-bl-status">
                    @if ($open)
                        <span class="s-soon s-open">{{ __('pages.site.course_open') }}</span>
                    @else
                        <x-site.soon />
                    @endif
                    <span class="cp-bl-size">{{ __('pages.site.course_chip_free') }}@if ($outline !== null) · {{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}@endif</span>
                </p>
                <h1 id="page-h">{{ $love['h1'][0] }} <em>{{ $love['h1'][1] }}</em></h1>
                <p class="s-lede">{{ $page['lead'] }}</p>
                <p class="cp-hero-btns">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                </p>
                @unless ($open)
                    <p class="cp-bl-quiet">{{ __('pages.site.course_soon') }}</p>
                @endunless
            </div>
            <figure class="cp-bl-cover">
                <div class="cp-bl-sheet" role="img" aria-label="{{ $love['cover']['alt'] }}">
                    <div class="cp-bl-sheet-in" aria-hidden="true">
                        <p class="cp-bl-sheet-top"><span>{{ $love['cover']['place'] }}</span><span>1924</span></p>
                        <p class="cp-bl-sheet-title" translate="no">@foreach ($cover['lines'] as $word)<span>{{ $word }}</span>@endforeach<em>{{ $cover['last'] }}</em></p>
                        <ol class="cp-bl-sheet-lines">
                            @foreach ((array) $love['cover']['lines'] as $line)
                                <li><b>{{ $blues::LETTERS[$loop->index] ?? '' }}</b><span translate="no">{{ $line }}</span></li>
                            @endforeach
                        </ol>
                        <p class="cp-bl-sheet-credit"><span>{{ $love['cover']['words'] }}</span><span>{{ $love['cover']['music'] }}</span></p>
                    </div>
                </div>
                <figcaption class="cp-bl-cover-note">{{ $love['cover']['note'] }}</figcaption>
            </figure>
        </div>
    </section>

    <section class="s-wrap cp-dk-demosec cp-bl-demosec" aria-labelledby="demo-h">
        <div class="cp-dk-demo cp-bl-demo" data-bl-demo data-version="printed"
            data-line="{{ json_encode($roll['line']) }}" data-words="{{ json_encode(array_column($roll['notes'], 'word')) }}"
            data-blue="{{ $blues::BLUE }}" data-bright="{{ $blues::BRIGHT }}" data-beats="{{ $blues::BEATS }}" data-turn="{{ $blues::TURN }}"
            data-printed="{{ $demo['printed'] }}" data-plain="{{ $demo['plain'] }}" data-after="{{ $demo['after'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['kick'] }}</span><span>{{ $demo['lesson'] }}</span></p>
            <div class="cp-dk-demo-in">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}</p>
                    <p class="cp-dk-btns">
                        <button type="button" class="cp-dk-play" data-bl-play="printed" data-play="{{ $demo['play_printed'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['play_printed'] }}</span></button>
                        <button type="button" class="cp-dk-play cp-dk-play-b" data-bl-play="plain" data-play="{{ $demo['play_plain'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['play_plain'] }}</span></button>
                    </p>
                    <p class="cp-dk-say" role="status" data-bl-say>{{ $demo['ready'] }}</p>
                </div>
                <div>
                    <div class="cp-dk-roll cp-bl-roll">
                        <svg viewBox="0 0 600 170" role="img" aria-label="{{ $demo['roll'] }}">
                            @foreach ($roll['guides'] as $guide)
                                <path d="M16 {{ $guide }} H584" class="cp-dk-staff" />
                            @endforeach
                            <rect x="{{ $roll['turn']['x'] }}" y="20" width="{{ $roll['turn']['w'] }}" height="140" rx="6" class="cp-bl-roll-turn" data-bl-turn />
                            @foreach ($roll['notes'] as $note)
                                @if ($note['blue'])
                                    <rect x="{{ $note['x'] }}" y="{{ $note['y'] }}" width="{{ $note['w'] }}" height="12" rx="6" class="cp-dk-bar cp-bl-roll-blue" data-bl-note="{{ $loop->index }}" data-bl-v="printed" />
                                    <rect x="{{ $note['x'] }}" y="{{ $note['plain'] }}" width="{{ $note['w'] }}" height="12" rx="6" class="cp-dk-bar cp-bl-roll-plain" data-bl-note="{{ $loop->index }}" data-bl-v="plain" />
                                @else
                                    <rect x="{{ $note['x'] }}" y="{{ $note['y'] }}" width="{{ $note['w'] }}" height="12" rx="6" class="cp-dk-bar" data-bl-note="{{ $loop->index }}" />
                                @endif
                            @endforeach
                        </svg>
                        <p class="cp-dk-words cp-bl-roll-words" aria-hidden="true">
                            @foreach ($roll['words'] as $word)
                                <span data-bl-word="{{ $loop->index }}" translate="no">{{ $word }}</span>
                            @endforeach
                            <span class="cp-bl-roll-turnword" data-bl-turnword>{{ $demo['turn'] }}</span>
                        </p>
                    </div>
                    <p class="cp-dk-key">
                        <span><i class="cp-bl-dkey-blue" aria-hidden="true"></i>{{ $demo['key_blue'] }}</span>
                        <span><i class="cp-bl-dkey-plain" aria-hidden="true"></i>{{ $demo['key_plain'] }}</span>
                        <span><i class="cp-bl-dkey-turn" aria-hidden="true"></i>{{ $demo['turn'] }}</span>
                    </p>
                    <p class="cp-dk-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="cp-bl-band cp-bl-form" aria-labelledby="learn-h">
        <div class="s-wrap">
            <div class="cp-bl-form-head">
                <div>
                    <p class="cp-bl-kick">{{ __('pages.site.course_learn') }}</p>
                    <h2 id="learn-h" class="cp-bl-h2">{{ $love['learn_title'][0] }} <em>{{ $love['learn_title'][1] }}</em></h2>
                </div>
                <p class="cp-bl-aside">{{ $love['learn_note'] }}</p>
            </div>
            <div class="cp-bl-chart" role="group" aria-label="{{ $love['chart']['alt'] }}">
                @foreach ($rows as $row)
                    <div @class(['cp-bl-row', 'cp-bl-row-b' => $row['letter'] === 'B'])>
                        <p class="cp-bl-row-h"><b aria-hidden="true">{{ $row['letter'] }}</b><span>{{ $row['name'] }}</span></p>
                        <ol class="cp-bl-bars">
                            @foreach ($row['bars'] as $bar)
                                <li @class(['cp-bl-bar', 'cp-bl-turn' => ! $bar['sung']]) aria-label="{{ $bar['label'] }}">
                                    <span class="cp-bl-bar-n" aria-hidden="true">{{ $bar['n'] }}</span>
                                    <b translate="no" aria-hidden="true">@foreach ($bar['chords'] as $chord)<span>{{ $chord }}</span>@endforeach</b>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                @endforeach
            </div>
            <div class="cp-bl-chart-foot">
                <ul class="cp-bl-legend">
                    <li><i class="cp-bl-key-sung" aria-hidden="true"></i>{{ $love['chart']['sung'] }}</li>
                    <li><i class="cp-bl-key-turn" aria-hidden="true"></i>{{ $love['chart']['turn'] }}</li>
                </ul>
                <p class="cp-bl-chart-note">{{ $love['chart']['note'] }}</p>
            </div>
            <ol class="cp-bl-aims">
                @foreach ($blues::aims((array) $page['learn']) as $aim)
                    <li @class(['cp-bl-aim', 'cp-bl-aim-blue' => $loop->index === 1])>
                        <p class="cp-bl-aim-h"><x-site.skin :game="__('pages.site.course_stage', ['n' => $loop->iteration])" :serious="__('pages.site.course_unit', ['n' => $loop->iteration])" />@if ($aim['name'] !== '') · {{ $aim['name'] }}@endif</p>
                        <p>{{ $aim['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="s-wrap cp-bl-works" aria-labelledby="works-h">
        <p class="cp-bl-kick">{{ $love['works']['kick'] }}</p>
        <h2 id="works-h" class="cp-bl-h2">{{ $love['works']['title'][0] }} <em>{{ $love['works']['title'][1] }}</em></h2>
        <ol class="cp-bl-cards">
            @foreach ((array) $love['works']['cards'] as [$title, $text, $cites])
                <li @class(['cp-bl-card', 'cp-bl-card-blue' => $loop->index === 1])>
                    <p class="cp-bl-card-n" aria-hidden="true">{{ $blues::LETTERS[$loop->index] ?? '' }}</p>
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ol>
    </section>

    @if (is_array($page['history'] ?? null))
        <section class="s-wrap cp-bl-story" aria-labelledby="history-h">
            <h2 id="history-h" class="cp-bl-kick">{{ $page['history']['title'] }}</h2>
            <div class="cp-bl-story-grid">
                <p class="cp-bl-story-p">{{ $page['history']['body'][0] ?? '' }}</p>
                <ol class="cp-bl-years">
                    @foreach ((array) $love['story'] as [$when, $what])
                        <li @class(['cp-bl-year-first' => $loop->first, 'cp-bl-year-last' => $loop->last])><span class="cp-bl-when">{{ $when }}</span><span>{{ $what }}</span></li>
                    @endforeach
                </ol>
            </div>
            @if ($said['before'] !== '')
                {{-- The page's own sentence (the courses lead's), set large: not a quotation of anyone. --}}
                <p class="cp-bl-said">{{ $said['before'] }}@if ($said['after'] !== '') <em>{{ $said['after'] }}</em>@endif</p>
            @endif
        </section>
    @endif

    <section class="cp-bl-band cp-bl-archive" aria-labelledby="archive-h">
        <div class="s-wrap">
            <div class="cp-bl-form-head">
                <div>
                    <p class="cp-bl-kick">{{ $love['archive']['kick'] }}</p>
                    <h2 id="archive-h" class="cp-bl-h2">{{ $love['archive']['title'][0] }} <em>{{ $love['archive']['title'][1] }}</em></h2>
                </div>
                <p class="cp-bl-aside">{{ $love['archive']['lead'] }}</p>
            </div>
            <ol class="cp-bl-slips">
                @foreach ((array) $love['archive']['rows'] as [$year, $where, $title, $text, $cites])
                    <li class="cp-bl-slip">
                        <p class="cp-bl-slip-top"><span class="cp-bl-slip-year">{{ $year }}</span><span class="cp-bl-slip-where">{{ $where }}</span></p>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    @if ($outline !== null)
        <section class="s-wrap cp-bl-unitsec" aria-labelledby="units-h">
            <div class="cp-bl-units-head">
                <h2 id="units-h" class="cp-bl-kick">{{ __('pages.site.course_units') }}</h2>
                <p class="cp-bl-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-bl-units">
                @foreach ($outline['units'] as $unit)
                    <li @class(['cp-bl-unit', 'cp-bl-unit-blue' => $loop->index === 1])>
                        <p class="cp-bl-unit-top"><span class="cp-bl-unit-num" aria-hidden="true">{{ $loop->iteration }}</span>@isset($love['unit_songs'][$loop->index])<span class="cp-bl-unit-song" translate="no">{{ $love['unit_songs'][$loop->index] }}</span>@endisset</p>
                        <h3><span class="sr-only"><x-site.skin :game="__('pages.site.course_stage', ['n' => $loop->iteration])" :serious="__('pages.site.course_unit', ['n' => $loop->iteration])" />: </span>{{ $unit['title'] }}</h3>
                        <ol class="cp-bl-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li><span class="cp-bl-lesson-n" aria-hidden="true">{{ $loop->iteration }}</span><span class="cp-bl-lesson">{{ $lesson }}</span>@if ($loop->parent->first && $loop->first)<span class="cp-bl-start">{{ __('pages.site.course_free') }}</span>@endif</li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @if ($firstSteps !== [])
        <section class="s-wrap cp-bl-first" aria-labelledby="first-h">
            <div class="cp-bl-form-head">
                <div>
                    <p class="cp-bl-kick">{{ $love['first']['kick'] }}</p>
                    <h2 id="first-h" class="cp-bl-h2">{{ $love['first']['title'][0] }} <em>{{ $love['first']['title'][1] }}</em></h2>
                </div>
                <p class="cp-bl-aside">{{ $love['first']['lead'] }}</p>
            </div>
            <ol class="cp-bl-steps">
                @foreach ($firstSteps as $step)
                    <li @class(['cp-bl-step', 'cp-bl-step-last' => $loop->last])>
                        <p class="cp-bl-step-top"><span class="cp-bl-step-n" aria-hidden="true">{{ $loop->iteration }}</span><span class="cp-bl-step-kind">{{ $love['first']['kinds'][$step['kind']] ?? '' }}</span></p>
                        <p class="cp-bl-step-text">{{ $step['text'] }}</p>
                        @if ($step['measures'] !== '')
                            <p class="cp-bl-step-score">{{ $step['measures'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ol>
            <div class="cp-bl-first-foot">
                <p class="cp-bl-kept">{{ $love['first']['kept'] }}</p>
                <a href="{{ $tryUrl }}" class="btn-secondary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
            </div>
        </section>
    @endif

    @if (is_string($page['measures'] ?? null))
        <section class="cp-bl-band cp-bl-hears" aria-labelledby="hears-h">
            <div class="s-wrap cp-bl-hears-grid">
                <div>
                    <h2 id="hears-h" class="cp-bl-kick">{{ __('pages.site.course_hears') }}</h2>
                    <p class="cp-bl-big">{{ $heard['first'] }}</p>
                    @if ($heard['middle'] !== '' || $heard['last'] !== '')
                        <p class="cp-bl-big">{{ $heard['middle'] }}@if ($heard['last'] !== '') <em>{{ $heard['last'] }}</em>@endif</p>
                    @endif
                    @if (is_array($page['scoring'] ?? null))
                        <p class="cp-bl-scoring"><x-site.say :text="$page['scoring']" /></p>
                    @endif
                </div>
                <figure class="cp-bl-take">
                    <figcaption class="cp-bl-take-top"><b>{{ $love['take']['label'] }}</b><span>{{ $love['take']['not'] }}</span></figcaption>
                    <div class="cp-bl-take-rows" role="img" aria-label="{{ $love['take']['alt'] }}">
                        @foreach ($take as $line)
                            <p @class(['cp-bl-take-row', 'cp-bl-row-b' => $line['letter'] === 'B']) aria-hidden="true">
                                <b>{{ $line['letter'] }}</b>
                                @foreach ($line['notes'] as $onPitch)
                                    <span @class(['cp-bl-note', 'cp-bl-miss' => ! $onPitch])></span>
                                @endforeach
                            </p>
                        @endforeach
                    </div>
                    <p class="cp-bl-take-count"><b>{{ strtr((string) $love['take']['count'], [':hit' => $takeCount['hit'], ':all' => $takeCount['all']]) }}</b> <span>{{ $love['take']['unit'] }}</span></p>
                    <ul class="cp-bl-legend cp-bl-take-key">
                        <li><i class="cp-bl-note" aria-hidden="true"></i>{{ $love['take']['hit'] }}</li>
                        <li><i class="cp-bl-note cp-bl-miss" aria-hidden="true"></i>{{ $love['take']['miss'] }}</li>
                    </ul>
                </figure>
            </div>
        </section>
    @endif

    <section class="s-wrap cp-bl-two" aria-labelledby="begin-h">
        <h2 id="begin-h" class="cp-bl-kick cp-bl-two-h">{{ $love['begin']['kick'] }}</h2>
        <div>
            <h3 class="cp-bl-safe-h">{{ $love['begin']['who'] }}</h3>
            <ul class="cp-bl-safe cp-bl-ticks">
                @foreach ((array) $page['who'] as $item)
                    <li><x-site.icon name="check" size="18" /><span>{{ $item }}</span></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h3 class="cp-bl-safe-h">{{ $love['begin']['need'] }}</h3>
            <ul class="cp-bl-safe cp-bl-ticks">
                @foreach ((array) $page['need'] as $item)
                    <li><x-site.icon name="check" size="18" /><span>{{ $item }}</span></li>
                @endforeach
            </ul>
        </div>
        <div aria-labelledby="safety-h">
            <h2 id="safety-h" class="cp-bl-kick">{{ __('pages.site.course_safety') }}</h2>
            <p class="cp-bl-safe-h">{{ $love['safety_head'] }}</p>
            <ul class="cp-bl-safe">
                @foreach ((array) $love['safety_points'] as $point)
                    <li><x-site.icon name="info" size="18" /><span>{{ $point }}</span></li>
                @endforeach
            </ul>
        </div>
        @if (($page['songs'] ?? []) !== [])
            <div aria-labelledby="songs-h">
                <h2 id="songs-h" class="cp-bl-kick">{{ __('pages.site.course_songs') }}</h2>
                <ul class="cp-bl-songs">
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li>
                            <span class="cp-bl-song-year" aria-hidden="true">{{ $blues::year((string) $credit) }}</span>
                            <div><b translate="no">{{ $song }}</b><span>{{ $credit }}</span></div>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    {{-- The beginner course's band, as every course page has it; the questions sit between it and the close, so its
         Start and the close's are apart. --}}
    @include('site._course-today')

    <section class="s-wrap cp-bl-faq" aria-labelledby="faq-h">
        <div>
            <p class="cp-bl-kick">{{ $love['questions']['kick'] }}</p>
            <h2 id="faq-h" class="cp-bl-h2">{{ $love['questions']['title'][0] }} <em>{{ $love['questions']['title'][1] }}</em></h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="s-wrap cp-bl-close" aria-labelledby="join-h">
        <p class="cp-bl-kick">{{ $love['close']['kick'] }}</p>
        <h2 id="join-h" class="cp-bl-h2">{{ $love['close']['title'][0] }} <em>{{ $love['close']['title'][1] }}</em></h2>
        <p class="cp-bl-close-p">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
        <p class="cp-join-btns">
            <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
            <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
        </p>
        @if ($open)
            <p class="cp-bl-quiet">{{ __('pages.site.course_join_note') }}</p>
        @endif
        <h3 id="more-h" class="cp-bl-kick cp-bl-more-h">{{ __('pages.site.course_more', ['group' => $group['name'] ?? '']) }}</h3>
        <ul class="s-chips">
            @foreach ((array) ($group['courses'] ?? []) as $slug)
                @continue($slug === $page['slug'])
                <li><a href="/courses/{{ $slug }}">{{ __('site.courses.pages.'.$slug.'.name') }}</a></li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap cp-bl-sources" aria-labelledby="notes-h">
        <details data-bl-sources>
            <summary><h2 id="notes-h" class="cp-bl-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/blues-singing.js"></script>
</main>
