{{-- Pop and country styling (/courses/pop-and-country), its own page after Hillel's design (2026-11-18): a vinyl double
     album. The hero is a magenta sleeve with its record half out (turning, still under reduced motion) over a spine strip;
     Now playing is lesson 1's line (the course file's own notes and chords), side A plain and side B with the moves the
     visitor picks, the pitch line redrawn as they pick and a playhead running across the words on the audio clock
     (js/public/pop-and-country.js on voices.js); the four moves, each heard on its own; "Small and light" with a round
     Handle with care sticker and every safety line; the units as sides A to D, their lessons as tracks, each linked to
     its lesson page (so the shared list of every lesson isn't repeated under the page); a bonus track to try now; what
     the app listens for beside a result card plainly marked as a sample; the facts as liner notes led by their year;
     who it's for, what you need and the songs as album credits; questions; the close. Words: lang/en/site.php
     courses.pages.pop-and-country. Data: App\Support\Pages\PopAndCountryPage. --}}
@section('lesson-pages-inline', '1')
@php($line = \App\Support\Pages\PopAndCountryPage::line())
@php($roll = \App\Support\Pages\PopAndCountryPage::roll($line))
@php($sample = \App\Support\Pages\PopAndCountryPage::sample($line))
@php($sides = $outline !== null ? \App\Support\Pages\PopAndCountryPage::sides($outline) : [])
@php($moves = \App\Support\Pages\PopAndCountryPage::moves((array) $page['moves']['kinds']))
@php($chosen = \App\Support\Pages\PopAndCountryPage::CHOSEN)
@php($demo = $page['demo'])
<main class="cp cp-pop" data-po>
    <section class="cp-pop-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-pop-hero-grid">
            <div class="cp-pop-hero-copy">
                <x-site.crumbs :trail="[[__('pages.site.courses'), '/courses'], [$page['name']]]" />
                <p class="cp-pop-chip">
                    <b>{{ __('pages.site.course_chip_free') }}</b>
                    @if ($outline !== null)
                        <span>{{ __($page['hero_size'], ['lessons' => $outline['lessons'], 'sides' => count($outline['units'])]) }}</span>
                    @endif
                </p>
                <h1 id="page-h" class="cp-pop-h1">{{ $page['hero_title_lead'] }} <em>{{ $page['hero_title_em'] }}</em></h1>
                <p class="cp-pop-lede">{{ $page['hero_lead'] }}</p>
                <p class="cp-pop-btns">
                    @if ($open)
                        <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                    @else
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                    @endif
                    <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                </p>
            </div>
            <div class="cp-pop-album" aria-hidden="true">
                <div class="cp-pop-record"></div>
                <div class="cp-pop-sleeve">
                    <span class="cp-pop-sleeve-ring"></span>
                    <p class="cp-pop-sleeve-row"><span>{{ $page['sleeve']['label'] }}</span><span>{{ $page['sleeve']['speed'] }}</span></p>
                    <div class="cp-pop-sleeve-mid">
                        <p class="cp-pop-sleeve-title">@foreach ((array) $page['sleeve']['title'] as $part)<span>{{ $part }}</span>@endforeach</p>
                        <p class="cp-pop-sleeve-sub">{{ $page['sleeve']['sub'] }}</p>
                        <svg viewBox="0 0 200 50" class="cp-pop-sleeve-line"><path d="M0 40 L36 40 C48 40 50 22 62 22 L104 22 C118 22 120 10 132 10 L168 10 C180 10 186 26 196 38" /></svg>
                    </div>
                    <p class="cp-pop-sleeve-row"><span>{{ $page['sleeve']['a'] }}</span><span>{{ $page['sleeve']['b'] }}</span></p>
                </div>
            </div>
        </div>
        <div class="cp-pop-spine">
            <ul class="s-wrap">
                @foreach ((array) $page['spine'] as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- Now playing: lesson 1's line, side A plain and side B with the visitor's moves; then the outcomes. --}}
    <section class="s-wrap cp-pop-sec cp-pop-now" id="now-playing" aria-labelledby="demo-h">
        <div class="cp-pop-deck" data-po-demo data-line="{{ \App\Support\Pages\PopAndCountryPage::json($line) }}"
            data-one="{{ $demo['one'] }}" data-many="{{ $demo['many'] }}" data-none="{{ $demo['none'] }}"
            data-playing-plain="{{ $demo['playing_plain'] }}" data-playing-styled="{{ $demo['playing_styled'] }}" data-playing-none="{{ $demo['playing_none'] }}" data-after="{{ $demo['after'] }}">
            <p class="cp-pop-deck-bar"><span class="cp-pop-live"><span class="cp-pop-dot" aria-hidden="true"></span>{{ $demo['kick'] }}</span><span>{{ $demo['lesson'] }}</span></p>
            <div class="cp-pop-deck-in">
                <div class="cp-pop-deck-copy">
                    <h2 id="demo-h" class="cp-pop-deck-h">{{ $demo['title'] }}</h2>
                    <p class="cp-pop-deck-lead">{{ $demo['lead'] }}</p>
                    <p class="cp-pop-sides">
                        <button type="button" class="cp-pop-side" data-po-side="a" data-play="{{ $demo['plain'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-pop-icon" aria-hidden="true"></span><span data-label>{{ $demo['plain'] }}</span></button>
                        <button type="button" class="cp-pop-side cp-pop-side-b" data-po-side="b" data-play="{{ $demo['styled'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-pop-icon" aria-hidden="true"></span><span data-label>{{ $demo['styled'] }}</span></button>
                    </p>
                    <p class="cp-pop-say" role="status" data-po-say>{{ $demo['ready'] }}</p>
                </div>
                <div class="cp-pop-deck-side">
                    @if ($roll !== [])
                        <div class="cp-pop-roll">
                            <div class="cp-pop-roll-stage">
                                <svg viewBox="0 0 {{ \App\Support\Pages\PopAndCountryPage::WIDTH }} {{ \App\Support\Pages\PopAndCountryPage::HEIGHT }}" preserveAspectRatio="none" aria-hidden="true">
                                    @foreach ([50, 100, 150] as $rule)
                                        <path d="M0 {{ $rule }} H{{ \App\Support\Pages\PopAndCountryPage::WIDTH }}" class="cp-pop-rule" />
                                    @endforeach
                                    @foreach ($roll as $bar)
                                        <rect x="{{ $bar['x'] }}" y="{{ $bar['y'] }}" width="{{ $bar['w'] }}" height="10" rx="5" class="cp-pop-bar" data-po-note="{{ $loop->index }}" />
                                    @endforeach
                                    <path d="" class="cp-pop-trace" data-po-trace />
                                </svg>
                                <span class="cp-pop-playhead" data-po-head hidden></span>
                            </div>
                            <p class="cp-pop-words" aria-hidden="true">
                                @foreach ($roll as $bar)
                                    <span style="--at: {{ $bar['left'] }}%" @class(['cp-pop-word-low' => $bar['row'] === 1]) data-po-note="{{ $loop->index }}">{{ $bar['word'] }}</span>
                                @endforeach
                            </p>
                        </div>
                    @endif
                    <fieldset class="cp-pop-pick">
                        <legend>{{ $demo['moves_h'] }}</legend>
                        @foreach ((array) $demo['moves'] as $kind => $name)
                            <button type="button" class="cp-pop-move-chip" data-po-move="{{ $kind }}" aria-pressed="{{ in_array($kind, $chosen, true) ? 'true' : 'false' }}"><span class="cp-pop-tick" aria-hidden="true"></span>{{ $name }}</button>
                        @endforeach
                    </fieldset>
                    <p class="cp-pop-count" data-po-count></p>
                    <p class="cp-pop-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>

        <h2 id="outcomes-h" class="cp-pop-kick cp-pop-outcomes-h">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-pop-outcomes">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-pop-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    {{-- Four small moves, each drawn as the app draws it and heard on its own. --}}
    <section class="s-wrap cp-pop-sec" aria-labelledby="moves-h">
        <div class="cp-pop-head-row">
            <h2 id="moves-h" class="cp-pop-title">{{ $page['titles']['moves'] }}</h2>
            <p class="cp-pop-intro">{{ $page['moves']['lead'] }}</p>
        </div>
        <ul class="cp-pop-moves">
            @foreach ($moves as $move)
                <li class="cp-pop-move">
                    <div class="cp-pop-move-art" aria-hidden="true">
                        <span class="cp-pop-move-tag">{{ __($page['moves']['tag'], ['n' => $loop->iteration]) }}</span>
                        <span class="cp-pop-mini"></span>
                        <svg viewBox="0 0 200 60"><path d="M0 52 L200 52" class="cp-pop-move-base" /><path d="{{ $move['path'] }}" class="cp-pop-move-line" /></svg>
                    </div>
                    <div class="cp-pop-move-body">
                        <h3>{{ $move['name'] }}</h3>
                        <p>{{ $move['what'] }}</p>
                        <button type="button" class="cp-pop-hear" data-po-hear="{{ $move['kind'] }}" data-play="{{ $demo['hear'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false" aria-label="{{ $demo['hear'] }}: {{ $move['name'] }}"><span class="cp-pop-icon" aria-hidden="true"></span><span data-label>{{ $demo['hear'] }}</span></button>
                    </div>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Small and light: every safety line, beside the round sticker. --}}
    <section class="cp-pop-band cp-pop-care" aria-labelledby="light-h">
        <div class="s-wrap cp-pop-band-in">
            <div>
                <h2 id="light-h" class="cp-pop-title">{{ $page['titles']['light'] }}</h2>
                <p class="cp-pop-intro">{{ $page['light']['lead'] }}</p>
                <svg viewBox="0 0 200 200" class="cp-pop-sticker" aria-hidden="true">
                    <circle cx="100" cy="100" r="96" class="cp-pop-sticker-disc" />
                    <circle cx="100" cy="100" r="62" class="cp-pop-sticker-ring" />
                    <path id="cp-pop-ring" d="M100,100 m-76,0 a76,76 0 1,1 152,0 a76,76 0 1,1 -152,0" fill="none" />
                    <text class="cp-pop-sticker-arc"><textPath href="#cp-pop-ring">{{ $page['light']['sticker']['ring'] }}</textPath></text>
                    @foreach (\App\Support\Pages\PopAndCountryPage::twoLines((string) $page['light']['sticker']['words']) as $stickerLine)
                        <text x="100" y="{{ 96 + $loop->index * 26 }}" text-anchor="middle" class="cp-pop-sticker-word">{{ $stickerLine }}</text>
                    @endforeach
                </svg>
            </div>
            <ul class="cp-pop-care-list">
                @foreach ((array) $page['light']['points'] as $point)
                    <li><span class="cp-pop-care-tag">{{ $page['light']['tags'][$loop->index] ?? '' }}</span><span>{{ $point }}</span></li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- The tracklist: each unit a side, its lessons as tracks, each linked to its lesson page. --}}
    @if ($outline !== null)
        <section class="s-wrap cp-pop-sec" aria-labelledby="units-h">
            <div class="cp-pop-head-row">
                <div>
                    <p class="cp-pop-kick">{{ $page['titles']['units_kick'] }}</p>
                    <h2 id="units-h" class="cp-pop-title">{{ $page['titles']['units'] }}</h2>
                </div>
                <p class="cp-pop-size">{{ __($page['titles']['units_size'], ['sides' => count($outline['units']), 'tracks' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-pop-discs">
                @foreach ($sides as $side)
                    <li class="cp-pop-disc" data-unit="{{ $side['n'] }}" style="--unit: {{ $side['n'] }}">
                        <p class="cp-pop-disc-head"><span class="cp-pop-disc-letter" aria-hidden="true">{{ $side['side'] }}</span><span class="cp-pop-disc-kick">{{ __('pages.site.course_unit', ['n' => $side['n']]) }}</span></p>
                        <h3>{{ $side['title'] }}</h3>
                        @isset($page['units_say'][$loop->index])
                            <p class="cp-pop-disc-say">{{ $page['units_say'][$loop->index] }}</p>
                        @endisset
                        <ol class="cp-pop-tracks">
                            @foreach ($side['tracks'] as $track)
                                <li @class(['cp-pop-track-first' => $track['first']])>
                                    <span class="cp-pop-track-n">{{ $track['code'] }}</span>
                                    @if ($track['url'] !== null)
                                        <a href="{{ $track['url'] }}">{{ $track['name'] }}</a>
                                    @else
                                        <span>{{ $track['name'] }}</span>
                                    @endif
                                    @if ($track['first'])
                                        <span class="cp-pop-start">{{ __('pages.site.course_free') }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- A bonus track: something to try now, without the app. --}}
    <section class="s-wrap cp-pop-sec" aria-labelledby="try-h">
        <div class="cp-pop-try">
            <div class="cp-pop-try-top">
                <p class="cp-pop-kick">{{ $page['titles']['try_kick'] }}</p>
                <h2 id="try-h" class="cp-pop-try-h">{{ $page['titles']['try'] }}</h2>
                <p class="cp-pop-try-lead">{{ $page['try']['lead'] }}</p>
            </div>
            <ol class="cp-pop-try-steps">
                @foreach ((array) $page['try']['steps'] as $step)
                    <li><span class="cp-pop-try-n" aria-hidden="true">{{ $loop->iteration }}</span><span>{{ $step }}</span></li>
                @endforeach
            </ol>
            <p class="cp-pop-try-after">{{ $page['try']['after'] }}</p>
        </div>
    </section>

    {{-- What the app listens for, beside a result card plainly marked as a sample. --}}
    <section class="cp-pop-band cp-pop-hears" aria-labelledby="inside-h">
        <div class="s-wrap cp-pop-band-in">
            <div>
                <h2 id="inside-h" class="cp-pop-title">{{ $page['titles']['inside'] }}</h2>
                <p class="cp-pop-intro">{{ $page['measures'] }}</p>
                <p class="cp-pop-small"><x-site.say :text="$page['scoring']" /></p>
            </div>
            <figure class="cp-pop-take">
                <figcaption class="cp-pop-take-head"><span><b class="cp-pop-sample">{{ $page['sample']['kick'] }}</b> {{ $page['sample']['take'] }}</span><span translate="no">{{ $page['sample']['song'] }}</span></figcaption>
                @if ($sample['trace'] !== '')
                    <svg viewBox="0 0 400 110" class="cp-pop-take-art" aria-hidden="true">
                        @foreach ($sample['bars'] as $bar)
                            <path d="{{ $bar }}" class="cp-pop-take-bar" />
                        @endforeach
                        <path d="{{ $sample['trace'] }}" class="cp-pop-take-line" />
                        @foreach ($sample['dots'] as [$dotX, $dotY, $heard])
                            <circle cx="{{ $dotX }}" cy="{{ $dotY }}" r="6" @class(['cp-pop-take-dot', 'cp-pop-take-miss' => ! $heard]) />
                        @endforeach
                    </svg>
                @endif
                <ul class="cp-pop-take-rows">
                    @foreach ((array) $page['sample']['rows'] as [$asked, $heard])
                        <li><span>{{ $asked }}</span><span @class(['cp-pop-take-mark', 'cp-pop-take-not' => ! $heard])>{{ $heard ? $page['sample']['heard'] : $page['sample']['not_yet'] }}</span></li>
                    @endforeach
                </ul>
                <p class="cp-pop-take-result">{{ $page['sample']['result'] }}</p>
                <p class="cp-pop-take-note">{{ $page['sample']['note'] }}</p>
            </figure>
        </div>
    </section>

    {{-- Liner notes: the facts, each led by its year, with its source. --}}
    <section class="s-wrap cp-pop-sec" aria-labelledby="facts-h">
        <p class="cp-pop-kick">{{ $page['titles']['facts_kick'] }}</p>
        <h2 id="facts-h" class="cp-pop-title">{{ $page['titles']['facts'] }}</h2>
        <ul class="cp-pop-liners">
            @foreach ((array) $page['facts'] as [$text, $source, $url])
                <li class="cp-pop-liner">
                    <p class="cp-pop-liner-top"><span class="cp-pop-year">{{ $page['facts_years'][$loop->index] ?? '' }}</span><span class="cp-pop-mini" aria-hidden="true"></span></p>
                    <p class="cp-pop-liner-text">{{ $text }}</p>
                    <p class="cp-pop-liner-src">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $source }}</a>@else{{ $source }}@endif</p>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- Album credits: who it's for, what you need and the songs. --}}
    <section class="s-wrap cp-pop-credits-sec" aria-label="{{ $page['titles']['who'] }}">
        <div class="cp-pop-credits">
            <div>
                <h2 class="cp-pop-kick">{{ $page['titles']['who'] }}</h2>
                <ul>@foreach ((array) $page['who'] as $who)<li>{{ $who }}</li>@endforeach</ul>
            </div>
            <div>
                <h2 class="cp-pop-kick">{{ $page['titles']['need'] }}</h2>
                <ul>@foreach ((array) $page['need'] as $need)<li>{{ $need }}</li>@endforeach</ul>
            </div>
            <div>
                <h2 class="cp-pop-kick">{{ __('pages.site.course_songs') }}</h2>
                <ul class="cp-pop-songs">
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><b translate="no">{{ $song }}</b> {{ $credit }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-pop-faq-sec" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-pop-title">{{ $page['titles']['faq'] }}</h2>
        @include('site.courses._faq')
    </section>

    <section class="cp-pop-close" aria-labelledby="join-h">
        <div class="s-wrap cp-pop-close-in">
            <div>
                <h2 id="join-h" class="cp-pop-close-h">{{ $page['titles']['join'] }}</h2>
                @include('site.courses._join')
            </div>
            <div>
                <h3 class="cp-pop-kick">{{ $page['titles']['related'] }}</h3>
                @include('site.courses._related')
            </div>
        </div>
    </section>
    <script type="module" src="/js/public/pop-and-country.js"></script>
</main>
