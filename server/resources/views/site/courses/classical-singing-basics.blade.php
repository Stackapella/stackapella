{{-- Classical singing basics (/courses/classical-singing-basics), its own page after Hillel's design (2026-11-18): the look
     of sheet music, the logo's four colours (the parts' tokens, .s-v1 to .s-v4) marking the four units. The hero's card is
     lesson 1's own phrase engraved by the song pages' engraver (js/public/classical-singing-basics.js), a sample voice
     line tracing it and the words the app gives after that step; the units as four movements, each with its slur, its
     Italian name and its lessons; what the app scores as score markings, with what it doesn't in its own box; the songs
     as a concert programme beside singing safely; then the beginner course and the group's other courses. Every claim is
     what the course scores (docs/courses/classical-singing.md, js/lesson-scores.js). Words: lang/en/site.php
     courses.pages.classical-singing-basics (`love`). --}}
{{-- Lesson 1's phrase and its line, the programme and the units' lines: App\Support\Pages\ClassicalCoursePage. --}}
@php($love = (array) $page['love'])
@php($units = $outline['units'] ?? [])
@php($first = \App\Support\Pages\ClassicalCoursePage::firstPhrase())
@php($phrase = $first['notes'])
@php($measures = $first['measures'])
@php($programme = \App\Support\Pages\ClassicalCoursePage::programme((array) ($page['songs'] ?? [])))
@php($status = $programme['status'])
<main class="cp-cl" data-cl>
    <section class="s-wrap cp-cl-hero" aria-labelledby="page-h">
        <div class="cp-cl-hero-copy">
            <x-site.crumbs :trail="[[__('pages.site.courses'), '/courses'], [$page['name']]]" />
            <p class="cp-cl-pills">
                @if ($open)
                    <span class="s-soonpill"><span class="s-soon s-open">{{ __('pages.site.course_open') }}</span></span>
                @else
                    <span class="s-soonpill"><x-site.soon /></span>
                @endif
                <span class="cp-cl-mood">{{ $love['mood'] }}</span>
            </p>
            <h1 id="page-h">{{ $love['title_lead'] }} <span class="cp-cl-swash">{{ $love['title_em'] }}<svg viewBox="0 0 100 20" preserveAspectRatio="none" aria-hidden="true"><path d="M1 3 Q50 24 97 3" /></svg></span></h1>
            <p class="s-lede">{{ $page['lead'] }}</p>
            <div class="cp-cl-actions">
                @if ($open)
                    <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                @else
                    <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                @endif
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                <span class="cp-cl-actions-note">{{ $open ? __('pages.site.course_open_line') : __('pages.site.course_soon') }}</span>
            </div>
        </div>

        @if ($phrase !== [])
            <figure class="cp-cl-card">
                <figcaption class="cp-cl-card-head"><span>{{ str_replace(':name', (string) ($units[0]['lessons'][0] ?? ''), (string) $love['score']['lesson']) }}</span><span>{{ $love['score']['piece'] }}</span></figcaption>
                <div class="cp-cl-score" role="img" aria-label="{{ $love['score']['label'] }}" data-cl-score data-notes="{{ json_encode($phrase) }}" data-key="{{ \App\Support\Pages\ClassicalCoursePage::KEY }}" data-pickup="{{ \App\Support\Pages\ClassicalCoursePage::PICKUP }}" data-legato="{{ $love['score']['legato'] }}"></div>
                <ul class="cp-cl-legend" aria-hidden="true">
                    <li><i class="cp-cl-key-line"></i>{{ $love['score']['line'] }}</li>
                    <li><i class="cp-cl-key-slur"></i>{{ $love['score']['slur'] }}</li>
                    <li><i class="cp-cl-key-breath">,</i>{{ $love['score']['breath'] }}</li>
                </ul>
                @if ($measures !== '')
                    <div class="cp-cl-take">
                        <p class="cp-cl-take-kick">{{ $love['score']['take'] }}</p>
                        <p><b>{{ $love['score']['result'] }}</b> {{ $measures }}</p>
                    </div>
                @endif
            </figure>
        @endif
    </section>

    @if ($units !== [])
        <section class="s-wrap cp-cl-sec" aria-labelledby="units-h">
            <div class="cp-cl-head">
                <div>
                    <p class="cp-cl-kick">{{ $love['units_kick'] }}</p>
                    <h2 id="units-h" class="cp-cl-title">{{ $love['units_title'] }}</h2>
                </div>
                <p class="cp-cl-count">{{ __('pages.site.course_size', ['units' => count($units), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-cl-units">
                @foreach ($units as $unit)
                    @php($line = \App\Support\Pages\ClassicalCoursePage::unitLine($page['learn'][$loop->index] ?? null))
                    <li class="cp-cl-unit s-v{{ $loop->index % 4 + 1 }}">
                        <svg class="cp-cl-staff" viewBox="0 0 260 46" preserveAspectRatio="none" aria-hidden="true">
                            @foreach ([22, 27, 32, 37, 42] as $y)
                                <path d="M0 {{ $y }} H260" class="cp-cl-staff-line" />
                            @endforeach
                            <path d="M14 26 Q120 {{ 2 + $loop->index * 2 }} 224 26" class="cp-cl-staff-slur" />
                            @if ($loop->last)
                                <path d="M252 22 V42" class="cp-cl-staff-bar" /><path d="M257 22 V42" class="cp-cl-staff-end" />
                            @else
                                <text x="246" y="12" class="cp-cl-staff-breath">,</text>
                            @endif
                        </svg>
                        <p class="cp-cl-unit-n"><span class="cp-cl-roman">{{ \App\Support\Pages\ClassicalCoursePage::numeral($loop->iteration) }}</span> <span class="cp-cl-italian" lang="it">{{ $love['movements'][$loop->index] ?? '' }}</span></p>
                        <h3>{{ $unit['title'] }}</h3>
                        @if ($line !== '')
                            <p class="cp-cl-unit-line">{{ $line }}</p>
                        @endif
                        <ol class="cp-cl-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li><span class="cp-cl-lesson-n" aria-hidden="true">{{ $loop->iteration }}</span><span @class(['cp-cl-lesson', 'cp-cl-first' => $loop->parent->first && $loop->first])>{{ $lesson }}</span>@if ($loop->parent->first && $loop->first) <span class="cp-cl-start">{{ __('pages.site.course_free') }}</span>@endif</li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="cp-cl-band" aria-labelledby="hears-h">
        <div class="s-wrap cp-cl-hears">
            <div>
                <p class="cp-cl-kick">{{ __('pages.site.course_hears') }}</p>
                <h2 id="hears-h" class="cp-cl-title">{{ $love['hears_title'] }}</h2>
                @if (is_array($page['scoring'] ?? null))
                    <p class="cp-cl-body"><x-site.say :text="$page['scoring']" /></p>
                @endif
                <div class="cp-cl-unscored">
                    <p class="cp-cl-unscored-kick">{{ $love['unscored_kick'] }}</p>
                    <ul>
                        @foreach ((array) $love['unscored'] as $thing)
                            <li>{{ $thing }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <dl class="cp-cl-marks">
                @foreach ((array) $love['markings'] as $mark)
                    <div class="s-v{{ $mark['unit'] }}">
                        <dt lang="it">{{ $mark['term'] }}</dt>
                        <dd>{{ $mark['what'] }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="s-wrap cp-cl-sec cp-cl-concert">
        <div class="cp-cl-programme">
            <p class="cp-cl-kick">{{ __('pages.site.course_songs') }}</p>
            <h2 id="songs-h" class="cp-cl-programme-h">{{ $love['programme'] }}</h2>
            @if ($status !== null)
                <p class="cp-cl-programme-note">{{ $status }}</p>
            @endif
            <ol class="cp-cl-pieces">
                @foreach ($programme['pieces'] as $piece)
                    <li>
                        <p class="cp-cl-piece"><b translate="no">{{ $piece['title'] }}</b>@if ($piece['aside'] !== null) <span>{{ $piece['aside'] }}</span>@endif</p>
                        <p class="cp-cl-credit">{{ $piece['credit'] }}@if ($status === null && $piece['status'] !== null); {{ $piece['status'] }}@endif</p>
                    </li>
                @endforeach
            </ol>
        </div>
        @if (($page['safety'] ?? []) !== [])
            <div class="cp-cl-safety">
                <h2 id="safety-h" class="cp-cl-kick">{{ __('pages.site.course_safety') }}</h2>
                <p class="cp-cl-motto">{{ $love['safety_motto'] }}</p>
                <ul>
                    @foreach ((array) $page['safety'] as $note)
                        <li><x-site.icon name="info" size="18" />{{ $note }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    @include('site._course-today')

    <section class="s-wrap s-sec" aria-labelledby="more-h">
        <h2 id="more-h" class="s-kick">{{ __('pages.site.course_more', ['group' => $group['name'] ?? '']) }}</h2>
        <ul class="s-chips">
            @foreach ((array) ($group['courses'] ?? []) as $slug)
                @continue($slug === $page['slug'])
                <li><a href="/courses/{{ $slug }}">{{ __('site.courses.pages.'.$slug.'.name') }}</a></li>
            @endforeach
        </ul>
    </section>
    <script type="module" src="/js/public/classical-singing-basics.js"></script>
</main>
