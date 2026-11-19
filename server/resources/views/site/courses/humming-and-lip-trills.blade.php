{{-- Humming and lip trills (/courses/humming-and-lip-trills), its own page after Hillel's design (2026-11-18,
     ~/Code/course-designs/humming-and-lip-trills): the course's four sounds, each in its own part colour and line shape,
     lip trill (part 1, bubbles on a roller coaster), "ng" siren (part 2, a glide), hum (part 3, a buzzing line) and
     "ah" (part 4, a ribbon that widens). Every shape is drawn from the lessons' own exercises
     (App\Support\Site\HummingRibbons). The hero is one ribbon through the four sounds with a slow line sweeping across
     it (CSS, still under reduced motion, and a button pauses it); pressed, js/public/humming-and-lip-trills.js sings
     the same ribbon on voices.js, on the audio clock. Then the units, each with its shape, what it teaches and every
     lesson linked to its page; the facts, each led by its big word and cited; what the app listens for beside a take
     plainly marked as a sample; singing safely beside a quiet-to-forced scale; the beginner course beside Amazing
     Grace's four parts from its template; the group's other courses; every lesson page by unit (in place of the shared
     list); and the sources. Part colours are the site's s-v1 to s-v4, so calm light and dark and game all draw them.
     Words: lang/en/site.php courses.pages.humming-and-lip-trills (`hum` for the page's own). --}}
@section('lesson-pages-inline', '1')
{{-- Everything drawn, from the course's own files (App\Support\Site\HummingRibbons::view). --}}
@php($v = \App\Support\Site\HummingRibbons::view($page, $outline, $group))
@php($hum = $v['hum'])
@php($hero = $v['hero'])
@php($units = $v['units'])
@php($facts = $v['facts'])
@php($sources = $v['sources'])
@php($hears = $v['hears'])
@php($take = $v['take'])
@php($grace = $v['grace'])
@php($reads = $v['reads'])
<main class="cp cp-hum">
    <section class="s-wrap cp-hum-hero" aria-labelledby="page-h">
        <div class="cp-hum-top">
            <div class="cp-hum-copy">
                <x-site.crumbs :trail="[[__('pages.site.courses'), '/courses'], [$page['name']]]" />
                @if ($open)
                    <p class="cp-hum-open"><span class="cp-hum-open-dot" aria-hidden="true"></span>{{ __('pages.site.course_open') }}</p>
                @else
                    <p class="s-soonpill"><x-site.soon /></p>
                @endif
                {{-- The headline's marked words in their sounds' colours (PHP drops the newline after each @endif, so no space is added). --}}
                <h1 id="page-h">@foreach ($v['headline'] as $bit)@if ($bit['part'] !== null)<span class="cp-hum-em s-v{{ $bit['part'] }}">{{ $bit['text'] }}</span>@else{{ $bit['text'] }}@endif
@endforeach</h1>
                <p class="cp-hum-lead">{{ $page['lead'] }}</p>
                <p class="cp-hum-line">{{ $open ? __('pages.site.course_open_line') : __('pages.site.course_soon') }}</p>
                <p class="cp-hum-btns">
                    @if ($open)
                        <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                    @else
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                    @endif
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                </p>
            </div>

            {{-- The ribbon: the four sounds, each a quarter, its name and sound under it. --}}
            <figure class="cp-hum-ribbon" data-hum-ribbon data-sing="{{ $hero['sing'] }}">
                <div class="cp-hum-ribbon-head">
                    <button type="button" class="cp-hum-play" data-hum-play data-play="{{ $hum['ribbon']['play'] }}" data-stop="{{ $hum['ribbon']['stop'] }}" aria-pressed="false"><span class="cp-hum-play-icon" aria-hidden="true"></span><span data-label>{{ $hum['ribbon']['play'] }}</span></button>
                    <p class="cp-hum-ribbon-name"><b>{{ $hum['ribbon']['kick'] }}</b> <span>{{ $hum['ribbon']['note'] }}</span></p>
                    <button type="button" class="cp-hum-still" data-hum-still aria-pressed="false" aria-label="{{ $hum['ribbon']['motion'] }}" title="{{ $hum['ribbon']['motion'] }}"><span aria-hidden="true"></span></button>
                </div>
                <div class="cp-hum-stage">
                    <svg viewBox="0 0 560 220" aria-hidden="true" focusable="false">
                        <path d="M20,150L540,150" class="cp-hum-base" />
                        @foreach ($hero['segments'] as $segment)
                            <g class="s-v{{ $segment['part'] }}">
                                @include('site.courses._humming-shape', ['shape' => $segment])
                            </g>
                        @endforeach
                    </svg>
                    <span class="cp-hum-ph" data-hum-ph aria-hidden="true"></span>
                </div>
                <ul class="cp-hum-sounds" aria-hidden="true">
                    @foreach ((array) $hum['ribbon']['sounds'] as [$sound, $name])
                        <li class="s-v{{ $loop->iteration }}"><b translate="no">{{ $sound }}</b><span>{{ $name }}</span></li>
                    @endforeach
                </ul>
                <figcaption class="sr-only">{{ $hum['ribbon']['alt'] }}</figcaption>
            </figure>
        </div>
    </section>

    {{-- The course itself, unit by unit, from its own file (SitePages::courseOutline): each unit's sound shape, what
         it teaches (the page's `learn`), and its lessons, each linked to its page; the first one marked Start here. --}}
    @if ($units !== [])
        <section class="s-wrap cp-hum-sec" aria-labelledby="units-h">
            <div class="cp-hum-head">
                <p class="s-kick">{{ __('pages.site.course_units') }}</p>
                <p class="cp-hum-count">{{ __('pages.site.course_size', ['units' => count($units), 'lessons' => $v['lessons']]) }}</p>
            </div>
            <h2 id="units-h" class="cp-hum-h2">{{ $hum['units_h'] }}</h2>
            <ol class="cp-hum-units">
                @foreach ($units as $unit)
                    <li class="cp-hum-unit s-v{{ $unit['part'] }}">
                        <div class="cp-hum-unit-top">
                            <p class="cp-hum-unit-n"><x-site.skin :game="$unit['stage']" :serious="$unit['unit']" /></p>
                            <p class="cp-hum-glyph" translate="no" aria-hidden="true">{{ $hum['ribbon']['sounds'][$loop->index][0] ?? '' }}</p>
                            <svg viewBox="0 0 280 80" aria-hidden="true" focusable="false">
                                @include('site.courses._humming-shape', ['shape' => $unit])
                            </svg>
                        </div>
                        <div class="cp-hum-unit-body">
                            <h3>{{ $unit['title'] }}</h3>
                            @if ($unit['learn'] !== '')
                                <p class="cp-hum-unit-say">{{ $unit['learn'] }}</p>
                            @endif
                            <ol class="cp-hum-lessons">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li>
                                        <span class="cp-hum-n" aria-hidden="true">{{ $lesson['n'] }}</span>
                                        @if ($lesson['url'] !== null)
                                            <a href="{{ $lesson['url'] }}">{{ $lesson['name'] }}</a>
                                        @else
                                            <span>{{ $lesson['name'] }}</span>
                                        @endif
                                        @if ($lesson['start'])
                                            <span class="s-free">{{ __('pages.site.course_free') }}</span>
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

    {{-- How each sound works, in plain words: each sound's own learn step (the course's words) and the checked fact that
         explains it, in its colour, with its shape. --}}
    @if ($v['science'] !== [])
        <section class="s-wrap cp-hum-sec" aria-labelledby="science-h">
            <p class="s-kick">{{ $hum['science']['kick'] }}</p>
            <h2 id="science-h" class="cp-hum-h2">{{ $hum['science']['title'] }}</h2>
            <ol class="cp-hum-science">
                @foreach ($v['science'] as $card)
                    <li class="s-v{{ $card['part'] }}">
                        <p class="cp-hum-science-name"><b translate="no">{{ $hum['ribbon']['sounds'][$loop->index][0] ?? '' }}</b> <span>{{ $hum['ribbon']['sounds'][$loop->index][1] ?? '' }}</span></p>
                        <p class="cp-hum-science-lead">{{ $card['lead'] }}</p>
                        @if ($card['fact'] !== '')
                            <p class="cp-hum-science-fact">{{ $card['fact'] }}@include('site.courses._cites', ['keys' => [$card['key']]])</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- Did you know: each fact led by its big word, cited from the lesson that tells it (the Sources at the end). --}}
    @if ($facts !== [])
        <section class="s-wrap cp-hum-sec cp-hum-know" aria-labelledby="know-h">
            <h2 id="know-h" class="s-kick">{{ $page['history']['title'] }}</h2>
            <ul class="cp-hum-facts">
                @foreach ($facts as $fact)
                    <li class="s-v{{ $fact['part'] }}">
                        <p class="cp-hum-word" translate="no" @if ($fact['lang'] !== null) lang="{{ $fact['lang'] }}" @endif>{{ $fact['word'] }}</p>
                        <p class="cp-hum-fact">{{ $fact['text'] }}@if ($fact['cited'])@include('site.courses._cites', ['keys' => [$fact['key']]])@endif</p>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- A short history: the dated facts, oldest first, each a stop on one ribbon in its sound's colour. --}}
    @if ($v['story'] !== [])
        <section class="s-wrap cp-hum-sec" aria-labelledby="story-h">
            <div class="cp-hum-story">
                <div>
                    <p class="s-kick">{{ $hum['story']['kick'] }}</p>
                    <h2 id="story-h" class="cp-hum-h2">{{ $hum['story']['title'] }}</h2>
                </div>
                <ol class="cp-hum-years">
                    @foreach ($v['story'] as $stop)
                        <li class="s-v{{ $stop['part'] }}">
                            <p class="cp-hum-year">{{ $stop['year'] }}</p>
                            <p class="cp-hum-year-text">{{ $stop['text'] }}@include('site.courses._cites', ['keys' => [$stop['key']]])</p>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- What the app listens for, beside a take plainly marked as a sample (never a singer's score). --}}
    <section class="s-wrap cp-hum-sec" aria-labelledby="hears-h">
        <div class="cp-hum-hears">
            <div>
                <h2 id="hears-h" class="s-kick">{{ __('pages.site.course_hears') }}</h2>
                <p class="cp-hum-big">{{ $hears[0] }}</p>
                @if ($hears[1] !== '')
                    <p class="cp-hum-p">{{ $hears[1] }}</p>
                @endif
                @if ($page['scoring'] ?? false)
                    <p class="cp-hum-p"><x-site.say :text="$page['scoring']" /></p>
                @endif
            </div>
            <figure class="cp-hum-take">
                <p class="cp-hum-take-head"><b>{{ $hum['take']['kick'] }}</b> <span>{{ $hum['take']['title'] }}</span></p>
                <div class="cp-hum-take-art">
                    <svg viewBox="0 0 520 200" aria-hidden="true" focusable="false">
                        <path d="{{ $take['target'] }}" class="cp-hum-take-target" />
                        <path d="{{ $take['sung'] }}" class="cp-hum-take-sung" />
                        <path d="{{ $take['on'] }}" class="cp-hum-take-on" />
                        <path d="{{ $take['off'] }}" class="cp-hum-take-off" />
                        <path d="{{ $take['gap'] }}" class="cp-hum-take-gap" />
                    </svg>
                    <span class="cp-hum-take-stop" style="left: {{ $take['stopX'] }}%; top: {{ $take['stopY'] }}%" aria-hidden="true">{{ $hum['take']['stop'] }}</span>
                </div>
                <ul class="cp-hum-take-key" aria-hidden="true">
                    @foreach ((array) $hum['take']['legend'] as $key)
                        <li><i></i>{{ $key }}</li>
                    @endforeach
                </ul>
                <p class="cp-hum-take-foot"><b>{{ __('site.courses.pages.humming-and-lip-trills.hum.take.result', ['hit' => $take['hit'], 'all' => $take['all']]) }}</b> <span>{{ $hum['take']['stops'] }}</span></p>
                <figcaption class="sr-only">{{ $hum['take']['alt'] }}</figcaption>
            </figure>
        </div>
    </section>

    {{-- Try it now: the first lesson as the app gives it (its learn cards and what it asks you to sing, in the course's own
         words), over its roller coaster, and the way into it. --}}
    @if ($v['try']['steps'] !== [])
        <section class="s-wrap cp-hum-sec" aria-labelledby="try-h">
            <div class="cp-hum-try s-v1">
                <p class="s-kick">{{ $hum['try']['kick'] }}</p>
                <h2 id="try-h" class="cp-hum-h2">{{ __('site.courses.pages.humming-and-lip-trills.hum.try.title', ['name' => $v['try']['name']]) }}</h2>
                <figure class="cp-hum-try-art">
                    <svg viewBox="0 0 560 140" aria-hidden="true" focusable="false">
                        <g class="cp-hum-is-trill">
                            <path d="{{ $v['try']['line'] }}" class="cp-hum-shape-line" />
                            <path d="{{ $v['try']['dots'] }}" class="cp-hum-shape-dots" />
                        </g>
                    </svg>
                    <figcaption class="sr-only">{{ $hum['try']['alt'] }}</figcaption>
                </figure>
                <ol class="cp-hum-try-steps">
                    @foreach ($v['try']['steps'] as $step)
                        <li><span class="cp-hum-n" aria-hidden="true">{{ $loop->iteration }}</span><p>@if ($step['title'] !== '')<b>{{ $step['title'] }}</b> @endif<span>{{ $step['text'] }}</span></p></li>
                    @endforeach
                </ol>
                <p class="cp-hum-btns">
                    @if ($open)
                        <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                    @else
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                    @endif
                </p>
            </div>
        </section>
    @endif

    {{-- Who it's for. --}}
    <section class="s-wrap cp-hum-sec2" aria-labelledby="who-h">
        <h2 id="who-h" class="s-kick">{{ $hum['who']['kick'] }}</h2>
        <ul class="cp-hum-who">
            @foreach ((array) $hum['who']['items'] as $line)
                <li class="s-v{{ $loop->iteration }}"><span class="cp-hum-who-mark" aria-hidden="true"></span><p>{{ $line }}</p></li>
            @endforeach
        </ul>
    </section>

    {{-- Singing safely: the two tips beside a scale from quiet to forced, with where to keep it. --}}
    @if ($page['safety'] ?? false)
        <section class="s-wrap cp-hum-sec2" aria-labelledby="safety-h">
            <div class="cp-hum-safe">
                <div>
                    <h2 id="safety-h" class="s-kick">{{ __('pages.site.course_safety') }}</h2>
                    <ul class="cp-hum-tips">
                        @foreach ((array) $page['safety'] as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>
                </div>
                <div class="cp-hum-scale" aria-hidden="true">
                    <p class="cp-hum-scale-bar"><i class="cp-hum-scale-ok"></i><i class="cp-hum-scale-no"></i></p>
                    <p class="cp-hum-scale-words"><span class="cp-hum-scale-ok">{{ $hum['scale']['ok'] }}</span><span>{{ $hum['scale']['no'] }}</span></p>
                </div>
            </div>
        </section>
    @endif

    {{-- New to harmony: the beginner course, beside Amazing Grace's four parts as the course sings them. --}}
    <section class="s-ink cp-hum-today" aria-labelledby="today-h">
        <div class="s-wrap cp-hum-today-in">
            <div>
                <p class="s-kick">{{ __('pages.site.course_today_kick') }}</p>
                <h2 id="today-h" class="cp-hum-h2 cp-hum-h2-l">{{ __('pages.site.course_today_title') }}</h2>
                <p class="cp-hum-p">{{ __('pages.site.course_today_body') }}</p>
                <p class="cp-hum-btns"><a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
            <figure class="cp-hum-satb">
                <ul aria-hidden="true">
                    @foreach ($grace as $line)
                        <li class="s-v{{ $loop->iteration }}"><span>{{ $hum['grace']['parts'][$loop->index] ?? '' }}</span><svg viewBox="0 0 430 56" preserveAspectRatio="none" focusable="false"><path d="{{ $line }}" vector-effect="non-scaling-stroke" /></svg></li>
                    @endforeach
                </ul>
                <figcaption>{{ $hum['grace']['caption'] }}</figcaption>
            </figure>
        </div>
    </section>

    {{-- Questions, with only the course's own answers, then the close: the first lesson again, beside the four sounds. --}}
    <section class="s-wrap cp-hum-sec" aria-labelledby="faq-h">
        <div class="cp-side">
            <h2 id="faq-h" class="cp-hum-h2">{{ $hum['questions'] }}</h2>
            @include('site.courses._faq')
        </div>
    </section>

    <section class="s-wrap cp-hum-sec" aria-labelledby="close-h">
        <div class="cp-hum-close">
            <p class="cp-hum-close-sounds" aria-hidden="true">
                @foreach ((array) $hum['ribbon']['sounds'] as [$sound])
                    <b class="s-v{{ $loop->iteration }}" translate="no">{{ $sound }}</b>
                @endforeach
            </p>
            <h2 id="close-h" class="cp-hum-h2 cp-hum-h2-l">{{ $hum['close']['title'] }}</h2>
            <p class="cp-hum-p">{{ $hum['close']['body'] }}</p>
            @if ($open)
                <p class="cp-hum-close-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <p class="cp-hum-btns">
                @if ($open)
                    <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                @else
                    <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                @endif
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </p>
        </div>
    </section>

    <section class="s-wrap cp-hum-sec" aria-labelledby="more-h">
        <h2 id="more-h" class="s-kick">{{ __('pages.site.course_more', ['group' => $group['name'] ?? '']) }}</h2>
        <ul class="s-chips">
            @foreach ($v['more'] as [$slug, $name])
                <li><a href="/courses/{{ $slug }}">{{ $name }}</a></li>
            @endforeach
        </ul>
    </section>

    {{-- Every lesson's own page (docs/seo-lesson-pages-plan.md), by unit, each headed in its unit's colour; this page's
         list in place of the shared one (course.blade.php's _lesson-pages). --}}
    @if ($reads !== [])
        <nav class="s-wrap cp-hum-sec2" aria-labelledby="reads-h">
            <h2 id="reads-h" class="s-kick">{{ __('site.lessons.every') }}</h2>
            <div class="cp-hum-reads">
                @foreach ($reads as $unit)
                    <div class="s-v{{ $unit['part'] }}">
                        <h3><span class="cp-hum-reads-glyph" translate="no" aria-hidden="true">{{ $hum['ribbon']['sounds'][$loop->index][0] ?? '' }}</span> {{ __('site.lessons.unitOf', ['number' => $unit['n'], 'unit' => $unit['unit']]) }}</h3>
                        <ul>
                            @foreach ($unit['pages'] as $read)
                                <li><a href="{{ $read['url'] }}"><b>{{ $read['name'] }}</b><span>{{ $read['title'] }}</span></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </nav>
    @endif

    @if ($sources !== [])
        <section class="s-wrap cp-hum-sources" aria-labelledby="sources-h">
            <h2 id="sources-h" class="s-kick">{{ __('pages.site.sources') }}</h2>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </section>
    @endif
    <script type="module" src="/js/public/humming-and-lip-trills.js"></script>
</main>
