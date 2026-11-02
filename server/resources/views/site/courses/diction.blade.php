{{-- Diction for singers (/courses/diction), its own page: a love letter in Vibrato's calm, light design
     (white cards, thin headlines, the hero's picture in its own card with a legend), from the research pack
     (docs/courses/diction-love-letter.md), every claim with its numbered notes. The hero is a vowel map:
     ee, ah and oo as points on the F2 and F1 plane (Peterson and Barney's averages for men), with the e of
     "head". Then the featured demo, a vowel made of filters: a bright tone on C3 or C5 through two
     band-pass filters at each vowel's formants, its harmonics and the filters' shape drawn
     (js/public/diction.js). By the end (the outcomes), in plain words (the note lives on the vowel, why
     sung words blur, and a second demo: the consonant before the beat, the t on the click or just before
     it), from the archive (1562 to 1914), the course (the units, from the course file), lesson 1 beside
     what the app hears (pitch and timing only: it never hears words, vowels or consonants), margin notes
     (the facts), before you begin, questions, the close and the sources. No drawn people; every sound only
     when pressed, on the audio clock. Game skin: square arcade frames on the night. Words:
     lang/en/site.php courses.pages.diction (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($map = $love['map'])
@php($demo = $love['demo'])
@php($plain = $love['plain'])
@php($beat = $love['beat'])
@php($tryUrl = $open ? \App\Support\Http\Hosts::app('/learn/c/'.$page['app']) : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
{{-- The vowel map (Hz): F2 runs right to left, F1 top to bottom, as phoneticians draw it. --}}
@php($mapX = fn (float $f2): float => round(64 + (2400 - $f2) / 1700 * 350, 1))
@php($mapY = fn (float $f1): float => round(56 + ($f1 - 200) / 600 * 210, 1))
@php($points = ['ee' => [270, 2290], 'ah' => [730, 1090], 'oo' => [300, 870]])
@php($head = [500, 1800])
{{-- The beat deck: four beats 150 units apart (0.6 s), the t 0.09 s and the vowel 0.32 s long, at 250 units a second. --}}
@php($beatX = fn (int $i): int => 75 + $i * 150)
@php($tW = 22.5)
@php($vW = 80)
<main class="cp cp-diction" data-di>
    <section class="cp-hero cp-di-hero" aria-labelledby="page-h">
        <div class="s-wrap cp-hero-grid">
            <div class="cp-hero-copy">
                <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
                <p class="cp-hero-chip"><b>{{ __('pages.site.course_chip_plus') }}</b>@if ($outline !== null)<span>{{ __('pages.site.course_chip_lessons', ['n' => $outline['lessons']]) }}</span>@endif</p>
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
                @unless ($open)
                    <p class="cp-di-quiet">{{ __('pages.site.course_soon') }}</p>
                @endunless
            </div>
            <figure class="cp-hero-card cp-di-map">
                <svg viewBox="0 0 440 320" class="cp-di-map-art" aria-hidden="true">
                    @foreach ([2400, 2000, 1600, 1200, 800] as $f2)
                        <path d="M{{ $mapX($f2) }} 48 V274" class="cp-di-grid" />
                        <text x="{{ $mapX($f2) }}" y="292" text-anchor="middle" class="cp-di-tick">{{ number_format($f2) }}</text>
                    @endforeach
                    @foreach ([200, 400, 600, 800] as $f1)
                        <path d="M56 {{ $mapY($f1) }} H424" class="cp-di-grid" />
                        <text x="50" y="{{ $mapY($f1) + 5 }}" text-anchor="end" class="cp-di-tick">{{ $f1 }}</text>
                    @endforeach
                    <text x="240" y="316" text-anchor="middle" class="cp-di-axis">{{ $map['axis_x'] }}</text>
                    <text x="14" y="161" text-anchor="middle" transform="rotate(-90 14 161)" class="cp-di-axis">{{ $map['axis_y'] }}</text>
                    <path d="M{{ $mapX($points['ee'][1]) }} {{ $mapY($points['ee'][0]) }} L{{ $mapX($points['ah'][1]) }} {{ $mapY($points['ah'][0]) }} L{{ $mapX($points['oo'][1]) }} {{ $mapY($points['oo'][0]) }} Z" class="cp-di-tri" />
                    <circle cx="{{ $mapX($head[1]) }}" cy="{{ $mapY($head[0]) }}" r="8" class="cp-di-ring" />
                    <text x="{{ $mapX($head[1]) - 15 }}" y="{{ $mapY($head[0]) + 6 }}" text-anchor="end" class="cp-di-vname cp-di-vname-head">{{ $map['vowels']['head'] }}</text>
                    @foreach ($points as $vowel => [$f1, $f2])
                        <circle cx="{{ $mapX($f2) }}" cy="{{ $mapY($f1) }}" r="9" class="cp-di-dot" />
                        {{-- ee and oo name themselves under their points (clear of the triangle's top edge), ah to its right. --}}
                        <text x="{{ $vowel === 'ah' ? $mapX($f2) + 16 : $mapX($f2) }}" y="{{ $vowel === 'ah' ? $mapY($f1) - 10 : $mapY($f1) + 34 }}" text-anchor="{{ $vowel === 'ah' ? 'start' : 'middle' }}" class="cp-di-vname">{{ $map['vowels'][$vowel] }}</text>
                    @endforeach
                </svg>
                <figcaption>
                    <ul class="cp-hero-legend">
                        @foreach ((array) $map['legend'] as $key)
                            <li><i class="cp-di-key cp-di-key-{{ ['dot', 'ring'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</li>
                        @endforeach
                    </ul>
                    <p class="cp-di-map-note">{{ $map['note'] }}@include('site.courses._cites', ['keys' => ['ccrma', 'unsw']])</p>
                </figcaption>
            </figure>
        </div>
    </section>

    <section class="s-wrap cp-dk-demosec cp-di-demosec" aria-labelledby="demo-h">
        <div class="cp-dk-demo cp-dk-screen" data-di-demo data-say="{{ $demo['say'] }}" data-after="{{ $demo['after'] }}" data-say-hold="{{ $demo['say_hold'] }}" data-read="{{ $demo['read'] }}" data-notes="{{ json_encode($demo['notes']) }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['tag'] }}</span></p>
            <div class="cp-dk-stage">
                <div class="cp-dk-roll cp-di-roll">
                    <p class="cp-di-read" aria-hidden="true"><b data-di-vowel>{{ $map['vowels']['ah'] }}</b><span data-di-note>{{ $demo['notes']['C3'] }}</span><span data-di-formants>{{ strtr($demo['read'], [':f1' => '730', ':f2' => '1090']) }}</span></p>
                    <svg viewBox="0 0 600 200" preserveAspectRatio="none" aria-hidden="true" data-di-spectrum>
                        @foreach ([1000, 2000] as $f)
                            <path d="M{{ $f / 5 }} 8 V190" class="cp-di-hz" />
                        @endforeach
                        <path d="M0 190 H600" class="cp-dk-staff" />
                        <g data-di-harmonics></g>
                        <path d="" class="cp-di-curve" data-di-curve />
                    </svg>
                    <p class="cp-di-scale" aria-hidden="true">
                        @foreach ((array) $demo['ticks'] as $tick)
                            <span style="--at: {{ $loop->index / 3 }}">{{ $tick }}</span>
                        @endforeach
                    </p>
                </div>
                <button type="button" class="cp-dk-big" data-di-play="tour" data-play="{{ $demo['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-big-icon" aria-hidden="true"></span><span data-label>{{ $demo['play'] }}</span></button>
            </div>
            <div class="cp-dk-caption">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}</p>
                    <p class="cp-dk-demo-lead">{{ $demo['how'] }}@include('site.courses._cites', ['keys' => ['ccrma', 'unsw']])</p>
                </div>
                <div>
                    <fieldset class="cp-dk-pick cp-di-pick">
                        <legend>{{ $demo['vowel_label'] }}</legend>
                        @foreach (['ee', 'ah', 'oo'] as $vowel)
                            <button type="button" class="cp-dk-chip" data-di-vowel-pick="{{ $vowel }}" aria-pressed="{{ $vowel === 'ah' ? 'true' : 'false' }}"><span class="cp-dk-tick" aria-hidden="true"></span>{{ $map['vowels'][$vowel] }}</button>
                        @endforeach
                    </fieldset>
                    <fieldset class="cp-dk-pick cp-di-pick">
                        <legend>{{ $demo['note_label'] }}</legend>
                        @foreach ((array) $demo['notes'] as $note => $name)
                            <button type="button" class="cp-dk-chip" data-di-note-pick="{{ $note }}" aria-pressed="{{ $note === 'C3' ? 'true' : 'false' }}"><span class="cp-dk-tick" aria-hidden="true"></span>{{ $name }}</button>
                        @endforeach
                    </fieldset>
                    <p class="cp-dk-btns"><button type="button" class="cp-dk-play" data-di-play="hold" data-play="{{ $demo['hold'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['hold'] }}</span></button></p>
                    <p class="cp-dk-say" role="status" data-di-say>{{ $demo['ready'] }}</p>
                    <p class="cp-dk-key">
                        @foreach ((array) $demo['key'] as $key)
                            <span><i class="cp-di-dkey cp-di-dkey-{{ ['line', 'curve'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-dk-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap s-sec cp-di-aims" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="cp-di-kick">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-di-goals">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-di-n" aria-hidden="true">0{{ $loop->iteration }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap s-sec cp-di-plain" aria-labelledby="plain-h">
        <div class="cp-di-plain-head">
            <div>
                <p class="cp-di-kick">{{ $love['kicks']['plain'] }}</p>
                <h2 id="plain-h" class="cp-di-title">{{ $plain['title'] }}</h2>
            </div>
            <div>
                <p class="cp-di-body">{{ $plain['lead'] }}@include('site.courses._cites', ['keys' => ['unsw']])</p>
                <p class="cp-di-body">{{ $plain['formants'] }}@include('site.courses._cites', ['keys' => ['unsw']])</p>
            </div>
        </div>
        <h3 class="cp-di-h3 cp-di-blur-h">{{ $plain['blur_title'] }}</h3>
        <p class="cp-di-small">{{ $plain['blur'] }}</p>
        <ol class="cp-di-nums">
            @foreach ((array) $plain['numbers'] as [$big, $what, $text, $cites])
                <li class="cp-di-card cp-di-num">
                    <p class="cp-di-big">{{ $big }}</p>
                    <h4>{{ $what }}</h4>
                    <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                </li>
            @endforeach
        </ol>
        <div class="cp-di-pair">
            <div class="cp-dk-demo cp-di-beat" data-di-beat data-say-a="{{ $beat['say_a'] }}" data-say-b="{{ $beat['say_b'] }}" data-after="{{ $beat['after'] }}">
                <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $beat['kick'] }}</span><span>{{ $beat['tag'] }}</span></p>
                <div class="cp-di-deck-in">
                    <h3 class="cp-di-beat-h">{{ $beat['title'] }}</h3>
                    <p class="cp-di-beat-lead">{{ $beat['lead'] }}</p>
                    <div class="cp-dk-roll cp-di-beat-roll">
                        <p class="cp-di-lanes" aria-hidden="true">
                            @foreach ((array) $beat['lanes'] as $lane)
                                <span data-di-lane="{{ ['a', 'b'][$loop->index] }}">{{ $lane }}</span>
                            @endforeach
                        </p>
                        <svg viewBox="0 0 600 150" preserveAspectRatio="none" aria-hidden="true">
                            @foreach (range(0, 3) as $i)
                                <path d="M{{ $beatX($i) }} 6 V144" class="cp-di-beatline" data-di-click="{{ $i }}" />
                            @endforeach
                            @foreach (['a' => 45, 'b' => 105] as $lane => $y)
                                @foreach (range(0, 3) as $i)
                                    @php($t0 = $lane === 'a' ? $beatX($i) : $beatX($i) - $tW)
                                    <rect x="{{ $t0 }}" y="{{ $y - 9 }}" width="{{ $tW }}" height="18" class="cp-di-t" data-di-tah="{{ $lane }}-{{ $i }}" />
                                    <rect x="{{ $t0 + $tW }}" y="{{ $y - 9 }}" width="{{ $vW }}" height="18" rx="4" class="cp-di-v" data-di-tah="{{ $lane }}-{{ $i }}" />
                                @endforeach
                            @endforeach
                        </svg>
                    </div>
                    <p class="cp-dk-btns">
                        <button type="button" class="cp-dk-play" data-di-play="a" data-play="{{ $beat['play_a'] }}" data-stop="{{ $beat['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $beat['play_a'] }}</span></button>
                        <button type="button" class="cp-dk-play cp-dk-play-b" data-di-play="b" data-play="{{ $beat['play_b'] }}" data-stop="{{ $beat['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $beat['play_b'] }}</span></button>
                    </p>
                    <p class="cp-dk-say" role="status" data-di-beat-say>{{ $beat['ready'] }}</p>
                    <p class="cp-dk-key">
                        @foreach ((array) $beat['key'] as $key)
                            <span><i class="cp-di-dkey cp-di-dkey-{{ ['t', 'v', 'beat'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-dk-note">{{ $beat['note'] }}</p>
                </div>
            </div>
            <div class="cp-di-pair-text">
                {{-- Folded on a phone (the courses lead's cut for length), open on a wide screen. --}}
                <details class="cp-di-more" data-di-wide><summary><span>{{ $plain['consonants_more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
                <h3 class="cp-di-h3">{{ $plain['time_title'] }}</h3>
                <p class="cp-di-body">{{ $plain['time'] }}@include('site.courses._cites', ['keys' => ['garcia']])</p>
                <h3 class="cp-di-h3 cp-di-hold-h">{{ $plain['hold_title'] }}</h3>
                <p class="cp-di-body">{{ $plain['hold'] }}@include('site.courses._cites', ['keys' => ['garcia', 'unsw']])</p>
                </details>
            </div>
        </div>
    </section>

    <section class="cp-di-archive" aria-labelledby="archive-h">
        <div class="s-wrap s-sec">
            <p class="cp-di-kick">{{ $love['kicks']['archive'] }}</p>
            <h2 id="archive-h" class="cp-di-title">{{ $love['archive']['title'] }}</h2>
            {{-- The first two years, then the rest folded on a phone (open on a wide screen). --}}
            @php($rows = (array) $love['archive']['rows'])
            @foreach ([array_slice($rows, 0, 2), array_slice($rows, 2)] as $part)
                @if ($loop->last && ! $loop->first)
                    <details class="cp-di-more" data-di-wide><summary><span>{{ $love['archive']['more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
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

    <section class="s-wrap s-sec cp-di-margin" aria-labelledby="facts-h">
        <p class="cp-di-kick">{{ $love['kicks']['notes'] }}</p>
        <h2 id="facts-h" class="cp-di-title">{{ $love['facts_title'] }}</h2>
        @php($facts = (array) $love['facts'])
        @foreach ([array_slice($facts, 0, 2), array_slice($facts, 2)] as $part)
            @if ($loop->last && ! $loop->first)
                <details class="cp-di-more" data-di-wide><summary><span>{{ $love['facts_more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
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

    <section class="s-wrap s-sec cp-di-kit" aria-labelledby="kit-h">
        <h2 id="kit-h" class="cp-di-kick">{{ $love['kicks']['kit'] }}</h2>
        <div class="cp-di-kit-grid">
            <div><h3>{{ $page['titles']['who'] }}</h3><ul class="cp-di-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div><h3>{{ $page['titles']['need'] }}</h3><ul class="cp-di-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            <div class="cp-di-safe"><h3>{{ $page['titles']['safety'] }}</h3><ul class="cp-di-ticks">@foreach ((array) $page['safety'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
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
            <p class="cp-join-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                @if ($open)
                    <a href="/pricing" class="btn-secondary btn-lg">{{ __('pages.site.course_plus') }}</a>
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
        <details data-di-sources>
            <summary><h2 id="notes-h" class="cp-di-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/diction.js"></script>
</main>
