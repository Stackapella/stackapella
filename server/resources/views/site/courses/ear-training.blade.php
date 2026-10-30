{{-- Ear training (/courses/ear-training), its own page: a birder's field guide to intervals
     (docs/course-pages-polish.md). Birders learn a call by ear and keep it with its bird; here a leap
     is learned by ear and kept with its song. Serious skin: a field guide's serif headings and
     small-caps labels, plates numbered like a guide's. The hero is a tree at dawn whose branches sit
     at the scale's heights: a home bird on the low branch, three birds higher, the leaps arcing
     between them, and song rippling out. Game skin: a neon night forest. Then: your life list (what
     you'll be able to do), the field guide (three leaps, each with its song), day and dusk (major and
     minor), field trips (the units), in the field (try this), what the app hears, field notes
     (facts), your field kit (who, what you need, the songs), questions and go listening. The art is
     wordless. Words: lang/en/site.php courses.pages.ear-training. --}}
@php($branches = [0, 2, 4, 5, 7, 9, 11, 12])
@php($perches = [[300, 4, 'c1'], [380, 5, 'c2'], [470, 7, 'c3']])
@php($sizes = [4, 5, 7])
<main class="cp cp-ear">
    <svg width="0" height="0" class="cp-ear-defs" aria-hidden="true" focusable="false">
        <defs>
            <symbol id="ear-bird" viewBox="0 0 44 34">
                <path d="M8 20 L0 12 L4 24 Z" fill="currentColor" />
                <ellipse cx="19" cy="21" rx="13" ry="9" fill="currentColor" />
                <circle cx="31" cy="12" r="7" fill="currentColor" />
                <path d="M37 11 L44 13 L37 15 Z" class="cp-ear-beak" />
                <circle cx="33" cy="10.5" r="1.4" class="cp-ear-eye" />
                <path d="M15 30 V34 M22 30 V34" class="cp-ear-legs" />
            </symbol>
        </defs>
    </svg>

    <section class="cp-hero cp-ear-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-ear-kick cp-eyebrow">{{ $page['eyebrow'] }}</p>
                <h1 id="page-h">{{ $page['hero_title'] }}</h1>
                <p class="s-lede">{{ $page['hero_lead'] }}</p>
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
            <div class="cp-ear-art" aria-hidden="true">
                <svg viewBox="0 0 600 420" class="cp-ear-svg">
                    <defs>
                        <linearGradient id="ear-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="cp-ear-sky-1" /><stop offset="1" class="cp-ear-sky-2" /></linearGradient>
                        <clipPath id="ear-frame"><rect width="600" height="420" rx="28" /></clipPath>
                    </defs>
                    <g clip-path="url(#ear-frame)">
                        <rect width="600" height="420" fill="url(#ear-sky)" />
                        <circle cx="500" cy="90" r="46" class="cp-ear-sun" />
                        <path d="M0 360 C 120 330, 220 352, 330 336 S 520 322, 600 340 V420 H0 Z" class="cp-ear-hill" />
                        <path d="M86 420 C 92 330, 80 250, 96 70" class="cp-ear-trunk" />
                        @foreach ($branches as $step)
                            <path d="M92 {{ 330 - $step * 20 }} C 220 {{ 326 - $step * 20 }}, 380 {{ 334 - $step * 20 }}, {{ 520 - $loop->index * 8 }} {{ 328 - $step * 20 }}" class="cp-ear-branch" />
                            <g class="cp-ear-leaves"><ellipse cx="{{ 520 - $loop->index * 8 }}" cy="{{ 326 - $step * 20 }}" rx="16" ry="7" /><ellipse cx="{{ 500 - $loop->index * 8 }}" cy="{{ 320 - $step * 20 }}" rx="10" ry="5" /></g>
                        @endforeach
                        <g class="cp-ear-song"><path d="M190 300 a22 22 0 0 1 0 -30" /><path d="M200 306 a34 34 0 0 1 0 -42" /><path d="M210 312 a46 46 0 0 1 0 -54" /></g>
                        @foreach ($perches as [$x, $step, $c])
                            <path d="M178 312 Q {{ ($x + 178) / 2 }} {{ 200 - $step * 18 }} {{ $x + 10 }} {{ 316 - $step * 20 }}" class="cp-ear-arc" />
                            <use href="#ear-bird" x="{{ $x - 10 }}" y="{{ 284 - $step * 20 }}" width="60" height="46" class="cp-ear-bird cp-ear-{{ $c }}" />
                        @endforeach
                        <use href="#ear-bird" x="128" y="280" width="70" height="54" class="cp-ear-bird cp-ear-home" />
                    </g>
                </svg>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-ear-life" aria-labelledby="outcomes-h">
        <p class="cp-ear-kick">{{ $page['kicks']['life'] }}</p>
        <h2 id="outcomes-h" class="cp-ear-title">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-ear-ticks">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-ear-box" aria-hidden="true"></span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-ear-guide" aria-labelledby="leaps-h">
        <p class="cp-ear-kick">{{ $page['kicks']['guide'] }}</p>
        <h2 id="leaps-h" class="cp-ear-title">{{ $page['titles']['leaps'] }}</h2>
        <p class="s-lede">{{ $page['leaps']['lead'] }}</p>
        <div class="cp-ear-plates">
            @foreach ((array) $page['leaps']['kinds'] as [$leap, $song, $how])
                <figure class="cp-ear-plate">
                    <p class="cp-ear-plate-n">{{ str_replace(':n', (string) $loop->iteration, $page['plate_n']) }}</p>
                    <svg viewBox="0 0 200 130" aria-hidden="true">
                        <path d="M10 110 H190 M10 {{ 110 - $sizes[$loop->index] * 12 }} H190" class="cp-ear-branch cp-ear-branch-thin" />
                        <path d="M50 102 Q 100 {{ 40 - $sizes[$loop->index] * 6 }} 150 {{ 102 - $sizes[$loop->index] * 12 }}" class="cp-ear-arc" />
                        <use href="#ear-bird" x="22" y="84" width="40" height="30" class="cp-ear-bird cp-ear-home" />
                        <use href="#ear-bird" x="134" y="{{ 84 - $sizes[$loop->index] * 12 }}" width="40" height="30" class="cp-ear-bird cp-ear-c{{ $loop->iteration }}" />
                    </svg>
                    <figcaption><b>{{ $leap }}</b><i translate="no">{{ $song }}</i><span>{{ $how }}</span></figcaption>
                </figure>
            @endforeach
        </div>
        <p class="cp-fig-note">{{ $page['leaps']['body'] }}</p>
    </section>

    <section class="s-ink cp-ear-colour" aria-labelledby="colour-h">
        <div class="s-wrap s-sec cp-pair">
            <div>
                <p class="cp-ear-kick">{{ $page['kicks']['colour'] }}</p>
                <h2 id="colour-h" class="cp-ear-title">{{ $page['titles']['colour'] }}</h2>
                <p class="s-lede">{{ $page['colour']['lead'] }}</p>
            </div>
            <div class="cp-pair-figs">
                @foreach (['major' => 4, 'minor' => 3] as $kind => $third)
                    <figure class="cp-ear-sky cp-ear-{{ $kind }}">
                        <svg viewBox="0 0 120 140" aria-hidden="true">
                            <circle cx="92" cy="26" r="13" class="cp-ear-orb" />
                            @if ($kind === 'minor')
                                <circle cx="54" cy="{{ 118 - 4 * 13 }}" r="12" class="cp-ear-ghost" />
                            @endif
                            <circle cx="54" cy="118" r="12" class="cp-ear-note" />
                            <circle cx="54" cy="{{ 118 - $third * 13 }}" r="12" class="cp-ear-third" />
                            <circle cx="54" cy="{{ 118 - 7 * 13 }}" r="12" class="cp-ear-note" />
                        </svg>
                        <figcaption><b>{{ $page['colour'][$kind][0] }}</b> {{ $page['colour'][$kind][1] }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    @if ($outline !== null)
        <section class="s-wrap s-sec cp-ear-trips" aria-labelledby="units-h">
            <p class="cp-ear-kick">{{ $page['kicks']['trips'] }}</p>
            <div class="cp-head">
                <h2 id="units-h" class="cp-ear-title">{{ $page['titles']['units'] }}</h2>
                <p class="cp-head-note">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-ear-triplist">
                @foreach ($outline['units'] as $unit)
                    @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                    @php($trip = str_replace(':n', (string) $loop->iteration, $page['trip_n']))
                    <li class="cp-ear-trip">
                        <div class="cp-ear-trip-head">
                            <p class="cp-ear-trip-n"><x-site.skin :game="$stage" :serious="$trip" /></p>
                            <p class="cp-ear-sightings" aria-hidden="true">@foreach ($unit['lessons'] as $lesson)<svg viewBox="0 0 44 34"><use href="#ear-bird" class="cp-ear-bird cp-ear-c{{ $loop->index % 3 + 1 }}" /></svg>@endforeach</p>
                        </div>
                        <h3>{{ $unit['title'] }}</h3>
                        @isset($page['units_say'][$loop->index])
                            <p class="cp-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                        @endisset
                        <ol class="cp-ear-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li>{{ $lesson }}@if ($loop->parent->first && $loop->first) <span class="s-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="s-wrap s-sec cp-ear-field" aria-labelledby="try-h">
        <p class="cp-ear-kick">{{ $page['kicks']['field'] }}</p>
        <h2 id="try-h" class="cp-ear-title">{{ $page['titles']['try'] }}</h2>
        <p class="s-lede">{{ $page['try']['lead'] }}</p>
        <ol class="cp-ear-steps">
            @foreach ((array) $page['try']['steps'] as $step)
                <li @class(['cp-ear-step-first' => $loop->first])><span class="cp-ear-step-n" aria-hidden="true">{{ $loop->iteration }}</span><p>@if ($loop->first)<b class="cp-ear-start">{{ $page['try_first'] }}</b> @endif{{ $step }}</p></li>
            @endforeach
        </ol>
        <p class="cp-fig-note">{{ $page['try']['after'] }}</p>
    </section>

    @include('site.courses._inside')

    <section class="s-wrap s-sec cp-ear-notes" aria-labelledby="facts-h">
        <p class="cp-ear-kick">{{ $page['kicks']['notes'] }}</p>
        <h2 id="facts-h" class="cp-ear-title">{{ $page['titles']['facts'] }}</h2>
        <ul class="cp-ear-notebook">
            @foreach ((array) $page['facts'] as [$text, $source, $url])
                <li>
                    <p class="cp-ear-note-text">{{ $text }}</p>
                    <p class="cp-ear-note-src">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $source }}</a>@else{{ $source }}@endif</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap s-sec cp-ear-kit" aria-labelledby="kit-h">
        <p class="cp-ear-kick">{{ $page['kicks']['kit'] }}</p>
        <h2 id="kit-h" class="cp-ear-title">{{ $page['titles']['kit'] }}</h2>
        <div class="cp-ear-kit-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul class="s-ticks">@foreach ((array) $page['who'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul></div>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="s-ticks">@foreach ((array) $page['need'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul>
                <h3 class="cp-ear-kit-gap">{{ $page['titles']['safety'] }}</h3><ul class="s-ticks">@foreach ((array) $page['safety'] as $line)<li><x-site.icon name="info" size="18" />{{ $line }}</li>@endforeach</ul></div>
            <div class="cp-ear-songs">
                <h3>{{ __('pages.site.course_songs') }}</h3>
                <ul>
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-ear-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-ear-kick">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-ear-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-ear-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-ear-close-in">
            <svg viewBox="0 0 240 70" class="cp-ear-close-art" aria-hidden="true">
                <path d="M6 60 C 70 54, 160 64, 234 56" class="cp-ear-branch" />
                <use href="#ear-bird" x="30" y="30" width="40" height="30" class="cp-ear-bird cp-ear-home" />
                <use href="#ear-bird" x="100" y="28" width="40" height="30" class="cp-ear-bird cp-ear-c1" />
                <use href="#ear-bird" x="170" y="30" width="40" height="30" class="cp-ear-bird cp-ear-c3" />
            </svg>
            <p class="cp-ear-kick">{{ $page['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-ear-title">{{ $page['titles']['join'] }}</h2>
            <p class="s-lede">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
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
            <h3 class="cp-ear-kick cp-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
</main>
