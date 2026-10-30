{{-- Vibrato (/courses/vibrato), its own page: still water at dawn (docs/course-pages-polish.md). A
     straight note is a still lake, and vibrato is the ripple that comes by itself. Serious skin: a
     lavender sky, a low sun, mist on far hills, the sun's shimmer on the water and reeds at its edge;
     light, thin headlines. Game skin: a synthwave lake, its sun striped and its water a grid. The
     voice is drawn on the water's surface, straight, then blooming into an even wave, with ripples
     widening from where it begins. Then: still water (what you'll be able to do), three surfaces
     (straight, a wave, a wobble), calm water (never force it), stepping stones (the units), wade in
     (try this), what the app hears, ripples (facts), on the bank (who, what you need, the songs),
     questions and let it come. The art is wordless. Words: lang/en/site.php courses.pages.vibrato. --}}
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
@php($stones = [[70, 82, 34], [200, 64, 30], [330, 86, 36], [460, 66, 32]])
<main class="cp cp-vibrato">
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
                        <clipPath id="vi-frame"><rect width="600" height="380" rx="28" /></clipPath>
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
                </svg>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-vi-still" aria-labelledby="outcomes-h">
        <p class="cp-vi-kick">{{ $page['kicks']['still'] }}</p>
        <h2 id="outcomes-h" class="cp-vi-title">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-vi-aims">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-vi-aim-n" aria-hidden="true">0{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-vi-surfaces" aria-labelledby="kinds-h">
        <p class="cp-vi-kick">{{ $page['kicks']['surfaces'] }}</p>
        <h2 id="kinds-h" class="cp-vi-title">{{ $page['titles']['kinds'] }}</h2>
        <div class="cp-vi-pools">
            @foreach ((array) $page['kinds'] as [$kind, $what])
                <figure class="cp-vi-pool cp-vi-pool-{{ $loop->iteration }}">
                    <svg viewBox="0 0 160 64" aria-hidden="true"><polyline points="{{ $kinds[$loop->index] }}" class="cp-vi-line" /></svg>
                    <figcaption><b>{{ $kind }}</b> {{ $what }}</figcaption>
                </figure>
            @endforeach
        </div>
        <p class="cp-fig-note">{{ $page['kinds_body'] }}</p>
    </section>

    <section class="s-ink cp-vi-calm" aria-labelledby="gentle-h">
        <svg class="cp-vi-calm-rings" viewBox="0 0 400 200" aria-hidden="true"><ellipse cx="200" cy="100" rx="60" ry="14" /><ellipse cx="200" cy="100" rx="120" ry="28" /><ellipse cx="200" cy="100" rx="190" ry="44" /></svg>
        <div class="s-wrap s-sec cp-vi-calm-grid">
            <div>
                <p class="cp-vi-kick">{{ $page['kicks']['calm'] }}</p>
                <h2 id="gentle-h" class="cp-vi-title">{{ $page['titles']['gentle'] }}</h2>
                <p class="s-lede">{{ $page['gentle']['lead'] }}</p>
            </div>
            <ul class="cp-vi-calm-points">
                @foreach ((array) $page['gentle']['points'] as $point)
                    <li><span class="cp-vi-drop" aria-hidden="true"></span>{{ $point }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    @if ($outline !== null)
        <section class="s-wrap s-sec cp-vi-stonesec" aria-labelledby="units-h">
            <p class="cp-vi-kick">{{ $page['kicks']['stones'] }}</p>
            <div class="cp-head">
                <h2 id="units-h" class="cp-vi-title">{{ $page['titles']['units'] }}</h2>
                <p class="cp-head-note">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <svg viewBox="0 0 540 130" class="cp-vi-stones" aria-hidden="true">
                <path d="M0 96 Q 135 80 270 96 T 540 96" class="cp-vi-stone-water" />
                @foreach ($stones as [$x, $y, $r])
                    <ellipse cx="{{ $x }}" cy="{{ $y + 18 }}" rx="{{ $r + 18 }}" ry="9" class="cp-vi-stone-ring" />
                    <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="{{ $r }}" ry="{{ $r * .62 }}" class="cp-vi-stone" />
                    <text x="{{ $x }}" y="{{ $y + 7 }}" class="cp-vi-stone-n">{{ $loop->iteration }}</text>
                @endforeach
            </svg>
            <ol class="cp-vi-steps">
                @foreach ($outline['units'] as $unit)
                    @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                    @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                    <li class="cp-vi-step">
                        <p class="cp-vi-step-n"><x-site.skin :game="$stage" :serious="$unitName" /></p>
                        <h3>{{ $unit['title'] }}</h3>
                        @isset($page['units_say'][$loop->index])
                            <p class="cp-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                        @endisset
                        <ol class="cp-vi-lessons">
                            @foreach ($unit['lessons'] as $lesson)
                                <li>{{ $lesson }}@if ($loop->parent->first && $loop->first) <span class="s-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="s-wrap s-sec cp-vi-wade" aria-labelledby="try-h">
        <p class="cp-vi-kick">{{ $page['kicks']['wade'] }}</p>
        <h2 id="try-h" class="cp-vi-title">{{ $page['titles']['try'] }}</h2>
        <p class="s-lede">{{ $page['try']['lead'] }}</p>
        <ol class="cp-vi-try">
            @foreach ((array) $page['try']['steps'] as $step)
                <li @class(['cp-vi-try-first' => $loop->first])><span class="cp-vi-ring-n" aria-hidden="true"><b>{{ $loop->iteration }}</b></span><p>@if ($loop->first)<b class="cp-vi-start">{{ $page['try_first'] }}</b> @endif{{ $step }}</p></li>
            @endforeach
        </ol>
        <p class="cp-fig-note">{{ $page['try']['after'] }}</p>
    </section>

    @include('site.courses._inside')

    <section class="s-wrap s-sec cp-vi-facts" aria-labelledby="facts-h">
        <p class="cp-vi-kick">{{ $page['kicks']['ripples'] }}</p>
        <h2 id="facts-h" class="cp-vi-title">{{ $page['titles']['facts'] }}</h2>
        <ul class="cp-vi-cards">
            @foreach ((array) $page['facts'] as [$text, $source, $url])
                <li class="cp-vi-card">
                    <svg viewBox="0 0 80 30" class="cp-vi-card-rings" aria-hidden="true"><ellipse cx="40" cy="15" rx="10" ry="3" /><ellipse cx="40" cy="15" rx="24" ry="7" /><ellipse cx="40" cy="15" rx="38" ry="12" /></svg>
                    <p class="cp-vi-card-text">{{ $text }}</p>
                    <p class="cp-vi-card-src">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $source }}</a>@else{{ $source }}@endif</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap s-sec cp-vi-bank" aria-labelledby="bank-h">
        <p class="cp-vi-kick">{{ $page['kicks']['bank'] }}</p>
        <h2 id="bank-h" class="cp-vi-title">{{ $page['titles']['bank'] }}</h2>
        <div class="cp-vi-bank-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul class="s-ticks">@foreach ((array) $page['who'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul></div>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="s-ticks">@foreach ((array) $page['need'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul></div>
            <div class="cp-vi-songs">
                <h3>{{ __('pages.site.course_songs') }}</h3>
                <ul>
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
                <h3>{{ $page['titles']['safety'] }}</h3>
                <ul class="cp-vi-safety">@foreach ((array) $page['safety'] as $line)<li>{{ $line }}</li>@endforeach</ul>
            </div>
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
            <p class="s-lede">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            @if ($open)
                <p class="cp-join-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <div class="cp-vi-close-btns">
                <svg viewBox="0 0 400 120" class="cp-vi-close-rings" aria-hidden="true"><ellipse cx="200" cy="60" rx="110" ry="26" /><ellipse cx="200" cy="60" rx="160" ry="38" /><ellipse cx="200" cy="60" rx="196" ry="50" /></svg>
                <p class="cp-join-btns">
                    @if ($open)
                        <a href="{{ \App\Support\Http\Hosts::app('/learn/c/'.$page['app']) }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                        <a href="/pricing" class="btn-secondary btn-lg">{{ __('pages.site.course_plus') }}</a>
                    @else
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                        <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                    @endif
                </p>
            </div>
            <h3 class="cp-vi-kick cp-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
</main>
