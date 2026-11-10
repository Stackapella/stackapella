{{-- First steps to belting (/courses/belting), its own page: a love letter in Diction's calm, light layout (its cp-di-*
     classes, as Singing in another language reuses them), from the research pack (docs/courses/belting-love-letter.md),
     every claim with its numbered notes, and bound by the voice-health review (docs/courses/belting-voice-health.md):
     no loudness ever asked for, no promise of power, range, high notes or safety for everyone, and the app hears pitch
     and timing only. The hero is the calling contour: "Hel-lo!" as two level notes, the second three semitones lower,
     beside the same call held flat. Then the featured demo, Same note, two colours: one synthesized note, veiled and
     then bright through filters at the same level, its harmonics drawn and each colour lit as it sounds
     (js/public/belting.js). Then the care (near the top, and again by the sign-up), by the end (the outcomes), in plain
     words (the colour does the carrying, and why loud is the risk), from the archive (1711 to 1914), the course (the
     units, from the course file), lesson 1 beside what the app hears, margin notes, before you begin, questions, the
     close and the sources. Hey, Ho and Haul Away are named, never linked. No drawn people; every sound only when
     pressed, on the audio clock. Only its palette and the demo's band are its own (.cp-fb). Game skin: Diction's square
     arcade frames on the night. Words: lang/en/site.php courses.pages.belting (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($call = $love['call'])
@php($demo = $love['demo'])
@php($plain = $love['plain'])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($rangeName = __('site.courses.pages.range-and-registers.name'))
{{-- The calling contour: semitones from + 3 (top) to - 2, 34 units apart; a beat 90 units (at 80 a minute), "Hel-" a beat on + 2, "-lo!" a beat and a half on - 1. --}}
@php($semi = fn (int $s): int => 34 + (3 - $s) * 34)
@php($bx = fn (float $beat): int => (int) round(66 + $beat * 90))
{{-- The demo's spectrum: 0 to 5,000 Hz across 600 units. --}}
@php($fx = fn (int $f): int => (int) round($f * 0.12))
<main class="cp cp-diction cp-fb" data-fb>
    <section class="cp-hero cp-di-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-hero-chip"><b>{{ __('pages.site.course_chip_free') }}</b>@if ($outline !== null)<span>{{ __('pages.site.course_chip_lessons', ['n' => $outline['lessons']]) }}</span>@endif</p>
                <h1 id="page-h">{{ $page['hero_title'] }}</h1>
                <p class="s-lede">{{ $page['hero_lead'] }}</p>
                <ul class="cp-hero-facts">
                    @foreach ((array) $page['hero_facts'] as $fact)
                        <li>{{ $fact }}</li>
                    @endforeach
                </ul>
                <p class="cp-hero-btns">
                    <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="{{ $outline !== null ? '#units-h' : '#outcomes-h' }}" class="btn-secondary btn-lg">{{ $outline !== null ? $page['see_lessons'] : $page['see_aims'] }}</a>
                </p>
                <p class="cp-di-quiet">{{ $page['hero_care'] }}</p>
                @unless ($open)
                    <p class="cp-di-quiet">{{ __('pages.site.course_soon') }}</p>
                @endunless
            </div>
            <figure class="cp-hero-card cp-di-map">
                <svg viewBox="0 0 440 268" class="cp-di-map-art" aria-hidden="true">
                    @foreach (range(3, -2) as $s)
                        <path d="M48 {{ $semi($s) }} H424" class="cp-di-grid" />
                    @endforeach
                    <text x="20" y="119" text-anchor="middle" transform="rotate(-90 20 119)" class="cp-di-axis">{{ $call['pitch'] }}</text>
                    <text x="236" y="260" text-anchor="middle" class="cp-di-axis">{{ $call['time'] }}</text>
                    {{-- Held flat: the second note left on the first's pitch, an outline. --}}
                    <rect x="{{ $bx(1) + 4 }}" y="{{ $semi(2) - 10 }}" width="{{ $bx(2.5) - $bx(1) - 8 }}" height="20" rx="10" class="cp-di-ring" />
                    {{-- The call: two level notes, the second lower. --}}
                    <rect x="{{ $bx(0) + 4 }}" y="{{ $semi(2) - 10 }}" width="{{ $bx(1) - $bx(0) - 8 }}" height="20" rx="10" class="cp-di-dot" />
                    <rect x="{{ $bx(1) + 4 }}" y="{{ $semi(-1) - 10 }}" width="{{ $bx(2.5) - $bx(1) - 8 }}" height="20" rx="10" class="cp-di-dot" />
                    <text x="{{ $bx(0) + 4 }}" y="{{ $semi(2) - 22 }}" class="cp-di-vname">{{ $call['syllables'][0] }}</text>
                    <text x="{{ $bx(1) + 4 }}" y="{{ $semi(-1) + 42 }}" class="cp-di-vname">{{ $call['syllables'][1] }}</text>
                    {{-- The step between them. --}}
                    <path d="M{{ $bx(2.5) + 22 }} {{ $semi(2) }} H{{ $bx(2.5) + 34 }} M{{ $bx(2.5) + 28 }} {{ $semi(2) }} V{{ $semi(-1) }} M{{ $bx(2.5) + 22 }} {{ $semi(-1) }} H{{ $bx(2.5) + 34 }}" class="cp-di-tri" />
                    @foreach ((array) $call['step'] as $line)
                        <text x="{{ $bx(2.5) + 44 }}" y="{{ $semi(1) + 10 + $loop->index * 20 }}" class="cp-di-tick">{{ $line }}</text>
                    @endforeach
                </svg>
                <figcaption>
                    <ul class="cp-hero-legend">
                        @foreach ((array) $call['legend'] as $key)
                            <li><i class="cp-di-key cp-di-key-{{ ['dot', 'ring'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</li>
                        @endforeach
                    </ul>
                    <p class="cp-di-map-note">{{ $call['note'] }}@include('site.courses._cites', ['keys' => ['ladd', 'dayoc']])</p>
                </figcaption>
            </figure>
        </div>
    </section>

    <section class="s-wrap cp-dk-demosec cp-fb-demosec" aria-labelledby="demo-h">
        <div class="cp-dk-demo cp-dk-screen" data-fb-demo data-ready="{{ $demo['ready'] }}" data-after="{{ $demo['after'] }}" data-say-veiled="{{ $demo['say']['veiled'] }}" data-say-bright="{{ $demo['say']['bright'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['tag'] }}</span></p>
            <div class="cp-dk-stage">
                <div class="cp-dk-roll cp-di-roll">
                    <p class="cp-di-read" aria-hidden="true">
                        @foreach (['veiled', 'bright'] as $colour)
                            <b data-fb-colour="{{ $colour }}" @if ($colour !== 'veiled') hidden @endif>{{ $demo['colours'][$colour] }}</b>
                        @endforeach
                        @foreach ((array) $demo['homes'] as $home => $name)
                            <span data-fb-home-name="{{ $home }}" @if ($home !== 'A3') hidden @endif>{{ $name }}</span>
                        @endforeach
                        @foreach ((array) $demo['filters'] as $colour => $filter)
                            <span data-fb-filter="{{ $colour }}" @if ($colour !== 'veiled') hidden @endif>{{ $filter }}</span>
                        @endforeach
                    </p>
                    <svg viewBox="0 0 600 200" preserveAspectRatio="none" aria-hidden="true" data-fb-spectrum>
                        <rect x="{{ $fx(1000) }}" y="8" width="{{ $fx(3000) }}" height="182" class="cp-fb-band" />
                        @foreach ([2000, 3000] as $f)
                            <path d="M{{ $fx($f) }} 8 V190" class="cp-di-hz" />
                        @endforeach
                        <path d="M0 190 H600" class="cp-dk-staff" />
                        <g data-fb-harmonics></g>
                    </svg>
                    <p class="cp-di-scale" aria-hidden="true">
                        @foreach ((array) $demo['ticks'] as $tick)
                            <span style="--at: {{ $loop->index / 5 }}">{{ $tick }}</span>
                        @endforeach
                    </p>
                    <p class="cp-dk-words" aria-hidden="true">
                        @foreach ((array) $demo['colours'] as $colour => $name)
                            <span data-fb-step="{{ $colour }}">{{ $loop->iteration }}. {{ $name }}</span>
                        @endforeach
                    </p>
                </div>
                <button type="button" class="cp-dk-big" data-fb-play="both" data-play="{{ $demo['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-big-icon" aria-hidden="true"></span><span data-label>{{ $demo['play'] }}</span></button>
            </div>
            <div class="cp-dk-caption">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}</p>
                    <p class="cp-dk-demo-lead">{{ $demo['how'] }}@include('site.courses._cites', ['keys' => ['unsw']])</p>
                </div>
                <div>
                    <fieldset class="cp-dk-pick cp-di-pick">
                        <legend>{{ $demo['voice_label'] }}</legend>
                        @foreach ((array) $demo['homes'] as $home => $name)
                            <button type="button" class="cp-dk-chip" data-fb-home="{{ $home }}" aria-pressed="{{ $home === 'A3' ? 'true' : 'false' }}"><span class="cp-dk-tick" aria-hidden="true"></span>{{ $name }}</button>
                        @endforeach
                    </fieldset>
                    <fieldset class="cp-dk-pick cp-di-pick">
                        <legend>{{ $demo['one_label'] }}</legend>
                        @foreach ((array) $demo['one'] as $colour => $name)
                            <button type="button" class="cp-dk-play {{ $loop->last ? 'cp-dk-play-b' : '' }}" data-fb-play="{{ $colour }}" data-play="{{ $name }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $name }}</span></button>
                        @endforeach
                    </fieldset>
                    <p class="cp-dk-say" role="status" data-fb-say>{{ $demo['ready'] }}</p>
                    <p class="cp-dk-key">
                        @foreach ((array) $demo['key'] as $key)
                            <span><i class="cp-di-dkey {{ ['cp-di-dkey-line', 'cp-fb-dkey-band'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-dk-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-fb-care" aria-labelledby="care-h">
        <div class="cp-di-plain-head cp-di-card">
            <div>
                <p class="cp-di-kick">{{ $love['kicks']['care'] }}</p>
                <h2 id="care-h" class="cp-di-title">{{ $page['titles']['care'] }}</h2>
            </div>
            <div>
                <p class="cp-di-body">{{ $page['care']['lead'] }}</p>
                <ul class="cp-di-ticks">
                    @foreach ((array) $page['care']['points'] as $point)
                        <li><x-site.icon name="info" size="16" /><span>{{ $point }}</span></li>
                    @endforeach
                </ul>
                <p class="cp-di-note"><a href="/courses/range-and-registers" class="s-arrow">{{ $rangeName }} <x-site.icon name="arrow" size="16" /></a></p>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-fb-aims" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="cp-di-kick">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-di-goals">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-di-n" aria-hidden="true">0{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-fb-plain" aria-labelledby="plain-h">
        <div class="cp-di-plain-head">
            <div>
                <p class="cp-di-kick">{{ $love['kicks']['plain'] }}</p>
                <h2 id="plain-h" class="cp-di-title">{{ $plain['title'] }}</h2>
            </div>
            <div>
                <p class="cp-di-body">{{ $plain['lead'] }}@include('site.courses._cites', ['keys' => ['unsw', 'bele']])</p>
                <p class="cp-di-body">{{ $plain['speech'] }}@include('site.courses._cites', ['keys' => ['henrich', 'dayoc']])</p>
            </div>
        </div>
        {{-- Folded on a phone (for length), open on a wide screen. --}}
        <details class="cp-di-more cp-fb-risk" data-fb-wide><summary><span>{{ $plain['risk_title'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
        <h3 class="cp-di-h3 cp-di-blur-h">{{ $plain['risk_title'] }}</h3>
        <p class="cp-di-small">{{ $plain['risk'] }}</p>
        <ol class="cp-di-nums">
            @foreach ((array) $plain['numbers'] as [$big, $what, $text, $cites])
                <li class="cp-di-card cp-di-num">
                    <p class="cp-di-big">{{ $big }}</p>
                    <h4>{{ $what }}</h4>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ol>
        </details>
    </section>

    <section class="cp-di-archive" aria-labelledby="archive-h">
        <div class="s-wrap s-sec">
            <p class="cp-di-kick">{{ $love['kicks']['archive'] }}</p>
            <h2 id="archive-h" class="cp-di-title">{{ $love['archive']['title'] }}</h2>
            {{-- The first two rows, then the rest folded on a phone (open on a wide screen). --}}
            @php($rows = (array) $love['archive']['rows'])
            @foreach ([array_slice($rows, 0, 2), array_slice($rows, 2)] as $part)
                @if ($loop->last && ! $loop->first)
                    <details class="cp-di-more" data-fb-wide><summary><span>{{ $love['archive']['more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
                @endif
                <ol class="cp-di-years" start="{{ $loop->first ? 1 : 3 }}">
                    @foreach ($part as [$year, $place, $title, $text, $cites])
                        <li>
                            <p class="cp-di-year"><span>{{ $year }}</span><span class="cp-di-place">{{ $place }}</span></p>
                            <div>
                                <h3>{{ $title }}</h3>
                                <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                @if ($loop->last && ! $loop->first)
                    </details>
                @endif
            @endforeach
        </div>
    </section>

    @if ($outline !== null)
        <section class="cp-di-climbsec" aria-labelledby="units-h">
            <div class="s-wrap s-sec">
                <p class="cp-di-kick">{{ $love['kicks']['climb'] }}</p>
                <div class="cp-di-head">
                    <h2 id="units-h" class="cp-di-title">{{ $page['titles']['units'] }}</h2>
                    <p class="cp-di-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <ol class="cp-di-units">
                    @foreach ($outline['units'] as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-di-card cp-di-unit" style="--step: {{ $loop->index }}">
                            <p class="cp-di-unit-n"><span class="cp-di-unit-dot" aria-hidden="true">{{ $loop->iteration }}</span><x-site.skin :game="$stage" :serious="$unitName" /></p>
                            <h3>{{ $unit['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-di-small">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            <ol class="cp-di-lessons">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li><span>{{ $lesson }}</span>@if ($open && $loop->parent->first && $loop->first)<a href="{{ $tryUrl }}" class="cp-di-free">{{ __('pages.site.course_free') }}</a>@endif</li>
                                @endforeach
                            </ol>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="s-wrap s-sec cp-di-first" aria-labelledby="try-h">
        <div>
            <p class="cp-di-kick">{{ $love['kicks']['first'] }}</p>
            <h2 id="try-h" class="cp-di-title">{{ $page['titles']['try'] }}</h2>
            <p class="cp-di-body">{{ $page['try']['lead'] }}</p>
            <ol class="cp-di-steps">
                @foreach ((array) $page['try']['steps'] as $step)
                    <li><span class="cp-di-step-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $step }}</p></li>
                @endforeach
            </ol>
            <p class="cp-di-note">{{ $page['try']['after'] }}</p>
        </div>
        <div class="cp-di-card cp-di-inside">
            <h2 id="inside-h" class="cp-di-h3">{{ $page['titles']['inside'] }}</h2>
            <p class="cp-di-small">{{ $page['measures'] }}</p>
            <p class="cp-di-small"><x-site.say :text="$page['scoring']" /></p>
        </div>
    </section>

    <section class="s-wrap s-sec cp-fb-margin" aria-labelledby="facts-h">
        <p class="cp-di-kick">{{ $love['kicks']['notes'] }}</p>
        <h2 id="facts-h" class="cp-di-title">{{ $love['facts_title'] }}</h2>
        @php($facts = (array) $love['facts'])
        @foreach ([array_slice($facts, 0, 2), array_slice($facts, 2)] as $part)
            @if ($loop->last && ! $loop->first)
                <details class="cp-di-more" data-fb-wide><summary><span>{{ $love['facts_more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
            @endif
            <ul class="cp-di-facts">
                @foreach ($part as [$text, $source, $cites])
                    <li>
                        <p class="cp-di-fact">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                        <p class="cp-di-src">{{ $source }}</p>
                    </li>
                @endforeach
            </ul>
            @if ($loop->last && ! $loop->first)
                </details>
            @endif
        @endforeach
    </section>

    <section class="s-wrap s-sec cp-fb-kit" aria-labelledby="kit-h">
        <h2 id="kit-h" class="cp-di-kick">{{ $love['kicks']['kit'] }}</h2>
        <div class="cp-di-kit-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul class="cp-di-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="cp-di-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div class="cp-di-safe"><h3>{{ $page['titles']['safety'] }}</h3><ul class="cp-di-ticks">@foreach ((array) $page['safety'] as $item)<li><x-site.icon name="info" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-di-fine" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-di-title">{{ $page['titles']['faq'] }}</h2>
        @include('site.courses._faq')
    </section>

    <section class="cp-di-close" aria-labelledby="join-h">
        <div class="s-wrap s-sec cp-di-close-in">
            <p class="cp-di-kick">{{ $love['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-di-title">{{ $page['titles']['join'] }}</h2>
            <p class="cp-di-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-di-note">{{ $page['close_care'] }}</p>
            <p class="cp-join-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                @if ($open)
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @else
                    <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
                @endif
            </p>
            @if ($open)
                <p class="cp-di-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-di-kick cp-di-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>

    <section class="s-wrap cp-di-sources" aria-labelledby="notes-h">
        <details data-fb-sources>
            <summary><h2 id="notes-h" class="cp-di-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/belting.js"></script>
</main>
