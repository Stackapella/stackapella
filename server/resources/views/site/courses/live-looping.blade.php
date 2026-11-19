{{-- Live looping (/courses/live-looping), its own page after Hillel's design (2026-11-18,
     ~/Code/course-designs/live-looping): a song built from tracks on repeat, one colour per track (the site's part colours
     s-v1 to s-v4, so calm light and dark and game all draw them), carried through the headline, what you'll learn, the
     units and the lesson list. The hero's loop is four rings round one 8-bar cycle, the course's own Are You Sleeping
     (Frère Jacques): pressed, js/public/live-looping.js sings it on voices.js, each track joining in turn, on the audio
     clock; idle, the playhead sweeps by CSS alone (still under reduced motion). The units are a track arrangement, every
     lesson a block linked to its page; a sample take plainly marked as one; singing safely beside the numbered songs;
     the beginner course beside a real screen of its map; every lesson's page, to read (this page lists them, so the
     shared list after <main> is dropped). Pieces: App\Support\Site\LiveLoopingPage. Words: lang/en/site.php
     courses.pages.live-looping (`ll`). --}}
@section('lesson-pages-inline', '1')
@php($ll = (array) $page['ll'])
@php($loop8 = \App\Support\Site\LiveLoopingPage::rings())
@php($tracks = $outline !== null ? \App\Support\Site\LiveLoopingPage::units($page['slug'], $outline) : [])
@php($every = \App\Support\Site\LiveLoopingPage::lessonPages($page['slug']))
<main class="cp cp-ll">
    <section class="s-wrap cp-ll-hero" aria-labelledby="page-h">
        <div class="cp-ll-copy">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <div class="cp-ll-meta">
                @if ($open)
                    <p class="cp-ll-open"><span class="cp-ll-open-dot" aria-hidden="true"></span>{{ __('pages.site.course_open') }}</p>
                @else
                    <p class="s-soonpill"><x-site.soon /></p>
                @endif
                @if ($outline !== null)
                    <p class="cp-ll-mono">{{ __('site.courses.pages.live-looping.ll.eyebrow', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                @endif
            </div>
            <h1 id="page-h">{{ $ll['title'][0] }}@foreach (array_slice((array) $ll['title'], 1) as $word) <span class="cp-ll-word s-v{{ $loop->iteration }}">{{ $word }}</span>@endforeach</h1>
            <p class="cp-ll-lead">{{ $page['lead'] }}</p>
            <div class="cp-ll-btns">
                @if ($open)
                    <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                @else
                    <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                @endif
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @if ($open)
                    <span class="cp-ll-free">{{ __('pages.site.course_open_line') }}</span>
                @endif
            </div>
        </div>

        {{-- The loop: a ring a track, outside in, each note an arc where it sits in the 8 bars; the play bubble at the
             centre. --}}
        <figure class="cp-ll-loop" data-ll data-tempo="{{ $loop8['tempo'] }}" data-span="{{ $loop8['span'] }}" data-length="{{ $loop8['length'] }}">
            <div class="cp-ll-rings" style="--ll-turn: {{ $loop8['turn'] }}s">
                <svg class="cp-ll-svg" viewBox="0 0 400 400" aria-hidden="true" focusable="false">
                    @foreach ($loop8['rings'] as $ring)
                        <g class="cp-ll-ring s-v{{ $ring['v'] }}" data-ll-ring="{{ $ring['key'] }}" data-from="{{ $ring['from'] }}" data-to="{{ $ring['to'] }}" data-loop="{{ $ring['loop'] }}" data-notes="{{ $ring['sound'] }}">
                            <circle class="cp-ll-track" cx="200" cy="200" r="{{ $ring['r'] }}" />
                            @foreach ($ring['arcs'] as $arc)
                                <circle class="cp-ll-arc" cx="200" cy="200" r="{{ $ring['r'] }}" pathLength="{{ $loop8['span'] }}" stroke-dasharray="{{ $arc['dash'] }} {{ $loop8['span'] * 2 }}" stroke-dashoffset="-{{ $arc['pos'] }}" data-pos="{{ $arc['pos'] }}" data-len="{{ $arc['len'] }}" />
                            @endforeach
                        </g>
                    @endforeach
                </svg>
                <span class="cp-ll-ph" data-ll-ph aria-hidden="true"><i></i></span>
                <button type="button" class="cp-ll-play bubble" data-ll-play data-play="{{ $ll['rings']['play'] }}" data-stop="{{ $ll['rings']['stop'] }}" aria-pressed="false"><span class="cp-ll-play-icon" aria-hidden="true"></span><span class="sr-only" data-label>{{ $ll['rings']['play'] }}</span></button>
            </div>
            <ul class="cp-ll-legend" aria-hidden="true">
                @foreach ($loop8['rings'] as $ring)
                    <li class="s-v{{ $ring['v'] }}"><span class="cp-ll-swatch"></span>{{ $ll['rings']['tracks'][$ring['key']] }}</li>
                @endforeach
                <li class="cp-ll-mono">{{ $ll['rings']['bars'] }}</li>
            </ul>
            <figcaption class="cp-ll-source"><span class="sr-only">{{ $ll['rings']['alt'] }} </span>{{ $ll['rings']['source'] }}</figcaption>
        </figure>
    </section>

    <section class="s-wrap cp-ll-sec cp-ll-ruled" aria-labelledby="learn-h">
        <h2 id="learn-h" class="cp-ll-kick">{{ $ll['learn_kick'] }}</h2>
        <ul class="cp-ll-learn">
            @foreach ((array) ($page['learn'] ?? []) as $point)
                <li class="s-v{{ $loop->index % 4 + 1 }}"><span class="cp-ll-bar" aria-hidden="true"></span><h3>{{ $ll['learn_titles'][$loop->index] ?? '' }}</h3><p>{{ $point }}</p></li>
            @endforeach
        </ul>
    </section>

    {{-- The course itself, from its own file (SitePages::courseOutline): each unit a track, each lesson a block linked to
         the page that teaches it; the first marked Start here, the last spanning the end in every track's colour. --}}
    @if ($tracks !== [])
        <section class="s-wrap cp-ll-sec cp-ll-ruled" aria-labelledby="units-h">
            <div class="cp-ll-units-head">
                <div>
                    <p class="cp-ll-kick">{{ __('pages.site.course_units') }}</p>
                    <h2 id="units-h" class="cp-ll-h2">{{ $ll['units_h'] }}</h2>
                </div>
                <p class="cp-ll-mono">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-ll-arrange">
                @foreach ($tracks as $unit)
                    <li class="cp-ll-unit s-v{{ $loop->index % 4 + 1 }}">
                        <h3><span class="cp-ll-track-n">{{ __('site.courses.pages.live-looping.ll.track', ['n' => $loop->iteration]) }}</span> <span class="sr-only">{{ __('pages.site.course_unit', ['n' => $loop->iteration]) }}: </span>{{ $unit['title'] }}</h3>
                        <ol class="cp-ll-blocks">
                            @foreach ($unit['lessons'] as $lesson)
                                <li @class(['cp-ll-first' => $lesson['first'], 'cp-ll-all' => $lesson['last']])>
                                    @if ($lesson['url'] !== null)
                                        <a href="{{ $lesson['url'] }}" class="cp-ll-block"><span class="cp-ll-block-n">{{ $lesson['tag'] }}</span><span class="cp-ll-block-t">{{ $lesson['title'] }}</span></a>
                                    @else
                                        <span class="cp-ll-block"><span class="cp-ll-block-n">{{ $lesson['tag'] }}</span><span class="cp-ll-block-t">{{ $lesson['title'] }}</span></span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- What the app listens for, beside a take plainly marked as a sample (the course's own example numbers, lang
         `scoring`), never a singer's score. --}}
    <section class="s-wrap cp-ll-sec cp-ll-ruled cp-ll-hears" aria-labelledby="hears-h">
        <div>
            <p class="cp-ll-kick">{{ __('pages.site.course_hears') }}</p>
            <h2 id="hears-h" class="cp-ll-h2">{{ $ll['hears_h'] }}</h2>
            @foreach ((array) $ll['hears'] as $line)
                <p class="cp-ll-p">{{ $line }}</p>
            @endforeach
            @if (is_array($page['scoring'] ?? null))
                <p class="cp-ll-p"><x-site.say :text="$page['scoring']" /></p>
            @endif
        </div>
        <div class="cp-ll-take cp-ll-dark">
            <p class="cp-ll-sample"><b>{{ $ll['sample']['kick'] }}</b> <span>{{ $ll['sample']['note'] }}</span></p>
            <p class="sr-only">{{ $ll['sample']['dots'] }}</p>
            <ol class="cp-ll-dots" aria-hidden="true">
                @foreach (\App\Support\Site\LiveLoopingPage::sampleDots() as $dot)
                    <li @class(['s-v'.$dot['v'], 'cp-ll-miss' => $dot['miss']])></li>
                @endforeach
            </ol>
            <p class="cp-ll-stat">{{ __('site.courses.pages.live-looping.ll.sample.hit', ['hit' => 21, 'n' => 24]) }}</p>
            <p class="cp-ll-stat2">{{ __('site.courses.pages.live-looping.ll.sample.time', ['pct' => '93%']) }}</p>
        </div>
    </section>

    @if (($page['songs'] ?? []) !== [] || ($page['safety'] ?? []) !== [])
        <section class="s-wrap cp-ll-sec cp-ll-ruled cp-ll-notes">
            @if (($page['safety'] ?? []) !== [])
                <div aria-labelledby="safety-h">
                    <h2 id="safety-h" class="cp-ll-kick">{{ __('pages.site.course_safety') }}</h2>
                    <ol class="cp-ll-safe">
                        @foreach ((array) $page['safety'] as $note)
                            <li><span class="cp-ll-n" aria-hidden="true">{{ \App\Support\Site\LiveLoopingPage::place($loop->index) }}</span>{{ $note }}</li>
                        @endforeach
                    </ol>
                </div>
            @endif
            @if (($page['songs'] ?? []) !== [])
                <div aria-labelledby="songs-h">
                    <h2 id="songs-h" class="cp-ll-kick">{{ __('pages.site.course_songs') }}</h2>
                    <ol class="cp-ll-songs">
                        @foreach ((array) $page['songs'] as [$song, $credit])
                            <li><span class="cp-ll-n" aria-hidden="true">{{ \App\Support\Site\LiveLoopingPage::place($loop->index) }}</span><b translate="no">{{ $song }}</b><span class="cp-ll-credit">{{ $credit }}</span></li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </section>
    @endif

    {{-- New to harmony: the beginner course, beside a real screen of its map in the app. --}}
    <section class="s-ink cp-ll-today cp-ll-dark" aria-labelledby="today-h">
        <div class="s-wrap cp-ll-today-in">
            <div>
                <p class="cp-ll-kick">{{ __('pages.site.course_today_kick') }}</p>
                <h2 id="today-h" class="cp-ll-h2 cp-ll-h2-l">{{ __('pages.site.course_today_title') }}</h2>
                <p class="cp-ll-p">{{ __('pages.site.course_today_body') }}</p>
                <div class="cp-ll-btns"><a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a></div>
            </div>
            <div class="cp-ll-shot"><x-site.appshot id="your-voice--map" :alt="__('site.features.pages.course.shot_alt')" /></div>
        </div>
    </section>

    <section class="s-wrap cp-ll-sec" aria-labelledby="more-h">
        <h2 id="more-h" class="cp-ll-kick">{{ __('pages.site.course_more', ['group' => $group['name'] ?? '']) }}</h2>
        <ul class="s-chips cp-ll-chips">
            @foreach ((array) ($group['courses'] ?? []) as $slug)
                @continue($slug === $page['slug'])
                <li><a href="/courses/{{ $slug }}">{{ __('site.courses.pages.'.$slug.'.name') }}</a></li>
            @endforeach
        </ul>
    </section>

    {{-- Every lesson's own page (docs/seo-lesson-pages-plan.md), by unit, each unit in its track's colour. --}}
    @if ($every !== [])
        <nav class="s-wrap cp-ll-sec cp-ll-ruled cp-ll-every" aria-labelledby="every-h">
            <h2 id="every-h" class="cp-ll-kick">{{ __('site.lessons.every') }}</h2>
            <div class="cp-ll-every-grid">
                @foreach ($every as $unit)
                    <div class="s-v{{ ($unit['number'] - 1) % 4 + 1 }}">
                        <h3><span class="cp-ll-swatch" aria-hidden="true"></span>{{ __('site.lessons.unitOf', ['number' => $unit['number'], 'unit' => $unit['unit']]) }}</h3>
                        <ul>
                            @foreach ($unit['pages'] as $lessonPage)
                                <li><a href="{{ $lessonPage['url'] }}"><b>{{ $lessonPage['name'] }}</b><span>{{ $lessonPage['title'] }}</span><x-site.icon name="arrow" size="16" /></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>
    @endif
    <script type="module" src="/js/public/live-looping.js"></script>
</main>
