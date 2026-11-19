{{-- Scat singing (/courses/scat), its own page, after Hillel's design of 2026-11-18 (course-designs/scat): a jazz
     record. A 1920s Chicago display face (Big Shoulders Display) for the headlines, an italic serif (Instrument Serif)
     for syllables and liner notes, the site's sans for reading. One colour code throughout: the twelve-bar card's
     chords, I ochre (home), IV blue (away) and V red (the turn back), always with its numeral or a word beside it, carry
     the units' sleeves, the outcomes' numbers and the story's record labels. The hero: "Sing like a horn." with doo, bah
     and dat drawn as a long, a short and a clean bar, and the twelve bars on a record sleeve with its disc peeking out.
     Then Straight or swung? (js/public/scat.js), lesson 2's own line twice round over C7 with a playhead, the tiles and
     each "and" sliding between the feels; the story as Side A's tracks, A1 to A5; "By the end" drawn as four bars of
     music; the units as four record sleeves, every lesson a link to its page (so the separate list of lesson pages under
     the page is left out); the science, a small drawing each; the liner notes as the back of a sleeve beside what the app
     listens for; the personnel; questions; "Your turn." on ochre, the band and you trading bars; the sources. Game skin:
     the arcade's night, the chords in neon, square sleeves on hard shadows, pixel headings, PRESS START. No drawn people.
     Words: lang/en/site.php courses.pages.scat (love); numbers: App\Support\Pages\ScatPage. --}}
@section('lesson-pages-inline', '1')
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($chart = $love['chart'])
@php($drawn = $love['drawn'])
@php($sc = \App\Support\Pages\ScatPage::class)
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($lessonCount = (int) ($outline['lessons'] ?? 0))
@php($unitCount = count($outline['units'] ?? []))
@php($counts = [':lessons' => (string) $lessonCount, ':units' => (string) $unitCount])
@php([$h1, $h1Stop] = $sc::stop($page['hero_title']))
@php([$close, $closeStop] = $sc::stop($page['titles']['join']))
@php($units = $sc::units($page, $outline))
@php($numerals = $chart['numerals'])
<main class="cp cp-sc" data-course="scat">
    <section class="cp-sc-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-sc-hero-in">
            <div class="cp-sc-hero-words">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-sc-kick cp-sc-eyebrow">
                    @if ($outline !== null)
                        <span class="cp-sc-cat" translate="no">{{ $sc::catalogue($love['catalogue'], $lessonCount) }}</span>
                    @endif
                    <span>{{ $outline !== null ? strtr($love['eyebrow'], $counts) : $page['eyebrow'] }}</span>
                </p>
                <h1 id="page-h" class="cp-sc-display cp-sc-h1">{{ $h1 }}<span class="cp-sc-stop">{{ $h1Stop }}</span></h1>
                <ul class="cp-sc-syls">
                    @foreach ((array) $love['syllables'] as [$syllable, $kind])
                        <li class="cp-sc-syl-{{ $loop->index }}"><span class="cp-sc-serif">{{ $syllable }}</span><span class="cp-sc-syl-bar" aria-hidden="true"></span><span class="cp-sc-mono">{{ $kind }}</span></li>
                    @endforeach
                </ul>
                <p class="cp-sc-lead">{{ $page['hero_lead'] }}</p>
                <p class="cp-sc-btns">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                </p>
                @unless ($open)
                    <p class="cp-sc-quiet">{{ __('pages.site.course_soon') }}</p>
                @endunless
                <ul class="cp-sc-facts">
                    @foreach ((array) $love['chips'] as $chip)
                        @if ($outline !== null || ! str_contains($chip, ':lessons'))
                            <li>{{ strtr($chip, $counts) }}</li>
                        @endif
                    @endforeach
                </ul>
            </div>

            {{-- The twelve bars on a record sleeve, its disc peeking out behind. --}}
            <figure class="cp-sc-record">
                <div class="cp-sc-disc" aria-hidden="true">
                    <span class="cp-sc-disc-label"><b class="cp-sc-display">{{ $love['sleeve']['side'] }}</b><span class="cp-sc-mono">{{ $love['sleeve']['bars'] }}</span><i></i></span>
                </div>
                <div class="cp-sc-sleeve12">
                    <p class="cp-sc-mono cp-sc-sleeve12-top" aria-hidden="true"><span>{{ $love['sleeve']['label'] }}</span><span translate="no">{{ $sc::catalogue($love['catalogue'], count($chart['bars'])) }}</span></p>
                    <p class="cp-sc-display cp-sc-sleeve12-h">{{ $chart['title'] }}</p>
                    <ol class="cp-sc-bars" aria-label="{{ $chart['alt'] }}">
                        @foreach ((array) $chart['bars'] as $bar)
                            @php($job = $sc::JOBS[$loop->index])
                            <li class="cp-sc-bar cp-sc-job-{{ $job }}">
                                <span class="cp-sc-mono">{{ str_replace(':n', (string) $loop->iteration, $chart['bar_n']) }}</span>
                                <span class="cp-sc-bar-chord"><b class="cp-sc-display" translate="no">{{ $bar }}</b><span class="cp-sc-mono" aria-hidden="true">{{ $numerals[$job] }}</span></span>
                            </li>
                        @endforeach
                    </ol>
                    <ul class="cp-sc-legend">
                        @foreach ((array) $chart['legend'] as $job => $what)
                            <li><i class="cp-sc-job-{{ $job }}" aria-hidden="true"></i>{{ $what }}</li>
                        @endforeach
                    </ul>
                </div>
                <figcaption class="cp-sc-caption">{{ $chart['note'] }}@include('site.courses._cites', ['keys' => ['handy']])</figcaption>
            </figure>
        </div>
    </section>

    {{-- Straight or swung? (js/public/scat.js): lesson 2's own line, a bar of doo-bah on C and D, twice round and landing
         on doo, over C7. Each tile is placed by when its note starts and how long it lasts in the feel chosen (the same
         numbers time the sound), higher notes higher; the counts' "and" moves with the feel; a playhead crosses the bar
         as it plays (only the lit tile under reduced motion). --}}
    <section class="cp-sc-demosec" aria-labelledby="demo-h">
        <div class="s-wrap cp-sc-demo-in">
            <div class="cp-sc-demo-head">
                <div>
                    <p class="cp-sc-kick cp-sc-live"><span class="cp-sc-rec" aria-hidden="true"></span>{{ $demo['live'] }} · {{ $demo['lesson'] }}</p>
                    <h2 id="demo-h" class="cp-sc-display cp-sc-h2">{{ $demo['title'] }}</h2>
                </div>
                <p class="cp-sc-demo-lead">{{ $demo['lead'] }}</p>
            </div>
            <div class="cp-sc-deck" data-sc-demo data-feel="swung"
                data-midi="{{ json_encode($sc::midi()) }}" data-feels="{{ json_encode($sc::feels()) }}" data-tempo="{{ $sc::TEMPO }}"
                data-ready="{{ $demo['ready'] }}" data-after="{{ $demo['after'] }}"
                data-playing-straight="{{ $demo['playing']['straight'] }}" data-playing-swung="{{ $demo['playing']['swung'] }}" data-playing-soloist="{{ $demo['playing']['soloist'] }}">
                <div class="cp-sc-deck-top">
                    <p class="cp-sc-readout cp-sc-mono" aria-live="polite">
                        @foreach ($sc::FEELS as $feel)
                            <span class="cp-sc-show-{{ $feel }}">{{ $demo['readout'][$feel] }}</span>
                        @endforeach
                    </p>
                    <div class="cp-sc-seg" role="group" aria-label="{{ $demo['feel'] }}">
                        @foreach ((array) $demo['feels'] as $feel => $name)
                            <button type="button" data-sc-feel="{{ $feel }}" aria-pressed="{{ $feel === 'swung' ? 'true' : 'false' }}">{{ $name }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="cp-sc-lane" role="img" aria-label="{{ $demo['lane'] }}">
                    @foreach ([0, 25, 50, 75, 100] as $x)
                        <span class="cp-sc-beat" style="--x: {{ $x }}" aria-hidden="true"></span>
                    @endforeach
                    <ol class="cp-sc-notes" aria-hidden="true">
                        @foreach ($sc::tiles() as $tile)
                            <li @class(['cp-sc-note', 'cp-sc-short' => $tile['short']]) data-sc-note="{{ $tile['index'] }}" style="--rise: {{ $tile['rise'] }}; {{ $tile['style'] }}">
                                <span class="cp-sc-serif">{{ $demo['syllables'][$tile['index']] ?? '' }}</span>
                                <span class="cp-sc-mono" translate="no">{{ $demo['notes'][$tile['index']] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <span class="cp-sc-head" data-sc-head aria-hidden="true"></span>
                </div>
                <p class="cp-sc-counts cp-sc-mono" aria-hidden="true">
                    @foreach ($sc::counts() as $count)
                        <span @class(['cp-sc-count', 'cp-sc-and' => $count['and']]) style="{{ $count['style'] }}">{{ $count['and'] ? $demo['and'] : $demo['beats'][$count['beat']] }}</span>
                    @endforeach
                </p>
                <div class="cp-sc-deck-foot">
                    <button type="button" class="cp-sc-play" data-sc-play aria-pressed="false"><span class="cp-sc-play-icon" aria-hidden="true"></span><span class="cp-sc-go"><x-site.skin :game="__('pages.site.demo_press_start')" :serious="$demo['play']" /></span><span class="cp-sc-halt">{{ $demo['stop'] }}</span></button>
                    <div>
                        <p class="cp-sc-say" role="status" data-sc-say>{{ $demo['ready'] }}</p>
                        <p class="cp-sc-demo-note">{{ $demo['note'] }}@include('site.courses._cites', ['keys' => ['kth']])</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-sc-story" aria-labelledby="story-h">
        <div class="cp-sc-story-head">
            <p class="cp-sc-kick">{{ $page['kicks']['story'] }}</p>
            <h2 id="story-h" class="cp-sc-display cp-sc-h2">{{ $page['titles']['story'] }}</h2>
            <p class="cp-sc-body">{{ $page['story_lead'] }}</p>
        </div>
        <ol class="cp-sc-tracks">
            @foreach ((array) $love['story'] as [$when, $title, $text, $cites])
                <li>
                    <span class="cp-sc-mono cp-sc-track-n">{{ str_replace(':n', (string) $loop->iteration, $love['track']) }}</span>
                    <p class="cp-sc-track-when"><span class="cp-sc-display cp-sc-year">{{ $when }}</span><span class="cp-sc-label cp-sc-job-{{ $sc::STORY_JOBS[$loop->index % 5] }}" aria-hidden="true"></span></p>
                    <div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap cp-sc-aimsec" aria-labelledby="outcomes-h">
        <p class="cp-sc-kick">{{ $page['kicks']['aims'] }}</p>
        <h2 id="outcomes-h" class="cp-sc-display cp-sc-h2">{{ $page['titles']['outcomes'] }}</h2>
        {{-- Four bars of music: a staff across the top, a bar line between, and the double bar at the end. --}}
        <ol class="cp-sc-aims">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-sc-display cp-sc-aim-n cp-sc-job-{{ ['home', 'away', 'turn', 'ink'][$loop->index % 4] }}" aria-hidden="true">0{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    @if ($outline !== null)
        <section class="cp-sc-unitsec" aria-labelledby="units-h">
            <div class="s-wrap">
                <div class="cp-sc-unithead">
                    <div>
                        <p class="cp-sc-kick">{{ $page['kicks']['units'] }}</p>
                        <h2 id="units-h" class="cp-sc-display cp-sc-h2">{{ $page['titles']['units'] }}</h2>
                    </div>
                    <p class="cp-sc-mono cp-sc-unitcount">{{ strtr($love['unit_count'], $counts) }}</p>
                </div>
                <ol class="cp-sc-sleeves">
                    @foreach ($units as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-sc-sleeve cp-sc-job-{{ $unit['job'] }}">
                            <div class="cp-sc-sleeve-front">
                                <p class="cp-sc-mono cp-sc-sleeve-top"><span><x-site.skin :game="$stage" :serious="$unitName" /></span><span translate="no" aria-hidden="true">{{ $sc::catalogue($love['catalogue'], $loop->iteration) }}</span></p>
                                <span class="cp-sc-display cp-sc-sleeve-n" aria-hidden="true">{{ $loop->iteration }}</span>
                                <h3 class="cp-sc-display">{{ $unit['title'] }}</h3>
                            </div>
                            <div class="cp-sc-sleeve-back">
                                @if ($unit['say'] !== '')
                                    <p>{{ $unit['say'] }}</p>
                                @endif
                                <ol class="cp-sc-tracklist">
                                    @foreach ($unit['lessons'] as $lesson)
                                        @php($first = $loop->parent->first && $loop->first)
                                        <li>
                                            @if ($lesson['url'] !== null)
                                                <a href="{{ $lesson['url'] }}"><span class="cp-sc-mono" aria-hidden="true">0{{ $loop->iteration }}</span><span>{{ $lesson['name'] }}</span>@if ($first)<span class="cp-sc-start">{{ __('pages.site.course_free') }}</span>@endif</a>
                                            @else
                                                <span class="cp-sc-mono" aria-hidden="true">0{{ $loop->iteration }}</span><span>{{ $lesson['name'] }}</span>@if ($first)<span class="cp-sc-start">{{ __('pages.site.course_free') }}</span>@endif
                                            @endif
                                        </li>
                                    @endforeach
                                </ol>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- The science, a small drawing each: sketches of what the words say, not measured data, hidden from screen
         readers. --}}
    <section class="s-wrap cp-sc-science" aria-labelledby="science-h">
        <p class="cp-sc-kick">{{ $page['kicks']['science'] }}</p>
        <h2 id="science-h" class="cp-sc-display cp-sc-h2">{{ $page['titles']['science'] }}</h2>
        <ul class="cp-sc-cards">
            @foreach ((array) $love['science'] as [$title, $text, $cites])
                <li class="cp-sc-card">
                    <div class="cp-sc-fig cp-sc-fig-{{ $loop->index }}" aria-hidden="true">
                        @if ($loop->index === 0)
                            {{-- Two beats and a little more: the drummer's long notes longer than 2 to 1, the soloist's
                                 shorter and late on the beat, both on the same "and" (the dashed lines). --}}
                            <p><span class="cp-sc-mono">{{ $drawn['drums'] }}</span><span class="cp-sc-runs"><i style="--a: 0; --b: 31.8"></i><i class="cp-sc-off" style="--a: 31.8; --b: 45.5"></i><i style="--a: 45.5; --b: 77.3"></i><i class="cp-sc-off" style="--a: 77.3; --b: 90.9"></i><i style="--a: 90.9; --b: 100"></i><s style="--a: 31.8"></s><s style="--a: 77.3"></s></span></p>
                            <p><span class="cp-sc-mono">{{ $drawn['soloist'] }}</span><span class="cp-sc-runs cp-sc-solo"><i style="--a: 4.5; --b: 31.8"></i><i class="cp-sc-off" style="--a: 31.8; --b: 50"></i><i style="--a: 50; --b: 77.3"></i><i class="cp-sc-off" style="--a: 77.3; --b: 95.5"></i><s style="--a: 31.8"></s><s style="--a: 77.3"></s></span></p>
                        @elseif ($loop->index === 1)
                            @foreach (['cleaned' => [0, 0, 0, 0, 0, 0, 0, 0], 'original' => [0.6, -0.8, 1, -0.4, 0.7, -1, 0.3, -0.6], 'doubled' => [1.2, -1.6, 2, -0.8, 1.4, -2, 0.6, -1.2]] as $row => $shifts)
                                <p @class(['cp-sc-doubled' => $row === 'doubled'])><span class="cp-sc-mono">{{ $drawn[$row] }}</span><span class="cp-sc-marks">@foreach ($shifts as $k => $shift)<i style="--a: {{ 4 + $k * 12.5 + $shift }}"></i>@endforeach</span></p>
                            @endforeach
                        @elseif ($loop->index === 2)
                            <ol class="cp-sc-fours">
                                @foreach (['you', 'partner', 'you', 'partner'] as $who)
                                    <li @class(['cp-sc-partner' => $who === 'partner'])><span class="cp-sc-mono">{{ $drawn['bars'] }}</span><b class="cp-sc-mono">{{ $drawn[$who] }}</b></li>
                                @endforeach
                            </ol>
                        @else
                            <p class="cp-sc-keys"><span class="cp-sc-key-b cp-sc-mono">♭3</span><span class="cp-sc-key-w cp-sc-mono">3</span><i class="cp-sc-blue"></i><span class="cp-sc-serif">{{ $drawn['blue'] }}</span></p>
                        @endif
                    </div>
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="cp-sc-band" aria-labelledby="margin-h">
        <div class="s-wrap cp-sc-band-in">
            <div>
                <p class="cp-sc-kick">{{ $page['kicks']['margin'] }}</p>
                <h2 id="margin-h" class="cp-sc-display cp-sc-h2">{{ $page['titles']['margin'] }}</h2>
                <div class="cp-sc-listens">
                    <h3 class="cp-sc-kick">{{ $page['kicks']['inside'] }}</h3>
                    <p>{{ $love['listens']['lead'] }}</p>
                    <ul>
                        @foreach ((array) $love['listens']['yes'] as $line)
                            <li><x-site.icon name="check" size="16" /><span>{{ $line }}</span></li>
                        @endforeach
                    </ul>
                    <p class="cp-sc-listens-no"><span class="cp-sc-no" aria-hidden="true">&#x2715;</span><span>{{ $love['listens']['no'] }}</span></p>
                </div>
            </div>
            <div class="cp-sc-sleeveback">
                <p class="cp-sc-mono cp-sc-sleeveback-top"><span><span translate="no">{{ $sc::catalogue($love['catalogue'], $lessonCount) }}</span> · {{ $page['name'] }}</span><span>{{ $love['notes_by'] }}</span></p>
                <div class="cp-sc-liner">
                    @foreach ((array) $love['margin'] as [$text, $cites])
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-sc-begin" aria-labelledby="begin-h">
        <p class="cp-sc-kick">{{ $page['kicks']['begin'] }}</p>
        <h2 id="begin-h" class="cp-sc-display cp-sc-h2">{{ $page['titles']['who'] }}</h2>
        <div class="cp-sc-begin-grid">
            <div><h3 class="cp-sc-mono">{{ $love['who_h'] }}</h3><ul class="cp-sc-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div><h3 class="cp-sc-mono">{{ $page['titles']['need'] }}</h3><ul class="cp-sc-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div>
                <h3 class="cp-sc-mono">{{ $page['titles']['safety'] }}</h3>
                <ul class="cp-sc-ticks">@foreach ((array) $page['safety'] as $item)<li><x-site.icon name="info" size="16" /><span>{{ $item }}</span></li>@endforeach</ul>
                <h3 class="cp-sc-mono cp-sc-songs-h">{{ __('pages.site.course_songs') }}</h3>
                <ul class="cp-sc-songs">
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b><span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-sc-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-sc-kick">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-sc-display cp-sc-h2">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-sc-close" aria-labelledby="join-h">
        <div class="s-wrap cp-sc-close-in">
            <p class="cp-sc-kick">{{ $page['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-sc-display cp-sc-close-h">{{ $close }}<span class="cp-sc-stop">{{ $closeStop }}</span></h2>
            {{-- Lesson 1's trade: the band's two bars (the call's syllables as the lesson sings them), then yours. --}}
            <div class="cp-sc-trade" role="img" aria-label="{{ $love['trade']['alt'] }}">
                @foreach ((array) $love['trade']['call'] as $bar)
                    <span><span class="cp-sc-mono">{{ $love['trade']['band'] }}</span><span class="cp-sc-serif">{{ $bar }}</span></span>
                @endforeach
                @foreach ((array) $love['trade']['call'] as $bar)
                    <span class="cp-sc-yours"><span class="cp-sc-mono">{{ $love['trade']['you'] }}</span><span class="cp-sc-serif">&hellip;</span></span>
                @endforeach
            </div>
            <p class="cp-sc-close-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-sc-btns cp-join-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </p>
            @if ($open)
                <p class="cp-sc-close-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-sc-kick cp-sc-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-sc-sources" aria-labelledby="notes-h">
        <details data-sc-sources>
            <summary><h2 id="notes-h" class="cp-sc-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span> · {{ $page['kicks']['sources'] }}</h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/scat.js"></script>
</main>
