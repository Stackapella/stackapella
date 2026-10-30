{{-- Folk and early music (/courses/folk-and-early-music), its own page: a broadside, printed and
     pasted on a tavern wall (docs/course-pages-polish.md). Serious skin: the sheet tacked to wooden
     boards, a woodcut of a ship under sail on a hatched sea with clouds and gulls, the tune in shape
     notes across its head; a sturdy serif, small caps, printer's ornaments and double rules. Game
     skin: a neon tavern. Then: the ballad (what you'll be able to do, as stanzas), the old modes,
     grace notes (ornaments), four verses (the units as printed columns), strike up (try this), what
     the app hears, on the tavern wall (facts as taped clippings), for the singer (who, what you need,
     singing safely, the songs), questions and all hands. The art is wordless. Words:
     lang/en/site.php courses.pages.folk-and-early-music. --}}
@php($shapes = [[80, 74, 'fa'], [118, 60, 'sol'], [156, 68, 'la'], [194, 50, 'mi'], [232, 60, 'sol'], [270, 74, 'fa'], [308, 56, 'la'], [346, 68, 'mi'], [384, 82, 'fa']])
@php($modes = ['bright' => [[0, 2, 3, 5, 7, 9, 10, 12], 5, 8], 'soft' => [[0, 2, 4, 5, 7, 9, 10, 12], 6, 11]])
@php($roman = ['I', 'II', 'III', 'IV', 'V', 'VI'])
<main class="cp cp-folk">
    <section class="cp-hero cp-fo-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-fo-kick cp-eyebrow"><span aria-hidden="true">✶</span> {{ $page['eyebrow'] }} <span aria-hidden="true">✶</span></p>
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
            <div class="cp-fo-art" aria-hidden="true">
                <div class="cp-fo-wall">
                    <svg viewBox="0 0 460 420" class="cp-fo-svg">
                        <defs>
                            <pattern id="fo-hatch" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(35)"><path d="M0 0 V6" class="cp-fo-hatchline" /></pattern>
                        </defs>
                        <rect x="10" y="10" width="440" height="400" class="cp-fo-paper" />
                        <rect x="24" y="24" width="412" height="372" class="cp-fo-rule" />
                        <rect x="30" y="30" width="400" height="360" class="cp-fo-rule cp-fo-thin" />
                        <path d="M60 96 H400" class="cp-fo-staffline" />
                        @foreach ($shapes as [$x, $y, $shape])
                            @if ($shape === 'fa')
                                <path d="M{{ $x - 9 }} {{ $y + 7 }} L{{ $x + 9 }} {{ $y + 7 }} L{{ $x + 9 }} {{ $y - 7 }} Z" class="cp-fo-shape" />
                            @elseif ($shape === 'sol')
                                <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="9" ry="7" class="cp-fo-shape" />
                            @elseif ($shape === 'la')
                                <rect x="{{ $x - 8 }}" y="{{ $y - 7 }}" width="16" height="14" class="cp-fo-shape" />
                            @else
                                <path d="M{{ $x }} {{ $y - 8 }} L{{ $x + 9 }} {{ $y }} L{{ $x }} {{ $y + 8 }} L{{ $x - 9 }} {{ $y }} Z" class="cp-fo-shape" />
                            @endif
                        @endforeach
                        <g class="cp-fo-woodcut">
                            <rect x="60" y="116" width="340" height="236" class="cp-fo-frame" />
                            <circle cx="340" cy="160" r="22" class="cp-fo-sun" />
                            <path d="M90 156 q18 -14 36 0 q14 -10 28 0 q-30 8 -64 0 Z M250 146 q14 -10 28 0 q10 -8 20 0 q-24 6 -48 0 Z" class="cp-fo-cloud" />
                            <path d="M180 150 q6 -6 12 0 q6 -6 12 0 M296 196 q5 -5 10 0 q5 -5 10 0" class="cp-fo-gull" />
                            <path d="M228 286 V170" class="cp-fo-mast" />
                            <path d="M232 176 C 300 206, 314 252, 300 280 L232 280 Z" fill="url(#fo-hatch)" class="cp-fo-sail" />
                            <path d="M224 186 C 176 222, 170 258, 180 280 L224 280 Z" class="cp-fo-sail cp-fo-sail-plain" />
                            <path d="M228 170 L252 178 L228 186" class="cp-fo-flag" />
                            <path d="M150 284 H310 L292 310 H170 Z" class="cp-fo-hull" />
                            <rect x="61" y="300" width="338" height="51" fill="url(#fo-hatch)" class="cp-fo-sea" />
                            <path d="M61 300 q14 -10 28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0" class="cp-fo-wave" />
                            <path d="M61 324 q14 -10 28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0" class="cp-fo-wave" />
                        </g>
                        <path d="M60 372 H190 M270 372 H400" class="cp-fo-orn-rule" />
                        <path d="M230 362 L240 372 L230 382 L220 372 Z" class="cp-fo-diamond" />
                    </svg>
                    @foreach ([[1, 1], [1, 2], [2, 1], [2, 2]] as [$row, $col])
                        <span class="cp-fo-tack cp-fo-tack-{{ $row }}{{ $col }}"></span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-fo-ballad" aria-labelledby="outcomes-h">
        <p class="cp-fo-kick"><span aria-hidden="true">✶</span> {{ $page['kicks']['ballad'] }} <span aria-hidden="true">✶</span></p>
        <h2 id="outcomes-h" class="cp-fo-title">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-fo-stanzas">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-fo-num" aria-hidden="true">{{ $roman[$loop->index] }}.</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-fo-modesec" aria-labelledby="modes-h">
        <div class="cp-pair">
            <div>
                <p class="cp-fo-kick"><span aria-hidden="true">✶</span> {{ $page['kicks']['modes'] }}</p>
                <h2 id="modes-h" class="cp-fo-title">{{ $page['titles']['modes'] }}</h2>
                <p class="s-lede">{{ $page['modes']['lead'] }}</p>
            </div>
            <div class="cp-pair-figs">
                @foreach ($modes as $kind => [$steps, $changed, $usual])
                    <figure class="cp-fo-mode">
                        <svg viewBox="0 0 220 120" aria-hidden="true">
                            <path d="M8 112 H212" class="cp-fo-base" />
                            <circle cx="{{ 18 + $changed * 26 }}" cy="{{ 108 - $usual * 8 }}" r="7" class="cp-fo-ghost" />
                            @foreach ($steps as $step)
                                <circle cx="{{ 18 + $loop->index * 26 }}" cy="{{ 108 - $step * 8 }}" r="7" class="{{ $loop->index === $changed ? 'cp-fo-changed' : 'cp-fo-step' }}" />
                            @endforeach
                        </svg>
                        <figcaption><b>{{ $page['modes'][$kind][0] }}</b> {{ $page['modes'][$kind][1] }}</figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    <section class="s-ink cp-fo-grace" aria-labelledby="ornaments-h">
        <div class="s-wrap s-sec">
            <p class="cp-fo-kick"><span aria-hidden="true">✶</span> {{ $page['kicks']['grace'] }}</p>
            <h2 id="ornaments-h" class="cp-fo-title">{{ $page['titles']['ornaments'] }}</h2>
            <p class="s-lede">{{ $page['ornaments']['lead'] }}</p>
            <div class="cp-fo-orns">
                <figure class="cp-fo-orn">
                    <svg viewBox="0 0 200 70" aria-hidden="true"><rect x="20" y="30" width="80" height="12" rx="6" class="cp-fo-blk" /><rect x="104" y="18" width="8" height="12" rx="4" class="cp-fo-blk cp-fo-orn-hi" /><rect x="116" y="30" width="72" height="12" rx="6" class="cp-fo-blk" /></svg>
                    <figcaption><b>{{ $page['ornaments']['flick'][0] }}</b> {{ $page['ornaments']['flick'][1] }}</figcaption>
                </figure>
                <figure class="cp-fo-orn">
                    <svg viewBox="0 0 200 70" aria-hidden="true"><rect x="20" y="30" width="44" height="12" rx="6" class="cp-fo-blk" /><rect x="68" y="16" width="20" height="12" rx="6" class="cp-fo-blk cp-fo-orn-hi" /><rect x="92" y="30" width="20" height="12" rx="6" class="cp-fo-blk cp-fo-orn-hi" /><rect x="116" y="44" width="20" height="12" rx="6" class="cp-fo-blk cp-fo-orn-hi" /><rect x="140" y="30" width="48" height="12" rx="6" class="cp-fo-blk" /></svg>
                    <figcaption><b>{{ $page['ornaments']['turn'][0] }}</b> {{ $page['ornaments']['turn'][1] }}</figcaption>
                </figure>
            </div>
        </div>
    </section>

    @if ($outline !== null)
        <section class="s-wrap s-sec cp-fo-verses" aria-labelledby="units-h">
            <p class="cp-fo-kick"><span aria-hidden="true">✶</span> {{ $page['kicks']['verses'] }}</p>
            <div class="cp-head">
                <h2 id="units-h" class="cp-fo-title">{{ $page['titles']['units'] }}</h2>
                <p class="cp-head-note">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-fo-sheet">
                @foreach ($outline['units'] as $unit)
                    @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                    @php($verse = str_replace(':n', $roman[$loop->index], $page['verse_n']))
                    <li class="cp-fo-col">
                        <p class="cp-fo-col-n"><x-site.skin :game="$stage" :serious="$verse" /></p>
                        <h3>{{ $unit['title'] }}</h3>
                        @isset($page['units_say'][$loop->index])
                            <p class="cp-fo-col-say">{{ $page['units_say'][$loop->index] }}</p>
                        @endisset
                        <ol class="cp-fo-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li>{{ $lesson }}@if ($loop->parent->first && $loop->first) <span class="s-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="s-wrap s-sec cp-fo-strike" aria-labelledby="try-h">
        <p class="cp-fo-kick"><span aria-hidden="true">✶</span> {{ $page['kicks']['strike'] }}</p>
        <h2 id="try-h" class="cp-fo-title">{{ $page['titles']['try'] }}</h2>
        <p class="s-lede">{{ $page['try']['lead'] }}</p>
        <ol class="cp-fo-steps">
            @foreach ((array) $page['try']['steps'] as $step)
                <li @class(['cp-fo-step-first' => $loop->first])><span class="cp-fo-num" aria-hidden="true">{{ $roman[$loop->index] }}.</span><p>@if ($loop->first)<b class="cp-fo-start">{{ $page['try_first'] }}</b> @endif{{ $step }}</p></li>
            @endforeach
        </ol>
        <p class="cp-fig-note">{{ $page['try']['after'] }}</p>
    </section>

    @include('site.courses._inside')

    <section class="s-wrap s-sec cp-fo-wallsec" aria-labelledby="facts-h">
        <p class="cp-fo-kick"><span aria-hidden="true">✶</span> {{ $page['kicks']['wall'] }}</p>
        <h2 id="facts-h" class="cp-fo-title">{{ $page['titles']['facts'] }}</h2>
        <ul class="cp-fo-clippings">
            @foreach ((array) $page['facts'] as [$text, $source, $url])
                <li class="cp-fo-clip">
                    <span class="cp-fo-tape" aria-hidden="true"></span>
                    <p class="cp-fo-clip-text">{{ $text }}</p>
                    <p class="cp-fo-clip-src">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $source }}</a>@else{{ $source }}@endif</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap s-sec cp-fo-singer" aria-labelledby="singer-h">
        <p class="cp-fo-kick"><span aria-hidden="true">✶</span> {{ $page['kicks']['singer'] }}</p>
        <h2 id="singer-h" class="cp-fo-title">{{ $page['titles']['singer'] }}</h2>
        <div class="cp-fo-singer-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul class="s-ticks">@foreach ((array) $page['who'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul></div>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="s-ticks">@foreach ((array) $page['need'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul>
                <h3 class="cp-fo-gap">{{ $page['titles']['safety'] }}</h3><ul class="s-ticks">@foreach ((array) $page['safety'] as $line)<li><x-site.icon name="info" size="18" />{{ $line }}</li>@endforeach</ul></div>
            <div class="cp-fo-songs">
                <h3>{{ __('pages.site.course_songs') }}</h3>
                <ul>
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-fo-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-fo-kick"><span aria-hidden="true">✶</span> {{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-fo-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-fo-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-fo-close-in">
            <svg viewBox="0 0 220 40" class="cp-fo-close-orn" aria-hidden="true"><path d="M10 20 H90 M130 20 H210" class="cp-fo-orn-rule" /><path d="M110 8 L122 20 L110 32 L98 20 Z" class="cp-fo-diamond" /></svg>
            <p class="cp-fo-kick">{{ $page['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-fo-title">{{ $page['titles']['join'] }}</h2>
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
            <h3 class="cp-fo-kick cp-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
</main>
