{{-- Backing vocals (/courses/backing-vocals), its own page after Hillel's design (2026-11-18,
     ~/Code/course-designs/backing-vocals): the backing parts drawn as stacked coloured lanes, like a multitrack.
     The hero's "behind" has a copy in each backing part's colour behind it; under it, Shenandoah's first two lines as
     a panel of lanes (the tune from its song template, the pad from the course's own lesson, a doo-wah figure and an
     answer drawn on the template's chords) with a playhead, which js/public/backing-vocals.js plays on voices.js when
     pressed. Then the units, each in its part's colour with a strip of its technique; what the app listens for beside
     a result card marked as a sample; the setlist with singing safely; the beginner course beside Amazing Grace's four
     parts from its template; the group's other courses. Every lesson's own page follows (course.blade.php's
     _lesson-pages, laid out by unit here in CSS). Part colours are the site's s-v1 to s-v4, so calm light and dark and
     game all draw them. The length pass (Hillel, 2026-11-18: every course page about the same length) adds, in the
     same lanes: lesson 1's pad to try without the app; why it works, lesson 1's pad under Amazing Grace against its chords
     (each chord named by its notes, the pad's note marked) and three points each with its strip; the archive as a
     session of four tracks, a clip a dated fact; who it's for and what you need beside the setlist; the questions; a
     close where the lanes are yours; and the numbered Sources, open on a wide screen. Words: lang/en/site.php
     courses.pages.backing-vocals (`bv`, and `faq`). --}}
@php($bv = (array) $page['bv'])
{{-- Shenandoah's first two lines as four lanes, and Amazing Grace's first line as four parts, from real notes
     (App\Support\Site\BackingVocalsLanes). --}}
@php($hero = \App\Support\Site\BackingVocalsLanes::hero())
@php($grace = \App\Support\Site\BackingVocalsLanes::grace())
{{-- Lesson 1's pad under Amazing Grace, with its chords (the science's panel and strips). --}}
@php($first = \App\Support\Site\BackingVocalsLanes::firstPad())
@php($sources = (array) $bv['sources'])
@php($counts = [':lessons' => (string) ($outline['lessons'] ?? ''), ':units' => (string) count($outline['units'] ?? [])])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($kinds = ['lead' => null, 'pad' => 1, 'doo' => 2, 'answer' => 3])
{{-- Each unit's strip: which lanes it shows, and the ghost of the tune where the lead isn't the point. --}}
@php($strips = [[['lead', true], ['pad', false]], [['lead', true], ['doo', false]], [['lead', false], ['answer', false]], [['pad', false], ['answer', false], ['doo', false]]])
{{-- A lesson with its own page links to it, by its name. --}}
@php($pages = collect(\App\Support\Pages\Lessons::pages($page['slug']))->filter(static fn (array $p): bool => ($p['kind'] ?? '') === 'lesson')->pluck('url', 'name')->all())
<main class="cp cp-bv">
    <section class="s-wrap cp-bv-hero" aria-labelledby="page-h">
        <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
        <div class="cp-bv-top">
            <div>
                @if ($open)
                    <p class="cp-bv-open"><span class="cp-bv-open-dot" aria-hidden="true"></span>{{ $bv['open'] }}</p>
                @else
                    <p class="s-soonpill"><x-site.soon /></p>
                @endif
                <h1 id="page-h"><span class="cp-bv-line">{{ $bv['headline'][0] }}</span> <span class="cp-bv-line"><span class="cp-bv-behind">{{ $bv['headline'][1] }}</span></span> <span class="cp-bv-line">{{ $bv['headline'][2] }}</span></h1>
            </div>
            <div>
                <p class="cp-bv-lead">{{ $page['lead'] }}</p>
                <p class="cp-bv-btns">
                    @if ($open)
                        <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                    @else
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                    @endif
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                </p>
            </div>
        </div>

        <div class="cp-bv-panel" data-bv data-tempo="{{ $hero['tempo'] }}" data-span="{{ $hero['span'] }}">
            <div class="cp-bv-panel-head">
                <p class="s-kick cp-bv-quiet">{{ $bv['panel']['kick'] }}</p>
                <p class="cp-bv-panel-note">{{ $bv['panel']['note'] }}</p>
                <button type="button" class="cp-bv-play" data-bv-play data-play="{{ $bv['panel']['play'] }}" data-stop="{{ $bv['panel']['stop'] }}" aria-pressed="false"><span class="cp-bv-play-icon" aria-hidden="true"></span><span data-label>{{ $bv['panel']['play'] }}</span></button>
            </div>
            <figure class="cp-bv-fig">
                <div class="cp-bv-tracks" aria-hidden="true">
                    <span class="cp-bv-grid" style="grid-row: 1 / {{ count($hero['lanes']) + 1 }}"></span>
                    @foreach ($hero['lanes'] as $kind => $notes)
                        <span class="cp-bv-name {{ $kinds[$kind] ? 's-v'.$kinds[$kind] : 'cp-bv-is-lead' }}" style="grid-row: {{ $loop->iteration }}">{{ $bv['panel']['lanes'][$kind] }}</span>
                        <span class="cp-bv-lane {{ $kinds[$kind] ? 's-v'.$kinds[$kind] : 'cp-bv-is-lead' }}" style="grid-row: {{ $loop->iteration }}" data-bv-lane="{{ $kind }}">
                            @foreach ($notes as $note)
                                <span class="cp-bv-seg" data-at="{{ $note['at'] }}" data-len="{{ $note['len'] }}" data-midi="{{ $note['midi'] }}" data-word="{{ $note['word'] }}" style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"><span>{{ $note['word'] }}</span></span>
                            @endforeach
                        </span>
                    @endforeach
                    <span class="cp-bv-over" style="grid-row: 1 / {{ count($hero['lanes']) + 1 }}"><span class="cp-bv-ph" data-bv-ph></span></span>
                </div>
                <figcaption class="sr-only">{{ $bv['panel']['alt'] }}</figcaption>
            </figure>
            <p class="cp-bv-source">{{ $bv['panel']['source'] }}</p>
            <h2 class="sr-only">{{ __('pages.site.course_learn') }}</h2>
            <ul class="cp-bv-learn">
                @foreach ((array) ($page['learn'] ?? []) as $point)
                    {{-- "Doo-wop: figures..." as a bold name, then its line. --}}
                    @php($said = explode(': ', $point, 2))
                    <li class="s-v{{ $loop->index % 4 + 1 }}"><span class="cp-bv-key" aria-hidden="true"></span><span>@if (count($said) === 2)<b>{{ $said[0] }}.</b> {{ \Illuminate\Support\Str::ucfirst($said[1]) }}.@else{{ $point }}@endif</span></li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- The course itself, unit by unit, from its own file (SitePages::courseOutline): each unit in its part's colour
         with a strip of its technique, its lessons numbered, a lesson with its own page linked to it. --}}
    @if ($outline !== null)
        <section class="s-wrap cp-bv-sec" aria-labelledby="units-h">
            <div class="cp-bv-units-head">
                <h2 id="units-h" class="cp-bv-h2">{{ $bv['units_h'] }}</h2>
                <p class="cp-bv-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-bv-units">
                @foreach ($outline['units'] as $unit)
                    @php($u = $loop->index)
                    <li class="cp-bv-unit s-v{{ $u % 4 + 1 }}">
                        <div>
                            <div class="cp-bv-unit-head">
                                <span class="cp-bv-unit-n" aria-hidden="true">{{ $loop->iteration }}</span>
                                <div>
                                    <h3><span class="sr-only">{{ __('pages.site.course_unit', ['n' => $loop->iteration]) }}: </span>{{ $unit['title'] }}</h3>
                                    @if (isset($bv['units'][$u]))
                                        <p class="cp-bv-unit-sub">{{ $bv['units'][$u] }}</p>
                                    @endif
                                </div>
                            </div>
                            @if (isset($strips[$u]))
                                <div class="cp-bv-strip" aria-hidden="true">
                                    @foreach ($strips[$u] as [$kind, $ghost])
                                        <span class="cp-bv-strip-lane {{ $ghost ? 'cp-bv-ghost' : ($kinds[$kind] ? 's-v'.$kinds[$kind] : 'cp-bv-is-lead') }}">
                                            @foreach ($hero['lanes'][$kind] as $note)
                                                <i style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"></i>
                                            @endforeach
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                        <ol class="cp-bv-lessons">
                            @foreach ($unit['lessons'] as $title)
                                <li>
                                    <span class="cp-bv-num" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                    @if (isset($pages[$title]))
                                        <a href="{{ $pages[$title] }}">{{ $title }}</a>
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

    {{-- Lesson 1 without the app: its own words, then four takes, each in a part's colour. --}}
    <section class="s-wrap cp-bv-sec" aria-labelledby="try-h">
        <div class="cp-bv-try">
            <div>
                <p class="s-kick">{{ $bv['try']['kick'] }}</p>
                <h2 id="try-h" class="cp-bv-h2 cp-bv-h2-s">{{ $bv['try']['title'] }}</h2>
                <p class="cp-bv-p">{{ $bv['try']['lead'] }}</p>
            </div>
            <div>
                <ol class="cp-bv-takes">
                    @foreach ((array) $bv['try']['steps'] as $step)
                        <li class="s-v{{ $loop->index % 4 + 1 }}"><span class="cp-bv-take" aria-hidden="true">{{ $loop->iteration }}</span><span>{{ $step }}</span></li>
                    @endforeach
                </ol>
                <p class="cp-bv-after">{{ $bv['try']['after'] }}</p>
            </div>
        </div>
    </section>

    {{-- Why it works: lesson 1's pad under Amazing Grace as three lanes (the tune, the chords named by their notes with
         the pad's note marked, the pad), then three points, each with a strip of its idea. --}}
    <section class="s-wrap cp-bv-sec" aria-labelledby="why-h">
        <p class="s-kick">{{ $bv['science']['kick'] }}</p>
        <h2 id="why-h" class="cp-bv-h2 cp-bv-why-h">{{ $bv['science']['title'] }}</h2>
        <p class="cp-bv-p">{{ $bv['science']['lead'] }}</p>
        <div class="cp-bv-panel cp-bv-panel-why">
            <p class="s-kick cp-bv-quiet">{{ $bv['science']['panel']['kick'] }}</p>
            <figure class="cp-bv-fig">
                <div class="cp-bv-tracks cp-bv-tracks-why" aria-hidden="true">
                    <span class="cp-bv-grid cp-bv-grid-8" style="grid-row: 1 / 4"></span>
                    <span class="cp-bv-name cp-bv-is-lead" style="grid-row: 1">{{ $bv['science']['panel']['lanes']['lead'] }}</span>
                    <span class="cp-bv-lane cp-bv-is-lead cp-bv-lane-ghost" style="grid-row: 1">
                        @foreach ($first['lead'] as $note)
                            <span class="cp-bv-seg" style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"><span>{{ $note['word'] }}</span></span>
                        @endforeach
                    </span>
                    <span class="cp-bv-name cp-bv-name-chords" style="grid-row: 2">{{ $bv['science']['panel']['lanes']['chords'] }}</span>
                    <span class="cp-bv-lane cp-bv-lane-chords s-v1" style="grid-row: 2">
                        @foreach ($first['chords'] as $chord)
                            <span class="cp-bv-chord" style="left: {{ $chord['left'] }}%; width: {{ $chord['width'] }}%">
                                @foreach ($chord['tones'] as $tone)
                                    <span @class(['cp-bv-shared' => $tone['shared']])>{{ $tone['name'] }}</span>
                                @endforeach
                            </span>
                        @endforeach
                    </span>
                    <span class="cp-bv-name s-v1" style="grid-row: 3">{{ $bv['science']['panel']['lanes']['pad'] }}</span>
                    <span class="cp-bv-lane s-v1" style="grid-row: 3">
                        @foreach ($first['pad'] as $note)
                            <span class="cp-bv-seg" style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"><span>{{ $note['word'] }}</span></span>
                        @endforeach
                    </span>
                </div>
                <figcaption class="sr-only">{{ $bv['science']['panel']['alt'] }}</figcaption>
            </figure>
            <p class="cp-bv-source">{{ $bv['science']['panel']['note'] }}</p>
        </div>
        <ol class="cp-bv-points">
            @foreach ((array) $bv['science']['points'] as [$title, $text, $cites])
                <li class="s-v{{ $loop->iteration }}">
                    {{-- Each idea's strip: a note held still under the moving tune; the pad stepping as its chords change;
                         three parts that end on the same beat. --}}
                    <div class="cp-bv-strip cp-bv-strip-8" aria-hidden="true">
                        @if ($loop->index === 0)
                            <span class="cp-bv-strip-lane cp-bv-is-lead">@foreach ($first['lead'] as $note)<i style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"></i>@endforeach</span>
                            <span class="cp-bv-strip-lane"><i style="left: 0%; width: 100%"></i></span>
                        @elseif ($loop->index === 1)
                            <span class="cp-bv-strip-lane cp-bv-ghost">@foreach ($first['chords'] as $chord)<i style="left: {{ $chord['left'] }}%; width: {{ $chord['width'] }}%"></i>@endforeach</span>
                            <span class="cp-bv-strip-lane">@foreach ($first['pad'] as $note)<i style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"></i>@endforeach</span>
                        @else
                            @foreach ([[0, 75], [25, 50], [50, 25]] as [$left, $width])
                                <span class="cp-bv-strip-lane"><i style="left: {{ $left }}%; width: {{ $width }}%"></i></span>
                            @endforeach
                            <span class="cp-bv-strip-cut"></span>
                        @endif
                    </div>
                    <h3><span class="cp-bv-num" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>{{ $title }}</h3>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- The archive as a session: a track for each kind of backing part, in the hero's colours, each fact a clip whose
         header is its year where the source gives one. --}}
    <section class="s-wrap cp-bv-sec" aria-labelledby="story-h">
        <p class="s-kick">{{ $bv['story']['kick'] }}</p>
        <h2 id="story-h" class="cp-bv-h2">{{ $bv['story']['title'] }}</h2>
        <p class="cp-bv-p">{{ $bv['story']['lead'] }}</p>
        <div class="cp-bv-session">
            @foreach (['pad' => 1, 'doo' => 2, 'answer' => 3, 'stack' => 4] as $kind => $v)
                <div class="cp-bv-track s-v{{ $v }}">
                    <h3 class="cp-bv-track-name"><span class="cp-bv-key" aria-hidden="true"></span>{{ $bv['story']['lanes'][$kind] }}</h3>
                    <ul class="cp-bv-clips">
                        @foreach ((array) $bv['story']['clips'][$kind] as [$tag, $text, $cites])
                            <li class="cp-bv-clip">
                                <p class="cp-bv-clip-tag">{{ $tag }}</p>
                                <p class="cp-bv-clip-text">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    {{-- What the app listens for, beside a result card plainly marked as a sample (the course's own example numbers,
         lang `scoring`), never a singer's score. --}}
    <section class="s-wrap cp-bv-sec" aria-labelledby="hears-h">
        <div class="cp-bv-hears">
            <div>
                <p class="s-kick">{{ __('pages.site.course_hears') }}</p>
                <h2 id="hears-h" class="cp-bv-h2 cp-bv-h2-s">{{ $bv['hears_h'] }}</h2>
                @foreach ((array) $bv['hears'] as $line)
                    <p class="cp-bv-p">{{ $line }}</p>
                @endforeach
                @if (is_array($page['scoring'] ?? null))
                    <p class="cp-bv-p"><x-site.say :text="$page['scoring']" /></p>
                @endif
            </div>
            <div class="cp-bv-card">
                <p class="cp-bv-sample"><b>{{ $bv['sample']['kick'] }}</b> <span>{{ $bv['sample']['note'] }}</span></p>
                <p class="sr-only">{{ $bv['sample']['dots'] }}</p>
                <ol class="cp-bv-dots s-v3" aria-hidden="true">
                    @for ($i = 0; $i < 24; $i++)
                        <li @class(['cp-bv-miss' => in_array($i, [5, 13, 19], true)])></li>
                    @endfor
                </ol>
                <div class="cp-bv-stats">
                    <p><b>21 <small>{{ str_replace(':n', '24', $bv['sample']['of']) }}</small></b><span>{{ $bv['sample']['hit'] }}</span></p>
                    <p><b>93%</b><span>{{ $bv['sample']['time'] }}</span></p>
                </div>
                <p class="cp-bv-phones"><b>{{ $bv['headphones'][0] }}</b> {{ $bv['headphones'][1] }}</p>
            </div>
        </div>
    </section>

    {{-- Who it's for and what you need, then the setlist beside singing safely. --}}
    <section class="s-wrap cp-bv-sec cp-bv-songs">
        <div aria-labelledby="who-h">
            <h2 id="who-h" class="s-kick">{{ __('pages.site.who') }}</h2>
            <ul class="cp-bv-rules">
                @foreach ((array) $bv['who'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
        </div>
        <div aria-labelledby="need-h">
            <h2 id="need-h" class="s-kick">{{ $bv['need_h'] }}</h2>
            <ul class="cp-bv-rules">
                @foreach ((array) $bv['need'] as $line)
                    @continue($outline === null && str_contains($line, ':lessons'))
                    <li>{{ strtr($line, $counts) }}</li>
                @endforeach
            </ul>
        </div>
        @if (($page['songs'] ?? []) !== [])
            <div aria-labelledby="songs-h">
                <h2 id="songs-h" class="s-kick">{{ $bv['setlist'] }}</h2>
                <ol class="cp-bv-setlist">
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><span class="cp-bv-num" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><b translate="no">{{ $song }}</b><span class="cp-bv-credit">{{ $credit }}</span></li>
                    @endforeach
                </ol>
            </div>
        @endif
        @if (($page['safety'] ?? []) !== [])
            <div aria-labelledby="safety-h">
                <h2 id="safety-h" class="s-kick">{{ __('pages.site.course_safety') }}</h2>
                <ul class="cp-bv-safe">
                    @foreach ((array) $page['safety'] as $note)
                        <li><x-site.icon name="info" size="18" />{{ $note }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    {{-- New to harmony: the beginner course, beside Amazing Grace's four parts as the course sings them. --}}
    <section class="s-ink cp-bv-today" aria-labelledby="today-h">
        <div class="s-wrap cp-bv-today-in">
            <div>
                <p class="s-kick">{{ $bv['today']['kick'] }}</p>
                <h2 id="today-h" class="cp-bv-h2 cp-bv-h2-l">{{ $bv['today']['title'] }}</h2>
                <p class="cp-bv-p">{{ $bv['today']['body'] }}</p>
                <p class="cp-bv-btns"><a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
            <figure class="cp-bv-grace">
                <p class="s-kick cp-bv-quiet" aria-hidden="true">{{ $bv['today']['panel'] }}</p>
                <div class="cp-bv-tracks cp-bv-tracks-sm" aria-hidden="true">
                    <span class="cp-bv-grid cp-bv-grid-3" style="grid-row: 1 / 5"></span>
                    @foreach ($grace['parts'] as $notes)
                        <span class="cp-bv-name" style="grid-row: {{ $loop->iteration }}">{{ $bv['today']['parts'][$loop->index] ?? '' }}</span>
                        <span class="cp-bv-lane s-v{{ $loop->iteration }}" style="grid-row: {{ $loop->iteration }}">
                            @foreach ($notes as $note)
                                <span class="cp-bv-seg" style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"><span>{{ $loop->parent->first ? $note['word'] : '' }}</span></span>
                            @endforeach
                        </span>
                    @endforeach
                </div>
                <figcaption class="sr-only">{{ $bv['today']['alt'] }}</figcaption>
            </figure>
        </div>
    </section>

    <section class="s-wrap cp-bv-sec cp-bv-faq" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-bv-h2">{{ $bv['faq_h'] }}</h2>
        @include('site.courses._faq')
    </section>

    {{-- The close: the hero's lanes again, the guide on the lead and every backing lane yours, then the way in and the
         group's other courses. --}}
    <section class="s-wrap cp-bv-sec" aria-labelledby="close-h">
        <div class="cp-bv-panel cp-bv-close">
            <div>
                <p class="s-kick">{{ $bv['close']['kick'] }}</p>
                <h2 id="close-h" class="cp-bv-h2 cp-bv-h2-l">{{ $bv['close']['title'] }}</h2>
                <p class="cp-bv-p">{{ $open ? $bv['close']['body'] : __('pages.site.course_soon') }}</p>
                <p class="cp-bv-btns">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                </p>
                @if ($open)
                    <p class="cp-bv-join">{{ __('pages.site.course_join_note') }}</p>
                @endif
            </div>
            <div class="cp-bv-tracks cp-bv-tracks-close" aria-hidden="true">
                <span class="cp-bv-grid" style="grid-row: 1 / {{ count($hero['lanes']) + 1 }}"></span>
                @foreach ($hero['lanes'] as $kind => $notes)
                    <span class="cp-bv-name {{ $kinds[$kind] ? 's-v'.$kinds[$kind] : 'cp-bv-is-lead' }}" style="grid-row: {{ $loop->iteration }}">{{ $bv['close']['lanes'][$loop->index] ?? '' }}</span>
                    <span class="cp-bv-lane {{ $kinds[$kind] ? 's-v'.$kinds[$kind] : 'cp-bv-is-lead' }}" style="grid-row: {{ $loop->iteration }}">
                        @foreach ($notes as $note)
                            <span class="cp-bv-seg" style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"></span>
                        @endforeach
                    </span>
                @endforeach
            </div>
        </div>
        <div class="cp-bv-more">
            <h2 id="more-h" class="s-kick">{{ __('pages.site.course_more', ['group' => $group['name'] ?? '']) }}</h2>
            <ul class="s-chips">
                @foreach ((array) ($group['courses'] ?? []) as $slug)
                    @continue($slug === $page['slug'])
                    <li><a href="/courses/{{ $slug }}">{{ __('site.courses.pages.'.$slug.'.name') }}</a></li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- The numbered notes the claims point to, folded on a phone and open on a wide screen (js/public/backing-vocals.js). --}}
    <section class="s-wrap cp-bv-sources" aria-labelledby="notes-h">
        <details data-bv-sources>
            <summary><h2 id="notes-h" class="s-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/backing-vocals.js"></script>
</main>
