{{-- Breath and stamina (/courses/breath-and-stamina), its own page: a voyage under sail
     (docs/course-pages-polish.md). Breath is the wind for the voice: a long note is a steady passage,
     running out is the sail going slack, and ending on purpose is coming into harbour. Serious skin:
     a pale sky with gulls and signal flags, the sea in bands, a sailboat whose sail is filled by
     streams of breath, and a chart ruler counting steady seconds; tracked nautical capitals. Game
     skin: a night sea, the boat in neon. Then: bearings (what you'll be able to do), coming in (a
     slack sail against a boat reaching harbour), fair weather (singing safely, as signal flags), the
     voyage (the units as legs on a chart), cast off (try this), what the app hears, the ship's log
     (facts), the crew list (who, what you need, the songs), questions and set sail. The art is
     wordless. Words: lang/en/site.php courses.pages.breath-and-stamina. --}}
@php($flags = ['a', 'b', 'c', 'd', 'a', 'c', 'b', 'd'])
@php($ports = [[70, 176], [210, 112], [350, 150], [490, 74]])
<main class="cp cp-breath">
    <section class="cp-hero cp-br-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-br-kick cp-eyebrow"><span class="cp-br-flag cp-br-flag-a" aria-hidden="true"></span>{{ $page['eyebrow'] }}</p>
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
            <div class="cp-br-art" aria-hidden="true">
                <svg viewBox="0 0 600 420" class="cp-br-svg">
                    <defs>
                        <linearGradient id="br-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="cp-br-sky-1" /><stop offset="1" class="cp-br-sky-2" /></linearGradient>
                        <clipPath id="br-frame"><rect width="600" height="420" rx="28" /></clipPath>
                    </defs>
                    <g clip-path="url(#br-frame)">
                        <rect width="600" height="260" fill="url(#br-sky)" />
                        <path d="M20 28 Q 300 70 580 28" class="cp-br-line" />
                        @foreach ($flags as $flag)
                            <g transform="translate({{ 46 + $loop->index * 66 }} {{ 30 + round(36 * sin(M_PI * ($loop->index + .5) / 8), 1) }})"><path d="M0 0 H24 V22 H0 Z" class="cp-br-sig cp-br-sig-{{ $flag }}" /></g>
                        @endforeach
                        <path d="M420 92 q8 -8 16 0 q8 -8 16 0 M470 120 q6 -6 12 0 q6 -6 12 0" class="cp-br-gull" />
                        <rect y="260" width="600" height="160" class="cp-br-sea" />
                        <path d="M0 274 Q 75 266 150 274 T 300 274 T 450 274 T 600 274" class="cp-br-wave" />
                        <path d="M0 300 Q 75 292 150 300 T 300 300 T 450 300 T 600 300" class="cp-br-wave cp-br-wave-2" />
                        <path d="M0 330 Q 75 322 150 330 T 300 330 T 450 330 T 600 330" class="cp-br-wave cp-br-wave-3" />
                        <g class="cp-br-air">
                            <path d="M30 150 C 110 130, 170 168, 250 150" />
                            <path d="M20 186 C 100 168, 170 204, 252 186" />
                            <path d="M40 222 C 110 206, 180 236, 254 222" />
                        </g>
                        <g class="cp-br-boat" transform="translate(300 120)">
                            <path d="M40 0 V150" class="cp-br-mast" />
                            <path d="M44 6 C 120 40, 140 100, 120 140 L44 140 Z" class="cp-br-sail" />
                            <path d="M36 20 C -6 60, -10 110, 6 140 L36 140 Z" class="cp-br-jib" />
                            <path d="M-20 150 H140 L120 176 H0 Z" class="cp-br-hull" />
                            <path d="M40 0 L62 8 L40 16" class="cp-br-pennant" />
                        </g>
                        <rect x="24" y="370" width="552" height="26" rx="13" class="cp-br-track" />
                        <rect x="24" y="370" width="356" height="26" rx="13" class="cp-br-fill" />
                        <path d="M64 402 v8 M104 402 v8 M144 402 v8 M184 402 v8 M224 402 v8 M264 402 v8 M304 402 v8 M344 402 v8 M384 402 v8 M424 402 v8 M464 402 v8 M504 402 v8 M544 402 v8" class="cp-br-ticks" />
                    </g>
                </svg>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-br-bearings" aria-labelledby="outcomes-h">
        <p class="cp-br-kick"><span class="cp-br-flag cp-br-flag-b" aria-hidden="true"></span>{{ $page['kicks']['bearings'] }}</p>
        <h2 id="outcomes-h" class="cp-br-title">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-br-aims">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-br-aim-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="cp-br-endsec" aria-labelledby="ending-h">
        <div class="s-wrap s-sec">
            <p class="cp-br-kick"><span class="cp-br-flag cp-br-flag-c" aria-hidden="true"></span>{{ $page['kicks']['coming'] }}</p>
            <h2 id="ending-h" class="cp-br-title">{{ $page['titles']['ending'] }}</h2>
            <p class="s-lede">{{ $page['ending']['lead'] }}</p>
            <div class="cp-br-ends">
                <figure class="cp-br-end">
                    <svg viewBox="0 0 280 150" aria-hidden="true">
                        <path d="M0 116 Q 70 110 140 116 T 280 116" class="cp-br-wave" />
                        <g transform="translate(110 20)"><path d="M30 0 V90" class="cp-br-mast" /><path d="M34 8 C 50 40, 44 70, 40 90 L34 90 Z" class="cp-br-sail cp-br-slack" /><path d="M-6 96 H86 L74 112 H6 Z" class="cp-br-hull" /></g>
                        <path d="M20 40 Q 60 50 90 70" class="cp-br-sag" />
                    </svg>
                    <figcaption><b>{{ $page['ending']['out'][0] }}</b> {{ $page['ending']['out'][1] }}</figcaption>
                </figure>
                <figure class="cp-br-end cp-br-end-good">
                    <svg viewBox="0 0 280 150" aria-hidden="true">
                        <path d="M0 116 Q 70 110 140 116 T 280 116" class="cp-br-wave" />
                        <path d="M210 120 V66 H250 V120 Z" class="cp-br-pier" /><path d="M200 66 H260" class="cp-br-mast" />
                        <g transform="translate(70 20)"><path d="M30 0 V90" class="cp-br-mast" /><path d="M34 6 C 84 30, 92 70, 80 90 L34 90 Z" class="cp-br-sail" /><path d="M-6 96 H86 L74 112 H6 Z" class="cp-br-hull" /></g>
                        <path d="M10 60 C 40 50, 60 64, 96 56" class="cp-br-air-one" />
                    </svg>
                    <figcaption><b>{{ $page['ending']['purpose'][0] }}</b> {{ $page['ending']['purpose'][1] }}</figcaption>
                </figure>
            </div>
            <p class="cp-fig-note">{{ $page['ending']['body'] }}</p>
        </div>
    </section>

    <section class="s-ink cp-br-weather" aria-labelledby="easy-h">
        <div class="s-wrap s-sec">
            <p class="cp-br-kick"><span class="cp-br-flag cp-br-flag-d" aria-hidden="true"></span>{{ $page['kicks']['weather'] }}</p>
            <h2 id="easy-h" class="cp-br-title">{{ $page['titles']['easy'] }}</h2>
            <p class="s-lede">{{ $page['easy']['lead'] }}</p>
            <ul class="cp-br-signals">
                @foreach ((array) $page['easy']['points'] as $point)
                    <li><span class="cp-br-sigtile cp-br-sig-{{ $flags[$loop->index] }}" aria-hidden="true"></span><span>{{ $point }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>

    @if ($outline !== null)
        <section class="s-wrap s-sec cp-br-voyage" aria-labelledby="units-h">
            <p class="cp-br-kick"><span class="cp-br-flag cp-br-flag-a" aria-hidden="true"></span>{{ $page['kicks']['voyage'] }}</p>
            <div class="cp-head">
                <h2 id="units-h" class="cp-br-title">{{ $page['titles']['units'] }}</h2>
                <p class="cp-head-note">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <svg viewBox="0 0 560 220" class="cp-br-chart" aria-hidden="true">
                <path d="M0 40 H560 M0 90 H560 M0 140 H560 M0 190 H560 M80 0 V220 M200 0 V220 M320 0 V220 M440 0 V220" class="cp-br-grid" />
                <path d="M20 200 C 40 190, 60 180, 70 176 S 170 120, 210 112 S 320 150, 350 150 S 450 90, 490 74 L 540 40" class="cp-br-route" />
                @foreach ($ports as [$x, $y])
                    <g transform="translate({{ $x }} {{ $y }})" class="cp-br-port"><circle r="18" /><text y="6">{{ $loop->iteration }}</text></g>
                @endforeach
                <g transform="translate(520 22)" class="cp-br-compass"><circle r="14" /><path d="M0 -12 L4 0 L0 12 L-4 0 Z" /></g>
            </svg>
            <ol class="cp-br-legs">
                @foreach ($outline['units'] as $unit)
                    @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                    @php($leg = str_replace(':n', (string) $loop->iteration, $page['leg_n']))
                    <li class="cp-br-leg">
                        <p class="cp-br-leg-n"><x-site.skin :game="$stage" :serious="$leg" /></p>
                        <h3>{{ $unit['title'] }}</h3>
                        @isset($page['units_say'][$loop->index])
                            <p class="cp-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                        @endisset
                        <ol class="cp-br-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li>{{ $lesson }}@if ($loop->parent->first && $loop->first) <span class="s-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="s-wrap s-sec cp-br-castoff" aria-labelledby="try-h">
        <p class="cp-br-kick"><span class="cp-br-flag cp-br-flag-b" aria-hidden="true"></span>{{ $page['kicks']['castoff'] }}</p>
        <h2 id="try-h" class="cp-br-title">{{ $page['titles']['try'] }}</h2>
        <p class="s-lede">{{ $page['try']['lead'] }}</p>
        <ol class="cp-br-try">
            @foreach ((array) $page['try']['steps'] as $step)
                <li @class(['cp-br-try-first' => $loop->first])><span class="cp-br-knot" aria-hidden="true">{{ $loop->iteration }}</span><p>@if ($loop->first)<b class="cp-br-start">{{ $page['try_first'] }}</b> @endif{{ $step }}</p></li>
            @endforeach
        </ol>
        <p class="cp-fig-note">{{ $page['try']['after'] }}</p>
    </section>

    @include('site.courses._inside')

    <section class="s-wrap s-sec cp-br-logsec" aria-labelledby="facts-h">
        <p class="cp-br-kick"><span class="cp-br-flag cp-br-flag-c" aria-hidden="true"></span>{{ $page['kicks']['log'] }}</p>
        <h2 id="facts-h" class="cp-br-title">{{ $page['titles']['facts'] }}</h2>
        <ol class="cp-br-log">
            @foreach ((array) $page['facts'] as [$text, $source, $url])
                <li class="cp-br-entry">
                    <p class="cp-br-entry-n">{{ str_replace(':n', (string) $loop->iteration, $page['entry_n']) }}</p>
                    <p class="cp-br-entry-text">{{ $text }}</p>
                    <p class="cp-br-entry-src">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $source }}</a>@else{{ $source }}@endif</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-br-crew" aria-labelledby="crew-h">
        <p class="cp-br-kick"><span class="cp-br-flag cp-br-flag-d" aria-hidden="true"></span>{{ $page['kicks']['crew'] }}</p>
        <h2 id="crew-h" class="cp-br-title">{{ $page['titles']['crew'] }}</h2>
        <div class="cp-br-crew-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul class="s-ticks">@foreach ((array) $page['who'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul></div>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="s-ticks">@foreach ((array) $page['need'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul></div>
            <div class="cp-br-hold">
                <h3>{{ __('pages.site.course_songs') }}</h3>
                <ul>
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-br-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-br-kick"><span class="cp-br-flag cp-br-flag-a" aria-hidden="true"></span>{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-br-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-br-close" aria-labelledby="join-h">
        <svg class="cp-br-close-sea" viewBox="0 0 1440 80" preserveAspectRatio="none" aria-hidden="true"><path d="M0 40 Q 180 20 360 40 T 720 40 T 1080 40 T 1440 40 V80 H0 Z" /></svg>
        <div class="s-wrap s-sec cp-br-close-in">
            <p class="cp-br-kick"><span class="cp-br-flag cp-br-flag-b" aria-hidden="true"></span>{{ $page['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-br-title">{{ $page['titles']['join'] }}</h2>
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
            <h3 class="cp-br-kick cp-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
</main>
