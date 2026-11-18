{{-- Ear training (/courses/ear-training), its own page after Hillel's design (2026-11-18,
     ~/Code/course-designs/ear-training): the page climbs a scale. Each section is a step, 1 do to 8 do, ending on
     "Home again" (the course's last unit is Find home); the steps are decoration, and every section keeps its own h2.
     The hero is the leap ladder: the scale's eight notes with each of lesson 1's three song leaps drawn where it really
     sits (EarTrainingLeaps: Saints do to mi, Twinkle do to so, Amazing Grace so to do), its song beside the note it
     lands on; tapping one plays it. Then Name that leap (the shared game, site/courses/_ear-leap and
     js/public/ear-training.js, as on Music teachers and schools), what you leave with, the three openings as cards with
     each word at the height of its note, bright or dark (the third dropping, both chords heard), the course as four
     units rising like stairs with every lesson linked to its page and its first line, inside lesson 1, what the app
     listens for beside a board marked as a sample, who it's for, the facts with their numbered sources, questions, and
     the close. Every sound is made by js/public/ear-training-page.js on voices.js, only when pressed, on the audio
     clock. Calm light and dark from the theme's tokens; game mode restyles the same pieces. Words:
     lang/en/site.php courses.pages.ear-training (`et` for the page's own). --}}
@php($et = (array) $page['et'])
@php($sol = (array) $et['sol'])
@php($songs = \App\Support\Site\EarTrainingLeaps::songs())
@php($ladder = \App\Support\Site\EarTrainingLeaps::ladder())
@php($units = $outline !== null ? \App\Support\Site\EarTrainingLeaps::lessons($outline) : [])
{{-- The facts' sources, numbered in the order the facts give them. --}}
@php($sources = \App\Support\Site\EarTrainingLeaps::sources((array) $page['facts']))
<main class="cp cp-ear">
    <section class="s-wrap cp-et-hero" aria-labelledby="page-h">
        <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
        <div class="cp-et-top">
            <div class="cp-et-copy">
                <p class="cp-et-eyebrow">
                    @if ($open)
                        <span class="cp-et-open">{{ $et['open'] }}</span>
                    @else
                        <span class="s-soonpill"><x-site.soon /></span>
                    @endif
                    @if ($outline !== null)
                        <span class="cp-et-size">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</span>
                    @endif
                </p>
                <h1 id="page-h">{{ \App\Support\Site\EarTrainingLeaps::marked((string) $et['headline']) }}</h1>
                <p class="cp-et-lead">{{ $page['hero_lead'] }}</p>
                <ul class="cp-et-facts">
                    @foreach ((array) $et['facts'] as $fact)
                        <li>{{ $fact }}</li>
                    @endforeach
                </ul>
                <p class="cp-et-btns">
                    @if ($open)
                        <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                    @else
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                    @endif
                    <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                </p>
            </div>

            {{-- The leap ladder: rungs do to do, each song's leap an arc to the note it lands on, its song a button there. --}}
            <figure class="cp-et-ladder" data-et-ladder>
                <div class="cp-et-ladder-head">
                    <p class="cp-et-kick">{{ $et['ladder']['kick'] }}</p>
                    <p class="cp-et-hint" aria-hidden="true">{{ $et['ladder']['hint'] }}</p>
                </div>
                <div class="cp-et-ladder-in">
                    <div class="cp-et-ladder-art">
                    <svg viewBox="0 0 520 420" aria-hidden="true" focusable="false">
                        @foreach ($ladder['rungs'] as $rung)
                            <line x1="70" x2="505" y1="{{ $rung['y'] }}" y2="{{ $rung['y'] }}" class="cp-et-rung" />
                        @endforeach
                        @foreach ($ladder['leaps'] as $leap)
                            <g class="cp-et-leap cp-et-{{ $leap['key'] }}" data-et-arc="{{ $leap['key'] }}">
                                <path d="{{ $leap['path'] }}" class="cp-et-arc" />
                                <path d="{{ $leap['path'] }}" class="cp-et-arc-hit" />
                                @if ($leap['from'] !== 1)
                                    <circle cx="{{ $leap['x0'] }}" cy="{{ $leap['y0'] }}" r="9" class="cp-et-dot-from" />
                                @endif
                                <circle cx="{{ $leap['x1'] }}" cy="{{ $leap['y1'] }}" r="11" class="cp-et-dot" />
                            </g>
                        @endforeach
                        <circle cx="110" cy="{{ $ladder['rungs'][0]['y'] }}" r="12" class="cp-et-home" />
                    </svg>
                    @foreach ($ladder['rungs'] as $rung)
                        <span class="cp-et-rung-n" style="top: {{ $rung['top'] }}%" aria-hidden="true"><b>{{ $rung['n'] }}</b> <em>{{ $sol[$loop->index] ?? '' }}</em></span>
                    @endforeach
                    <span class="cp-et-home-n" style="top: {{ $ladder['rungs'][0]['top'] }}%" aria-hidden="true">{{ $et['ladder']['home'] }}</span>
                    </div>
                    @foreach ($ladder['leaps'] as $leap)
                        <button type="button" class="cp-et-tag cp-et-{{ $leap['key'] }}" style="left: {{ $leap['left'] }}%; top: {{ $leap['top'] }}%" data-et-leap="{{ $leap['key'] }}" data-from="{{ $leap['fromMidi'] }}" data-to="{{ $leap['toMidi'] }}" aria-pressed="false">
                            <b translate="no">{{ $leap['title'] }}</b><i>{{ $et['leap'][$leap['key']] }}</i>
                        </button>
                    @endforeach
                </div>
                <figcaption class="sr-only">{{ $et['ladder']['alt'] }}</figcaption>
            </figure>
        </div>
    </section>

    {{-- 1 do: Name that leap, the shared game. --}}
    <section class="s-wrap cp-et-sec cp-et-game" aria-labelledby="demo-h">
        @include('site.courses._ear-training-step', ['n' => 1])
        @include('site.courses._ear-leap')
    </section>

    {{-- 2 re: what you leave with. --}}
    <section class="s-wrap cp-et-sec" aria-labelledby="outcomes-h">
        @include('site.courses._ear-training-step', ['n' => 2])
        <h2 id="outcomes-h" class="cp-et-h2">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-et-outcomes">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-et-glyph" aria-hidden="true">{{ $et['glyphs'][$loop->index] ?? '' }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    {{-- 3 mi: the openings, each word at the height of its note, the leap marked. --}}
    <section class="s-wrap cp-et-sec" aria-labelledby="leaps-h">
        @include('site.courses._ear-training-step', ['n' => 3])
        <div class="cp-et-split">
            <h2 id="leaps-h" class="cp-et-h2">{{ $page['titles']['leaps'] }}</h2>
            <p class="cp-et-p">{{ $page['leaps']['lead'] }}</p>
        </div>
        <ul class="cp-et-cards">
            @foreach ($songs as $song)
                @php($card = (array) ($et['cards'][$song['key']] ?? ['', '', '']))
                <li class="cp-et-card cp-et-{{ $song['key'] }}" data-et-card data-tempo="{{ $song['tempo'] }}">
                    <div class="cp-et-card-head">
                        <p class="cp-et-card-song" translate="no">{{ $song['title'] }}</p>
                        <button type="button" class="cp-et-round" data-et-play aria-pressed="false" aria-label="{{ $et['card_play'] }}: {{ $song['title'] }}"><span class="cp-et-round-icon" aria-hidden="true"></span></button>
                    </div>
                    <figure class="cp-et-sheet">
                        <div class="cp-et-sheet-in" aria-hidden="true">
                            @foreach (\App\Support\Site\EarTrainingLeaps::lines() as $top)
                                <span class="cp-et-line" style="top: {{ $top }}%"></span>
                            @endforeach
                            <svg viewBox="0 0 100 100" preserveAspectRatio="none" class="cp-et-sheet-arc"><path d="{{ $song['arc'] }}" vector-effect="non-scaling-stroke" /></svg>
                            @foreach ($song['notes'] as $note)
                                <span @class(['cp-et-word', 'cp-et-word-leap' => $note['leap'], 'cp-et-slur' => $note['word'] === '']) style="left: {{ $note['x'] }}%; top: {{ $note['y'] }}%" data-at="{{ $note['at'] }}" data-len="{{ $note['len'] }}" data-midi="{{ $note['midi'] }}" translate="no">{{ $note['word'] }}</span>
                            @endforeach
                        </div>
                        <figcaption class="sr-only">{{ __($et['card_alt'], ['song' => $song['title'], 'leap' => $et['leap'][$song['key']]]) }}</figcaption>
                    </figure>
                    <h3>{{ $card[0] }}</h3>
                    <p class="cp-et-card-note">{{ $card[1] }} <i>{{ $card[2] }}</i></p>
                </li>
            @endforeach
        </ul>
        <p class="cp-et-fine">{{ $page['leaps']['body'] }}</p>
    </section>

    {{-- 4 fa: bright or dark, split down the middle; the third drops and both chords play. --}}
    <section class="cp-et-colour" aria-labelledby="colour-h">
        <div class="cp-et-bright">
            <div class="cp-et-half">
                @include('site.courses._ear-training-step', ['n' => 4])
                <h2 id="colour-h" class="cp-et-colour-h"><span>{{ $et['colour']['bright'] }}</span> <span class="sr-only">{{ $et['colour']['dark'] }}</span></h2>
                <p class="cp-et-p">{{ $page['colour']['lead'] }}</p>
                <figure class="cp-et-chord" data-et-chord="major">
                    <div class="cp-et-stack" aria-hidden="true"><i class="cp-et-c-so"></i><i class="cp-et-c-third"></i><i class="cp-et-c-do"></i></div>
                    <figcaption><b>{{ $page['colour']['major'][0] }}</b> <span>{{ $page['colour']['major'][1] }}</span><span class="sr-only">. {{ $et['colour']['alt_major'] }}</span></figcaption>
                    <button type="button" class="cp-et-hear" data-et-hear="major" data-play="{{ $et['colour']['hear'] }}" data-stop="{{ $et['colour']['stop'] }}" aria-pressed="false" aria-label="{{ $et['colour']['hear_major'] }}"><span class="cp-et-hear-icon" aria-hidden="true"></span><span data-label>{{ $et['colour']['hear'] }}</span></button>
                </figure>
            </div>
        </div>
        <div class="cp-et-dark">
            <div class="cp-et-half">
                <p class="cp-et-dark-big" aria-hidden="true">{{ $et['colour']['dark'] }}</p>
                <p class="cp-et-p">{{ $et['colour']['dark_lead'] }}</p>
                <figure class="cp-et-chord" data-et-chord="minor">
                    <div class="cp-et-stack" aria-hidden="true"><i class="cp-et-c-so"></i><i class="cp-et-c-ghost"></i><i class="cp-et-c-third"></i><i class="cp-et-c-do"></i></div>
                    <figcaption><b>{{ $page['colour']['minor'][0] }}</b> <span>{{ $page['colour']['minor'][1] }}</span><span class="sr-only">. {{ $et['colour']['alt_minor'] }}</span></figcaption>
                    <button type="button" class="cp-et-hear" data-et-hear="minor" data-play="{{ $et['colour']['hear'] }}" data-stop="{{ $et['colour']['stop'] }}" aria-pressed="false" aria-label="{{ $et['colour']['hear_minor'] }}"><span class="cp-et-hear-icon" aria-hidden="true"></span><span data-label>{{ $et['colour']['hear'] }}</span></button>
                </figure>
            </div>
        </div>
    </section>

    {{-- 5 so: the course, four units rising like stairs, every lesson with its first line and its page. --}}
    @if ($outline !== null)
        <section class="s-wrap cp-et-sec" aria-labelledby="units-h">
            @include('site.courses._ear-training-step', ['n' => 5])
            <div class="cp-et-split cp-et-split-end">
                <h2 id="units-h" class="cp-et-h2">{{ $page['titles']['units'] }}</h2>
                <p class="cp-et-p">{{ __($et['units_note'], ['lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-et-stairs">
                @foreach ($units as $unit)
                    @php($deg = (array) ($et['unit_steps'][$loop->index] ?? [(string) $loop->iteration, '']))
                    <li class="cp-et-unit">
                        <p class="cp-et-unit-top"><span class="cp-et-unit-deg" aria-hidden="true">{{ $deg[0] }} <em>{{ $deg[1] }}</em></span><span class="cp-et-unit-n">{{ __('pages.site.course_unit', ['n' => $loop->iteration]) }}</span></p>
                        <div class="cp-et-unit-card">
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-et-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            <ol class="cp-et-lessons">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li>
                                        <span class="cp-et-num" aria-hidden="true">{{ $lesson['n'] }}</span>
                                        <span class="cp-et-lesson">
                                            @if ($lesson['url'] !== null)
                                                <a href="{{ $lesson['url'] }}">{{ $lesson['name'] }}</a>
                                            @else
                                                <b>{{ $lesson['name'] }}</b>
                                            @endif
                                            @if ($loop->parent->first && $loop->first)
                                                <span class="s-free">{{ __('pages.site.course_free') }}</span>
                                            @endif
                                            @if ($lesson['line'] !== '')
                                                <span class="cp-et-line-text">{{ $lesson['line'] }}</span>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- Inside lesson 1: what it asks, step by step. --}}
    <section class="s-wrap cp-et-sec cp-et-inside" aria-labelledby="try-h">
        <div>
            <p class="cp-et-kick">{{ $et['inside_kick'] }}</p>
            <h2 id="try-h" class="cp-et-h2 cp-et-h2-s">{{ $page['titles']['try'] }}</h2>
            <p class="cp-et-p">{{ $page['try']['after'] }}</p>
        </div>
        <ol class="cp-et-try">
            @foreach ((array) $page['try']['steps'] as $line)
                <li><span class="cp-et-try-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $line }}</p></li>
            @endforeach
        </ol>
    </section>

    {{-- 6 la: what the app listens for, beside a board marked as a sample (the course's own example, lang `scoring`). --}}
    <section class="cp-et-mic" aria-labelledby="inside-h">
        <div class="s-wrap cp-et-mic-in">
            <div>
                @include('site.courses._ear-training-step', ['n' => 6, 'ink' => true])
                <h2 id="inside-h" class="cp-et-h2">{{ $page['titles']['inside'] }}</h2>
                <p class="cp-et-p">{{ $page['measures'] }}</p>
                <p class="cp-et-fine"><x-site.say :text="$page['scoring']" /></p>
            </div>
            <figure class="cp-et-board">
                <p class="cp-et-sample"><b>{{ $et['board']['sample'] }}</b> <span>{{ $et['board']['sample_note'] }}</span></p>
                <div class="cp-et-board-head" aria-hidden="true"><span>{{ $et['board']['asked'] }}</span><span>{{ $et['board']['darts'] }}</span></div>
                <div class="cp-et-board-in" aria-hidden="true">
                    @foreach ([[6, 10], [5, 50], [4, 90]] as [$n, $top])
                        <span class="cp-et-board-rung" style="top: {{ $top }}%"><b>{{ $n }} <em>{{ $sol[$n - 1] }}</em></b></span>
                    @endforeach
                    <span class="cp-et-band"></span>
                    @foreach ([[16, 50], [30, 58], [44, 43], [58, 61], [72, 52], [86, 17]] as [$left, $top])
                        <i @class(['cp-et-dart', 'cp-et-dart-off' => $loop->last]) style="left: {{ $left }}%; top: {{ $top }}%"></i>
                    @endforeach
                </div>
                <figcaption class="sr-only">{{ $et['board']['alt'] }}</figcaption>
                <p class="cp-et-found">{{ __($et['board']['found'], ['n' => 5, 'total' => 6]) }}</p>
            </figure>
        </div>
    </section>

    {{-- 7 ti: who it's for, what you need, singing safely, the songs. --}}
    <section class="s-wrap cp-et-sec" aria-labelledby="kit-h">
        @include('site.courses._ear-training-step', ['n' => 7])
        <h2 id="kit-h" class="cp-et-h2">{{ $page['titles']['kit'] }}</h2>
        <div class="cp-et-kit">
            <div>
                <h3 class="cp-et-kick">{{ $page['titles']['who'] }}</h3>
                <ul class="cp-et-rules">@foreach ((array) $page['who'] as $line)<li>{{ $line }}</li>@endforeach</ul>
            </div>
            <div>
                <h3 class="cp-et-kick">{{ $page['titles']['need'] }}</h3>
                <ul class="cp-et-rules">@foreach ((array) $page['need'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                <h3 class="cp-et-kick cp-et-gap">{{ $page['titles']['safety'] }}</h3>
                <ul class="cp-et-rules">@foreach ((array) $page['safety'] as $line)<li>{{ $line }}</li>@endforeach</ul>
            </div>
            <div class="cp-et-songs">
                <h3 class="cp-et-kick">{{ __('pages.site.course_songs') }}</h3>
                <ul>
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- The facts, each with its numbered source. --}}
    <section class="s-wrap cp-et-sec" aria-labelledby="facts-h">
        <h2 id="facts-h" class="cp-et-kick cp-et-facts-h">{{ $page['titles']['facts'] }}</h2>
        <ul class="cp-et-notes">
            @foreach ((array) $page['facts'] as [$text])
                <li><p>{{ $text }}@include('site.courses._cites', ['keys' => ['f'.$loop->index]])</p></li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap cp-et-sec cp-et-faq" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-et-h2">{{ $page['titles']['faq'] }}</h2>
        @include('site.courses._faq')
    </section>

    {{-- 8 do: home again. --}}
    <section class="s-wrap cp-et-sec cp-et-close" aria-labelledby="join-h">
        <div class="cp-et-climb" aria-hidden="true">@for ($i = 0; $i < 8; $i++)<i></i>@endfor</div>
        @include('site.courses._ear-training-step', ['n' => 8])
        <h2 id="join-h" class="cp-et-join">{{ \App\Support\Site\EarTrainingLeaps::marked((string) $et['join']) }}</h2>
        <p class="cp-et-p">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
        @if ($open)
            <p class="cp-et-note">{{ __('pages.site.course_join_note') }}</p>
        @endif
        <p class="cp-et-btns">
            @if ($open)
                <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
            @else
                <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
            @endif
            <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
        </p>
        <h3 class="cp-et-kick cp-et-related-h">{{ $page['titles']['related'] }}</h3>
        @include('site.courses._related')
    </section>

    <section class="s-wrap cp-et-sources" aria-labelledby="notes-h">
        <h2 id="notes-h" class="cp-et-kick">{{ __('pages.site.sources') }}</h2>
        <ol>
            @foreach ($sources as [$label, $url])
                <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
            @endforeach
        </ol>
    </section>
    <script type="module" src="/js/public/ear-training-page.js"></script>
</main>
