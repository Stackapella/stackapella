{{-- Whistling (/courses/whistling), its own page after Hillel's design of 2026-11-18 (course-designs/whistling): the app's
     note bars, in magenta, the course's colour. The hero's card is Hot Cross Buns as lesson 4 lays it (your whistle note
     and the two just above it, from the course file), coloured by a labelled sample take with a line where it's got to;
     its button plays that sample take on a whistle-like tone (js/public/whistling.js, on voices.js and the audio clock,
     only when pressed). The units two by two, each with a drawing of one of its real exercises and its line from "what
     you'll learn"; lesson 1's first card and a tip from each unit; what the app listens for on a magenta band, scored
     beside what you check yourself, with a sample result on The Water Is Wide's real first verse; Did you know with its
     large words; inside a whistle; whistled speech; the songs in the order you meet them beside whistling safely; the
     questions; the beginner course, the group's other courses and the sources. Every fact is the course's own, sourced
     there (web/course/plus/whistling/course.json). No drawn people. Words: lang/en/site.php courses.pages.whistling
     (`love`); drawings and readings: App\Support\Pages\WhistlingPage. --}}
@php($love = (array) $page['love'])
@php($hero = \App\Support\Pages\WhistlingPage::hero())
@php($verse = \App\Support\Pages\WhistlingPage::verse())
@php($drawings = \App\Support\Pages\WhistlingPage::drawings())
@php($firstCard = \App\Support\Pages\WhistlingPage::card('wh-1-find'))
@php($tips = \App\Support\Pages\WhistlingPage::tips())
@php($finale = \App\Support\Pages\WhistlingPage::finale())
@php($lastCard = \App\Support\Pages\WhistlingPage::card('wh-4-challenge'))
@php($songs = \App\Support\Pages\WhistlingPage::songsByUnit((array) ($page['songs'] ?? []), $outline))
@php($faqs = \App\Support\Pages\WhistlingPage::faq((array) $love['faq'], $outline))
@php($lessonLinks = \App\Support\Pages\WhistlingPage::lessonLinks($page['slug']))
@php($sources = (array) $love['sources'])
@php($units = $outline['units'] ?? [])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@if ($lessonLinks !== [])
    @section('lesson-pages-inline', '1')
@endif
<main class="cp-wh" data-wh>
    <section class="s-wrap cp-wh-hero" aria-labelledby="page-h">
        <div class="cp-wh-hero-copy">
            <x-site.crumbs :trail="[[__('pages.site.courses'), '/courses'], [$page['name']]]" />
            <p class="cp-wh-pills">
                @if ($open)
                    <span class="s-soonpill"><span class="s-soon s-open">{{ __('pages.site.course_open') }}</span></span>
                @else
                    <span class="s-soonpill"><x-site.soon /></span>
                @endif
                <span class="cp-wh-free">{{ __('pages.site.course_chip_free') }}</span>
            </p>
            <h1 id="page-h">{{ $love['title_lead'] }} <span class="cp-wh-em">{{ $love['title_em'] }}</span></h1>
            <p class="s-lede">{{ $page['lead'] }}</p>
            @if (! $open && is_string($page['needs'] ?? null))
                <p class="cp-wh-quiet">{{ __('pages.site.course_needs', ['what' => $page['needs']]) }}</p>
            @endif
            <div class="cp-wh-actions">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </div>
        </div>

        @if ($hero['notes'] !== [])
            @php($h = $love['hero'])
            <figure class="cp-wh-card" data-wh-hero
                data-notes="{{ \App\Support\Pages\WhistlingPage::notesJson($hero) }}"
                data-beats="{{ $hero['beats'] }}" data-tempo="{{ $hero['tempo'] }}" data-base="{{ \App\Support\Pages\WhistlingPage::SAMPLE_BASE }}"
                data-tolerance="{{ \App\Support\Pages\WhistlingPage::TOLERANCE }}" data-so-far="{{ $h['so_far'] }}" data-done-words="{{ $h['done'] }}"
                data-play="{{ $h['play'] }}" data-stop="{{ $h['stop'] }}">
                <figcaption class="cp-wh-card-top">
                    <span class="cp-wh-mono">{{ \App\Support\Pages\WhistlingPage::heroAt((string) $h['at']) }}</span>
                    <b translate="no">{{ $units[\App\Support\Pages\WhistlingPage::HERO_UNIT - 1]['lessons'][\App\Support\Pages\WhistlingPage::HERO_LESSON - 1] ?? '' }}</b>
                </figcaption>
                <p class="cp-wh-sample"><span>{{ $h['sample'] }}</span></p>
                <div class="cp-wh-lane" role="img" aria-label="{{ $h['label'] }}">
                    <ul class="cp-wh-rows" aria-hidden="true">
                        @foreach ($hero['rows'] as $row)
                            <li>{{ $h['rows'][$loop->index] ?? '' }}</li>
                        @endforeach
                    </ul>
                    <div class="cp-wh-track" style="--rows: {{ count($hero['rows']) }}" aria-hidden="true">
                        @foreach ($hero['notes'] as $n)
                            <span class="cp-wh-n" data-wh-i="{{ $loop->index }}" data-state="{{ $n['state'] }}" style="--l: {{ $n['left'] }}%; --w: {{ $n['width'] }}%; --r: {{ $n['row'] }}"></span>
                        @endforeach
                        <i class="cp-wh-at" data-wh-head style="--at: {{ $hero['now'] }}%"></i>
                    </div>
                </div>
                <div class="cp-wh-card-foot">
                    <ul class="cp-wh-legend">
                        @foreach ((array) $h['legend'] as $state => $name)
                            <li><i class="cp-wh-key" data-state="{{ $state }}" aria-hidden="true"></i>{{ $name }}</li>
                        @endforeach
                    </ul>
                    <p class="cp-wh-count" data-wh-count>{{ \App\Support\Pages\WhistlingPage::fill((string) $h['so_far'], $hero['so_far'], $hero['done']) }}</p>
                </div>
                <p class="cp-wh-playrow">
                    <button type="button" class="cp-wh-play" data-wh-play aria-pressed="false"><span class="cp-wh-play-icon" aria-hidden="true"></span><span data-label>{{ $h['play'] }}</span></button>
                </p>
                <p class="cp-wh-note">{{ $h['note'] }}</p>
            </figure>
        @endif
    </section>

    @if ($units !== [])
        <section class="s-wrap cp-wh-sec" aria-labelledby="units-h">
            <div class="cp-wh-head">
                <div>
                    <p class="cp-wh-kick">{{ __('pages.site.course_units') }}</p>
                    <h2 id="units-h" class="cp-wh-title">{{ $love['units_title'] }}</h2>
                </div>
                <p class="cp-wh-count-all cp-wh-mono">{{ __('pages.site.course_size', ['units' => count($units), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-wh-units">
                @foreach ($units as $unit)
                    @php($d = $drawings[$loop->index] ?? null)
                    @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                    @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                    <li class="cp-wh-unit">
                        @if ($d !== null)
                            <svg class="cp-wh-draw" viewBox="0 0 300 70" role="img" aria-label="{{ $love['drawings'][$loop->index] ?? '' }}">
                                @foreach ($d['bars'] as $b)
                                    <rect x="{{ $b['x'] }}" y="{{ $b['y'] - 3 }}" width="{{ $b['w'] }}" height="6" rx="3" @class(['cp-wh-bar', 'cp-wh-bar-mark' => $b['mark']]) />
                                @endforeach
                                @if ($d['path'] !== null)
                                    <path d="{{ $d['path'] }}" class="cp-wh-slide" />
                                @endif
                                @if ($d['breath'] !== null)
                                    <text x="{{ $d['breath'] }}" y="14" class="cp-wh-breath">,</text>
                                @endif
                            </svg>
                        @endif
                        <p class="cp-wh-unit-n cp-wh-mono"><x-site.skin :game="$stage" :serious="$unitName" /></p>
                        <h3>{{ $unit['title'] }}</h3>
                        @isset($page['learn'][$loop->index])
                            <p class="cp-wh-unit-line">{{ \App\Support\Pages\WhistlingPage::unitLine($page['learn'][$loop->index]) }}</p>
                        @endisset
                        <ol class="cp-wh-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                @php($url = $lessonLinks[$loop->parent->iteration][$loop->index] ?? null)
                                <li>
                                    <span class="cp-wh-mono cp-wh-ln" aria-hidden="true">{{ \App\Support\Pages\WhistlingPage::two($loop->iteration) }}</span>
                                    @if ($url !== null)
                                        <a href="{{ $url }}">{{ $lesson }}</a>
                                    @else
                                        <span>{{ $lesson }}</span>
                                    @endif
                                    @if ($loop->parent->first && $loop->first)
                                        <span class="cp-wh-start">{{ __('pages.site.course_free') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    @if ($firstCard['headline'] !== '')
        <section class="s-wrap cp-wh-sec cp-wh-first" aria-labelledby="first-h">
            <div>
                <p class="cp-wh-kick">{{ $love['first']['kick'] }}</p>
                <h2 id="first-h" class="cp-wh-title">{{ $love['first']['title'] }}</h2>
                <p class="cp-wh-body">{{ \App\Support\Pages\WhistlingPage::stepText('wh-1-find', 1) }}</p>
            </div>
            <div class="cp-wh-first-card">
                <p class="cp-wh-mono cp-wh-label">{{ $love['first']['lesson'] }}</p>
                <p class="cp-wh-first-lead">{{ $firstCard['headline'] }}</p>
                <ul class="cp-wh-points">
                    @foreach ($firstCard['points'] as [$title, $detail])
                        <li><b>{{ $title }}</b><span>{{ $detail }}</span></li>
                    @endforeach
                </ul>
            </div>
            @if ($tips !== [])
                <div class="cp-wh-tips">
                    <h3 class="cp-wh-mono cp-wh-label">{{ $love['first']['tips_title'] }}</h3>
                    <ol>
                        @foreach ($tips as [$title, $detail])
                            <li><span class="cp-wh-mono cp-wh-tip-n">{{ __('pages.site.course_unit', ['n' => $loop->iteration]) }}</span><b>{{ $title }}</b><span>{{ $detail }}</span></li>
                        @endforeach
                    </ol>
                </div>
            @endif
        </section>
    @endif

    @if ($finale['tune'] !== [] && $lastCard['headline'] !== '')
        <section class="s-wrap cp-wh-sec cp-wh-finale" aria-labelledby="finale-h">
            <div>
                <p class="cp-wh-kick">{{ $love['finale']['kick'] }}</p>
                <h2 id="finale-h" class="cp-wh-title">{{ $love['finale']['title'] }}</h2>
                <p class="cp-wh-body">{{ $lastCard['headline'] }}</p>
                <ul class="cp-wh-points cp-wh-points-3">
                    @foreach ($lastCard['points'] as [$title, $detail])
                        <li><b>{{ $title }}</b><span>{{ $detail }}</span></li>
                    @endforeach
                </ul>
            </div>
            <figure class="cp-wh-two" role="img" aria-label="{{ $love['finale']['label'] }}">
                @foreach (['tune', 'under'] as $track)
                    <div class="cp-wh-two-row" aria-hidden="true">
                        <p class="cp-wh-mono cp-wh-label">{{ $love['finale']['rows'][$loop->index] }}</p>
                        <div class="cp-wh-two-track cp-wh-two-{{ $track }}">
                            @foreach ($finale[$track] as $n)
                                <span style="--l: {{ $n['left'] }}%; --w: {{ $n['width'] }}%; --t: {{ $n['top'] }}%"></span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </figure>
        </section>
    @endif

    <section class="s-wrap cp-wh-sec" aria-labelledby="hears-h">
        <div class="cp-wh-hears">
            <div>
                <p class="cp-wh-kick">{{ __('pages.site.course_hears') }}</p>
                <h2 id="hears-h" class="cp-wh-title">{{ $love['hears']['title'] }}</h2>
                <p class="cp-wh-body">{{ $love['hears']['lead'] }}</p>
                <dl class="cp-wh-split">
                    <div><dt>{{ $love['hears']['scored'] }}</dt><dd>{{ $love['hears']['scored_text'] }}</dd></div>
                    <div><dt>{{ $love['hears']['self'] }}</dt><dd>{{ $love['hears']['self_text'] }}</dd></div>
                </dl>
            </div>
            @if ($verse['notes'] !== [])
                <figure class="cp-wh-result">
                    <figcaption class="cp-wh-result-top"><span class="cp-wh-sample-tag">{{ $love['hears']['sample'] }}</span><span class="cp-wh-mono">{{ $love['hears']['take'] }}</span></figcaption>
                    <div class="cp-wh-verse" role="img" aria-label="{{ $love['hears']['label'] }}">
                        @foreach ($verse['notes'] as $n)
                            <span data-state="{{ $n['state'] }}" style="--l: {{ $n['left'] }}%; --w: {{ $n['width'] }}%; --t: {{ $n['top'] }}%"></span>
                        @endforeach
                    </div>
                    <ul class="cp-wh-legend cp-wh-legend-light" aria-hidden="true">
                        @foreach (['on', 'flat'] as $state)
                            <li><i class="cp-wh-key" data-state="{{ $state }}"></i>{{ $love['hero']['legend'][$state] }}</li>
                        @endforeach
                    </ul>
                    <p class="cp-wh-result-big">{{ \App\Support\Pages\WhistlingPage::fill((string) $love['hero']['done'], $verse['on'], $verse['total']) }}</p>
                    <p class="cp-wh-small"><x-site.say :text="$love['hears']['scoring']" /></p>
                </figure>
            @endif
        </div>
    </section>

    @if (is_array($page['history'] ?? null))
        <section class="s-wrap cp-wh-sec" aria-labelledby="facts-h">
            <h2 id="facts-h" class="cp-wh-kick">{{ $page['history']['title'] }}</h2>
            <ol class="cp-wh-facts">
                @foreach ((array) $page['history']['body'] as $fact)
                    <li>
                        <p class="cp-wh-big">{{ $love['facts_big'][$loop->index] ?? '' }}</p>
                        <p>{{ $fact }}@include('site.courses._cites', ['keys' => $love['facts_cites'][$loop->index] ?? []])</p>
                    </li>
                @endforeach
            </ol>
            <ol class="cp-wh-facts cp-wh-facts-more">
                @foreach ((array) $love['more_facts'] as [$big, $fact, $cites])
                    <li>
                        <p class="cp-wh-big">{{ $big }}</p>
                        <p>{{ $fact }}@include('site.courses._cites', ['keys' => $cites])</p>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="s-wrap cp-wh-sec cp-wh-science" aria-labelledby="science-h">
        <div>
            <p class="cp-wh-kick">{{ $love['science']['kick'] }}</p>
            <h2 id="science-h" class="cp-wh-title">{{ $love['science']['title'] }}</h2>
            <p class="cp-wh-body">{{ $love['science']['lead'] }}</p>
        </div>
        <ol class="cp-wh-cards">
            @foreach ((array) $love['science']['items'] as [$big, $text, $cites])
                <li>
                    <h3>{{ $big }}</h3>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap cp-wh-sec" aria-labelledby="speech-h">
        <p class="cp-wh-kick">{{ $love['speech']['kick'] }}</p>
        <h2 id="speech-h" class="cp-wh-title">{{ $love['speech']['title'] }}</h2>
        <ol class="cp-wh-speech">
            @foreach ((array) $love['speech']['items'] as [$big, $text, $cites])
                <li>
                    <p class="cp-wh-big">{{ $big }}</p>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap cp-wh-sec cp-wh-notes">
        @if ($songs !== [])
            <div>
                <h2 id="songs-h" class="cp-wh-kick">{{ $love['songs_title'] }}</h2>
                <ol class="cp-wh-songs">
                    @foreach ($songs as $song)
                        <li>
                            <span class="cp-wh-mono cp-wh-song-u">{{ $song['unit'] !== null ? __('pages.site.course_unit', ['n' => $song['unit']]) : '' }}</span>
                            <div><b translate="no">{{ $song['title'] }}</b><span>{{ $song['credit'] }}</span></div>
                        </li>
                    @endforeach
                </ol>
            </div>
        @endif
        @if (($page['safety'] ?? []) !== [])
            <div>
                <h2 id="safety-h" class="cp-wh-kick">{{ $love['safety_title'] }}</h2>
                <ul class="cp-wh-safety">
                    @foreach ((array) $page['safety'] as $note)
                        <li><x-site.icon name="info" size="18" /><span>{{ $note }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <section class="s-wrap cp-wh-sec cp-wh-faq" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-wh-title">{{ $love['faq_title'] }}</h2>
        @include('site.courses._faq', ['page' => ['faq' => $faqs]])
    </section>

    <div class="cp-wh-today">
        @include('site._course-today')
    </div>

    <section class="s-wrap s-sec" aria-labelledby="more-h">
        <h2 id="more-h" class="s-kick">{{ __('pages.site.course_more', ['group' => $group['name'] ?? '']) }}</h2>
        <ul class="s-chips">
            @foreach ((array) ($group['courses'] ?? []) as $slug)
                @continue($slug === $page['slug'])
                <li><a href="/courses/{{ $slug }}">{{ __('site.courses.pages.'.$slug.'.name') }}</a></li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap cp-wh-sources" aria-labelledby="notes-h">
        <details data-wh-sources>
            <summary><h2 id="notes-h" class="cp-wh-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/whistling.js"></script>
</main>
