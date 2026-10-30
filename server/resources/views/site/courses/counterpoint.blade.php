{{-- Counterpoint (/courses/counterpoint), its own page: a treatise in the manner of Fux's Gradus ad
     Parnassum, 1725 (docs/course-pages-polish.md). Parchment, an italic serif, rubric red and gold,
     chapters in small caps with a fleuron, illuminated initials, and notes in the margin (serious
     skin); the same score lit on a dark stage (game skin). The hero is a page lying at a slant with a
     round on three staves: one tune entering in ink, then red, then gold, two bars apart, and a quill
     across the corner. Then the chapters: the argument (what you'll be able to do), of motion (the
     three ways two lines move, and which intervals are smooth or rub), the lessons in five books, an
     exercise (be a round), what the app hears, in the margins (facts), to the reader (who, what you
     need, singing safely), questions and finis. The art is wordless but for the table's interval
     names. Words: lang/en/site.php courses.pages.counterpoint. --}}
@php($motif = [[0, 0], [22, 0], [44, 0], [60, 1], [76, 2], [112, 2], [128, 1], [144, 2], [160, 3], [176, 4]])
@php($voices = [['cp-cp-v1', 0, 66], ['cp-cp-v2', 112, 156], ['cp-cp-v3', 224, 246]])
@php($moves = [[[[12, 62], [52, 50], [92, 38], [132, 26]], [[12, 82], [52, 70], [92, 58], [132, 46]]], [[[12, 62], [52, 50], [92, 38], [132, 26]], [[12, 34], [52, 46], [92, 58], [132, 70]]], [[[12, 50], [52, 50], [92, 50], [132, 50]], [[12, 80], [52, 66], [92, 72], [132, 58]]]])
@php($roman = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII'])
<main class="cp cp-counterpoint">
    <section class="cp-hero cp-cp-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-cp-kick cp-eyebrow"><span aria-hidden="true">❧</span> {{ $page['eyebrow'] }}</p>
                <h1 id="page-h">{{ $page['hero_title'] }}</h1>
                <p class="s-lede cp-cp-lede">{{ $page['hero_lead'] }}</p>
                <ul class="cp-hero-facts">
                    @foreach ((array) $page['hero_facts'] as $fact)
                        <li>{{ $fact }}</li>
                    @endforeach
                </ul>
                <p class="cp-hero-btns">
                    @if ($open)
                        <a href="{{ \App\Support\Http\Hosts::app('/learn/c/'.$page['app']) }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                    @endif
                    <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                </p>
            </div>
            <div class="cp-cp-art" aria-hidden="true">
                <svg viewBox="0 0 560 400" class="cp-cp-svg">
                    <defs>
                        <filter id="cp-shadow" x="-10%" y="-10%" width="130%" height="130%"><feDropShadow dx="0" dy="14" stdDeviation="14" flood-opacity=".28" /></filter>
                        <linearGradient id="cp-page" x1="0" y1="0" x2="1" y2="1"><stop offset="0" class="cp-cp-page-1" /><stop offset="1" class="cp-cp-page-2" /></linearGradient>
                    </defs>
                    <g transform="rotate(-3 280 200)">
                        <path d="M30 34 Q140 26 280 32 T 530 30 L534 368 Q420 376 280 370 T 26 372 Z" fill="url(#cp-page)" filter="url(#cp-shadow)" class="cp-cp-sheet" />
                        <path d="M58 70 V318" class="cp-cp-brace" />
                        @foreach ($voices as [$class, $offset, $staffY])
                            <path d="M66 {{ $staffY }} H508 M66 {{ $staffY + 10 }} H508 M66 {{ $staffY + 20 }} H508 M66 {{ $staffY + 30 }} H508 M66 {{ $staffY + 40 }} H508" class="cp-cp-staff" />
                            <path d="M66 {{ $staffY }} V{{ $staffY + 40 }} M190 {{ $staffY }} V{{ $staffY + 40 }} M302 {{ $staffY }} V{{ $staffY + 40 }} M414 {{ $staffY }} V{{ $staffY + 40 }} M508 {{ $staffY }} V{{ $staffY + 40 }}" class="cp-cp-bar" />
                            @foreach ($motif as [$x, $step])
                                <ellipse cx="{{ 92 + $offset + $x }}" cy="{{ $staffY + 40 - $step * 5 }}" rx="7.5" ry="5.5" transform="rotate(-22 {{ 92 + $offset + $x }} {{ $staffY + 40 - $step * 5 }})" class="cp-cp-note {{ $class }}" />
                                <path d="M{{ 99 + $offset + $x }} {{ $staffY + 38 - $step * 5 }} V{{ $staffY + 8 - $step * 5 }}" class="cp-cp-stem {{ $class }}" />
                            @endforeach
                        @endforeach
                        <path d="M58 344 Q 160 356 260 346" class="cp-cp-flourish" />
                    </g>
                    <g class="cp-cp-quill" transform="translate(410 250) rotate(-38)">
                        <path d="M0 0 C 30 -10, 120 -40, 170 -34 C 130 -18, 60 4, 0 0 Z" class="cp-cp-feather" />
                        <path d="M0 0 C 50 -12, 110 -26, 170 -34" class="cp-cp-shaft" />
                        <path d="M-26 6 L0 0" class="cp-cp-nib" />
                    </g>
                </svg>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-cp-chapter cp-cp-argument" aria-labelledby="outcomes-h">
        <p class="cp-cp-kick"><span aria-hidden="true">❧</span> {{ $page['kicks']['argument'] }}</p>
        <h2 id="outcomes-h" class="cp-cp-title">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-cp-points">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-cp-num" aria-hidden="true">{{ $roman[$loop->index] }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="cp-cp-motion" aria-labelledby="move-h">
        <div class="s-wrap s-sec">
            <p class="cp-cp-kick"><span aria-hidden="true">❧</span> {{ $page['kicks']['motion'] }}</p>
            <h2 id="move-h" class="cp-cp-title">{{ $page['titles']['move'] }}</h2>
            <p class="s-lede">{{ $page['move']['lead'] }}</p>
            <div class="cp-cp-motion-grid">
                <div class="cp-cp-moves">
                    @foreach ((array) $page['move']['kinds'] as [$kind, $what])
                        <figure class="cp-cp-move">
                            <svg viewBox="0 0 144 96" aria-hidden="true">
                                <path class="cp-cp-staff" d="M4 26 H140 M4 46 H140 M4 66 H140" />
                                @foreach ($moves[$loop->index] as $i => $line)
                                    <polyline class="cp-cp-path {{ $i === 0 ? 'cp-cp-hi' : 'cp-cp-lo' }}" points="{{ implode(' ', array_map(fn ($p) => $p[0].','.$p[1], $line)) }}" />
                                    @foreach ($line as [$x, $y])
                                        <circle cx="{{ $x }}" cy="{{ $y }}" r="5" class="{{ $i === 0 ? 'cp-cp-dot-hi' : 'cp-cp-dot-lo' }}" />
                                    @endforeach
                                @endforeach
                            </svg>
                            <figcaption><span class="cp-cp-num" aria-hidden="true">{{ $roman[$loop->index] }}</span><b>{{ $kind }}</b> {{ $what }}</figcaption>
                        </figure>
                    @endforeach
                </div>
                <div class="cp-cp-table">
                    <h3>{{ $page['move']['table_title'] }}</h3>
                    <dl>
                        <div class="cp-cp-smooth-row"><dt>{{ $page['move']['smooth_label'] }}</dt><dd>@foreach ((array) $page['move']['smooth_list'] as $interval)<span>{{ $interval }}</span>@endforeach</dd></div>
                        <div class="cp-cp-rub-row"><dt>{{ $page['move']['rub_label'] }}</dt><dd>@foreach ((array) $page['move']['rub_list'] as $interval)<span>{{ $interval }}</span>@endforeach</dd></div>
                    </dl>
                    <p>{{ $page['move']['rub_note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    @if ($outline !== null)
        <section class="s-wrap s-sec cp-cp-chapter" aria-labelledby="units-h">
            <p class="cp-cp-kick"><span aria-hidden="true">❧</span> {{ $page['kicks']['books'] }}</p>
            <div class="cp-head">
                <h2 id="units-h" class="cp-cp-title">{{ $page['titles']['units'] }}</h2>
                <p class="cp-head-note">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-cp-books">
                @foreach ($outline['units'] as $unit)
                    @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                    @php($book = str_replace(':n', $roman[$loop->index], $page['book_n']))
                    <li class="cp-cp-book">
                        <p class="cp-cp-book-n"><x-site.skin :game="$stage" :serious="$book" /></p>
                        <h3>{{ $unit['title'] }}</h3>
                        @isset($page['units_say'][$loop->index])
                            <p class="cp-cp-book-say">{{ $page['units_say'][$loop->index] }}</p>
                        @endisset
                        <ol class="cp-cp-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li>{{ $lesson }}@if ($loop->parent->first && $loop->first) <span class="s-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="s-wrap s-sec cp-cp-exercise" aria-labelledby="try-h">
        <div class="cp-cp-exercise-head">
            <div>
                <p class="cp-cp-kick"><span aria-hidden="true">❧</span> {{ $page['kicks']['exercise'] }}</p>
                <h2 id="try-h" class="cp-cp-title">{{ $page['titles']['try'] }}</h2>
                <p class="s-lede">{{ $page['try']['lead'] }}</p>
            </div>
            <figure class="cp-cp-entries">
                <svg viewBox="0 0 300 120" aria-hidden="true">
                    <path d="M10 112 H290" class="cp-cp-time" />
                    @foreach ([['cp-cp-v1', 10, 14], ['cp-cp-v2', 70, 46], ['cp-cp-v3', 130, 78]] as [$class, $x, $y])
                        <rect x="{{ $x }}" y="{{ $y }}" width="{{ 290 - $x }}" height="20" rx="10" class="cp-cp-entry {{ $class }}" />
                        <circle cx="{{ $x + 10 }}" cy="{{ $y + 10 }}" r="5" class="cp-cp-entry-start" />
                    @endforeach
                </svg>
                <figcaption>{{ $page['try']['figure'] }}</figcaption>
            </figure>
        </div>
        <ol class="cp-cp-steps">
            @foreach ((array) $page['try']['steps'] as $step)
                <li @class(['cp-cp-step-first' => $loop->first])><span class="cp-cp-num" aria-hidden="true">{{ $roman[$loop->index] }}</span><p>@if ($loop->first)<b class="cp-cp-start">{{ $page['try_first'] }}</b> @endif{{ $step }}</p></li>
            @endforeach
        </ol>
        <p class="cp-fig-note">{{ $page['try']['after'] }}</p>
    </section>

    @include('site.courses._inside')

    <section class="s-wrap s-sec cp-cp-margins" aria-labelledby="facts-h">
        <div class="cp-cp-margins-head">
            <p class="cp-cp-kick"><span aria-hidden="true">❧</span> {{ $page['kicks']['margins'] }}</p>
            <h2 id="facts-h" class="cp-cp-title">{{ $page['titles']['facts'] }}</h2>
        </div>
        <ul class="cp-cp-glosses">
            @foreach ((array) $page['facts'] as [$text, $source, $url])
                <li class="cp-cp-gloss">
                    <span class="cp-cp-hand" aria-hidden="true">☞</span>
                    <p class="cp-cp-gloss-text">{{ $text }}</p>
                    <p class="cp-cp-gloss-src">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $source }}</a>@else{{ $source }}@endif</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap s-sec" aria-labelledby="reader-h">
        <div class="cp-cp-reader">
            <p class="cp-cp-kick"><span aria-hidden="true">❧</span> {{ $page['kicks']['reader'] }}</p>
            <h2 id="reader-h" class="cp-cp-title">{{ $page['titles']['reader'] }}</h2>
            <div class="cp-cp-reader-cols">
                <div><h3>{{ $page['titles']['who'] }}</h3><ul>@foreach ((array) $page['who'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
                <div><h3>{{ $page['titles']['need'] }}</h3><ul>@foreach ((array) $page['need'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
                <div><h3>{{ $page['titles']['safety'] }}</h3><ul>@foreach ((array) $page['safety'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
            </div>
            <div class="cp-cp-songs">
                <h3>{{ __('pages.site.course_songs') }}</h3>
                <ul>
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-cp-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-cp-kick"><span aria-hidden="true">❧</span> {{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-cp-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-cp-finis" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-cp-finis-in">
            <svg viewBox="0 0 220 40" class="cp-cp-tail" aria-hidden="true"><path d="M10 20 H90 M130 20 H210" /><path d="M110 6 C 96 14, 96 26, 110 34 C 124 26, 124 14, 110 6 Z" /><circle cx="90" cy="20" r="3" /><circle cx="130" cy="20" r="3" /></svg>
            <p class="cp-cp-kick">{{ $page['kicks']['finis'] }}</p>
            <h2 id="join-h" class="cp-cp-title">{{ $page['titles']['join'] }}</h2>
            <p class="s-lede">{{ $open ? $page['finis'] : __('pages.site.course_soon') }}</p>
            @if ($open)
                <p class="cp-join-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <p class="cp-join-btns">
                @if ($open)
                    <a href="{{ \App\Support\Http\Hosts::app('/learn/c/'.$page['app']) }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="/pricing" class="btn-secondary btn-lg">{{ __('pages.site.course_plus') }}</a>
                @else
                    <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @endif
            </p>
            <h3 class="cp-cp-kick cp-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
</main>
