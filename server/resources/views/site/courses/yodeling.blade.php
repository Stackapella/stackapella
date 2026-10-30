{{-- Yodeling (/courses/yodeling), its own page: a Swiss alpine trail (docs/course-pages-polish.md).
     Serious skin: an old Alpine travel poster, flat layered ranges in a blue haze, a meadow with its
     path, a chalet and pines, and every running label a yellow hiking sign. Game skin: the same valley
     as a neon night, the ranges lit along their ridges. The hero's yodel is a ribbon that flips between
     two voice lines and echoes across the valley, smaller and fainter each time. Then the trail: the
     trailhead (what you'll be able to do), two voices (a flip against a slide), mountain rules (singing
     it safely, as pictograms), the trail map (the units as four huts up a mountain), first steps (a
     mini-lesson on trail blazes), base camp (what the app hears), postcards from the valleys (facts),
     your pack (who, what you need and the songs), before you set off (questions) and the summit. The
     art's only words are the two voices' names. Words: lang/en/site.php courses.pages.yodeling. --}}
@php($ribbon = 'M24 216 H92 Q100 216 102 206 L106 134 Q108 124 116 124 H170 Q178 124 180 134 L184 206 Q186 216 194 216 H222 Q230 216 232 206 L236 134 Q238 124 246 124 H286 Q294 124 296 134 L300 206 Q302 216 310 216 H334 Q342 216 344 206 L348 134 Q350 124 358 124 H384 Q392 124 394 134 L398 206 Q400 216 408 216 H440')
@php($huts = [[96, 318], [250, 236], [388, 150], [520, 66]])
<main class="cp cp-yodel">
    <section class="cp-hero cp-yo-hero" aria-labelledby="page-h">
        <svg class="cp-yo-sky" viewBox="0 0 1440 520" preserveAspectRatio="xMidYMid slice" aria-hidden="true">
            <circle cx="1180" cy="120" r="70" class="cp-yo-sun" />
            <circle cx="1180" cy="120" r="110" class="cp-yo-sunhalo" />
            <g class="cp-yo-cloud cp-yo-cloud-1"><ellipse cx="300" cy="110" rx="70" ry="18" /><ellipse cx="340" cy="96" rx="44" ry="22" /><ellipse cx="270" cy="100" rx="34" ry="16" /></g>
            <g class="cp-yo-cloud cp-yo-cloud-2"><ellipse cx="900" cy="70" rx="58" ry="14" /><ellipse cx="930" cy="58" rx="36" ry="18" /></g>
        </svg>
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-yo-sign cp-eyebrow">{{ $page['eyebrow'] }}</p>
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
            <div class="cp-yo-art" aria-hidden="true">
                <svg viewBox="0 0 560 300" class="cp-yo-svg">
                    <defs>
                        <linearGradient id="yo-ribbon" x1="0" x2="1"><stop offset="0" class="cp-yo-rib-1" /><stop offset="1" class="cp-yo-rib-2" /></linearGradient>
                    </defs>
                    <path d="M24 124 H520 M24 216 H520" class="cp-yo-guide" />
                    <g class="cp-yo-echoes">
                        <path d="{{ $ribbon }}" transform="translate(300 96) scale(.52)" class="cp-yo-echo-2" />
                        <path d="{{ $ribbon }}" transform="translate(212 62) scale(.72)" class="cp-yo-echo-1" />
                    </g>
                    <path d="{{ $ribbon }}" class="cp-yo-line" />
                    @foreach ([[104, 170], [182, 170], [234, 170], [298, 170], [346, 170], [396, 170]] as [$x, $y])
                        <circle cx="{{ $x }}" cy="{{ $y }}" r="5" class="cp-yo-flip" />
                    @endforeach
                    <g class="cp-yo-tag" transform="translate(24 92)"><path d="M0 0 H104 L116 12 L104 24 H0 Z" /><text x="10" y="17">{{ $page['art']['head'] }}</text></g>
                    <g class="cp-yo-tag" transform="translate(24 234)"><path d="M0 0 H112 L124 12 L112 24 H0 Z" /><text x="10" y="17">{{ $page['art']['chest'] }}</text></g>
                </svg>
            </div>
        </div>
        <svg class="cp-yo-range" viewBox="0 0 1440 260" preserveAspectRatio="none" aria-hidden="true">
            <defs><linearGradient id="yo-haze" x1="0" y1="0" x2="0" y2="1"><stop offset="0" class="cp-yo-haze-1" /><stop offset="1" class="cp-yo-haze-2" /></linearGradient></defs>
            <polygon class="cp-yo-far" points="0,260 0,120 120,70 230,110 380,24 520,100 650,44 790,112 930,34 1080,104 1210,56 1340,96 1440,66 1440,260" />
            <polygon class="cp-yo-snow" points="380,24 412,54 394,52 372,64 352,48" />
            <polygon class="cp-yo-snow" points="930,34 960,60 942,58 922,70 906,54" />
            <polygon class="cp-yo-snow" points="650,44 674,66 660,64 644,74 630,60" />
            <rect y="40" width="1440" height="220" fill="url(#yo-haze)" />
            <polygon class="cp-yo-mid" points="0,260 0,160 160,108 300,158 470,96 620,156 780,112 940,166 1100,116 1260,158 1440,124 1440,260" />
            <polygon class="cp-yo-near" points="0,260 0,200 220,168 420,200 640,172 860,206 1080,176 1300,204 1440,186 1440,260" />
            <path d="M560 260 C 600 230, 700 236, 720 214 S 800 190, 840 192" class="cp-yo-path" />
            <g class="cp-yo-chalet" transform="translate(840 150)"><path d="M0 40 L28 18 L56 40 Z" class="cp-yo-roof" /><rect x="6" y="38" width="44" height="30" class="cp-yo-wall" /><rect x="22" y="50" width="12" height="18" class="cp-yo-door" /></g>
            @foreach ([[180, 196], [214, 190], [1180, 192], [1214, 198], [1250, 190]] as [$x, $y])
                <path d="M{{ $x }} {{ $y - 34 }} L{{ $x + 14 }} {{ $y }} H{{ $x - 14 }} Z" class="cp-yo-pine" />
            @endforeach
        </svg>
    </section>

    <section class="s-wrap s-sec cp-yo-trailhead" aria-labelledby="outcomes-h">
        <p class="cp-yo-sign">{{ $page['kicks']['trailhead'] }}</p>
        <h2 id="outcomes-h" class="cp-yo-title">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-yo-goals">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-yo-goal-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-yo-voicesec" aria-labelledby="voices-h">
        <div class="cp-pair">
            <div>
                <p class="cp-yo-sign">{{ $page['kicks']['voices'] }}</p>
                <h2 id="voices-h" class="cp-yo-title">{{ $page['titles']['voices'] }}</h2>
                <p class="s-lede">{{ $page['voices']['lead'] }}</p>
                <p class="cp-fig-note">{{ $page['voices']['body'] }}</p>
            </div>
            <div class="cp-pair-figs">
                <figure class="cp-fig cp-yo-ledge">
                    <svg viewBox="0 0 140 100" aria-hidden="true"><path d="M4 76 H58 V96 H4 Z M82 30 H136 V96 H82 Z" class="cp-yo-rock" /><path d="M4 76 H58 M82 30 H136" class="cp-yo-grass" /><path d="M28 70 Q 56 2 104 24" class="cp-yo-line cp-yo-jump" /><circle cx="104" cy="24" r="5" class="cp-yo-flip" /></svg>
                    <figcaption><b>{{ $page['voices']['flip_label'] }}</b> {{ $page['voices']['flip'] }}</figcaption>
                </figure>
                <figure class="cp-fig cp-yo-ledge">
                    <svg viewBox="0 0 140 100" aria-hidden="true"><path d="M4 76 H40 L100 30 H136 V96 H4 Z" class="cp-yo-rock" /><path d="M4 76 H40 L100 30 H136" class="cp-yo-grass" /><path d="M10 70 H38 L98 24 H130" class="cp-yo-line cp-yo-slide" /></svg>
                    <figcaption><b>{{ $page['voices']['slide_label'] }}</b> {{ $page['voices']['slide'] }}</figcaption>
                </figure>
            </div>
        </div>
    </section>

    <section class="s-ink cp-yo-rules" aria-labelledby="light-h">
        <div class="s-wrap s-sec">
            <p class="cp-yo-sign">{{ $page['kicks']['rules'] }}</p>
            <h2 id="light-h" class="cp-yo-title">{{ $page['titles']['light'] }}</h2>
            <p class="s-lede">{{ $page['light']['lead'] }}</p>
            <ul class="cp-yo-picto">
                @foreach ((array) $page['light']['points'] as $point)
                    <li>
                        <svg viewBox="0 0 48 48" aria-hidden="true">
                            @if ($loop->index === 0)
                                <path d="M8 19 H16 L26 10 V38 L16 29 H8 Z" /><path d="M32 18 Q37 24 32 30" class="cp-yo-picto-line" />
                            @elseif ($loop->index === 1)
                                <path d="M24 6 V30" class="cp-yo-picto-line" /><path d="M16 22 L24 30 L32 22" class="cp-yo-picto-line" /><path d="M8 40 H40" class="cp-yo-picto-line" />
                            @elseif ($loop->index === 2)
                                <circle cx="24" cy="26" r="15" class="cp-yo-picto-line" /><path d="M24 26 V17 M24 26 L30 30 M20 6 H28" class="cp-yo-picto-line" />
                            @else
                                <path d="M24 42 V22" class="cp-yo-picto-line" /><path d="M24 26 C 24 16, 14 12, 8 14 C 10 22, 18 26, 24 26 Z M24 22 C 24 12, 34 8, 40 10 C 38 18, 30 22, 24 22 Z" />
                            @endif
                        </svg>
                        <span>{{ $point }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    @if ($outline !== null)
        <section class="cp-yo-trailsec" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-yo-sign">{{ $page['kicks']['trail'] }}</p>
                <div class="cp-head">
                    <h2 id="units-h" class="cp-yo-title">{{ $page['titles']['units'] }}</h2>
                    <p class="cp-head-note">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <svg viewBox="0 0 620 360" class="cp-yo-map" aria-hidden="true">
                    <path d="M0 360 L120 300 L190 318 L330 196 L400 222 L520 54 L620 160 V360 Z" class="cp-yo-map-mtn" />
                    <path d="M480 108 L520 54 L556 104 L536 98 L520 110 L500 96 Z" class="cp-yo-snow" />
                    <path d="M20 352 C 60 340, 80 330, 96 318 S 170 290, 200 272 S 240 246, 250 236 S 320 196, 340 186 S 380 160, 388 150 S 470 92, 520 66" class="cp-yo-map-path" />
                    @foreach ($huts as [$x, $y])
                        <g transform="translate({{ $x }} {{ $y }})" class="cp-yo-hut"><circle r="20" /><text y="6">{{ $loop->iteration }}</text></g>
                    @endforeach
                    <path d="M520 46 V14 L546 22 L520 30" class="cp-yo-flag" />
                </svg>
                <ol class="cp-yo-stages">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-yo-stage">
                            <p class="cp-yo-sign cp-yo-sign-sm"><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            <ol class="cp-yo-lessons">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li>{{ $lesson }}@if ($loop->parent->first && $loop->first) <span class="s-free">{{ __('pages.site.course_free') }}</span>@endif</li>
                                @endforeach
                            </ol>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="s-wrap s-sec cp-yo-first" aria-labelledby="try-h">
        <p class="cp-yo-sign">{{ $page['kicks']['first'] }}</p>
        <h2 id="try-h" class="cp-yo-title">{{ $page['titles']['try'] }}</h2>
        <p class="s-lede">{{ $page['try']['lead'] }}</p>
        <ol class="cp-yo-steps">
            @foreach ((array) $page['try']['steps'] as $step)
                <li @class(['cp-yo-step-first' => $loop->first])><span class="cp-yo-blaze" aria-hidden="true"><b>{{ $loop->iteration }}</b></span><p>@if ($loop->first)<b class="cp-yo-start">{{ $page['try_first'] }}</b> @endif{{ $step }}</p></li>
            @endforeach
        </ol>
        <p class="cp-fig-note">{{ $page['try']['after'] }}</p>
    </section>

    @include('site.courses._inside')

    <section class="s-wrap s-sec cp-yo-posts" aria-labelledby="facts-h">
        <p class="cp-yo-sign">{{ $page['kicks']['post'] }}</p>
        <h2 id="facts-h" class="cp-yo-title">{{ $page['titles']['facts'] }}</h2>
        <ul class="cp-yo-cards">
            @foreach ((array) $page['facts'] as [$text, $source, $url])
                <li class="cp-yo-card">
                    <svg viewBox="0 0 64 76" class="cp-yo-stamp" aria-hidden="true"><rect x="2" y="2" width="60" height="72" class="cp-yo-stamp-edge" /><rect x="8" y="8" width="48" height="60" class="cp-yo-stamp-sky" /><path d="M8 68 L22 40 L32 52 L44 30 L56 50 V68 Z" class="cp-yo-stamp-mtn" /><path d="M40 37 L44 30 L48 37 Z" class="cp-yo-stamp-snow" /></svg>
                    <svg viewBox="0 0 90 30" class="cp-yo-postmark" aria-hidden="true"><path d="M0 6 Q 11 0 22 6 T 45 6 T 68 6 T 90 6 M0 15 Q 11 9 22 15 T 45 15 T 68 15 T 90 15 M0 24 Q 11 18 22 24 T 45 24 T 68 24 T 90 24" /></svg>
                    <p class="cp-yo-card-text">{{ $text }}</p>
                    <p class="cp-yo-card-src">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $source }}</a>@else{{ $source }}@endif</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap s-sec cp-yo-pack" aria-labelledby="pack-h">
        <p class="cp-yo-sign">{{ $page['kicks']['pack'] }}</p>
        <h2 id="pack-h" class="cp-yo-title">{{ $page['titles']['pack'] }}</h2>
        <div class="cp-yo-pack-grid">
            <div>
                <h3>{{ $page['titles']['who'] }}</h3>
                <ul class="s-ticks">@foreach ((array) $page['who'] as $line)<li><x-site.icon name="check" size="18" />{{ $line }}</li>@endforeach</ul>
            </div>
            <div class="cp-yo-packlist">
                <h3>{{ $page['titles']['need'] }}</h3>
                <ul>@foreach ((array) $page['need'] as $line)<li><span class="cp-yo-box" aria-hidden="true"></span>{{ $line }}</li>@endforeach</ul>
            </div>
            <div>
                <h3>{{ __('pages.site.course_songs') }}</h3>
                <ul class="cp-yo-songs">
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b> <span>{{ $credit }}</span></li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-yo-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-yo-sign">{{ $page['kicks']['fine'] }}</p>
            <h2 id="faq-h" class="cp-yo-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-yo-summit" aria-labelledby="join-h">
        <svg class="cp-yo-summit-range" viewBox="0 0 1440 120" preserveAspectRatio="none" aria-hidden="true"><path d="M0 120 V70 L160 30 L300 76 L470 8 L620 70 L780 26 L940 80 L1100 18 L1260 66 L1440 34 V120 Z" /></svg>
        <div class="s-wrap s-sec cp-yo-summit-in">
            <p class="cp-yo-sign">{{ $page['kicks']['summit'] }}</p>
            <h2 id="join-h" class="cp-yo-title">{{ $page['titles']['join'] }}<span class="cp-yo-echo-word" aria-hidden="true">{{ $page['echo'] }}</span><span class="cp-yo-echo-word cp-yo-echo-word-2" aria-hidden="true">{{ $page['echo'] }}</span></h2>
            <p class="s-lede">{{ $open ? $page['summit'] : __('pages.site.course_soon') }}</p>
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
            <h3 class="cp-yo-sign cp-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
</main>
