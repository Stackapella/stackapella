{{-- Folk and early music (/courses/folk-and-early-music), its own page: a broadside ballad sheet.
     Rag paper and printer's ink with a madder red, the hero a woodcut of a ship on the sea inside a
     printed rule, the tune above it in shape notes and the drone's long line beneath. The old scales
     (each with its one changed note, the ordinary one a ghost), small ornaments, the units, something to
     try now (Drunken Sailor), what the app hears, three checked facts as clippings, who it's for,
     questions and joining. Both skins; the art is wordless. Words: lang/en/site.php
     courses.pages.folk-and-early-music. --}}
@php($shapes = [[70, 92, 'fa'], [110, 76, 'sol'], [150, 84, 'la'], [190, 64, 'mi'], [230, 76, 'sol'], [270, 92, 'fa'], [310, 70, 'la'], [350, 84, 'mi'], [390, 100, 'fa']])
@php($modes = ['bright' => [[0, 2, 3, 5, 7, 9, 10, 12], 5, 8], 'soft' => [[0, 2, 4, 5, 7, 9, 10, 12], 6, 11]])
<main class="cp cp-folk">
    <section class="cp-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="s-kick cp-eyebrow">{{ $page['eyebrow'] }}</p>
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
                <svg viewBox="0 0 480 320" class="cp-fo-svg">
                    <rect x="8" y="8" width="464" height="304" class="cp-fo-rule" />
                    <rect x="16" y="16" width="448" height="288" class="cp-fo-rule cp-fo-thin" />
                    <circle cx="396" cy="78" r="30" class="cp-fo-sun" />
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
                    <path d="M150 226 L330 226 L308 256 L172 256 Z" class="cp-fo-hull" />
                    <path d="M240 226 V112" class="cp-fo-mast" />
                    <path d="M244 118 L318 216 L244 216 Z" class="cp-fo-sail" />
                    <path d="M236 128 L174 216 L236 216 Z" class="cp-fo-sail" />
                    <path d="M250 140 L300 206 M250 160 L290 210 M250 180 L278 212 M226 150 L190 206 M226 170 L198 210 M226 190 L208 212" class="cp-fo-hatch" />
                    <path d="M240 112 l18 6 l-18 6" class="cp-fo-flag" />
                    <path d="M24 262 q14 -10 28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0" class="cp-fo-wave" />
                    <path d="M38 278 q14 -10 28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0" class="cp-fo-wave" />
                    <path d="M28 296 H452" class="cp-fo-drone" />
                </svg>
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

    <section class="s-wrap s-sec" aria-labelledby="modes-h">
        <div class="cp-pair">
            <div>
                <h2 id="modes-h" class="s-title">{{ $page['titles']['modes'] }}</h2>
                <p class="s-lede">{{ $page['modes']['lead'] }}</p>
            </div>
            <div class="cp-pair-figs">
                @foreach ($modes as $kind => [$steps, $changed, $usual])
                    <figure class="cp-fig cp-fo-mode">
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

    <section class="s-ink" aria-labelledby="ornaments-h">
        <div class="s-wrap s-sec">
            <h2 id="ornaments-h" class="s-title">{{ $page['titles']['ornaments'] }}</h2>
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
</main>
