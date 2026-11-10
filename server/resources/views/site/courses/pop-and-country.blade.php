{{-- Pop and country styling (/courses/pop-and-country), its own page: a record. A sunset, a vinyl
     disc turning, and a voice line sweeping across it with a scoop into a note and a fall-off at the
     end. Four small moves (the scoop, the fall-off, the slide and the little cry, each drawn as the app
     draws it), small and light (the safety band), the units, something to try now (plain to styled),
     what the app hears, three checked facts, who it's for, questions and joining. Both skins; the art
     is wordless. Words: lang/en/site.php courses.pages.pop-and-country. --}}
@php($demo = $page['demo'])
@php($kinds = ['scoop', 'fall', 'slide', 'cry'])
{{-- Down in the Valley's first line, as js/public/pop-and-country.js sings it: [beat, beats, step]. --}}
@php($line = [[0, 1, -5], [1, 1, 0], [2, 1, 2], [3, 3, 4], [6, 3, 0], [9, 1, 4], [10, 1, 2], [11, 1, 0], [12, 3, 2]])
@php($beat = 60 / 96)
@php($span = 15 * $beat + 0.4)
@php($moves = ['M10 50 C 30 50, 34 26, 52 24 H150', 'M10 26 H100 C 120 26, 128 40, 140 58', 'M10 54 H60 C 76 54, 84 22, 100 22 H150', 'M10 40 H66 L74 18 L82 40 H150'])
<main class="cp cp-pop" data-po>
    <section class="cp-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-hero-chip"><b>{{ __('pages.site.course_chip_free') }}</b>@if ($outline !== null)<span>{{ __('pages.site.course_chip_lessons', ['n' => $outline['lessons']]) }}</span>@endif</p>
                <h1 id="page-h">{{ $page['hero_title'] }}</h1>
                <p class="s-lede">{{ $page['hero_lead'] }}</p>
                <p class="cp-hero-btns">
                    @if ($open)
                        <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                    @endif
                    <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                </p>
                <ul class="cp-hero-facts">
                    @foreach ((array) $page['hero_facts'] as $fact)
                        <li>{{ $fact }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="cp-pop-art" aria-hidden="true">
                <svg viewBox="0 0 520 320" class="cp-pop-svg">
                    <g class="cp-pop-disc">
                        <circle cx="300" cy="160" r="140" class="cp-pop-vinyl" />
                        <circle cx="300" cy="160" r="122" class="cp-pop-groove" />
                        <circle cx="300" cy="160" r="104" class="cp-pop-groove" />
                        <circle cx="300" cy="160" r="86" class="cp-pop-groove" />
                        <circle cx="300" cy="160" r="68" class="cp-pop-groove" />
                        <circle cx="300" cy="160" r="46" class="cp-pop-label" />
                        <circle cx="300" cy="160" r="5" class="cp-pop-hole" />
                        <path d="M224 70 A 120 120 0 0 1 340 46" class="cp-pop-shine" />
                    </g>
                    <path d="M20 214 H96 C 116 214, 120 176, 142 172 H236 C 256 172, 262 140, 282 138 H360 C 384 138, 392 160, 402 188" class="cp-pop-line" />
                    <circle cx="142" cy="172" r="6" class="cp-pop-dot" />
                    <circle cx="282" cy="138" r="6" class="cp-pop-dot" />
                </svg>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-dk-demosec" aria-labelledby="demo-h">
        <div class="cp-dk-demo" data-po-demo
            data-one="{{ $demo['one'] }}" data-many="{{ $demo['many'] }}" data-none="{{ $demo['none'] }}"
            data-playing-plain="{{ $demo['playing_plain'] }}" data-playing-styled="{{ $demo['playing_styled'] }}" data-playing-none="{{ $demo['playing_none'] }}" data-after="{{ $demo['after'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['kick'] }}</span><span>{{ $demo['lesson'] }}</span></p>
            <div class="cp-dk-demo-in">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}</p>
                    <p class="cp-dk-btns">
                        <button type="button" class="cp-dk-play" data-po-side="a" data-play="{{ $demo['plain'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['plain'] }}</span></button>
                        <button type="button" class="cp-dk-play cp-dk-play-b" data-po-side="b" data-play="{{ $demo['styled'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['styled'] }}</span></button>
                    </p>
                    <p class="cp-dk-say" role="status" data-po-say>{{ $demo['ready'] }}</p>
                </div>
                <div>
                    <div class="cp-dk-roll">
                        <svg viewBox="0 0 600 220" preserveAspectRatio="none" aria-hidden="true">
                            @foreach ([-5, 0, 2, 4] as $step)
                                <path d="M20 {{ 110 - $step * 14 }} H580" class="cp-dk-staff" />
                            @endforeach
                            @foreach ($line as [$at, $long, $step])
                                <rect x="{{ round(20 + $at * $beat / $span * 560, 1) }}" y="{{ 110 - $step * 14 - 7 }}" width="{{ round($long * $beat / $span * 560 - 4, 1) }}" height="14" rx="7" class="cp-dk-bar" data-po-note="{{ $loop->index }}" />
                            @endforeach
                            <path d="" class="cp-dk-trace" data-po-trace />
                        </svg>
                        <p class="cp-dk-words" aria-hidden="true">
                            @foreach ((array) $demo['lyric'] as $word)
                                <span data-po-note="{{ $loop->index }}">{{ $word }}</span>
                            @endforeach
                        </p>
                    </div>
                    <fieldset class="cp-dk-pick">
                        <legend>{{ $demo['moves_h'] }}</legend>
                        @foreach ((array) $demo['moves'] as $kind => $name)
                            <button type="button" class="cp-dk-chip" data-po-move="{{ $kind }}" aria-pressed="{{ in_array($kind, ['scoop', 'fall'], true) ? 'true' : 'false' }}"><span class="cp-dk-tick" aria-hidden="true"></span>{{ $name }}</button>
                        @endforeach
                    </fieldset>
                    <p class="cp-dk-count" data-po-count></p>
                    <p class="cp-dk-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-first" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="s-kick">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-outcomes">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-outcome-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec" aria-labelledby="moves-h">
        <h2 id="moves-h" class="s-title">{{ $page['titles']['moves'] }}</h2>
        <p class="s-lede">{{ $page['moves']['lead'] }}</p>
        <div class="cp-figs cp-pop-moves">
            @foreach ((array) $page['moves']['kinds'] as [$move, $what])
                <figure class="cp-fig">
                    <svg viewBox="0 0 160 70" aria-hidden="true"><path d="{{ $moves[$loop->index] }}" class="cp-pop-line" /></svg>
                    <figcaption><b>{{ $move }}</b> {{ $what }}</figcaption>
                    <button type="button" class="cp-dk-hear" data-po-hear="{{ $kinds[$loop->index] }}" data-play="{{ $demo['hear'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false" aria-label="{{ $demo['hear'] }}: {{ $move }}"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['hear'] }}</span></button>
                </figure>
            @endforeach
        </div>
    </section>

    @include('site.courses._care', ['key' => 'light'])

    @if ($outline !== null)
        <section class="s-wrap s-sec" aria-labelledby="units-h">
            <div class="cp-head">
                <h2 id="units-h" class="s-title">{{ $page['titles']['units'] }}</h2>
                <p class="cp-head-note">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            @include('site.courses._units')
        </section>
    @endif

    <section class="s-wrap s-sec" aria-labelledby="try-h">
        <div class="cp-try">
            <h2 id="try-h" class="s-title">{{ $page['titles']['try'] }}</h2>
            @include('site.courses._try')
        </div>
    </section>

    @include('site.courses._inside')

    <section class="s-wrap s-sec" aria-labelledby="facts-h">
        <h2 id="facts-h" class="s-title">{{ $page['titles']['facts'] }}</h2>
        @include('site.courses._facts')
    </section>

    @include('site.courses._three')

    <section class="s-wrap s-sec" aria-labelledby="faq-h">
        <h2 id="faq-h" class="s-title">{{ $page['titles']['faq'] }}</h2>
        @include('site.courses._faq')
    </section>

    <section class="cp-join" aria-labelledby="join-h">
        <div class="s-wrap s-sec">
            <h2 id="join-h" class="s-title">{{ $page['titles']['join'] }}</h2>
            @include('site.courses._join')
            <h3 class="s-kick cp-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
    <script type="module" src="/js/public/pop-and-country.js"></script>
</main>
