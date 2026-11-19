{{-- Diction for singers (/courses/diction), its own page after Hillel's design (2026-11-18,
     ~/Code/course-designs/diction), built around the language of phonetics: headlines in a serif made for phonetic
     symbols (Gentium Book Plus, the site's serif behind it), every section marked with symbols in IPA instead of numbers,
     the sounds the course's own English (r dropped before a consonant, as unit 1 teaches). The hero's "Every word,
     heard." with the vowels it sings in colour, their IPA under it, the vowel map (ee, ah and oo on the F2 and F1 plane,
     Peterson and Barney's averages for men, with the e of "head"), and a strip of the three words as sung: each vowel on
     its beat, the consonants squeezed between. The featured demo, a vowel made of filters, and the second, the consonant
     before the beat with its click track and a line moving across the timeline (js/public/diction.js, on voices.js and
     Web Audio, every sound and mark on the audio clock, only when pressed). In plain words (UNSW's vowel-less sentence set
     large), the archive as a hymnal page with each year's own words pulled out, the units each with a symbol, lesson 1
     with Amazing Grace's line from its template (App\Support\Site\DictionPage, which holds the page's symbols and drawings: the stresses on the bar's first beats, the
     light "the"), the margin notes, before you begin, the questions, the close on the dark and the sources. Facts only
     from the research pack (docs/courses/diction-love-letter.md), each with its numbered notes. No drawn people. Words:
     lang/en/site.php courses.pages.diction (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($map = $love['map'])
@php($hero = $love['hero'])
@php($demo = $love['demo'])
@php($plain = $love['plain'])
@php($beat = $love['beat'])
@php($lesson = $love['lesson'])
@php($english = app()->getLocale() === 'en')
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($dn = \App\Support\Site\DictionPage::class)
@php($drawn = \App\Support\Site\DictionPage::map())
@php($strip = \App\Support\Site\DictionPage::strip((string) $hero['strip']))
@php($lanes = \App\Support\Site\DictionPage::lanes())
@php($widths = \App\Support\Site\DictionPage::widths())
@php($line = \App\Support\Site\DictionPage::grace())
@php($lessonPages = \App\Support\Site\DictionPage::lessonPages($page['slug']))
<main class="cp cp-dn" data-di>
    <section class="cp-dn-hero" aria-labelledby="page-h">
        <div class="s-wrap">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <div class="cp-dn-top">
                <div>
                    @if ($open)
                        <p class="cp-dn-chip"><b>{{ __('pages.site.course_chip_free') }}</b>@if ($outline !== null)<span>{{ __('pages.site.course_chip_lessons', ['n' => $outline['lessons']]) }} · {{ strtr($hero['units'], [':n' => count($outline['units'])]) }}</span>@endif</p>
                    @else
                        <p class="s-soonpill"><x-site.soon /></p>
                    @endif
                    <h1 id="page-h" class="cp-dn-h1">{{ $dn::marked($hero['marked'], 'cp-dn-v') }}</h1>
                    <p class="cp-dn-ipa cp-dn-hero-ipa">{{ $hero['ipa'] }}</p>
                    <p class="cp-dn-lede">{{ $page['hero_lead'] }}</p>
                    <ul class="cp-dn-herofacts">
                        @foreach ((array) $page['hero_facts'] as $fact)
                            <li><span aria-hidden="true">ː</span>{{ $fact }}</li>
                        @endforeach
                    </ul>
                    <p class="cp-dn-btns">
                        <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                        <a href="{{ $outline !== null ? '#units-h' : '#outcomes-h' }}" class="btn-secondary btn-lg">{{ $outline !== null ? $page['see_lessons'] : $page['see_aims'] }}</a>
                    </p>
                </div>
                <figure class="cp-dn-map">
                    <p class="cp-dn-map-head"><span class="cp-dn-kick">{{ $map['kick'] }}</span><span class="cp-dn-mono">{{ $map['unit'] }}</span></p>
                    <svg viewBox="0 0 480 290" class="cp-dn-map-art" role="img" aria-label="{{ $map['note'] }}">
                        @foreach ($drawn['xs'] as [$x, $hz])
                            <path d="M{{ $x }} 20 V260" class="cp-dn-grid" />
                            <text x="{{ $x }}" y="282" text-anchor="middle" class="cp-dn-tick">{{ $hz }}</text>
                        @endforeach
                        @foreach ($drawn['ys'] as [$y, $hz])
                            <path d="M60 {{ $y }} H440" class="cp-dn-grid" />
                            <text x="52" y="{{ $y + 4 }}" text-anchor="end" class="cp-dn-tick">{{ $hz }}</text>
                        @endforeach
                        <text x="10" y="140" text-anchor="middle" transform="rotate(-90 10 140)" class="cp-dn-axis">{{ $map['axis_y'] }}</text>
                        <path d="{{ $drawn['triangle'] }}" class="cp-dn-tri" />
                        @foreach ($drawn['points'] as $vowel => $point)
                            <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="7" class="cp-dn-dot" />
                            <text x="{{ $point['lx'] }}" y="{{ $point['ly'] }}" class="cp-dn-sym">{{ $point['ipa'] }}<tspan class="cp-dn-sym-key" dx="3">{{ $map['vowels'][$vowel] }}</tspan></text>
                        @endforeach
                        <circle cx="{{ $drawn['head']['x'] }}" cy="{{ $drawn['head']['y'] }}" r="7" class="cp-dn-ring" />
                        <text x="{{ $drawn['head']['x'] + 14 }}" y="{{ $drawn['head']['y'] - 4 }}" class="cp-dn-sym cp-dn-sym-head">{{ $drawn['head']['ipa'] }}<tspan class="cp-dn-sym-key" dx="4">{{ $map['head_word'] }}</tspan></text>
                    </svg>
                    <p class="cp-dn-map-axis" aria-hidden="true"><span>{{ $map['front'] }}</span><span>{{ $map['axis_x'] }}</span><span>{{ $map['back'] }}</span></p>
                    <figcaption>
                        <ul class="cp-dn-legend">
                            @foreach ((array) $map['legend'] as $key)
                                <li><i class="cp-dn-key cp-dn-key-{{ ['dot', 'ring'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</li>
                            @endforeach
                        </ul>
                        <p class="cp-dn-map-note">{{ $map['note'] }}@include('site.courses._cites', ['keys' => ['ccrma', 'unsw']])</p>
                    </figcaption>
                </figure>
            </div>

            {{-- The three words as sung: a vowel a beat, the consonants squeezed into the gaps. --}}
            <figure class="cp-dn-strip">
                <div class="cp-dn-strip-head">
                    <figcaption class="cp-dn-kick">{{ $hero['strip_kick'] }}</figcaption>
                    <p class="cp-dn-strip-key">
                        @foreach ((array) $hero['strip_key'] as $key)
                            <span><i class="cp-dn-sk cp-dn-sk-{{ ['v', 'c', 'b'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                </div>
                <div class="cp-dn-strip-row" aria-hidden="true">
                    @foreach ($strip['lead'] as $c)
                        <span class="cp-dn-sc"><b>{{ $c }}</b></span>
                    @endforeach
                    @foreach ($strip['beats'] as $b)
                        <span class="cp-dn-sv"><b>{{ $b['vowel'] }}</b></span>
                        @foreach ($b['after'] as $c)
                            <span class="cp-dn-sc"><b>{{ $c }}</b></span>
                        @endforeach
                    @endforeach
                </div>
                <p class="cp-dn-strip-note">{{ $hero['strip_note'] }}</p>
            </figure>
        </div>
    </section>

    {{-- The featured demo: a vowel made of filters. --}}
    <section class="s-wrap cp-dn-demosec" aria-labelledby="demo-h">
        <div class="cp-dn-deck" data-di-demo data-say="{{ $demo['say'] }}" data-after="{{ $demo['after'] }}" data-say-hold="{{ $demo['say_hold'] }}" data-read="{{ $demo['read'] }}" data-notes="{{ json_encode($demo['notes']) }}">
            <p class="cp-dn-bar"><span class="cp-dn-live"><span class="cp-dn-rec" aria-hidden="true"></span>{{ $demo['live'] }}</span><span>{{ $demo['tag'] }}</span></p>
            <div class="cp-dn-screen">
                <p class="cp-dn-read" aria-hidden="true"><b>[<span data-di-ipa>{{ $drawn['points']['ah']['ipa'] }}</span>]</b><span class="cp-dn-read-key" data-di-vowel>{{ $map['vowels']['ah'] }}</span><span class="cp-dn-mono"><span data-di-note>{{ $demo['notes']['C3'] }}</span> · <span data-di-formants>{{ strtr($demo['read'], [':f1' => '730', ':f2' => '1090']) }}</span></span></p>
                <svg viewBox="0 0 600 200" preserveAspectRatio="none" class="cp-dn-spec" aria-hidden="true" data-di-spectrum>
                    @foreach ([1000, 2000] as $f)
                        <path d="M{{ $f / 5 }} 4 V190" class="cp-dn-hz" />
                    @endforeach
                    <path d="" class="cp-dn-curve" data-di-curve />
                    <g data-di-harmonics></g>
                    <path d="M0 190 H600" class="cp-dn-base" />
                </svg>
                <p class="cp-dn-scale" aria-hidden="true">
                    @foreach ((array) $demo['ticks'] as $tick)
                        <span style="--at: {{ $loop->index / 3 }}">{{ $tick }}</span>
                    @endforeach
                </p>
            </div>
            <div class="cp-dn-deck-foot">
                <div>
                    <h2 id="demo-h" class="cp-dn-deck-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dn-deck-p">{{ $demo['lead'] }}</p>
                    <p class="cp-dn-deck-p">{{ $demo['how'] }}@include('site.courses._cites', ['keys' => ['ccrma', 'unsw']])</p>
                </div>
                <div>
                    <fieldset class="cp-dn-pick">
                        <legend>{{ $demo['vowel_label'] }}</legend>
                        @foreach ($drawn['points'] as $vowel => $point)
                            <button type="button" class="cp-dn-chip-b" data-di-vowel-pick="{{ $vowel }}" data-ipa="{{ $point['ipa'] }}" aria-pressed="{{ $vowel === 'ah' ? 'true' : 'false' }}"><span class="cp-dn-chip-ipa" aria-hidden="true">{{ $point['ipa'] }}</span><span data-name>{{ $map['vowels'][$vowel] }}</span></button>
                        @endforeach
                    </fieldset>
                    <fieldset class="cp-dn-pick">
                        <legend>{{ $demo['note_label'] }}</legend>
                        @foreach ((array) $demo['notes'] as $note => $name)
                            <button type="button" class="cp-dn-chip-b cp-dn-mono" data-di-note-pick="{{ $note }}" aria-pressed="{{ $note === 'C3' ? 'true' : 'false' }}"><span data-name>{{ $name }}</span></button>
                        @endforeach
                    </fieldset>
                    <p class="cp-dn-plays">
                        <button type="button" class="cp-dn-play cp-dn-play-main" data-di-play="tour" data-play="{{ $demo['play'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dn-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['play'] }}</span></button>
                        <button type="button" class="cp-dn-play" data-di-play="hold" data-play="{{ $demo['hold'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dn-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['hold'] }}</span></button>
                    </p>
                    <p class="cp-dn-say" role="status" data-di-say>{{ $demo['ready'] }}</p>
                    <p class="cp-dn-dkeys">
                        @foreach ((array) $demo['key'] as $key)
                            <span><i class="cp-dn-dk cp-dn-dk-{{ ['line', 'curve'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-dn-deck-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-dn-sec cp-dn-aims" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="cp-dn-kick">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-dn-goals">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li><span class="cp-dn-glyph" aria-hidden="true">{{ $dn::AIMS[$loop->index] ?? '' }}</span><p>{{ $outcome }}</p></li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap cp-dn-sec cp-dn-plain" aria-labelledby="plain-h">
        <p class="cp-dn-head"><span class="cp-dn-kick">{{ $love['kicks']['plain'] }}</span>@if ($english)<span class="cp-dn-ipa" aria-hidden="true">{{ $plain['ipa'] }}</span>@endif</p>
        <div class="cp-dn-gone">
            <p class="cp-dn-gone-k">{{ $plain['gone_kick'] }}</p>
            <p class="cp-dn-gone-line" lang="en">{{ $plain['gone'] }}</p>
            <p class="cp-dn-gone-whole" lang="en">{{ $dn::marked($plain['whole'], 'cp-dn-v') }}</p>
            <p class="cp-dn-gone-after">{{ $plain['gone_after'] }}@include('site.courses._cites', ['keys' => ['unsw']])</p>
        </div>
        <div class="cp-dn-split">
            <h2 id="plain-h" class="cp-dn-h2">{{ $dn::marked($plain['title_marked'], 'cp-dn-em') }}</h2>
            <div>
                <p class="cp-dn-body">{{ $plain['lead'] }}@include('site.courses._cites', ['keys' => ['unsw']])</p>
                <p class="cp-dn-body">{{ $plain['formants'] }}@include('site.courses._cites', ['keys' => ['unsw']])</p>
            </div>
        </div>
        <h3 class="cp-dn-h3 cp-dn-blur-h">{{ $plain['blur_title'] }}</h3>
        <p class="cp-dn-small">{{ $plain['blur'] }}</p>
        <ul class="cp-dn-blur">
            @foreach ((array) $plain['numbers'] as [$big, $what, $text, $cites])
                <li class="cp-dn-card cp-dn-blur-card">
                    @if ($loop->first)
                        <p class="cp-dn-zs" aria-hidden="true">@foreach ($dn::BUZZ as $sound)<span>{{ $sound }}</span>@endforeach</p>
                    @else
                        <p class="cp-dn-hz-big"><span>{{ explode(' ', $big)[0] }}</span><small>{{ $plain['hz_apart'] }}</small></p>
                    @endif
                    <div>
                        <h4>{{ $what }}</h4>
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    </div>
                </li>
            @endforeach
        </ul>
        <div class="cp-dn-pair">
            {{-- The second demo: four "tah"s on a click, the t on the beat (A) or just before it (B). --}}
            <div class="cp-dn-deck cp-dn-beat" data-di-beat data-say-a="{{ $beat['say_a'] }}" data-say-b="{{ $beat['say_b'] }}" data-after="{{ $beat['after'] }}">
                <p class="cp-dn-bar"><span class="cp-dn-live"><span class="cp-dn-rec" aria-hidden="true"></span>{{ $beat['kick'] }}</span><span class="cp-dn-ipa" aria-hidden="true">{{ $dn::TAH }}</span></p>
                <div class="cp-dn-beat-in">
                    <h3 class="cp-dn-deck-h">{{ $beat['title'] }}</h3>
                    <p class="cp-dn-deck-p">{{ $beat['lead'] }}</p>
                    <div class="cp-dn-lanes" aria-hidden="true">
                        @foreach (['a', 'b'] as $lane)
                            <span class="cp-dn-lane-n" data-di-lane="{{ $lane }}">{{ $beat['lanes'][$loop->index] }}</span>
                            <span class="cp-dn-track cp-dn-track-{{ $lane }}" data-di-track="{{ $lane }}">
                                @foreach ($lanes[$lane] as $i => $tah)
                                    <i class="cp-dn-beatline" data-di-click="{{ $i }}" style="left: {{ $tah['beat'] }}%"></i>
                                    <i class="cp-dn-t" data-di-tah="{{ $lane }}-{{ $i }}" style="left: {{ $tah['t'] }}%; width: {{ $widths['t'] }}%"></i>
                                    <i class="cp-dn-tv" data-di-tah="{{ $lane }}-{{ $i }}" style="left: {{ $tah['v'] }}%; width: {{ $widths['v'] }}%"></i>
                                @endforeach
                                <i class="cp-dn-ph" data-di-ph></i>
                            </span>
                        @endforeach
                    </div>
                    <p class="cp-dn-plays">
                        <button type="button" class="cp-dn-play cp-dn-play-a" data-di-play="a" data-play="{{ $beat['play_a'] }}" data-stop="{{ $beat['stop'] }}" aria-pressed="false"><span class="cp-dn-play-icon" aria-hidden="true"></span><span data-label>{{ $beat['play_a'] }}</span></button>
                        <button type="button" class="cp-dn-play cp-dn-play-b" data-di-play="b" data-play="{{ $beat['play_b'] }}" data-stop="{{ $beat['stop'] }}" aria-pressed="false"><span class="cp-dn-play-icon" aria-hidden="true"></span><span data-label>{{ $beat['play_b'] }}</span></button>
                    </p>
                    <p class="cp-dn-say" role="status" data-di-beat-say>{{ $beat['ready'] }}</p>
                    <p class="cp-dn-dkeys">
                        @foreach ((array) $beat['key'] as $key)
                            <span><i class="cp-dn-dk cp-dn-dk-{{ ['t', 'v', 'beat'][$loop->index] }}" aria-hidden="true"></i>{{ $key }}</span>
                        @endforeach
                    </p>
                    <p class="cp-dn-deck-note">{{ $beat['note'] }}</p>
                </div>
            </div>
            <div class="cp-dn-pair-text">
                <h3 class="cp-dn-h3">{{ $plain['time_title'] }}</h3>
                <p class="cp-dn-body">{{ $plain['time'] }}@include('site.courses._cites', ['keys' => ['garcia']])</p>
                <h3 class="cp-dn-h3 cp-dn-hold-h">{{ $plain['hold_title'] }}</h3>
                <p class="cp-dn-body">{{ $plain['hold'] }}@include('site.courses._cites', ['keys' => ['garcia', 'unsw']])</p>
                {{-- Garcia's sounds that flow on: the voiced ones solid, the hissing ones dashed. --}}
                <p class="cp-dn-tiles" aria-hidden="true">
                    @foreach ($dn::VOICED as $c)<span>{{ $c }}</span>@endforeach
                    @foreach ($dn::AIR as $c)<span class="cp-dn-tile-air">{{ $c }}</span>@endforeach
                </p>
                <p class="cp-dn-small cp-dn-tiles-note">{{ $plain['tiles_note'] }}</p>
            </div>
        </div>
    </section>

    {{-- The archive: a hymnal page, each year with its own words pulled out. --}}
    <section class="cp-dn-archive" aria-labelledby="archive-h">
        <div class="s-wrap">
            <p class="cp-dn-head"><span class="cp-dn-kick cp-dn-kick-amber">{{ $love['kicks']['archive'] }}</span><span class="cp-dn-ipa">{{ $love['archive']['range'] }}</span></p>
            <h2 id="archive-h" class="cp-dn-h2">{{ $dn::marked($love['archive']['title_marked'], 'cp-dn-it') }}</h2>
            {{-- The first two years, then the rest folded on a phone (open on a wide screen). --}}
            @php($rows = (array) $love['archive']['rows'])
            @foreach ([array_slice($rows, 0, 2), array_slice($rows, 2)] as $part)
                @if ($loop->last && ! $loop->first)
                    <details class="cp-dn-more" data-di-wide><summary><span>{{ $love['archive']['more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
                @endif
                <ol class="cp-dn-years" start="{{ $loop->first ? 1 : 3 }}">
                    @foreach ($part as [$year, $place, $title, $quote, $text, $cites])
                        <li>
                            <p class="cp-dn-year"><span>{{ $year }}</span><span class="cp-dn-place">{{ $place }}</span></p>
                            <div>
                                <blockquote class="cp-dn-pull"><p>“{{ $quote }}”@include('site.courses._cites', ['keys' => $cites])</p></blockquote>
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
        <section class="s-wrap cp-dn-sec cp-dn-course" aria-labelledby="units-h">
            <p class="cp-dn-head"><span class="cp-dn-kick">{{ $love['kicks']['climb'] }}</span><span class="cp-dn-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</span></p>
            <h2 id="units-h" class="cp-dn-h2 cp-dn-h2-wide">{{ $page['titles']['units'] }}</h2>
            <p class="cp-dn-climb" aria-hidden="true">
                @foreach ($dn::CLIMB as $step)
                    @unless ($loop->first)<x-site.icon name="arrow" size="16" />@endunless<span>{{ $step }}</span>
                @endforeach
            </p>
            <ol class="cp-dn-units">
                @php($n = 0)
                @foreach ($outline['units'] as $unit)
                    @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                    @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                    <li class="cp-dn-unit">
                        <p class="cp-dn-unit-n"><x-site.skin :game="$stage" :serious="$unitName" /></p>
                        <p class="cp-dn-glyph" aria-hidden="true">{{ $dn::UNITS[$loop->index] ?? '' }}</p>
                        <h3>{{ $unit['title'] }}</h3>
                        @isset($page['units_say'][$loop->index])
                            <p class="cp-dn-small">{{ $page['units_say'][$loop->index] }}</p>
                        @endisset
                        <ol class="cp-dn-lessons">
                            @foreach ($unit['lessons'] as $title)
                                @php($n++)
                                <li>
                                    <span class="cp-dn-lesson"><span class="cp-dn-mono" aria-hidden="true">{{ str_pad((string) $n, 2, '0', STR_PAD_LEFT) }}</span>@if (isset($lessonPages[$title]))<a href="{{ $lessonPages[$title] }}">{{ $title }}</a>@else{{ $title }}@endif</span>
                                    @if ($open && $loop->parent->first && $loop->first)<a href="{{ $tryUrl }}" class="cp-dn-free">{{ __('pages.site.course_free') }}</a>@endif
                                </li>
                            @endforeach
                        </ol>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    <section class="s-wrap cp-dn-sec cp-dn-first" aria-labelledby="try-h">
        <div>
            <p class="cp-dn-kick">{{ $love['kicks']['first'] }}</p>
            <h2 id="try-h" class="cp-dn-h2">{{ $dn::marked($lesson['title_marked'], 'cp-dn-it') }}</h2>
            <p class="cp-dn-lede cp-dn-first-lead">{{ $page['try']['lead'] }}</p>
            @if ($line !== [])
                <figure class="cp-dn-card cp-dn-line">
                    <p class="sr-only">{{ $lesson['line_alt'] }}</p>
                    <p class="cp-dn-sylls" lang="en" aria-hidden="true">
                        @foreach ($line as $syllable)
                            <span @class(['cp-dn-syl', 'cp-dn-syl-on' => $syllable['stressed'], 'cp-dn-syl-light' => $syllable['light']])><b>{{ $syllable['word'] }}@if ($syllable['joined'])<span class="cp-dn-hy">-</span>@endif</b><i>{{ $syllable['ipa'] }}</i></span>
                        @endforeach
                    </p>
                    <figcaption>
                        <span class="cp-dn-line-key" aria-hidden="true"><span><i class="cp-dn-lk cp-dn-lk-on"></i>{{ $lesson['line_key'][0] }}</span><span><i class="cp-dn-lk cp-dn-lk-light"></i>{{ $lesson['line_key'][1] }}</span></span>
                        <span>{{ $lesson['line_note'] }}</span>
                    </figcaption>
                </figure>
            @endif
            <ol class="cp-dn-steps">
                @foreach ((array) $page['try']['steps'] as $step)
                    <li><span class="cp-dn-step-n" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $step }}</p></li>
                @endforeach
            </ol>
            <p class="cp-dn-note">{{ $page['try']['after'] }}</p>
        </div>
        <div class="cp-dn-card cp-dn-inside">
            <h2 id="inside-h" class="cp-dn-h3 cp-dn-serif">{{ $page['titles']['inside'] }}</h2>
            <dl class="cp-dn-hears">
                <div><dt>{{ $lesson['hears'][0] }}</dt><dd>{{ $lesson['hears'][1] }}</dd></div>
                <div class="cp-dn-hears-ear"><dt>{{ $lesson['ear'][0] }}</dt><dd>{{ $lesson['ear'][1] }}</dd></div>
            </dl>
            <p class="cp-dn-small">{{ $page['measures'] }}</p>
            <p class="cp-dn-small"><x-site.say :text="$page['scoring']" /></p>
        </div>
    </section>

    <section class="s-wrap cp-dn-sec cp-dn-margin" aria-labelledby="facts-h">
        <p class="cp-dn-kick">{{ $love['kicks']['notes'] }}</p>
        <h2 id="facts-h" class="cp-dn-h2">{{ $love['facts_title'] }}</h2>
        @php($facts = (array) $love['facts'])
        @foreach ([array_slice($facts, 0, 2, true), array_slice($facts, 2, null, true)] as $part)
            @if ($loop->last && ! $loop->first)
                <details class="cp-dn-more" data-di-wide><summary><span>{{ $love['facts_more'] }}</span><span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
            @endif
            <ul class="cp-dn-facts">
                @foreach ($part as $i => [$text, $source, $cites])
                    <li>
                        <p @class(['cp-dn-glyph', 'cp-dn-glyph-words' => str_contains($dn::NOTES[$i] ?? '', ' ')]) aria-hidden="true">{{ $dn::NOTES[$i] ?? '' }}</p>
                        <div>
                            <p class="cp-dn-fact">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                            <p class="cp-dn-src">{{ $source }}</p>
                        </div>
                    </li>
                @endforeach
            </ul>
            @if ($loop->last && ! $loop->first)
                </details>
            @endif
        @endforeach
    </section>

    <section class="s-wrap cp-dn-sec cp-dn-kit" aria-labelledby="kit-h">
        <h2 id="kit-h" class="cp-dn-kick">{{ $love['kicks']['kit'] }}</h2>
        <div class="cp-dn-kit-grid">
            @foreach (['who', 'need', 'safety'] as $list)
                <div @class(['cp-dn-safe' => $list === 'safety'])><h3>{{ $page['titles'][$list] }}</h3><ul class="cp-dn-ticks">@foreach ((array) $page[$list] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul></div>
            @endforeach
        </div>
    </section>

    {{-- The questions, one open at a time, an answer that names other courses linking them; the same answers as structured
         data for search (as site.courses._faq gives them). --}}
    <section class="s-wrap cp-dn-sec cp-dn-fine" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-dn-h2">{{ $page['titles']['faq'] }}</h2>
        <div class="cp-faq cp-dn-faq">
            @foreach ((array) $page['faq'] as [$question, $answer])
                <details @if ($loop->first) open @endif>
                    <summary>{{ $question }}<span class="cp-faq-sign" aria-hidden="true"><x-site.icon name="plus" size="16" /></span></summary>
                    <p>{{ $answer }}</p>
                    @if (isset($love['faq_links'][$loop->index]))
                        <ul class="cp-dn-faq-links">
                            @foreach ((array) $love['faq_links'][$loop->index] as $slug)
                                <li><a href="/courses/{{ $slug }}" class="s-arrow">{{ __('site.courses.pages.'.$slug.'.name') }} <x-site.icon name="arrow" size="16" /></a></li>
                            @endforeach
                        </ul>
                    @endif
                </details>
            @endforeach
        </div>
        <x-site.json-ld :data="$dn::faqData((array) $page['faq'])" />
    </section>

    <section class="cp-dn-close" aria-labelledby="join-h">
        <div class="s-wrap cp-dn-close-in">
            <p class="cp-dn-kick">{{ $love['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-dn-close-h">{{ $dn::marked($love['close_marked'], 'cp-dn-it') }}</h2>
            @if ($line !== [])
                <p class="cp-dn-ipa cp-dn-close-ipa" lang="en" aria-hidden="true">{{ $dn::ipa($line) }}</p>
            @endif
            <p class="cp-dn-close-p">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-dn-btns cp-dn-close-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </p>
            @if ($open)
                <p class="cp-dn-close-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <div class="cp-dn-close-rel">
                <h3 class="cp-dn-kick">{{ $page['titles']['related'] }}</h3>
                @include('site.courses._related')
            </div>
        </div>
    </section>

    <section class="s-wrap cp-dn-sources" aria-labelledby="notes-h">
        <details data-di-sources>
            <summary><h2 id="notes-h" class="cp-dn-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/diction.js"></script>
</main>
