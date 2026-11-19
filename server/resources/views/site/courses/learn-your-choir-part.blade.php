{{-- Learn your part for rehearsal (/courses/learn-your-choir-part), its own page after Hillel's design (2026-11-18,
     ~/Code/course-designs/learn-your-choir-part): built round the score of the course's own song (O Come, All Ye
     Faithful, from its template). The hero's card draws the first two lines in four parts with their bar numbers and
     words; picking a part (soprano first) lights that line, fades the other three, and turns the headline's "your", the
     bar numbers, the words and the sample take below to that part (js/public/learn-your-choir-part.js; without the
     script, soprano). Hear it sings the lines on voices.js, the picked part out front. Then what you'll learn, the units
     as rehearsal marks A to D between bar lines (a double bar at the end, before the rehearsal itself), every lesson a
     link to its place on its unit's page; what the app listens for beside a take marked as a sample; singing safely
     with its music signs beside the song; the beginner course beside Amazing Grace's four parts, lit as they sound;
     the group's other courses; and every lesson page by its mark (so the shared list below is left out). For length
     (Hillel, 2026-11-18: course pages roughly the same height), from the course's own file (App\Support\Site\
     ChoirPartCourse): lesson 1 as the app runs it, in four numbered bars with your picked part's own card; the course's
     four sourced findings on a dated line with what the course does about each; who it's for and what you need; the
     questions; a close; and the numbered Sources. Part colours
     are the site's s-v1 to s-v4, so calm light and dark and game all draw them. Words: lang/en/site.php
     courses.pages.learn-your-choir-part (`lcp`); notes: App\Support\Site\ChoirPartScore. --}}
@php($lcp = (array) $page['lcp'])
@php($score = \App\Support\Site\ChoirPartScore::hero())
@php($takes = \App\Support\Site\ChoirPartScore::take($score['parts']))
@php($grace = \App\Support\Site\ChoirPartScore::grace())
@php($lessonLinks = \App\Support\Site\ChoirPartScore::lessons($page['slug']))
@php($unitPages = \App\Support\Site\ChoirPartScore::unitPages($page['slug']))
@php($names = (array) $lcp['score']['parts'])
@php($bars = __('site.courses.pages.learn-your-choir-part.lcp.score.bars', ['from' => $score['from'], 'to' => $score['to']]))
@php($first = \App\Support\Site\ChoirPartCourse::firstLesson())
@php($findings = \App\Support\Site\ChoirPartCourse::findings())
@php($sources = \App\Support\Site\ChoirPartCourse::sources($findings))
@php($minutes = \App\Support\Site\ChoirPartCourse::minutes())
{{-- This page links every lesson page itself (its marks, at the end), so the shared list is left out. --}}
@if ($unitPages !== [])
    @section('lesson-pages-inline', '1')
@endif
<main class="cp cp-lcp" data-lcp data-part="soprano">
    <section class="s-wrap cp-lcp-hero" aria-labelledby="page-h">
        <div>
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <p class="cp-lcp-status">
                @if ($open)
                    <span class="cp-lcp-open"><span class="cp-lcp-open-dot" aria-hidden="true"></span>{{ __('pages.site.course_open') }}</span>
                @else
                    <x-site.soon />
                @endif
                @if ($outline !== null)
                    <span class="cp-lcp-count">{{ __('site.courses.pages.learn-your-choir-part.lcp.count', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</span>
                @endif
            </p>
            <h1 id="page-h">{{ $lcp['headline'][0] }} <em class="cp-lcp-your">{{ $lcp['headline'][1] }}</em> {{ $lcp['headline'][2] }}</h1>
            <p class="cp-lcp-lead">{{ $page['lead'] }}</p>
            <p class="cp-lcp-btns">
                @if ($open)
                    <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                @else
                    <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                @endif
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </p>
            <div class="cp-lcp-ways">
                @include('site._carols-link', ['line' => __('site.carols.links.choir-course')])
                {{-- For choirs, led to from where a director reads (Hillel, 2026-11-12), counted by its place. --}}
                <p class="s-soonline"><a href="/for/choir-directors?ref=for-choirs-course" class="s-lk">{{ __('pages.songs.for_choirs') }}</a></p>
            </div>
        </div>

        {{-- The score: the course's song's first two lines in four parts, from its template. --}}
        <div class="cp-lcp-score" data-lcp-score data-tempo="{{ $score['tempo'] }}">
            <div class="cp-lcp-score-head">
                <div>
                    <p class="s-kick" id="lcp-pick-h">{{ $lcp['score']['kick'] }}</p>
                    <p class="cp-lcp-score-song"><i translate="no">{{ $score['title'] }}</i> <span>{{ $bars }}</span></p>
                </div>
                <button type="button" class="cp-lcp-play" data-lcp-play data-play="{{ $lcp['score']['play'] }}" data-stop="{{ $lcp['score']['stop'] }}" aria-pressed="false"><span class="cp-lcp-play-icon" aria-hidden="true"></span><span data-label>{{ $lcp['score']['play'] }}</span></button>
            </div>
            <div class="cp-lcp-pick" role="group" aria-labelledby="lcp-pick-h">
                @foreach (\App\Support\Site\ChoirPartScore::PARTS as $key)
                    <button type="button" class="cp-lcp-tab s-v{{ $loop->iteration }}" data-lcp-pick="{{ $key }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><span class="cp-lcp-dot" aria-hidden="true"></span>{{ $names[$key] ?? $key }}</button>
                @endforeach
            </div>
            <figure class="cp-lcp-fig">
                <div class="cp-lcp-lanes" aria-hidden="true" translate="no">
                    <span class="cp-lcp-bars">
                        @foreach ($score['bars'] as $bar)
                            <span class="cp-lcp-bar" style="left: {{ $bar['left'] }}%"><b>{{ $bar['n'] }}</b></span>
                        @endforeach
                    </span>
                    @foreach ($score['parts'] as $key => $notes)
                        <span class="cp-lcp-letter s-v{{ $loop->iteration }}" style="grid-row: {{ $loop->iteration + 1 }}" data-lcp-of="{{ $key }}" @if ($loop->first) data-picked @endif>{{ mb_substr((string) ($names[$key] ?? $key), 0, 1) }}</span>
                        <span class="cp-lcp-lane s-v{{ $loop->iteration }}" style="grid-row: {{ $loop->iteration + 1 }}" data-lcp-of="{{ $key }}" data-lane="{{ $key }}" @if ($loop->first) data-picked @endif>
                            @foreach ($notes as $note)
                                <i class="cp-lcp-n" data-at="{{ $note['at'] }}" data-len="{{ $note['len'] }}" data-midi="{{ $note['midi'] }}" style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%; --y: {{ $note['y'] }}"></i>
                            @endforeach
                        </span>
                    @endforeach
                    <span class="cp-lcp-words">
                        @foreach ($score['parts'] as $key => $notes)
                            <span class="cp-lcp-say" data-lcp-of="{{ $key }}" @if ($loop->first) data-picked @endif>
                                @foreach ($notes as $note)
                                    @if ($note['word'] !== '')
                                        <span style="left: {{ $note['left'] }}%">{{ $note['word'] }}</span>
                                    @endif
                                @endforeach
                            </span>
                        @endforeach
                    </span>
                </div>
                <figcaption class="sr-only">{{ __('site.courses.pages.learn-your-choir-part.lcp.score.alt', ['song' => $score['title']]) }}</figcaption>
            </figure>
            <p class="cp-lcp-caption">{{ $lcp['score']['caption'] }}</p>
        </div>
    </section>

    <section class="s-wrap cp-lcp-sec cp-lcp-learn" aria-labelledby="learn-h">
        <div>
            <p class="s-kick">{{ __('pages.site.course_learn') }}</p>
            <h2 id="learn-h" class="cp-lcp-h2">{{ $lcp['learn_h'] }}</h2>
        </div>
        <ol class="cp-lcp-outcomes">
            @foreach ((array) ($page['learn'] ?? []) as $point)
                <li><span class="cp-lcp-num" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><span>{{ $point }}</span></li>
            @endforeach
        </ol>
    </section>

    {{-- The course, unit by unit, from its own file (SitePages::courseOutline): each unit a rehearsal mark between bar
         lines, a double bar after the last; every lesson a link to its place on its unit's page. --}}
    @if ($outline !== null)
        <section class="s-wrap cp-lcp-sec" aria-labelledby="units-h">
            <div class="cp-lcp-units-head">
                <div>
                    <p class="s-kick">{{ __('pages.site.course_units') }}</p>
                    <h2 id="units-h" class="cp-lcp-h2">{{ $lcp['units_h'] }}</h2>
                </div>
                <p class="cp-lcp-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-lcp-units">
                @foreach ($outline['units'] as $unit)
                    <li class="cp-lcp-unit">
                        <p class="cp-lcp-unit-top"><span class="cp-lcp-mark" aria-hidden="true">{{ \App\Support\Site\ChoirPartScore::mark($loop->index) }}</span><span class="cp-lcp-unit-n">{{ __('pages.site.course_unit', ['n' => $loop->iteration]) }}</span></p>
                        <h3>{{ $unit['title'] }}</h3>
                        <ol class="cp-lcp-lessons">
                            @foreach ($unit['lessons'] as $title)
                                <li>
                                    <span class="cp-lcp-ln" aria-hidden="true">{{ $loop->iteration }}</span>
                                    @if (isset($lessonLinks[$title]))
                                        <a href="{{ $lessonLinks[$title] }}">{{ $title }}</a>
                                    @else
                                        <span>{{ $title }}</span>
                                    @endif
                                    @if ($loop->parent->first && $loop->first)
                                        <span class="s-free">{{ __('pages.site.course_free') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- Lesson 1 as the app runs it, from the course file: four bars, the part on its own following the hero's pick. --}}
    @if ($first !== null)
        <section class="s-wrap cp-lcp-sec" aria-labelledby="try-h">
            <p class="s-kick">{{ $lcp['try']['kick'] }}</p>
            <h2 id="try-h" class="cp-lcp-h2">{{ $lcp['try']['title'] }}</h2>
            <p class="cp-lcp-p">{{ $first['intro'] }}</p>
            <ol class="cp-lcp-try">
                <li>
                    <p class="cp-lcp-try-n"><span class="cp-lcp-mark" aria-hidden="true">1</span>{{ $lcp['try']['steps'][0] }}</p>
                    <ul class="cp-lcp-try-cards">
                        @foreach ($first['cards'] as $card)
                            <li><b>{{ $card['title'] }}</b> {{ $card['detail'] }}</li>
                        @endforeach
                    </ul>
                </li>
                <li>
                    <p class="cp-lcp-try-n"><span class="cp-lcp-mark" aria-hidden="true">2</span>{{ $lcp['try']['steps'][1] }}</p>
                    @foreach ($first['parts'] as $key => $own)
                        <div class="cp-lcp-try-part s-v{{ $own['n'] }}" data-lcp-of="{{ $key }}" @if ($loop->first) data-picked @endif>
                            <p class="cp-lcp-try-head">{{ $own['headline'] }}</p>
                            <ul class="cp-lcp-try-cards">
                                @foreach ($own['cards'] as $card)
                                    <li><b>{{ $card['title'] }}</b> {{ $card['detail'] }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endforeach
                    <p class="cp-lcp-try-note">{{ $lcp['try']['note'] }}</p>
                </li>
                <li>
                    <p class="cp-lcp-try-n"><span class="cp-lcp-mark" aria-hidden="true">3</span>{{ $lcp['try']['steps'][2] }}</p>
                    <p class="cp-lcp-try-text">{{ $first['find'] }}</p>
                </li>
                <li>
                    <p class="cp-lcp-try-n"><span class="cp-lcp-mark" aria-hidden="true">4</span>{{ $lcp['try']['steps'][3] }}</p>
                    <p class="cp-lcp-try-text">{{ $first['sing'] }}</p>
                    <p class="cp-lcp-try-note">{{ $first['scored'] }}</p>
                </li>
            </ol>
            @if ($open)
                <p class="cp-lcp-btns"><a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a></p>
                <p class="cp-lcp-join">{{ __('pages.site.course_join_note') }}</p>
            @endif
        </section>
    @endif

    {{-- What the app listens for, beside a take plainly marked as a sample (made-up notes on the song's real line, never a
         singer's score), in the picked part. --}}
    <section class="s-wrap cp-lcp-sec cp-lcp-hears" aria-labelledby="hears-h">
        <div>
            <p class="s-kick">{{ __('pages.site.course_hears') }}</p>
            <h2 id="hears-h" class="cp-lcp-h2">{{ $lcp['hears_h'] }}</h2>
            <p class="cp-lcp-p">{{ $lcp['hears'] }}</p>
            @if (is_array($page['scoring'] ?? null))
                <p class="cp-lcp-p"><x-site.say :text="$page['scoring']" /></p>
            @endif
        </div>
        <div class="cp-lcp-card">
            <p class="cp-lcp-sample"><b>{{ $lcp['sample']['kick'] }}</b> <span>{{ $lcp['sample']['note'] }}</span></p>
            <p class="cp-lcp-take-head">
                <span>
                    @foreach ($takes as $key => $take)
                        <span class="cp-lcp-say-one" data-lcp-of="{{ $key }}" @if ($loop->first) data-picked @endif>{{ __('site.courses.pages.learn-your-choir-part.lcp.sample.head', ['part' => $names[$key] ?? $key]) }}</span>
                    @endforeach
                </span>
                <span>{{ $bars }}</span>
            </p>
            <div class="cp-lcp-take" aria-hidden="true">
                @foreach ($takes as $key => $take)
                    <span class="cp-lcp-take-lane s-v{{ $loop->iteration }}" data-lcp-of="{{ $key }}" @if ($loop->first) data-picked @endif>
                        @foreach ($take['notes'] as $note)
                            @unless ($note['on'])
                                <i class="cp-lcp-where" style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%; --y: {{ $note['y'] }}"></i>
                            @endunless
                            <i @class(['cp-lcp-sung', 'cp-lcp-off' => ! $note['on']]) style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%; --y: {{ $note['sung'] }}"></i>
                        @endforeach
                    </span>
                @endforeach
            </div>
            @foreach ($takes as $key => $take)
                <p class="cp-lcp-tally" data-lcp-of="{{ $key }}" @if ($loop->first) data-picked @endif><b>{{ __('site.courses.pages.learn-your-choir-part.lcp.sample.count', ['hit' => $take['hit'], 'all' => $take['all']]) }}</b> <span>{{ $lcp['sample']['hit'] }}</span><span class="sr-only">{{ __('site.courses.pages.learn-your-choir-part.lcp.sample.alt', ['hit' => $take['hit'], 'all' => $take['all']]) }}</span></p>
            @endforeach
            <ul class="cp-lcp-key">
                <li><span class="cp-lcp-key-on" aria-hidden="true"></span>{{ $lcp['sample']['key']['on'] }}</li>
                <li><span class="cp-lcp-key-off" aria-hidden="true"></span>{{ $lcp['sample']['key']['off'] }}</li>
                <li><span class="cp-lcp-key-where" aria-hidden="true"></span>{{ $lcp['sample']['key']['where'] }}</li>
            </ul>
        </div>
    </section>

    {{-- The course's four findings (each lesson's "Did you know?"), oldest first on one dated line, each with what the
         course does about it and its numbered note. --}}
    @if ($findings !== [])
        <section class="s-wrap cp-lcp-sec" aria-labelledby="science-h">
            <p class="s-kick">{{ $lcp['science']['kick'] }}</p>
            <h2 id="science-h" class="cp-lcp-h2">{{ $lcp['science']['title'] }}</h2>
            <ol class="cp-lcp-found">
                @foreach ($findings as $finding)
                    <li>
                        <p class="cp-lcp-year">{{ $finding['year'] }}</p>
                        @isset($lcp['science']['facts'][$finding['id']])
                            <h3>{{ $lcp['science']['facts'][$finding['id']]['title'] }}</h3>
                        @endisset
                        <p class="cp-lcp-found-text">{{ $finding['text'] }}@include('site.courses._cites', ['keys' => [$finding['id']]])</p>
                        @isset($lcp['science']['facts'][$finding['id']])
                            <p class="cp-lcp-found-use"><span class="cp-lcp-unit-n">{{ __('site.courses.pages.learn-your-choir-part.lcp.science.unit', ['n' => $finding['unit']]) }}</span> {{ $lcp['science']['facts'][$finding['id']]['use'] }}</p>
                        @endisset
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @if (($page['safety'] ?? []) !== [] || ($page['songs'] ?? []) !== [])
        <section class="s-wrap cp-lcp-sec cp-lcp-notes">
            @if (($page['safety'] ?? []) !== [])
                <div aria-labelledby="safety-h">
                    <h2 id="safety-h" class="s-kick">{{ __('pages.site.course_safety') }}</h2>
                    <ul class="cp-lcp-safe">
                        @foreach ((array) $page['safety'] as $note)
                            <li><span class="cp-lcp-sign" aria-hidden="true">{{ $lcp['signs'][$loop->index] ?? '' }}</span><span>{{ $note }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (($page['songs'] ?? []) !== [])
                <div aria-labelledby="songs-h">
                    <h2 id="songs-h" class="s-kick">{{ $lcp['song'] }}</h2>
                    <ul class="cp-lcp-songs">
                        @foreach ((array) $page['songs'] as [$song, $credit])
                            <li><b translate="no">{{ $song }}</b><span>{{ $credit }}</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </section>
    @endif

    <section class="s-wrap cp-lcp-sec cp-lcp-who">
        <div aria-labelledby="who-h">
            <h2 id="who-h" class="s-kick">{{ $lcp['who']['title'] }}</h2>
            <ul class="cp-lcp-ruled">
                @foreach ((array) $lcp['who']['items'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
        <div aria-labelledby="need-h">
            <h2 id="need-h" class="s-kick">{{ $lcp['need']['title'] }}</h2>
            <ul class="cp-lcp-ruled">
                <li>{{ $lcp['need']['items'][0] }}</li>
                <li>{{ $lcp['need']['items'][1] }}</li>
                @if ($minutes !== null)
                    <li>{{ __('site.courses.pages.learn-your-choir-part.lcp.need.items.2', ['minutes' => $minutes]) }}</li>
                @endif
            </ul>
        </div>
    </section>

    <section class="s-wrap cp-lcp-sec" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-lcp-h2">{{ $lcp['faq_h'] }}</h2>
        @include('site.courses._faq')
    </section>

    {{-- New to harmony: the beginner course, beside Amazing Grace's four parts (its unit 7), lit as they sound. --}}
    <section class="s-ink cp-lcp-today" aria-labelledby="today-h">
        <div class="s-wrap cp-lcp-today-in">
            <div>
                <p class="s-kick">{{ __('pages.site.course_today_kick') }}</p>
                <h2 id="today-h" class="cp-lcp-h2 cp-lcp-h2-l">{{ $lcp['today']['title'][0] }} <em>{{ $lcp['today']['title'][1] }}</em></h2>
                <p class="cp-lcp-p">{{ __('pages.site.course_today_body') }}</p>
                <p class="cp-lcp-btns"><a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
            <figure class="cp-lcp-grace" data-lcp-grace data-tempo="{{ $grace['tempo'] }}">
                <div class="cp-lcp-grace-head">
                    <p>{{ $lcp['today']['panel'] }}</p>
                    <button type="button" class="cp-lcp-play" data-lcp-grace-play data-play="{{ $lcp['today']['play'] }}" data-stop="{{ $lcp['score']['stop'] }}" aria-pressed="false"><span class="cp-lcp-play-icon" aria-hidden="true"></span><span data-label>{{ $lcp['today']['play'] }}</span></button>
                </div>
                <div class="cp-lcp-glanes" aria-hidden="true">
                    @foreach ($grace['parts'] as $key => $notes)
                        <span class="cp-lcp-gname s-v{{ $loop->iteration }}">{{ $names[$key] ?? $key }}</span>
                        <span class="cp-lcp-glane s-v{{ $loop->iteration }}" data-lane="{{ $key }}">
                            @foreach ($notes as $note)
                                <i @class(['cp-lcp-gn', 'cp-lcp-still' => $note['at'] < $grace['span'] * 0.6]) data-at="{{ $note['at'] }}" data-len="{{ $note['len'] }}" data-midi="{{ $note['midi'] }}" style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%; --y: {{ $note['y'] }}"></i>
                            @endforeach
                        </span>
                    @endforeach
                </div>
                <figcaption class="sr-only">{{ $lcp['today']['alt'] }}</figcaption>
            </figure>
        </div>
    </section>

    {{-- The close: the way in, between a bar line and the final double bar. --}}
    <section class="s-wrap cp-lcp-sec" aria-labelledby="close-h">
        <div class="cp-lcp-close">
            <p class="s-kick">{{ $lcp['close']['kick'] }}</p>
            <h2 id="close-h" class="cp-lcp-h2 cp-lcp-h2-l">{{ $lcp['close']['title'] }}</h2>
            <p class="cp-lcp-p">{{ $open ? __('pages.site.course_join_open') : __('pages.site.course_soon') }}</p>
            <p class="cp-lcp-btns">
                @if ($open)
                    <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                @else
                    <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                @endif
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </p>
        </div>
    </section>

    <section class="s-wrap cp-lcp-sec" aria-labelledby="more-h">
        <h2 id="more-h" class="s-kick">{{ __('pages.site.course_more', ['group' => $group['name'] ?? '']) }}</h2>
        <ul class="s-chips">
            @foreach ((array) ($group['courses'] ?? []) as $slug)
                @continue($slug === $page['slug'])
                <li><a href="/courses/{{ $slug }}">{{ __('site.courses.pages.'.$slug.'.name') }}</a></li>
            @endforeach
        </ul>
    </section>

    {{-- Every lesson page (docs/seo-lesson-pages-plan.md), one a unit here, each by its rehearsal mark: the shared list's
         links, so a reader and Google reach each one. --}}
    @if ($unitPages !== [])
        <nav class="s-wrap cp-lcp-sec cp-lcp-reads-wrap" aria-labelledby="reads-h">
            <h2 id="reads-h" class="s-kick">{{ __('site.lessons.every') }}</h2>
            <ul class="cp-lcp-reads">
                @foreach ($unitPages as $unitPage)
                    <li><a href="{{ $unitPage['url'] }}"><span class="cp-lcp-mark" aria-hidden="true">{{ \App\Support\Site\ChoirPartScore::mark($loop->index) }}</span><b><span class="sr-only">{{ __('pages.site.course_unit', ['n' => $unitPage['unitNumber']]) }}: </span>{{ $unitPage['name'] }}</b><span>{{ $unitPage['title'] }}</span></a></li>
                @endforeach
            </ul>
        </nav>
    @endif
    {{-- The numbered notes the findings point to. --}}
    @if ($sources !== [])
        <section class="s-wrap cp-lcp-sec cp-lcp-sources" aria-labelledby="notes-h">
            <h2 id="notes-h" class="s-kick">{{ __('pages.site.sources') }}</h2>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </section>
    @endif
    <script type="module" src="/js/public/learn-your-choir-part.js"></script>
</main>
