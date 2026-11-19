{{-- Classical singing basics (/courses/classical-singing-basics), its own page after Hillel's design (2026-11-18): the look
     of sheet music, the logo's four colours (the parts' tokens, .s-v1 to .s-v4) marking the four units. The hero's card is
     lesson 1's own phrase engraved by the song pages' engraver (js/public/classical-singing-basics.js), a sample voice
     line tracing it and the words the app gives after that step; the units as four movements, each with its slur, its
     Italian name and its lessons; what the app scores as score markings, with what it doesn't in its own box; the songs
     as a concert programme beside singing safely; then the beginner course and the group's other courses. Every claim is
     what the course scores (docs/courses/classical-singing.md, js/lesson-scores.js). Words: lang/en/site.php
     courses.pages.classical-singing-basics (`love`). The length pass (2026-11-18) adds, in the same score's language: lesson
     1's first round to try (two takes, no mic), the old books' rules as performance practice, programme notes with
     dates, who it's for, questions, a da capo close and the numbered sources; every fact is one of the course's checked
     lesson facts, cited. --}}
{{-- Lesson 1's phrase and its line, the programme and the units' lines: App\Support\Pages\ClassicalCoursePage. --}}
@php($love = (array) $page['love'])
@php($units = $outline['units'] ?? [])
@php($first = \App\Support\Pages\ClassicalCoursePage::firstPhrase())
@php($phrase = $first['notes'])
@php($measures = $first['measures'])
@php($programme = \App\Support\Pages\ClassicalCoursePage::programme((array) ($page['songs'] ?? [])))
@php($status = $programme['status'])
@php($rounds = \App\Support\Pages\ClassicalCoursePage::firstRounds())
@php($try = (array) $love['try'])
@php($sources = (array) $love['sources'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
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

    @if ($rounds['rounds'] !== [])
        @php($round = $rounds['rounds'][0])
        <section class="s-wrap cp-cl-sec cp-cl-try" aria-labelledby="try-h">
            <div>
                <p class="cp-cl-kick">{{ $try['kick'] }}</p>
                <h2 id="try-h" class="cp-cl-title">{{ $try['title'] }}</h2>
                <p class="cp-cl-body">{{ $try['lead'] }}</p>
                <p class="cp-cl-steps-kick">{{ $try['steps_kick'] }}</p>
                <ol class="cp-cl-steps">
                    @foreach ((array) $try['steps'] as $step)
                        <li><span class="cp-cl-step-n" aria-hidden="true">{{ $loop->iteration }}</span><span>{{ $step }}</span></li>
                    @endforeach
                </ol>
                <p class="cp-cl-try-after">{{ $try['after'] }}</p>
            </div>
            <div class="cp-cl-round" data-cl-try data-tempo="{{ $rounds['tempo'] }}"
                data-round="{{ $try['round'] }}" data-hear="{{ $try['hear'] }}" data-stop="{{ $try['stop'] }}" data-right="{{ $try['right'] }}" data-wrong="{{ $try['wrong'] }}"
                data-next="{{ $try['next'] }}" data-again="{{ $try['again'] }}" data-done="{{ $try['done'] }}" data-ready="{{ $try['ready'] }}" data-playing="{{ $try['playing'] }}">
                <script type="application/json" data-cl-rounds>@json($rounds['rounds'])</script>
                <p class="cp-cl-card-head"><span data-cl-count>{{ strtr($try['round'], [':n' => '1', ':total' => (string) count($rounds['rounds'])]) }}</span><span>{{ str_replace(':name', (string) ($units[0]['lessons'][0] ?? ''), (string) $love['score']['lesson']) }}</span></p>
                <h3 class="cp-cl-prompt" data-cl-prompt>{{ $round['prompt'] }}</h3>
                <div class="cp-cl-takes">
                    @foreach ([1, 2] as $n)
                        <button type="button" class="cp-cl-hear" data-cl-hear="{{ $loop->index }}" aria-pressed="false">
                            <span class="cp-cl-hear-n" aria-hidden="true">{{ $n }}</span>
                            <span class="cp-cl-hear-label" data-cl-label>{{ str_replace(':n', (string) $n, $try['hear']) }}</span>
                            <span class="cp-cl-hear-bar" aria-hidden="true"><i></i></span>
                        </button>
                    @endforeach
                </div>
                <div class="cp-cl-picks" role="group" aria-labelledby="try-h">
                    @foreach ([1, 2] as $n)
                        <button type="button" class="cp-cl-pick" data-cl-pick="{{ $loop->index }}">{{ str_replace(':n', (string) $n, $try['choose']) }}</button>
                    @endforeach
                </div>
                <p class="cp-cl-say" role="status" data-cl-say>{{ $try['ready'] }}</p>
                <p class="cp-cl-next-row"><button type="button" class="btn-secondary" data-cl-next hidden>{{ $try['next'] }}</button></p>
            </div>
        </section>
    @endif

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

    <section class="s-wrap cp-cl-sec" aria-labelledby="practice-h">
        <p class="cp-cl-kick">{{ $love['practice_kick'] }}</p>
        <h2 id="practice-h" class="cp-cl-title">{{ $love['practice_title'] }}</h2>
        <ul class="cp-cl-rules">
            @foreach ((array) $love['practice'] as [$from, $title, $text, $cites, $unit])
                <li class="cp-cl-rule s-v{{ $unit }}">
                    <p class="cp-cl-rule-from">{{ $from }}</p>
                    <h3>{{ $title }}</h3>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ul>
    </section>

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

    <section class="s-wrap cp-cl-sec" aria-labelledby="notes-h">
        <p class="cp-cl-kick">{{ $love['notes_kick'] }}</p>
        <h2 id="notes-h" class="cp-cl-title">{{ $love['notes_title'] }}</h2>
        <ol class="cp-cl-notes">
            @foreach ((array) $love['notes'] as [$when, $title, $text, $cites])
                <li>
                    <p class="cp-cl-year">{{ $when }}</p>
                    <div>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap cp-cl-sec cp-cl-who" aria-labelledby="who-h">
        <div>
            <h2 id="who-h" class="cp-cl-kick">{{ $love['who_title'] }}</h2>
            <ul class="cp-cl-ticks">
                @foreach ((array) $love['who'] as $line)
                    <li><x-site.icon name="check" size="18" /><span>{{ $line }}</span></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2 class="cp-cl-kick">{{ $love['need_title'] }}</h2>
            <ul class="cp-cl-ticks">
                @foreach ((array) $love['need'] as $line)
                    <li><x-site.icon name="check" size="18" /><span>{{ $line }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>

    @include('site._course-today')

    @if (($page['faq'] ?? []) !== [])
        <section class="s-wrap cp-cl-sec cp-cl-faqsec" aria-labelledby="faq-h">
            <div>
                <p class="cp-cl-kick">{{ $love['faq_kick'] }}</p>
                <h2 id="faq-h" class="cp-cl-title">{{ $love['faq_title'] }}</h2>
            </div>
            @include('site.courses._faq')
        </section>
    @endif

    <section class="s-wrap cp-cl-sec cp-cl-close" aria-labelledby="close-h">
        <svg class="cp-cl-close-staff" viewBox="0 0 260 46" preserveAspectRatio="none" aria-hidden="true">
            @foreach ([22, 27, 32, 37, 42] as $y)
                <path d="M0 {{ $y }} H260" class="cp-cl-staff-line" />
            @endforeach
            <path d="M14 26 Q120 4 224 26" class="cp-cl-staff-slur" />
            <path d="M252 22 V42" class="cp-cl-staff-bar" /><path d="M257 22 V42" class="cp-cl-staff-end" />
        </svg>
        <p class="cp-cl-dc" lang="it">{{ $love['close_kick'] }}</p>
        <h2 id="close-h" class="cp-cl-title">{{ $love['close_title'] }}</h2>
        <p class="cp-cl-body">{{ $open ? $love['close'] : __('pages.site.course_soon') }}</p>
        <p class="cp-cl-actions">
            <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
            <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
        </p>
        @if ($open)
            <p class="cp-cl-actions-note">{{ __('pages.site.course_join_note') }}</p>
        @endif
        <h3 id="more-h" class="cp-cl-kick cp-cl-more-h">{{ __('pages.site.course_more', ['group' => $group['name'] ?? '']) }}</h3>
        <ul class="s-chips cp-cl-more">
            @foreach ((array) ($group['courses'] ?? []) as $slug)
                @continue($slug === $page['slug'])
                <li><a href="/courses/{{ $slug }}">{{ __('site.courses.pages.'.$slug.'.name') }}</a></li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap cp-cl-sources" aria-labelledby="sources-h">
        <h2 id="sources-h" class="cp-cl-kick">{{ __('pages.site.sources') }}</h2>
        <ol>
            @foreach ($sources as [$label, $url])
                <li id="src-{{ $loop->iteration }}">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $label }}</a>@else{{ $label }}@endif</li>
            @endforeach
        </ol>
    </section>
    <script type="module" src="/js/public/classical-singing-basics.js"></script>
</main>
