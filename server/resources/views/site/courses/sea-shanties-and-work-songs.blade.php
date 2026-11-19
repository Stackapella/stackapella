{{-- Sea shanties and work songs (/courses/sea-shanties-and-work-songs), its own page after Hillel's design of 2026-11-18
     (~/Code/course-designs/sea-shanties), built around call and response. The headline is the call and its answer, in
     the sea's teal; beside it a card with Haul Away, Joe's chorus from its song template, the shantyman's line and then
     yours, over a lane of the tune's notes (js/public/sea-shanties-and-work-songs.js plays it on voices.js when pressed,
     the call alone and then the crew's answer in four parts, each note lit on the audio clock as it sounds; idle, a
     silent sweep runs by CSS, still under reduced motion). Then the units as a route from the crew to the shantyman,
     each with its voices (who's the shantyman, who's crew, which is you, by shape and named) and every lesson a link to
     its page, so the shared list of lesson pages isn't shown again; the first lesson's cards and steps, as its page
     teaches them; the work and the rhythm (each job a shanty did, as
     its lesson teaches it, with the old books' fact on it); what the app listens for in a sea-teal panel beside a take
     marked as a sample; the old books by year; the songbook with its printed years and the safety lines numbered, word
     for word; the questions and the Sources every note points at (the lessons' own facts, cleared by the courses lead);
     the beginner course with its real screen; the group's other courses. Words: lang/en/site.php
     courses.pages.sea-shanties-and-work-songs (`sh`); support: App\Support\Pages\SeaShantiesPage. --}}
{{-- The units list every lesson as a link to its page, so the shared list isn't repeated under this page. --}}
@section('lesson-pages-inline', '1')
@php($sh = (array) $page['sh'])
@php($joe = \App\Support\Pages\SeaShantiesPage::exchange())
@php($units = \App\Support\Pages\SeaShantiesPage::units($page, $outline))
@php($songbook = \App\Support\Pages\SeaShantiesPage::songbook((array) ($page['songs'] ?? [])))
{{-- The lessons' own facts from the shanty collections, numbered against the Sources list at the end. --}}
@php($history = \App\Support\Pages\SeaShantiesPage::history())
@php($faq = \App\Support\Pages\SeaShantiesPage::faq($sh, $outline))
@php($first = \App\Support\Pages\SeaShantiesPage::firstLesson())
<main class="cp cp-sh">
    <section class="s-wrap cp-sh-hero" aria-labelledby="page-h">
        <div class="cp-sh-hero-text">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <p class="cp-sh-status">
                @if ($open)
                    <span class="cp-sh-open"><span class="cp-sh-open-dot" aria-hidden="true"></span>{{ __('pages.site.course_open') }}</span>
                @else
                    <x-site.soon />
                @endif
                @if ($outline !== null)
                    <span class="cp-sh-meta">{{ __('site.courses.pages.sea-shanties-and-work-songs.sh.meta', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</span>
                @endif
            </p>
            <h1 id="page-h" class="cp-sh-h1">{{ $sh['headline'][0] }} <em>{{ $sh['headline'][1] }}</em></h1>
            <p class="cp-sh-lead">{{ $page['lead'] }}</p>
            <p class="cp-sh-btns">
                @if ($open)
                    <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                @else
                    <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                @endif
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </p>
        </div>

        {{-- Haul Away, Joe's first exchange: the call, then your answer, its last word the pull. --}}
        <figure class="cp-sh-card" data-sh data-tempo="{{ $joe['tempo'] }}" data-span="{{ $joe['span'] }}" data-crew="{{ json_encode($joe['crew']) }}">
            <p class="cp-sh-card-top"><span translate="no">{{ $joe['song'] }}</span><span>{{ $sh['card']['where'] }}</span></p>
            <div class="cp-sh-lines">
                <p class="cp-sh-callline"><span class="cp-sh-who">{{ $sh['card']['shantyman'] }}</span><span class="cp-sh-sung" translate="no" lang="en">{{ $joe['call'] }}</span></p>
                <p class="cp-sh-answerline"><span class="cp-sh-who cp-sh-who-you">{{ $sh['card']['you'] }}</span><span class="cp-sh-sung" translate="no" lang="en">{{ $joe['answer'][0] }} <span class="cp-sh-pull">{{ $joe['answer'][1] }}</span></span></p>
            </div>
            <div class="cp-sh-lane" aria-hidden="true" style="--answer: {{ $joe['answerAt'] }}%">
                @foreach ($joe['tune'] as $note)
                    <span @class(['cp-sh-note', 'cp-sh-note-answer' => $note['answer']]) data-at="{{ $note['at'] }}" data-len="{{ $note['len'] }}" data-midi="{{ $note['midi'] }}" style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%; height: {{ $note['height'] }}%; --d: {{ $note['left'] }}"></span>
                @endforeach
                <span class="cp-sh-divide"></span>
                <span class="cp-sh-head" data-sh-head></span>
            </div>
            <p class="cp-sh-lane-labels" aria-hidden="true"><span>{{ $sh['card']['call'] }}</span><span>{{ $sh['card']['answer'] }}</span></p>
            <div class="cp-sh-card-foot">
                <button type="button" class="cp-sh-play" data-sh-play data-play="{{ $sh['card']['play'] }}" data-stop="{{ $sh['card']['stop'] }}" aria-pressed="false"><span class="cp-sh-play-icon" aria-hidden="true"></span><span data-label>{{ $sh['card']['play'] }}</span></button>
                <p class="cp-sh-source">{{ $sh['card']['source'] }}</p>
            </div>
            <figcaption class="sr-only">{{ $sh['card']['alt'] }}</figcaption>
        </figure>
    </section>

    {{-- The course, unit by unit, from its own file (SitePages::courseOutline): a route from the crew to the shantyman,
         each unit's voices drawn by shape and named, its outcome, and its lessons, each a link to its page. --}}
    @if ($units !== [])
        <section class="cp-sh-voyage" aria-labelledby="units-h">
            <div class="s-wrap">
                <div class="cp-sh-voyage-head">
                    <div>
                        <p class="cp-sh-kick">{{ __('pages.site.course_units') }}</p>
                        <h2 id="units-h" class="cp-sh-h2">{{ $sh['units_title'][0] }} <em>{{ $sh['units_title'][1] }}</em></h2>
                    </div>
                    <ul class="cp-sh-legend">
                        <li><span class="cp-sh-dot cp-sh-dot-lead" aria-hidden="true"></span>{{ $sh['legend']['lead'] }}</li>
                        <li><span class="cp-sh-dot cp-sh-dot-crew" aria-hidden="true"></span>{{ $sh['legend']['crew'] }}</li>
                        <li><span class="cp-sh-dot cp-sh-dot-you" aria-hidden="true"></span>{{ $sh['legend']['you'] }}</li>
                    </ul>
                </div>
                <ol class="cp-sh-units">
                    @foreach ($units as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-sh-unit" data-unit="{{ $loop->iteration }}">
                            <span class="cp-sh-node" aria-hidden="true"></span>
                            <div class="cp-sh-crew">
                                <ul class="cp-sh-voices" aria-label="{{ __('site.courses.pages.sea-shanties-and-work-songs.sh.voices_label', ['n' => $loop->iteration]) }}">
                                    @foreach ($unit['voices'] as $voice)
                                        <li class="cp-sh-dot cp-sh-dot-{{ $voice }}"><span class="sr-only">{{ $sh['voices'][$voice] }}</span></li>
                                    @endforeach
                                </ul>
                                <span class="cp-sh-role">{{ $sh['roles'][$loop->index] ?? '' }}</span>
                            </div>
                            <p class="cp-sh-unit-n"><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @if ($unit['outcome'] !== '')
                                <p class="cp-sh-outcome">{{ $unit['outcome'] }}</p>
                            @endif
                            <ol class="cp-sh-lessons">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li>
                                        @if ($lesson['url'])<a href="{{ $lesson['url'] }}">@else<span class="cp-sh-nolink">@endif
                                            <span class="cp-sh-ln" aria-hidden="true">{{ $loop->iteration }}</span>
                                            <span class="cp-sh-lt">
                                                <span class="cp-sh-lname">
                                                    <b @class(['cp-sh-song' => $lesson['song']]) @if ($lesson['song']) translate="no" @endif>{{ $lesson['name'] }}</b>
                                                    @if ($lesson['start'])<span class="cp-sh-start">{{ __('pages.site.course_free') }}</span>@endif
                                                </span>
                                                @if ($lesson['line'] !== '')<span class="cp-sh-line">{{ $lesson['line'] }}</span>@endif
                                            </span>
                                        @if ($lesson['url'])</a>@else</span>@endif
                                    </li>
                                @endforeach
                            </ol>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- Your first lesson as its page teaches it: the headline, its three cards, then the steps you sing, numbered, and
         the way to its page. --}}
    @if ($first !== null)
        <section class="s-wrap cp-sh-first" aria-labelledby="first-h">
            <div class="cp-sh-work-head">
                <div>
                    <p class="cp-sh-kick">{{ $sh['first_kick'] }}</p>
                    <h2 id="first-h" class="cp-sh-h2">{{ $sh['first_title'][0] }} <em>{{ $sh['first_title'][1] }}</em></h2>
                </div>
                <p class="cp-sh-work-lead">{{ $first['headline'] }}</p>
            </div>
            <div class="cp-sh-first-grid">
                <ul class="cp-sh-cards">
                    @foreach ($first['cards'] as $card)
                        <li><b>{{ $card['title'] }}</b><span>{{ $card['detail'] }}</span></li>
                    @endforeach
                </ul>
                <ol class="cp-sh-steps">
                    @foreach ($first['sing'] as $step)
                        <li><span class="cp-sh-step-n" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span><p>{{ $step }}</p></li>
                    @endforeach
                </ol>
            </div>
            <p class="cp-sh-first-more"><a href="{{ $first['url'] }}" class="s-arrow">{{ $first['title'] }} <x-site.icon name="arrow" size="16" /></a></p>
        </section>
    @endif

    {{-- The work and the rhythm: On the pull's own words and Conan Doyle's account, then each job a shanty did, as its
         lesson teaches it (its pull drawn as a mark and said in words), with the old books' fact on it. --}}
    @if ($history['work'] !== [])
        <section class="s-wrap cp-sh-work-sec" aria-labelledby="work-h">
            <div class="cp-sh-work-head">
                <div>
                    <p class="cp-sh-kick">{{ $sh['work_kick'] }}</p>
                    <h2 id="work-h" class="cp-sh-h2">{{ $sh['work_title'][0] }} <em>{{ $sh['work_title'][1] }}</em></h2>
                </div>
                @if ($history['pull'] !== null)
                    <p class="cp-sh-work-lead">{{ $history['pull']['headline'] }}</p>
                @endif
            </div>
            @if ($history['pull'] !== null)
                <blockquote class="cp-sh-quote"><p>{{ $history['pull']['text'] }}<sup class="s-cite"><a href="#src-{{ $history['pull']['n'] }}" aria-label="{{ __('pages.site.cite', ['n' => $history['pull']['n']]) }}">{{ $history['pull']['n'] }}</a></sup></p></blockquote>
            @endif
            <ol class="cp-sh-jobs">
                @foreach ($history['work'] as $job)
                    <li class="cp-sh-job">
                        <p class="cp-sh-job-n" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</p>
                        <h3>{{ $job['name'] }}</h3>
                        <p class="cp-sh-mark cp-sh-mark-{{ $job['key'] }}"><span class="cp-sh-mark-art" aria-hidden="true">@foreach (range(1, 4) as $beat)<span></span>@endforeach</span><span>{{ $sh['marks'][$job['key']] ?? '' }}</span></p>
                        <p class="cp-sh-job-what">{{ $job['headline'] }}</p>
                        <p class="cp-sh-job-fact">{{ $job['text'] }}<sup class="s-cite"><a href="#src-{{ $job['n'] }}" aria-label="{{ __('pages.site.cite', ['n' => $job['n']]) }}">{{ $job['n'] }}</a></sup></p>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- What the app listens for, scored and not, beside a take that's a sample, said as one. --}}
    <section class="s-wrap cp-sh-listen-sec" aria-labelledby="hears-h">
        <div class="cp-sh-listen">
            <div>
                <p class="cp-sh-kick">{{ __('pages.site.course_hears') }}</p>
                <h2 id="hears-h" class="cp-sh-h2">{{ $sh['listen_title'][0] }} <em>{{ $sh['listen_title'][1] }}</em></h2>
                <div class="cp-sh-scores">
                    <div>
                        <h3 class="cp-sh-score-h cp-sh-score-yes">{{ $sh['scored'] }}</h3>
                        <ul>@foreach ((array) $sh['scored_lines'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                    </div>
                    <div>
                        <h3 class="cp-sh-score-h">{{ $sh['unscored'] }}</h3>
                        <ul class="cp-sh-unscored">@foreach ((array) $sh['unscored_lines'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                    </div>
                </div>
                <p class="cp-sh-gear">{{ $sh['gear'] }}</p>
            </div>
            <figure class="cp-sh-sample" aria-label="{{ $sh['sample']['label'] }}">
                <p class="cp-sh-sample-top"><span>{{ $sh['sample']['top'] }} · {{ $sh['sample']['unit'] }}</span><span class="cp-sh-sample-tag">{{ $sh['sample']['tag'] }}</span></p>
                <p class="cp-sh-cells" aria-hidden="true">
                    @foreach (\App\Support\Pages\SeaShantiesPage::sample() as $on)
                        <span @class(['cp-sh-cell', 'cp-sh-cell-off' => ! $on])></span>
                    @endforeach
                </p>
                <p class="cp-sh-cells-key" aria-hidden="true"><span><span class="cp-sh-cell"></span>{{ $sh['sample']['on'] }}</span><span><span class="cp-sh-cell cp-sh-cell-off"></span>{{ $sh['sample']['off'] }}</span></p>
                <p class="cp-sh-sample-n">{{ $sh['sample']['result'] }}</p>
                <p class="cp-sh-sample-foot"><span>{{ $sh['sample']['beat'] }} <b>{{ $sh['sample']['beat_result'] }}</b></span><span class="cp-sh-sample-skin"><x-site.say :text="$sh['sample']['skin']" /></span></p>
            </figure>
        </div>
    </section>

    {{-- From the old books: the rest of the lessons' facts, by the year they tell of, oldest first. --}}
    @if ($history['books'] !== [])
        <section class="cp-sh-books" aria-labelledby="books-h">
            <div class="s-wrap">
                <p class="cp-sh-kick">{{ $sh['books_kick'] }}</p>
                <h2 id="books-h" class="cp-sh-h2">{{ $sh['books_title'][0] }} <em>{{ $sh['books_title'][1] }}</em></h2>
                <ol class="cp-sh-years">
                    @foreach ($history['books'] as $year)
                        <li>
                            <p class="cp-sh-yr">{{ $year['year'] }}</p>
                            <div class="cp-sh-yr-facts">
                                @foreach ($year['facts'] as $fact)
                                    <p>{{ $fact['text'] }}<sup class="s-cite"><a href="#src-{{ $fact['n'] }}" aria-label="{{ __('pages.site.cite', ['n' => $fact['n']]) }}">{{ $fact['n'] }}</a></sup></p>
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- The songs as a songbook's index, each with the year its source was printed where the credit gives one, and
         singing safely, numbered, its lines word for word. --}}
    <section class="s-wrap cp-sh-book" aria-label="{{ __('pages.site.course_songs') }}">
        <div>
            <h2 class="cp-sh-kick" id="songs-h">{{ __('pages.site.course_songs') }}</h2>
            <ul class="cp-sh-songs" aria-labelledby="songs-h">
                @foreach ($songbook as $song)
                    <li>
                        <b translate="no">{{ $song['title'] }}</b>
                        @if ($song['year'] !== null)<span class="cp-sh-year">{{ $song['year'] }}</span>@endif
                        <span class="cp-sh-src">{{ $song['source'] }}</span>
                        @if ($song['lead'] !== '')
                            <span class="cp-sh-story">{{ $song['lead'] }} @if ($song['url'])<a href="{{ $song['url'] }}">{{ $sh['story_link'] }}</a>@endif</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2 class="cp-sh-kick" id="safety-h">{{ __('pages.site.course_safety') }}</h2>
            <ol class="cp-sh-safety" aria-labelledby="safety-h">
                @foreach ((array) ($page['safety'] ?? []) as $note)
                    <li><span class="cp-sh-safety-n" aria-hidden="true">{{ sprintf('%02d', $loop->iteration) }}</span><span>{{ $note }}</span></li>
                @endforeach
            </ol>
        </div>
    </section>

    @if ($faq !== [])
        <section class="s-wrap cp-sh-fine" aria-labelledby="faq-h">
            <h2 id="faq-h" class="cp-sh-h2">{{ $sh['faq_title'] }}</h2>
            @include('site.courses._faq', ['page' => ['faq' => $faq]])
        </section>
    @endif

    @if ($history['sources'] !== [])
        <section class="s-wrap cp-sh-sources" aria-labelledby="sources-h">
            <h2 id="sources-h" class="cp-sh-kick">{{ $sh['sources'] }}</h2>
            <ol>
                @foreach ($history['sources'] as [$label, $url])
                    <li id="src-{{ $loop->iteration }}">@if ($url !== '')<a href="{{ $url }}" rel="noopener">{{ $label }}</a>@else{{ $label }}@endif</li>
                @endforeach
            </ol>
        </section>
    @endif

    @include('site._course-today')

    <section class="s-wrap cp-sh-more" aria-labelledby="more-h">
        <h2 id="more-h" class="cp-sh-kick">{{ __('pages.site.course_more', ['group' => $group['name'] ?? '']) }}</h2>
        <ul class="s-chips">
            @foreach ((array) ($group['courses'] ?? []) as $slug)
                @continue($slug === $page['slug'])
                <li><a href="/courses/{{ $slug }}">{{ __('site.courses.pages.'.$slug.'.name') }}</a></li>
            @endforeach
        </ul>
    </section>
    <script type="module" src="/js/public/sea-shanties-and-work-songs.js"></script>
</main>
