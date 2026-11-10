{{-- Perfect your pitch (/courses/perfect-your-pitch), its own page: an archery range, after Hillel's
     design (docs/course-pages-polish.md). Archers draw to an anchor point, as the course makes your own
     note an anchor you carry, and a round is shot in ends. Serious skin: a cream sports-poster page,
     heavy italic headlines and tracked labels with a gold dot; the hero is words, with its two buttons
     large in the right-hand column on wide screens (Hillel, 2026-10-31: no picture there, the space is
     for the button). Game skin: neon rings and glowing arrows on a night range. Then lesson 1's
     Bullseye, live (the guide sings, the visitor echoes with the mic, opt-in on a tap, or pulls a note
     in by ear without one), aim, the anchor point, on target (the beats, playable), four ends (the
     units, from the course file), first arrows, what the app hears, from the range (the facts, cited),
     your kit, questions, take aim, and the sources. Every sound is made here
     (js/public/perfect-your-pitch.js), only when pressed. Words: lang/en/site.php
     courses.pages.perfect-your-pitch (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($counts = [':lessons' => (string) ($outline['lessons'] ?? ''), ':units' => (string) count($outline['units'] ?? [])])
{{-- The demo's board: a band for each reach in cents (the rim a semitone, the outer 50, the inner 25,
     the gold 10), at the radii js/public/perfect-your-pitch.js places a dart by. --}}
@php($board = [[150, 'w'], [128, 'k'], [104, 'b'], [66, 'r'], [30, 'g']])
@php($ladder = [0, 2, 4, 5, 7, 9, 11, 12])
@php($bars = ['g', 'r', 'b', 'n'])
{{-- On target's two waves, as the design draws them: three beats across, and still. --}}
@php($apart = '')
@php($there = '')
@foreach (range(0, 400) as $i)
    @php($env = abs(cos($i / 400 * M_PI * 3)) * 0.85 + 0.15)
    @php($apart .= ($i ? 'L' : 'M').$i.' '.round(45 - sin($i * 0.5) * 40 * $env, 1))
    @php($there .= ($i ? 'L' : 'M').$i.' '.round(45 - sin($i * 0.5) * 32, 1))
@endforeach
<main class="cp cp-pt2" data-pt>
    <section class="s-wrap cp-pt2-hero" aria-labelledby="page-h">
        <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
        <div class="cp-pt2-hero-grid">
            <div>
                <p class="cp-pt2-kick">{{ $outline !== null ? strtr($love['eyebrow'], $counts) : $page['eyebrow'] }}</p>
                <h1 id="page-h" class="cp-pt2-h1">{{ $page['hero_title'] }}</h1>
                <p class="cp-pt2-lede">{{ $page['hero_lead'] }}</p>
                <div class="cp-pt2-hero-cta">
                    <p class="cp-pt2-ctas">
                        <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                        <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                    </p>
                    @unless ($open)
                        <p class="cp-pt2-quiet">{{ __('pages.site.course_soon') }}</p>
                    @endunless
                </div>
                <ul class="cp-pt2-chips">
                    @foreach ((array) $love['chips'] as $chip)
                        @if ($outline !== null || ! str_contains($chip, ':lessons'))
                            <li>{{ strtr($chip, $counts) }}</li>
                        @endif
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-pt2-demosec" aria-labelledby="demo-h">
        @include('site.courses._pt-bullseye')
    </section>

    <section class="s-wrap s-sec cp-pt2-aim" aria-labelledby="outcomes-h">
        <p class="cp-pt2-kick">{{ $page['kicks']['aim'] }}</p>
        <h2 id="outcomes-h" class="cp-pt2-h2">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-pt2-goals">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-pt2-num" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-pt2-anchor" aria-labelledby="relative-h">
        <div class="cp-pt2-pair">
            <div>
                <p class="cp-pt2-kick">{{ $page['kicks']['anchor'] }}</p>
                <h2 id="relative-h" class="cp-pt2-h2">{{ $page['titles']['relative'] }}</h2>
                <p class="cp-pt2-lede">{{ $page['relative']['lead'] }}</p>
                <p class="cp-pt2-small">{{ $page['relative']['body'] }}</p>
            </div>
            <figure class="cp-pt2-card cp-pt2-ladder">
                <svg viewBox="0 0 400 270" aria-hidden="true">
                    @foreach ($ladder as $step)
                        <path d="M20 {{ 250 - $step * 18 }} H380" class="cp-pt2-rung" />
                    @endforeach
                    <polyline points="@foreach ($ladder as $step){{ 40 + $loop->index * 48 }},{{ 250 - $step * 18 }} @endforeach" class="cp-pt2-climb" />
                    @foreach ($ladder as $step)
                        <circle cx="{{ 40 + $loop->index * 48 }}" cy="{{ 250 - $step * 18 }}" r="{{ $loop->first ? 15 : 9 }}" class="{{ $loop->first ? 'cp-pt2-anchor-dot' : 'cp-pt2-step' }}" />
                    @endforeach
                </svg>
                <figcaption><span>{{ $love['anchor']['you'] }}</span><span>{{ $love['anchor']['steps'] }}</span></figcaption>
            </figure>
        </div>
    </section>

    <section class="cp-pt2-band" aria-labelledby="ring-h">
        <div class="s-wrap s-sec cp-pt2-pair">
            <div>
                <p class="cp-pt2-kick">{{ $page['kicks']['target'] }}</p>
                <h2 id="ring-h" class="cp-pt2-h2">{{ $page['titles']['ring'] }}</h2>
                <p class="cp-pt2-lede">{{ $page['ring']['lead'] }}</p>
                <p class="cp-pt2-three"><b aria-hidden="true">3</b> <span>{{ $page['ring']['body'] }}@include('site.courses._cites', ['keys' => ['beats']])</span></p>
            </div>
            <div class="cp-pt2-beats" data-pt-beats>
                <figure class="cp-pt2-wave cp-pt2-wave-apart">
                    <svg viewBox="0 0 400 90" preserveAspectRatio="none" aria-hidden="true"><path d="{{ $apart }}" /></svg>
                    <figcaption><span>{{ $page['ring']['apart'] }}</span><span class="cp-pt2-hz">{{ $love['beats']['apart'] }}</span></figcaption>
                </figure>
                <figure class="cp-pt2-wave cp-pt2-wave-there">
                    <svg viewBox="0 0 400 90" preserveAspectRatio="none" aria-hidden="true"><path d="{{ $there }}" /></svg>
                    <figcaption><span>{{ $page['ring']['there'] }}</span><span class="cp-pt2-hz">{{ $love['beats']['there'] }}</span></figcaption>
                </figure>
                <p class="cp-pt2-btns"><button type="button" class="cp-pt2-play" data-pt-beats-play data-play="{{ $love['beats']['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-pt2-play-icon" aria-hidden="true"></span><span data-label>{{ $love['beats']['play'] }}</span></button></p>
            </div>
        </div>
    </section>

    @if ($outline !== null)
        <section class="s-wrap s-sec cp-pt2-endsec" aria-labelledby="units-h">
            <p class="cp-pt2-kick">{{ strtr($love['ends']['kick'], $counts) }}</p>
            <h2 id="units-h" class="cp-pt2-h2">{{ $page['titles']['units'] }}</h2>
            <ol class="cp-pt2-ends" tabindex="0" aria-labelledby="units-h">
                @foreach ($outline['units'] as $unit)
                    @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                    @php($end = str_replace(':n', (string) $loop->iteration, $page['end_n']))
                    @php($many = (string) count($unit['lessons']))
                    <li class="cp-pt2-end">
                        <p class="cp-pt2-end-top"><span class="cp-pt2-end-n"><x-site.skin :game="$stage" :serious="$end" /></span><span class="cp-pt2-end-count">{{ str_replace(':n', $many, $love['ends']['count']) }}</span></p>
                        <span class="cp-pt2-end-bar cp-pt2-bar-{{ $bars[$loop->index % 4] }}" style="--w: {{ round(100 * $loop->iteration / count($outline['units'])) }}%" aria-hidden="true"></span>
                        <h3>{{ $unit['title'] }}</h3>
                        @isset($page['units_say'][$loop->index])
                            <p class="cp-pt2-small">{{ $page['units_say'][$loop->index] }}</p>
                        @endisset
                        <ol class="cp-pt2-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li><span class="cp-pt2-ln" aria-hidden="true">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span><span>{{ $lesson }}@if ($loop->parent->first && $loop->first) <a href="{{ $tryUrl }}" class="cp-pt2-free">{{ __('pages.site.course_free') }}</a>@endif</span></li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="s-wrap s-sec cp-pt2-first" aria-labelledby="try-h">
        <p class="cp-pt2-kick">{{ $page['kicks']['first'] }}</p>
        <h2 id="try-h" class="cp-pt2-h2">{{ $page['titles']['try'] }}</h2>
        <p class="cp-pt2-lede">{{ $page['try']['lead'] }}</p>
        <ol class="cp-pt2-steps">
            @foreach ((array) $page['try']['steps'] as $step)
                <li @class(['cp-pt2-step-first' => $loop->first])><span class="cp-pt2-num" aria-hidden="true">{{ $loop->iteration }}</span>@if ($loop->first)<b class="cp-pt2-start">{{ $page['try_first'] }}</b>@endif<p>{{ $step }}</p></li>
            @endforeach
        </ol>
        <p class="cp-pt2-small">{{ $page['try']['after'] }}</p>
    </section>

    @include('site.courses._inside')

    <section class="s-wrap s-sec cp-pt2-range" aria-labelledby="facts-h">
        <p class="cp-pt2-kick">{{ $page['kicks']['range'] }}</p>
        <h2 id="facts-h" class="cp-pt2-h2">{{ $page['titles']['facts'] }}</h2>
        <ul class="cp-pt2-facts">
            @foreach ((array) $love['facts'] as [$text, $source, $cites])
                <li>
                    <svg viewBox="0 0 300 12" preserveAspectRatio="none" class="cp-pt2-fact-arrow" aria-hidden="true"><path d="M5 1 L10 6 L5 11 L0 6 Z" class="cp-pt2-nock" /><path d="M10 6 H290" class="cp-pt2-line" /><path d="M300 6 L289 0 V12 Z" class="cp-pt2-tip" /></svg>
                    <p class="cp-pt2-fact">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    <p class="cp-pt2-src">{{ $source }}</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap s-sec cp-pt2-kit" aria-labelledby="kit-h">
        <p class="cp-pt2-kick">{{ $page['kicks']['kit'] }}</p>
        <h2 id="kit-h" class="cp-pt2-h2">{{ $page['titles']['kit'] }}</h2>
        <div class="cp-pt2-kit-grid">
            <div><h3 class="cp-pt2-kit-h">{{ $page['titles']['who'] }}</h3><ul class="cp-pt2-ticks">@foreach ((array) $page['who'] as $line)<li><x-site.icon name="check" size="18" /><span>{{ $line }}</span></li>@endforeach</ul></div>
            <div><h3 class="cp-pt2-kit-h">{{ $page['titles']['need'] }}</h3><ul class="cp-pt2-ticks">@foreach ((array) $page['need'] as $line)<li><x-site.icon name="check" size="18" /><span>{{ $line }}</span></li>@endforeach</ul></div>
            <div class="cp-pt2-rules">
                <h3>{{ $page['titles']['safety'] }}</h3>
                <ul>@foreach ((array) $page['safety'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                <h3>{{ __('pages.site.course_songs') }}</h3>
                <ul>
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-pt2-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-pt2-kick">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-pt2-h2">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-pt2-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-pt2-close-in">
            <svg viewBox="0 0 88 88" class="cp-pt2-badge" aria-hidden="true">
                @foreach ([[43, 'w'], [36, 'k'], [28, 'b'], [19, 'r'], [9, 'g']] as [$r, $c])
                    <circle cx="44" cy="44" r="{{ $r }}" class="cp-pt2-ring cp-pt2-{{ $c }}" />
                @endforeach
            </svg>
            <h2 id="join-h" class="cp-pt2-h2 cp-pt2-close-h">{{ $page['titles']['join'] }}</h2>
            <p class="cp-pt2-lede">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-pt2-ctas">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                @if ($open)
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @else
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @endif
            </p>
            @if ($open)
                <p class="cp-pt2-quiet">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-pt2-kick cp-pt2-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-pt2-notes" aria-labelledby="notes-h">
        <details data-pt-open>
            <summary><h2 id="notes-h" class="cp-pt2-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/perfect-your-pitch.js"></script>
</main>
