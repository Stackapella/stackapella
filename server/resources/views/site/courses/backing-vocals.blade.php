{{-- Backing vocals (/courses/backing-vocals), its own page after Hillel's design (2026-11-18,
     ~/Code/course-designs/backing-vocals): the backing parts drawn as stacked coloured lanes, like a multitrack.
     The hero's "behind" has a copy in each backing part's colour behind it; under it, Shenandoah's first two lines as
     a panel of lanes (the tune from its song template, the pad from the course's own lesson, a doo-wah figure and an
     answer drawn on the template's chords) with a playhead, which js/public/backing-vocals.js plays on voices.js when
     pressed. Then the units, each in its part's colour with a strip of its technique; what the app listens for beside
     a result card marked as a sample; the setlist with singing safely; the beginner course beside Amazing Grace's four
     parts from its template; the group's other courses. Every lesson's own page follows (course.blade.php's
     _lesson-pages, laid out by unit here in CSS). Part colours are the site's s-v1 to s-v4, so calm light and dark and
     game all draw them. Words: lang/en/site.php courses.pages.backing-vocals (`bv`). --}}
@php($bv = (array) $page['bv'])
{{-- Shenandoah's first two lines as four lanes, and Amazing Grace's first line as four parts, from real notes
     (App\Support\Site\BackingVocalsLanes). --}}
@php($hero = \App\Support\Site\BackingVocalsLanes::hero())
@php($grace = \App\Support\Site\BackingVocalsLanes::grace())
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

    @if (($page['songs'] ?? []) !== [] || ($page['safety'] ?? []) !== [])
        <section class="s-wrap cp-bv-sec cp-bv-songs">
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
    @endif

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

    <section class="s-wrap cp-bv-sec" aria-labelledby="more-h">
        <h2 id="more-h" class="s-kick">{{ __('pages.site.course_more', ['group' => $group['name'] ?? '']) }}</h2>
        <ul class="s-chips">
            @foreach ((array) ($group['courses'] ?? []) as $slug)
                @continue($slug === $page['slug'])
                <li><a href="/courses/{{ $slug }}">{{ __('site.courses.pages.'.$slug.'.name') }}</a></li>
            @endforeach
        </ul>
    </section>
    <script type="module" src="/js/public/backing-vocals.js"></script>
</main>
