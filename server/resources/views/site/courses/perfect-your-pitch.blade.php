{{-- Perfect your pitch (/courses/perfect-your-pitch), its own page: an archery range
     (docs/course-pages-polish.md). Archers draw to an anchor point, as the course makes your own
     note an anchor you carry, and a round is shot in ends. Serious skin: a summer range, with sky, a
     tree line, mown grass with distance lines, a target face in the five ring colours on its stand,
     arrows in the gold, a wind flag and an archer at full draw; heavy italic headlines. Game skin:
     neon rings and glowing arrows on a night range. Then: aim (what you'll be able to do), the anchor
     point (relative pitch, honestly), on target (tuning by the beats), four ends (the units, each
     lesson an arrow), first arrows (try this), what the app hears, from the range (facts), your kit
     (who, what you need, singing safely, the songs), questions and take aim. The art is wordless.
     Words: lang/en/site.php courses.pages.perfect-your-pitch. --}}
@php($beats = '')
@php($still = '')
@foreach (range(0, 188) as $t)
    @php($beat = 26 * abs(cos(M_PI * $t / 94)))
    @php($beats .= (6 + $t).','.round(35 - $beat * sin(M_PI * $t / 4), 1).' ')
    @php($still .= (6 + $t).','.round(35 - 20 * sin(M_PI * $t / 4), 1).' ')
@endforeach
@php($ladder = [0, 2, 4, 5, 7, 9, 11, 12])
@php($rings = [[96, 'w'], [86, 'w2'], [76, 'k'], [66, 'k2'], [56, 'b'], [46, 'b2'], [36, 'r'], [26, 'r2'], [16, 'g'], [7, 'g2']])
@php($arrows = [[420, 176, -18], [432, 190, -12], [410, 196, -22]])
<main class="cp cp-pitch">
    <section class="cp-hero cp-pt-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-pt-kick cp-eyebrow">{{ $page['eyebrow'] }}</p>
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
            <div class="cp-pt-art" aria-hidden="true">
                <svg viewBox="0 0 600 420" class="cp-pt-svg">
                    <defs>
                        <linearGradient id="pt-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="cp-pt-sky-1" /><stop offset="1" class="cp-pt-sky-2" /></linearGradient>
                        <clipPath id="pt-frame"><rect width="600" height="420" rx="28" /></clipPath>
                    </defs>
                    <g clip-path="url(#pt-frame)">
                        <rect width="600" height="250" fill="url(#pt-sky)" />
                        <path d="M0 250 C 40 214, 70 222, 100 206 C 140 192, 170 214, 200 200 C 240 182, 270 206, 310 196 C 350 184, 380 208, 420 198 C 460 186, 500 210, 540 200 C 570 194, 590 206, 600 202 V250 Z" class="cp-pt-trees" />
                        <rect y="250" width="600" height="170" class="cp-pt-grass" />
                        <path d="M0 272 H600 M0 300 H600 M0 338 H600 M0 390 H600" class="cp-pt-lines" />
                        <path d="M70 250 V150" class="cp-pt-pole" /><path d="M70 152 L112 164 L70 176 Z" class="cp-pt-windflag" />
                        <path d="M404 296 L420 196 M452 296 L436 196 M428 296 V230" class="cp-pt-stand" />
                        <g transform="translate(428 186)">
                            @foreach ($rings as [$r, $c])
                                <circle r="{{ $r }}" class="cp-pt-ring cp-pt-{{ $c }}" />
                            @endforeach
                        </g>
                        @foreach ($arrows as [$x, $y, $a])
                            <g transform="translate({{ $x }} {{ $y }}) rotate({{ $a }})" class="cp-pt-arrow"><path d="M0 0 L-70 0" class="cp-pt-shaft" /><path d="M-62 0 L-74 -7 L-70 0 L-74 7 Z" class="cp-pt-fletch" /></g>
                        @endforeach
                        <g class="cp-pt-archer" transform="translate(150 196)">
                            <circle cx="0" cy="-58" r="13" class="cp-pt-body" />
                            <path d="M0 -44 L-4 30 M-4 30 L-18 96 M-4 30 L12 96" class="cp-pt-limb" />
                            <path d="M-2 -32 L56 -40 M-2 -26 L4 -48" class="cp-pt-limb" />
                            <path d="M56 -112 Q 92 -40 56 32" class="cp-pt-bow" />
                            <path d="M56 -112 L8 -48 L56 32" class="cp-pt-string" />
                            <path d="M8 -48 L130 -60" class="cp-pt-shaft" />
                        </g>
                    </g>
                </svg>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-pt-aim" aria-labelledby="outcomes-h">
        <p class="cp-pt-kick">{{ $page['kicks']['aim'] }}</p>
        <h2 id="outcomes-h" class="cp-pt-title">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-pt-goals">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><svg viewBox="0 0 40 40" class="cp-pt-mini" aria-hidden="true"><circle cx="20" cy="20" r="18" class="cp-pt-ring cp-pt-b" /><circle cx="20" cy="20" r="12" class="cp-pt-ring cp-pt-r" /><circle cx="20" cy="20" r="6" class="cp-pt-ring cp-pt-g" /></svg><span class="cp-pt-goal-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-pt-anchor" aria-labelledby="relative-h">
        <div class="cp-pair">
            <div>
                <p class="cp-pt-kick">{{ $page['kicks']['anchor'] }}</p>
                <h2 id="relative-h" class="cp-pt-title">{{ $page['titles']['relative'] }}</h2>
                <p class="s-lede">{{ $page['relative']['lead'] }}</p>
                <p class="cp-fig-note">{{ $page['relative']['body'] }}</p>
            </div>
            <figure class="cp-fig cp-pt-ladder" aria-hidden="true">
                <svg viewBox="0 0 240 160">
                    @foreach ($ladder as $step)
                        <path d="M10 {{ 140 - $step * 10 }} H230" class="cp-pt-rung" />
                        <circle cx="{{ 28 + $loop->index * 26 }}" cy="{{ 140 - $step * 10 }}" r="{{ $loop->first ? 11 : 6 }}" class="{{ $loop->first ? 'cp-pt-anchor-dot' : 'cp-pt-step' }}" />
                        @if (! $loop->first)
                            <path d="M28 140 Q {{ 14 + $loop->index * 13 }} {{ 140 - $step * 6 }} {{ 22 + $loop->index * 26 }} {{ 142 - $step * 10 }}" class="cp-pt-reach" />
                        @endif
                    @endforeach
                </svg>
            </figure>
        </div>
    </section>

    <section class="s-ink cp-pt-ringsec" aria-labelledby="ring-h">
        <div class="s-wrap s-sec">
            <p class="cp-pt-kick">{{ $page['kicks']['target'] }}</p>
            <h2 id="ring-h" class="cp-pt-title">{{ $page['titles']['ring'] }}</h2>
            <p class="s-lede">{{ $page['ring']['lead'] }}</p>
            <div class="cp-pt-beats">
                <figure class="cp-pt-beat">
                    <svg viewBox="0 0 200 70" aria-hidden="true"><polyline points="{{ $beats }}" /></svg>
                    <figcaption>{{ $page['ring']['apart'] }}</figcaption>
                </figure>
                <figure class="cp-pt-beat cp-pt-there">
                    <svg viewBox="0 0 200 70" aria-hidden="true"><polyline points="{{ $still }}" /></svg>
                    <figcaption>{{ $page['ring']['there'] }}</figcaption>
                </figure>
            </div>
            <p class="cp-pt-big"><b aria-hidden="true">3</b> {{ $page['ring']['body'] }}</p>
        </div>
    </section>

    @if ($outline !== null)
        <section class="s-wrap s-sec cp-pt-endsec" aria-labelledby="units-h">
            <p class="cp-pt-kick">{{ $page['kicks']['ends'] }}</p>
            <div class="cp-head">
                <h2 id="units-h" class="cp-pt-title">{{ $page['titles']['units'] }}</h2>
                <p class="cp-head-note">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-pt-ends">
                @foreach ($outline['units'] as $unit)
                    @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                    @php($end = str_replace(':n', (string) $loop->iteration, $page['end_n']))
                    <li class="cp-pt-end">
                        <div class="cp-pt-end-head">
                            <p class="cp-pt-end-n"><x-site.skin :game="$stage" :serious="$end" /></p>
                            <svg viewBox="0 0 64 64" class="cp-pt-end-face" aria-hidden="true">
                                <circle cx="32" cy="32" r="30" class="cp-pt-ring cp-pt-w" /><circle cx="32" cy="32" r="24" class="cp-pt-ring cp-pt-k" /><circle cx="32" cy="32" r="18" class="cp-pt-ring cp-pt-b" /><circle cx="32" cy="32" r="12" class="cp-pt-ring cp-pt-r" /><circle cx="32" cy="32" r="6" class="cp-pt-ring cp-pt-g" />
                                @foreach ($unit['lessons'] as $lesson)
                                    <circle cx="{{ 32 + round(5 * cos($loop->index * 2.4), 1) }}" cy="{{ 32 + round(5 * sin($loop->index * 2.4), 1) }}" r="2.2" class="cp-pt-hole" />
                                @endforeach
                            </svg>
                        </div>
                        <h3>{{ $unit['title'] }}</h3>
                        @isset($page['units_say'][$loop->index])
                            <p class="cp-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                        @endisset
                        <ol class="cp-pt-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li>{{ $lesson }}@if ($loop->parent->first && $loop->first) <span class="s-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="s-wrap s-sec cp-pt-first" aria-labelledby="try-h">
        <p class="cp-pt-kick">{{ $page['kicks']['first'] }}</p>
        <h2 id="try-h" class="cp-pt-title">{{ $page['titles']['try'] }}</h2>
        <p class="s-lede">{{ $page['try']['lead'] }}</p>
        <ol class="cp-pt-try">
            @foreach ((array) $page['try']['steps'] as $step)
                <li @class(['cp-pt-try-first' => $loop->first])><span class="cp-pt-ring-n" aria-hidden="true">{{ $loop->iteration }}</span><p>@if ($loop->first)<b class="cp-pt-start">{{ $page['try_first'] }}</b> @endif{{ $step }}</p></li>
            @endforeach
        </ol>
        <p class="cp-fig-note">{{ $page['try']['after'] }}</p>
    </section>

    @include('site.courses._inside')

    <section class="s-wrap s-sec cp-pt-range" aria-labelledby="facts-h">
        <p class="cp-pt-kick">{{ $page['kicks']['range'] }}</p>
        <h2 id="facts-h" class="cp-pt-title">{{ $page['titles']['facts'] }}</h2>
        <ul class="cp-pt-cards">
            @foreach ((array) $page['facts'] as [$text, $source, $url])
                <li class="cp-pt-card">
                    <svg viewBox="0 0 120 20" class="cp-pt-card-arrow" aria-hidden="true"><path d="M8 10 H108" class="cp-pt-shaft" /><path d="M18 10 L6 3 L10 10 L6 17 Z" class="cp-pt-fletch" /><path d="M108 10 L100 5 V15 Z" class="cp-pt-head" /></svg>
                    <p class="cp-pt-card-text">{{ $text }}</p>
                    <p class="cp-pt-card-src">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $source }}</a>@else{{ $source }}@endif</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap s-sec cp-pt-kit" aria-labelledby="kit-h">
        <p class="cp-pt-kick">{{ $page['kicks']['kit'] }}</p>
        <h2 id="kit-h" class="cp-pt-title">{{ $page['titles']['kit'] }}</h2>
        <div class="cp-pt-kit-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul class="s-ticks">@foreach ((array) $page['who'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul></div>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="s-ticks">@foreach ((array) $page['need'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul></div>
            <div class="cp-pt-rules">
                <h3>{{ $page['titles']['safety'] }}</h3>
                <ul>@foreach ((array) $page['safety'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                <h3>{{ __('pages.site.course_songs') }}</h3>
                <ul class="cp-pt-songs">
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-pt-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-pt-kick">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-pt-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-pt-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-pt-close-in">
            <svg viewBox="0 0 120 120" class="cp-pt-close-face" aria-hidden="true">
                @foreach ($rings as [$r, $c])
                    <circle cx="60" cy="60" r="{{ round($r * .58, 1) }}" class="cp-pt-ring cp-pt-{{ $c }}" />
                @endforeach
            </svg>
            <p class="cp-pt-kick">{{ $page['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-pt-title">{{ $page['titles']['join'] }}</h2>
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
            <h3 class="cp-pt-kick cp-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
</main>
