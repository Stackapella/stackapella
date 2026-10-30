{{-- Barbershop and tags (/courses/barbershop), its own page: a barbershop quartet's show bill
     (docs/course-pages-polish.md). Serious skin: a cream bill in red and navy, set in a serif like old
     wood type, with ornamental rules and stars. Game skin: the same quartet on a neon marquee stage.
     The hero is the scene: four singers in boaters under a shop's arched window, a turning pole, tiles
     running to a vanishing point and a lamp's glow, with the chord they ring rising above them. Then,
     as a show: tonight's bill (what you'll be able to do), the ring (a tuning needle and the two
     numbers), the four chairs (a singer each, and their lines through a chord), the set list (the
     units as one running order), a warm-up (find the ring), backstage (what the app hears), three
     trade cards from the shop, before you sing, the fine print (questions) and the encore. The art's
     only words are the seats' names and the needle's three marks. Words: lang/en/site.php
     courses.pages.barbershop. --}}
@php($quartet = [[4, 140, 1.22], [3, 228, 1.14], [2, 314, 1.19], [1, 402, 1.1]])
@php($bulbs = [])
@foreach (range(0, 12) as $i)
    @php($angle = M_PI - $i * M_PI / 12)
    @php($bulbs[] = [round(300 + 166 * cos($angle), 1), round(178 - 166 * sin($angle), 1)])
@endforeach
@php($ticks = [])
@foreach (range(-50, 50, 10) as $cents)
    @php($angle = deg2rad(90 - $cents * 1.6))
    @php($ticks[] = [round(150 + 112 * cos($angle), 1), round(150 - 112 * sin($angle), 1), round(150 + 126 * cos($angle), 1), round(150 - 126 * sin($angle), 1), $cents])
@endforeach
@php($voicing = [1 => [[30, 36], [100, 30], [170, 38], [240, 24], [310, 34], [380, 28]], 2 => [[30, 76], [100, 66], [170, 84], [240, 62], [310, 78], [380, 70]], 3 => [[30, 64], [100, 92], [170, 72], [240, 100], [310, 66], [380, 104]], 4 => [[30, 180], [100, 160], [170, 188], [240, 152], [310, 184], [380, 170]]])
<main class="cp cp-barbershop">
    <svg width="0" height="0" class="cp-bb-defs" aria-hidden="true" focusable="false">
        <defs>
            <symbol id="bb-singer" viewBox="0 0 80 210">
                <path d="M12 210 L16 104 Q18 84 40 80 Q62 84 64 104 L68 210 Z" fill="currentColor" />
                <path d="M30 86 L40 120 L50 86 Q40 80 30 86 Z" class="cp-bb-shirt" />
                <path d="M29 88 L40 94 L29 100 Z M51 88 L40 94 L51 100 Z" class="cp-bb-tie" />
                <circle cx="40" cy="94" r="3" class="cp-bb-tie" />
                <rect x="34" y="66" width="12" height="16" rx="4" class="cp-bb-face" />
                <ellipse cx="40" cy="54" rx="16" ry="18" class="cp-bb-face" />
                <ellipse cx="40" cy="61" rx="3.2" ry="4.4" class="cp-bb-mouth" />
                <ellipse cx="40" cy="38" rx="31" ry="6.5" class="cp-bb-hat" />
                <rect x="23" y="20" width="34" height="18" rx="3" class="cp-bb-hat" />
                <rect x="23" y="30" width="34" height="5" fill="currentColor" />
            </symbol>
        </defs>
    </svg>

    <section class="cp-hero cp-bb-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-bb-kick cp-eyebrow"><span aria-hidden="true">★</span> {{ $page['eyebrow'] }} <span aria-hidden="true">★</span></p>
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
            <div class="cp-bb-art" aria-hidden="true">
                <svg viewBox="0 0 600 470" class="cp-bb-svg">
                    <defs>
                        <pattern id="bb-wall" width="28" height="28" patternUnits="userSpaceOnUse"><rect width="28" height="28" class="cp-bb-wall" /><rect width="9" height="28" class="cp-bb-wall-stripe" /></pattern>
                        <pattern id="bb-stripes" width="120" height="48" patternUnits="userSpaceOnUse" patternTransform="rotate(-32)">
                            <g class="cp-bb-scroll">
                                <rect y="-48" width="120" height="144" fill="var(--bb-white)" />
                                @foreach ([-48, 0, 48] as $y)
                                    <rect y="{{ $y }}" width="120" height="17" fill="var(--bb-red)" />
                                    <rect y="{{ $y + 25 }}" width="120" height="11" fill="var(--bb-blue)" />
                                @endforeach
                            </g>
                        </pattern>
                        <linearGradient id="bb-glass" x1="0" x2="1">
                            <stop offset="0" stop-color="#000" stop-opacity=".22" />
                            <stop offset=".28" stop-color="#fff" stop-opacity=".5" />
                            <stop offset=".5" stop-color="#fff" stop-opacity=".06" />
                            <stop offset="1" stop-color="#000" stop-opacity=".3" />
                        </linearGradient>
                        <linearGradient id="bb-window" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0" class="cp-bb-sky-1" />
                            <stop offset="1" class="cp-bb-sky-2" />
                        </linearGradient>
                        <radialGradient id="bb-lamp" cx=".5" cy=".42" r=".55">
                            <stop offset="0" class="cp-bb-lamp-1" />
                            <stop offset="1" class="cp-bb-lamp-2" />
                        </radialGradient>
                        <radialGradient id="bb-chord">
                            <stop offset="0" stop-color="#fff" />
                            <stop offset=".45" class="cp-bb-chord-1" />
                            <stop offset="1" class="cp-bb-chord-2" />
                        </radialGradient>
                        <clipPath id="bb-floor"><rect x="0" y="352" width="600" height="118" /></clipPath>
                    </defs>
                    <rect width="600" height="470" fill="url(#bb-wall)" />
                    <path d="M134 352 V178 A166 166 0 0 1 466 178 V352 Z" class="cp-bb-frame" />
                    <path d="M150 352 V178 A150 150 0 0 1 450 178 V352 Z" fill="url(#bb-window)" />
                    <path d="M250 352 V60 M350 352 V60 M150 236 H450" class="cp-bb-mullion" />
                    @foreach ($bulbs as [$x, $y])
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="5" class="cp-bb-bulb" />
                    @endforeach
                    <rect width="600" height="470" fill="url(#bb-lamp)" class="cp-bb-glow" />
                    <rect x="0" y="352" width="600" height="118" class="cp-bb-floor" />
                    <g clip-path="url(#bb-floor)" class="cp-bb-tiles">
                        <path d="M0 366 H600 M0 386 H600 M0 414 H600 M0 452 H600" />
                        @foreach (range(-6, 6) as $n)
                            <path d="M300 300 L{{ 300 + $n * 110 }} 470" />
                        @endforeach
                    </g>
                    <g class="cp-bb-rings">
                        <path d="M190 132 Q232 104 284 92" />
                        <path d="M272 138 Q288 112 296 100" />
                        <path d="M362 134 Q316 110 304 100" />
                        <path d="M450 142 Q376 104 316 92" />
                    </g>
                    <circle cx="300" cy="82" r="34" fill="url(#bb-chord)" class="cp-bb-chord" />
                    <path d="M300 52 L305 77 L330 82 L305 87 L300 112 L295 87 L270 82 L295 77 Z" class="cp-bb-spark" />
                    @foreach ($quartet as [$v, $x, $s])
                        <use href="#bb-singer" x="{{ $x }}" y="{{ 358 - 210 * $s }}" width="{{ 80 * $s }}" height="{{ 210 * $s }}" class="cp-bb-singer s-v{{ $v }}" />
                    @endforeach
                    <ellipse cx="300" cy="364" rx="190" ry="10" class="cp-bb-shadow" />
                    <g transform="translate(508 78) scale(.62)">
                        <circle cx="48" cy="-14" r="15" class="cp-bb-cap" />
                        <rect x="12" y="0" width="72" height="24" rx="7" class="cp-bb-cap" />
                        <rect x="20" y="24" width="56" height="356" rx="28" fill="url(#bb-stripes)" />
                        <rect x="20" y="24" width="56" height="356" rx="28" fill="url(#bb-glass)" />
                        <rect x="12" y="380" width="72" height="24" rx="7" class="cp-bb-cap" />
                    </g>
                </svg>
            </div>
        </div>
    </section>
    <div class="cp-bb-band" aria-hidden="true"></div>

    <section class="s-wrap s-sec cp-bb-billsec" aria-labelledby="outcomes-h">
        <div class="cp-bb-bill">
            <p class="cp-bb-kick"><span aria-hidden="true">★</span> {{ $page['kicks']['bill'] }} <span aria-hidden="true">★</span></p>
            <h2 id="outcomes-h" class="cp-bb-title">{{ $page['titles']['outcomes'] }}</h2>
            <ol class="cp-bb-acts">
                @foreach ((array) $page['outcomes'] as $outcome)
                    <li><span class="cp-bb-act-n">{{ str_replace(':n', (string) $loop->iteration, $page['act_n']) }}</span><span class="cp-bb-act-dots" aria-hidden="true"></span><span class="cp-bb-act-text">{{ $outcome }}</span></li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="s-ink cp-bb-ringsec" aria-labelledby="ring-h">
        <div class="s-wrap s-sec">
            <div class="cp-bb-ring-grid">
                <div>
                    <p class="cp-bb-kick">{{ $page['kicks']['ring'] }}</p>
                    <h2 id="ring-h" class="cp-bb-title">{{ $page['titles']['ring'] }}</h2>
                    <p class="s-lede">{{ $page['ring']['lead'] }}</p>
                </div>
                <figure class="cp-bb-meter">
                    <svg viewBox="0 0 300 170" aria-hidden="true">
                        <path d="M38 150 A112 112 0 0 1 262 150" class="cp-bb-dial" />
                        <path d="M140.2 38.4 A112 112 0 0 1 159.8 38.4" class="cp-bb-dial-ring" />
                        @foreach ($ticks as [$x1, $y1, $x2, $y2, $cents])
                            <path d="M{{ $x1 }} {{ $y1 }} L{{ $x2 }} {{ $y2 }}" class="{{ $cents === 0 ? 'cp-bb-tick cp-bb-tick-0' : 'cp-bb-tick' }}" />
                        @endforeach
                        <text x="40" y="168" class="cp-bb-meter-l">{{ $page['ring']['meter'][0] }}</text>
                        <text x="150" y="22" class="cp-bb-meter-l cp-bb-meter-ring">{{ $page['ring']['meter'][1] }}</text>
                        <text x="260" y="168" class="cp-bb-meter-l cp-bb-meter-r">{{ $page['ring']['meter'][2] }}</text>
                        <g class="cp-bb-needle"><path d="M150 150 L150 46" /><circle cx="150" cy="150" r="9" /></g>
                    </svg>
                </figure>
            </div>
            <ul class="cp-bb-numbers">
                @foreach ((array) $page['ring']['numbers'] as [$number, $unit, $what])
                    <li><b>{{ $number }}<small>{{ $unit }}</small></b><span>{{ $what }}</span></li>
                @endforeach
            </ul>
            <p class="cp-bb-ring-body">{{ $page['ring']['body'] }}</p>
            <figure class="cp-waves">
                <div class="cp-wave cp-wave-beat">
                    <svg viewBox="0 0 480 80" aria-hidden="true" preserveAspectRatio="none"><path d="M0 40.0 L3 22.5 L6 10.3 L9 7.0 L12 13.6 L15 27.7 L18 44.9 L21 60.0 L24 68.5 L27 68.2 L30 59.8 L33 46.3 L36 32.1 L39 21.6 L42 17.5 L45 20.3 L48 28.4 L51 38.6 L54 47.5 L57 52.5 L60 52.9 L63 49.4 L66 44.1 L69 39.4 L72 36.9 L75 37.0 L78 38.7 L81 39.5 L84 39.2 L87 41.1 L90 44.6 L93 48.2 L96 49.9 L99 48.0 L102 42.2 L105 33.9 L108 25.8 L111 20.8 L114 21.5 L117 28.3 L120 40.0 L123 53.1 L126 63.5 L129 67.5 L132 63.2 L135 51.3 L138 35.2 L141 19.7 L144 9.6 L147 8.4 L150 16.7 L153 32.2 L156 50.4 L159 65.5 L162 73.2 L165 70.9 L168 59.5 L171 42.6 L174 25.3 L177 12.9 L180 9.0 L183 14.2 L186 26.7 L189 42.2 L192 56.0 L195 64.0 L198 64.4 L201 57.7 L204 46.7 L207 35.3 L210 26.8 L213 23.5 L216 25.5 L219 31.3 L222 38.2 L225 43.7 L228 46.3 L231 45.9 L234 43.5 L237 41.0 L240 40.0 L243 39.0 L246 36.5 L249 34.1 L252 33.7 L255 36.3 L258 41.8 L261 48.7 L264 54.5 L267 56.5 L270 53.2 L273 44.7 L276 33.3 L279 22.3 L282 15.6 L285 16.0 L288 24.0 L291 37.8 L294 53.3 L297 65.8 L300 71.0 L303 67.1 L306 54.7 L309 37.4 L312 20.5 L315 9.1 L318 6.8 L321 14.5 L324 29.6 L327 47.8 L330 63.3 L333 71.6 L336 70.4 L339 60.3 L342 44.8 L345 28.7 L348 16.8 L351 12.5 L354 16.5 L357 26.9 L360 40.0 L363 51.7 L366 58.5 L369 59.2 L372 54.2 L375 46.1 L378 37.8 L381 32.0 L384 30.1 L387 31.8 L390 35.4 L393 38.9 L396 40.8 L399 40.5 L402 41.3 L405 43.0 L408 43.1 L411 40.6 L414 35.9 L417 30.6 L420 27.1 L423 27.5 L426 32.5 L429 41.4 L432 51.6 L435 59.7 L438 62.5 L441 58.4 L444 47.9 L447 33.7 L450 20.2 L453 11.8 L456 11.5 L459 20.0 L462 35.1 L465 52.3 L468 66.4 L471 73.0 L474 69.7 L477 57.5 L480 40.0" /></svg>
                    <figcaption>{{ $page['ring']['beating'] }}</figcaption>
                </div>
                <div class="cp-wave cp-wave-ring">
                    <svg viewBox="0 0 480 80" aria-hidden="true" preserveAspectRatio="none"><path d="M0 40.0 L3 23.3 L6 11.6 L9 8.2 L12 14.2 L15 27.8 L18 45.0 L21 60.7 L24 70.4 L27 71.0 L30 62.6 L33 47.5 L36 30.1 L39 15.7 L42 8.5 L45 10.5 L48 21.2 L51 37.5 L54 54.5 L57 67.2 L60 71.9 L63 67.2 L66 54.5 L69 37.5 L72 21.2 L75 10.5 L78 8.5 L81 15.7 L84 30.1 L87 47.5 L90 62.6 L93 71.0 L96 70.4 L99 60.7 L102 45.0 L105 27.8 L108 14.2 L111 8.2 L114 11.6 L117 23.3 L120 40.0 L123 56.7 L126 68.4 L129 71.8 L132 65.8 L135 52.2 L138 35.0 L141 19.3 L144 9.6 L147 9.0 L150 17.4 L153 32.5 L156 49.9 L159 64.3 L162 71.5 L165 69.5 L168 58.8 L171 42.5 L174 25.5 L177 12.8 L180 8.1 L183 12.8 L186 25.5 L189 42.5 L192 58.8 L195 69.5 L198 71.5 L201 64.3 L204 49.9 L207 32.5 L210 17.4 L213 9.0 L216 9.6 L219 19.3 L222 35.0 L225 52.2 L228 65.8 L231 71.8 L234 68.4 L237 56.7 L240 40.0 L243 23.3 L246 11.6 L249 8.2 L252 14.2 L255 27.8 L258 45.0 L261 60.7 L264 70.4 L267 71.0 L270 62.6 L273 47.5 L276 30.1 L279 15.7 L282 8.5 L285 10.5 L288 21.2 L291 37.5 L294 54.5 L297 67.2 L300 71.9 L303 67.2 L306 54.5 L309 37.5 L312 21.2 L315 10.5 L318 8.5 L321 15.7 L324 30.1 L327 47.5 L330 62.6 L333 71.0 L336 70.4 L339 60.7 L342 45.0 L345 27.8 L348 14.2 L351 8.2 L354 11.6 L357 23.3 L360 40.0 L363 56.7 L366 68.4 L369 71.8 L372 65.8 L375 52.2 L378 35.0 L381 19.3 L384 9.6 L387 9.0 L390 17.4 L393 32.5 L396 49.9 L399 64.3 L402 71.5 L405 69.5 L408 58.8 L411 42.5 L414 25.5 L417 12.8 L420 8.1 L423 12.8 L426 25.5 L429 42.5 L432 58.8 L435 69.5 L438 71.5 L441 64.3 L444 49.9 L447 32.5 L450 17.4 L453 9.0 L456 9.6 L459 19.3 L462 35.0 L465 52.2 L468 65.8 L471 71.8 L474 68.4 L477 56.7 L480 40.0" /></svg>
                    <figcaption>{{ $page['ring']['ringing'] }}</figcaption>
                </div>
            </figure>
        </div>
    </section>

    <section class="s-wrap s-sec cp-bb-chairs" aria-labelledby="seats-h">
        <p class="cp-bb-kick">{{ $page['kicks']['chairs'] }}</p>
        <h2 id="seats-h" class="cp-bb-title">{{ $page['titles']['seats'] }}</h2>
        <ol class="cp-bb-seats">
            @foreach ((array) $page['seats'] as [$seat, $role])
                <li class="cp-bb-seat">
                    <svg viewBox="0 0 80 210" aria-hidden="true"><use href="#bb-singer" class="cp-bb-singer s-v{{ $loop->iteration }}" /></svg>
                    <h3>{{ $seat }}</h3>
                    <p>{{ $role }}</p>
                </li>
            @endforeach
        </ol>
        <figure class="cp-bb-voicing">
            <svg viewBox="0 0 470 220" aria-hidden="true">
                <g class="cp-staff"><path d="M0 40 H400 M0 80 H400 M0 120 H400 M0 160 H400 M0 200 H400" /></g>
                @foreach ($voicing as $v => $dots)
                    <polyline class="cp-line cp-c{{ $v }}" points="{{ implode(' ', array_map(fn ($d) => $d[0].','.$d[1], $dots)) }}" />
                    @foreach ($dots as [$x, $y])
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="7" class="cp-dot cp-c{{ $v }}" />
                    @endforeach
                    <text x="396" y="{{ end($dots)[1] + 5 }}" class="cp-bb-vlabel">{{ $page['seats'][$v - 1][0] }}</text>
                @endforeach
            </svg>
            <figcaption>{{ $page['seats_note'] }}</figcaption>
        </figure>
    </section>

    @if ($outline !== null)
        <section class="cp-bb-setsec" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-bb-kick">{{ $page['kicks']['set'] }}</p>
                <div class="cp-head">
                    <h2 id="units-h" class="cp-bb-title">{{ $page['titles']['units'] }}</h2>
                    <p class="cp-head-note">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <ol class="cp-bb-sets">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($set = str_replace(':n', (string) $loop->iteration, $page['set_n']))
                        <li class="cp-bb-set">
                            <p class="cp-bb-set-n"><x-site.skin :game="$stage" :serious="$set" /></p>
                            <div class="cp-bb-set-body">
                                <h3>{{ $unit['title'] }}</h3>
                                @isset($page['units_say'][$loop->index])
                                    <p class="cp-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                                @endisset
                                <ol class="cp-bb-running">
                                    @foreach ($unit['lessons'] as $lesson)
                                        <li><span>{{ $lesson }}</span>@if ($loop->parent->first && $loop->first) <span class="s-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                                    @endforeach
                                </ol>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="s-wrap s-sec cp-bb-warm" aria-labelledby="try-h">
        <div class="cp-bb-warm-head">
            <svg viewBox="0 0 120 120" class="cp-bb-pipe" aria-hidden="true">
                <circle cx="60" cy="60" r="54" class="cp-bb-pipe-rim" />
                <circle cx="60" cy="60" r="44" class="cp-bb-pipe-face" />
                @foreach (range(0, 12) as $i)
                    <circle cx="{{ round(60 + 36 * cos($i * M_PI / 6.5), 1) }}" cy="{{ round(60 + 36 * sin($i * M_PI / 6.5), 1) }}" r="3.6" class="cp-bb-pipe-hole" />
                @endforeach
                <circle cx="60" cy="60" r="14" class="cp-bb-pipe-cap" />
            </svg>
            <div>
                <p class="cp-bb-kick">{{ $page['kicks']['warm'] }}</p>
                <h2 id="try-h" class="cp-bb-title">{{ $page['titles']['try'] }}</h2>
                <p class="s-lede">{{ $page['try']['lead'] }}</p>
            </div>
        </div>
        <ol class="cp-bb-steps">
            @foreach ((array) $page['try']['steps'] as $step)
                <li @class(['cp-bb-step-first' => $loop->first])><span class="cp-bb-step-n" aria-hidden="true">{{ $loop->iteration }}</span><p>@if ($loop->first)<b class="cp-bb-start">{{ $page['try_first'] }}</b> @endif{{ $step }}</p></li>
            @endforeach
        </ol>
        <p class="cp-bb-after">{{ $page['try']['after'] }}</p>
    </section>

    @include('site.courses._inside')

    <section class="s-wrap s-sec cp-bb-shop" aria-labelledby="facts-h">
        <p class="cp-bb-kick">{{ $page['kicks']['shop'] }}</p>
        <h2 id="facts-h" class="cp-bb-title">{{ $page['titles']['facts'] }}</h2>
        <ul class="cp-bb-cards">
            @foreach ((array) $page['facts'] as [$text, $source, $url])
                <li class="cp-bb-card">
                    <span class="cp-bb-card-orn" aria-hidden="true">★ ★ ★</span>
                    <p class="cp-bb-card-text">{{ $text }}</p>
                    <p class="cp-bb-card-src">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $source }}</a>@else{{ $source }}@endif</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap s-sec cp-bb-before" aria-labelledby="before-h">
        <p class="cp-bb-kick">{{ $page['kicks']['before'] }}</p>
        <h2 id="before-h" class="cp-bb-title">{{ $page['titles']['before'] }}</h2>
        <div class="cp-bb-before-grid">
            <div class="cp-bb-before-lists">
                <div>
                    <h3>{{ $page['titles']['who'] }}</h3>
                    <ul class="s-ticks">@foreach ((array) $page['who'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul>
                </div>
                <div>
                    <h3>{{ $page['titles']['need'] }}</h3>
                    <ul class="s-ticks">@foreach ((array) $page['need'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul>
                </div>
            </div>
            <div class="cp-bb-rules">
                <h3>{{ $page['titles']['safety'] }}</h3>
                <ul>@foreach ((array) $page['safety'] as $line)<li>{{ $line }}</li>@endforeach</ul>
                <h3>{{ __('pages.site.course_songs') }}</h3>
                <ul class="cp-bb-program">
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-bb-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-bb-kick">{{ $page['kicks']['fine'] }}</p>
            <h2 id="faq-h" class="cp-bb-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-bb-encore" aria-labelledby="join-h">
        <div class="cp-bb-band" aria-hidden="true"></div>
        <div class="s-wrap s-sec cp-bb-encore-in">
            <svg viewBox="0 0 380 256" class="cp-bb-encore-art" aria-hidden="true">
                @foreach ($quartet as [$v, $x, $s])
                    <use href="#bb-singer" x="{{ $x - 130 }}" y="{{ 256 - 210 * $s }}" width="{{ 80 * $s }}" height="{{ 210 * $s }}" class="cp-bb-singer s-v{{ $v }}" />
                @endforeach
            </svg>
            <p class="cp-bb-kick"><span aria-hidden="true">★</span> {{ $page['kicks']['encore'] }} <span aria-hidden="true">★</span></p>
            <h2 id="join-h" class="cp-bb-title">{{ $page['encore']['title'] }}</h2>
            <p class="s-lede">{{ $open ? $page['encore']['body'] : __('pages.site.course_soon') }}</p>
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
            <h3 class="cp-bb-kick cp-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
</main>
