{{-- Vibrato (/courses/vibrato), its own page, after Hillel's design (shots-review/designs/hillel/
     vibrato-improve, 2026-10-31): still water at dawn, calm and light. A straight note is a still lake,
     and vibrato is the ripple that comes by itself. Serious skin: a lavender sky, a low sun on the water
     and the voice drawn on it, straight then blooming into a wave; thin headlines, white cards. Game
     skin: a synthwave lake, its sun striped and its water a grid. Then: three surfaces (straight, a wave,
     a wobble, each playable: the featured demo, js/public/vibrato.js), by the end (the outcomes),
     stepping stones (the units, from the course file), wade in (lesson 1's steps beside the live deck,
     your own wave from two sliders), under the surface (what the app hears, and the cited facts), on the
     bank (who, what you need, the songs, and never force it), questions and let it come. No drawn
     people. Words: lang/en/site.php courses.pages.vibrato. --}}
@php($bloom = '40,282 ')
@php($kinds = ['8,32 152,32', '', ''])
@foreach (range(0, 300, 4) as $t)
    @php($bloom .= (220 + $t).','.round(282 - min(1, $t / 130) * 18 * sin(M_PI * $t / 20), 1).' ')
@endforeach
@foreach (range(0, 144, 2) as $t)
    @php($swing = 8 + 12 * abs(sin($t / 38)))
    @php($turn = M_PI * $t / 23 + 0.6 * sin($t / 17))
    @php($kinds[1] .= (8 + $t).','.round(32 - 10 * sin(M_PI * $t / 10), 1).' ')
    @php($kinds[2] .= (8 + $t).','.round(32 - $swing * sin($turn), 1).' ')
@endforeach
@php($grow = '')
@foreach (range(0, 900, 6) as $t)
    @php($swell = min(1, max(0, $t - 160) / 700))
    @php($grow .= ($t ? 'L' : 'M').$t.' '.round(20 - $swell * 14 * sin(M_PI * $t / 18), 1))
@endforeach
@php($demo = $page['demo'])
@php($tryUrl = \App\Support\Http\Hosts::app('/learn/c/'.$page['app']))
<main class="cp cp-vibrato" data-vi-demo
    data-straight="{{ $demo['say_straight'] }}" data-wave="{{ $demo['say_wave'] }}" data-wobble="{{ $demo['say_wobble'] }}" data-own="{{ $demo['say_own'] }}"
    data-after="{{ $demo['after'] }}" data-rate-out="{{ $demo['rate_out'] }}" data-width-out="{{ $demo['width_out'] }}">
    <section class="cp-hero cp-vi-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-vi-kick cp-eyebrow">{{ $page['eyebrow'] }}</p>
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
            <div class="cp-vi-art" aria-hidden="true">
                <svg viewBox="0 0 600 380" class="cp-vi-svg">
                    <defs>
                        <linearGradient id="vi-sky" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="cp-vi-sky-1" /><stop offset="1" class="cp-vi-sky-2" /></linearGradient>
                        <linearGradient id="vi-water" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="cp-vi-water-1" /><stop offset="1" class="cp-vi-water-2" /></linearGradient>
                        <clipPath id="vi-frame"><rect width="600" height="380" rx="28" class="cp-vi-frame" /></clipPath>
                        <clipPath id="vi-sun-cut"><rect x="360" y="90" width="140" height="122" /></clipPath>
                    </defs>
                    <g clip-path="url(#vi-frame)">
                        <rect width="600" height="212" fill="url(#vi-sky)" />
                        <g class="cp-vi-sun" clip-path="url(#vi-sun-cut)">
                            <circle cx="430" cy="196" r="62" />
                            <path d="M360 176 H500 M360 186 H500 M360 195 H500 M360 203 H500" class="cp-vi-sun-cuts" />
                        </g>
                        <path d="M0 212 L80 188 L160 202 L250 176 L340 200 L420 182 L520 198 L600 184 V212 Z" class="cp-vi-hills" />
                        <rect x="0" y="194" width="600" height="20" class="cp-vi-mist" />
                        <rect y="212" width="600" height="168" fill="url(#vi-water)" />
                        <g class="cp-vi-grid"><path d="M0 230 H600 M0 252 H600 M0 282 H600 M0 322 H600 M0 372 H600" /><path d="M300 212 L-60 380 M300 212 L60 380 M300 212 L180 380 M300 212 L300 380 M300 212 L420 380 M300 212 L540 380 M300 212 L660 380" /></g>
                        <g class="cp-vi-shimmer">
                            <path d="M402 224 H458" /><path d="M392 236 H468" /><path d="M408 250 H452" /><path d="M396 264 H464" />
                        </g>
                        <g class="cp-vi-ripples">
                            <ellipse cx="220" cy="282" rx="34" ry="7" /><ellipse cx="220" cy="282" rx="70" ry="14" /><ellipse cx="220" cy="282" rx="108" ry="22" />
                        </g>
                        <polyline points="{{ $bloom }}" class="cp-vi-line" />
                        <polyline points="{{ $bloom }}" class="cp-vi-pulse" />
                        <circle cx="220" cy="282" r="7" class="cp-vi-bloom" />
                        <g class="cp-vi-reeds">
                            <path d="M22 380 Q26 300 18 250 M36 380 Q38 316 44 268 M50 380 Q48 330 58 296" />
                            <path d="M560 380 Q556 316 566 262 M578 380 Q580 330 572 288" />
                            <ellipse cx="18" cy="248" rx="4" ry="14" /><ellipse cx="44" cy="266" rx="4" ry="13" /><ellipse cx="566" cy="260" rx="4" ry="14" />
                        </g>
                    </g>
                    <text x="34" y="360" class="cp-vi-art-tag">{{ $page['art'][0] }}</text>
                    <text x="566" y="360" class="cp-vi-art-tag cp-vi-art-tag-end">{{ $page['art'][1] }}</text>
                </svg>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-vi-surfaces" aria-labelledby="kinds-h">
        <div class="cp-vi-surfaces-head">
            <p class="cp-vi-kick">{{ $page['kicks']['surfaces'] }}</p>
            <h2 id="kinds-h" class="cp-vi-title">{{ $page['titles']['kinds'] }}</h2>
            <p class="cp-vi-body">{{ $page['kinds_body'] }}</p>
            <p class="cp-vi-say" role="status" data-vi-say>{{ $demo['ready'] }}</p>
        </div>
        <ul class="cp-vi-pools">
            @foreach ((array) $page['kinds'] as [$kind, $what])
                @php($key = ['straight', 'wave', 'wobble'][$loop->index])
                <li class="cp-vi-pool cp-vi-pool-{{ $loop->iteration }}">
                    <svg viewBox="0 0 160 64" aria-hidden="true"><polyline points="{{ $kinds[$loop->index] }}" class="cp-vi-line" /></svg>
                    <div>
                        <h3>{{ $kind }}</h3>
                        <p>{{ $what }}</p>
                        <button type="button" class="cp-vi-hear" data-vi-play="{{ $key }}" data-play="{{ $demo['hear'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false" aria-label="{{ $demo['hear'] }}: {{ $kind }}"><span class="cp-vi-hear-icon" aria-hidden="true"></span><span data-label>{{ $demo['hear'] }}</span></button>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap s-sec cp-vi-still" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="cp-vi-kick">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-vi-aims">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-vi-aim-n" aria-hidden="true">0{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    @if ($outline !== null)
        <section class="cp-vi-stonesec" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-vi-kick">{{ $page['kicks']['stones'] }}</p>
                <div class="cp-vi-head">
                    <h2 id="units-h" class="cp-vi-title">{{ $page['titles']['units'] }}</h2>
                    <p class="cp-vi-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <svg viewBox="0 0 900 40" preserveAspectRatio="none" class="cp-vi-grow" aria-hidden="true"><path d="{{ $grow }}" /></svg>
                <ol class="cp-vi-steps">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-vi-step">
                            <p class="cp-vi-step-n"><span class="cp-vi-step-dot" aria-hidden="true">{{ $loop->iteration }}</span><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-vi-step-say">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            <ol class="cp-vi-lessons">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li><span>{{ $lesson }}</span>@if ($loop->parent->first && $loop->first)<span class="cp-vi-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                                @endforeach
                            </ol>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="s-wrap s-sec cp-vi-wade" aria-labelledby="try-h">
        <div>
            <p class="cp-vi-kick">{{ $demo['wade_kick'] }}</p>
            <h2 id="try-h" class="cp-vi-title">{{ $page['titles']['try'] }}</h2>
            <p class="cp-vi-body">{{ $page['try']['lead'] }}</p>
            <ol class="cp-vi-try">
                @foreach ((array) $page['try']['steps'] as $step)
                    <li @class(['cp-vi-try-first' => $loop->first])><span class="cp-vi-try-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $step }}</p></li>
                @endforeach
            </ol>
        </div>
        <div>
            <div class="cp-vi-deck">
                <p class="cp-vi-deck-bar"><span class="cp-vi-live"><span class="cp-vi-rec" aria-hidden="true"></span>{{ $demo['kick'] }}</span><span>{{ $demo['lesson'] }}</span></p>
                <h3 class="cp-vi-deck-h">{{ $demo['own'] }}</h3>
                <svg viewBox="0 0 600 220" preserveAspectRatio="none" class="cp-vi-deck-wave" aria-hidden="true">
                    <path d="M20 20 H580 M20 200 H580" class="cp-vi-deck-staff" />
                    <path d="M20 110 H580" class="cp-vi-deck-staff cp-vi-deck-centre" />
                    <path d="M20 110 H580" class="cp-vi-deck-trace" data-vi-trace />
                </svg>
                <div class="cp-vi-sliders">
                    <label class="cp-vi-slider"><span>{{ $demo['rate'] }} <output data-vi-rate-out></output></span><input type="range" min="0" max="8" step="0.5" value="6" data-vi-rate></label>
                    <label class="cp-vi-slider"><span>{{ $demo['width'] }} <output data-vi-width-out></output></span><input type="range" min="0" max="150" step="5" value="50" data-vi-width></label>
                </div>
                <p class="cp-vi-deck-btns"><button type="button" class="cp-vi-hear cp-vi-hear-big" data-vi-play="own" data-play="{{ $demo['own_play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-vi-hear-icon" aria-hidden="true"></span><span data-label>{{ $demo['own_play'] }}</span></button></p>
                <p class="cp-vi-say cp-vi-deck-say" aria-hidden="true" data-vi-say></p>
            </div>
            <p class="cp-vi-note">{{ $page['try']['after'] }}</p>
        </div>
    </section>

    <section class="cp-vi-under" aria-labelledby="inside-h">
        <div class="s-wrap s-sec cp-vi-under-grid">
            <div>
                <p class="cp-vi-kick">{{ $page['kicks']['under'] }}</p>
                <h2 id="inside-h" class="cp-vi-title">{{ $page['titles']['inside'] }}</h2>
                <p class="cp-vi-body">{{ $page['measures'] }}</p>
                <p class="cp-vi-note"><x-site.say :text="$page['scoring']" /></p>
            </div>
            <ul class="cp-vi-cards" aria-label="{{ $page['titles']['facts'] }}">
                @foreach ((array) $page['facts'] as [$text, $source, $url])
                    <li class="cp-vi-card">
                        <p class="cp-vi-card-text">{{ $text }}</p>
                        <p class="cp-vi-card-src">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $source }}</a>@else{{ $source }}@endif</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="s-wrap s-sec cp-vi-bank" aria-labelledby="bank-h">
        <p class="cp-vi-kick">{{ $page['kicks']['bank'] }}</p>
        <h2 id="bank-h" class="cp-vi-title">{{ $page['titles']['bank'] }}</h2>
        <div class="cp-vi-bank-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul class="cp-vi-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="cp-vi-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div>
                <h3>{{ __('pages.site.course_songs') }}</h3>
                <ul class="cp-vi-songs">
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b><span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="cp-vi-calm">
            <div>
                <h3 class="cp-vi-kick">{{ $page['titles']['safety'] }}</h3>
                <p class="cp-vi-calm-h">{{ $page['titles']['gentle'] }}</p>
                <p class="cp-vi-body">{{ $page['gentle']['lead'] }}</p>
            </div>
            <ul class="cp-vi-calm-points">
                @php($calm = (array) $page['safety'])
                @php(array_splice($calm, 1, 0, [$page['gentle']['points'][2]]))
                @foreach ($calm as $point)
                    <li>{{ $point }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="s-wrap s-sec cp-vi-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-vi-kick">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-vi-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-vi-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-vi-close-in">
            <p class="cp-vi-kick">{{ $page['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-vi-title">{{ $page['titles']['join'] }}</h2>
            <p class="cp-vi-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-join-btns">
                @if ($open)
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="/pricing" class="btn-secondary btn-lg">{{ __('pages.site.course_plus') }}</a>
                @else
                    <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @endif
            </p>
            @if ($open)
                <p class="cp-vi-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-vi-kick cp-vi-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
    <script type="module" src="/js/public/vibrato.js"></script>
</main>
