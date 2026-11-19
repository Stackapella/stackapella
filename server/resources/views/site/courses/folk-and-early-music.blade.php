{{-- Folk and early music (/courses/folk-and-early-music), its own page after Hillel's design (2026-11-18,
     ~/Code/course-designs/folk-and-early-music): an old printed ballad sheet. Headings in IM Fell English (its small caps
     for the labels), key words in red, double rules and framed cards; text in Figtree. The four shape notes of the American
     singing-school books (fa a triangle, sol an oval, la a square, mi a diamond) mark Verses I to IV; mi, the bright note,
     is red wherever it shows. The hero: the broadside tacked to wooden boards (the page's own woodcut ship), Drunken
     Sailor's first line in shape notes across its head, then the shape key and the drone as a thick bar. Now Playing
     (js/public/folk-and-early-music.js): Drunken Sailor's third line over a drone, from its song template
     (App\Support\Site\FolkShapeNotes), the sounding note lit on the diagram, and the held sixth switched between bright and
     dark; the drone bar turns red while it sounds. Then the ballad (outcomes), the old scales as rising steps of shapes,
     the ornaments on a four-line staff, the four verses (units), strike up (lesson 1), the ear (what the app listens for,
     a real screen of lesson 1), the old books (facts with their sources), before you sing (with a table of the tunes,
     oldest first), the questions and all hands. Game mode: a neon tavern on the arcade's tokens. Words:
     lang/en/site.php courses.pages.folk-and-early-music (`fo` for the design's own labels). --}}
@php($fo = (array) $page['fo'])
@php($demo = (array) $page['demo'])
@php($line = \App\Support\Site\FolkShapeNotes::line())
@php($head = \App\Support\Site\FolkShapeNotes::opening())
@php($ladders = \App\Support\Site\FolkShapeNotes::ladders())
@php($roman = ['I', 'II', 'III', 'IV', 'V', 'VI'])
{{-- The verses' shapes, in the books' order: fa, sol, la, mi. --}}
@php($verseShapes = ['fa', 'sol', 'la', 'mi'])
{{-- A lesson with its own page links to it, by its name. --}}
@php($pages = collect(\App\Support\Pages\Lessons::pages($page['slug']))->filter(static fn (array $p): bool => ($p['kind'] ?? '') === 'lesson')->pluck('url', 'name')->all())
@php($lessons = $outline['lessons'] ?? 16)
<main class="cp cp-folk">
    <section class="s-wrap cp-fo-hero" aria-labelledby="page-h">
        <div class="cp-fo-strap">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <p class="cp-fo-sc cp-fo-red">
                @foreach ((array) $fo['strap'] as $bit)
                    <span>{{ str_replace(':lessons', (string) $lessons, $bit) }}</span>@if (! $loop->last)<span class="cp-fo-star" aria-hidden="true">✶</span>@endif
                @endforeach
            </p>
        </div>
        <div class="cp-fo-top">
            <div class="cp-fo-copy">
                @unless ($open)
                    <p class="s-soonpill"><x-site.soon /></p>
                @endunless
                <h1 id="page-h" class="cp-fo-h1"><span class="cp-fo-h1a">{{ $fo['h1'][0] }}</span> <em class="cp-fo-h1b">{{ $fo['h1'][1] }}</em></h1>
                <p class="cp-fo-lead">{{ $page['hero_lead'] }}</p>
                <p class="cp-fo-facts">
                    @foreach ((array) $page['hero_facts'] as $fact)
                        @continue($loop->index > 1)
                        <span>{{ $fact }}</span>@if ($loop->first)<span class="cp-fo-star" aria-hidden="true">✶</span>@endif
                    @endforeach
                </p>
                <p class="cp-fo-btns">
                    @if ($open)
                        <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                    @else
                        <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                    @endif
                    <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                </p>
            </div>
            <figure class="cp-fo-art">
                <div class="cp-fo-wall" aria-hidden="true">
                    <svg viewBox="0 0 460 420" class="cp-fo-svg">
                        <defs>
                            <pattern id="fo-hatch" width="6" height="6" patternUnits="userSpaceOnUse" patternTransform="rotate(35)"><path d="M0 0 V6" class="cp-fo-hatchline" /></pattern>
                        </defs>
                        <rect x="10" y="10" width="440" height="400" class="cp-fo-paper" />
                        <rect x="24" y="24" width="412" height="372" class="cp-fo-rule" />
                        <rect x="30" y="30" width="400" height="360" class="cp-fo-rule cp-fo-thin" />
                        <path d="M60 100 H400" class="cp-fo-staffline" />
                        {{-- Drunken Sailor's first line, its shapes read in two sharps, a note's height its pitch. --}}
                        @foreach ($head as $note)
                            @php($x = 74 + $loop->index * 34)
                            @php($y = 88 - $note['semi'] * 4.5)
                            @if ($note['shape'] === 'fa')
                                <path d="M{{ $x - 9 }} {{ $y + 7 }} L{{ $x + 9 }} {{ $y + 7 }} L{{ $x + 9 }} {{ $y - 7 }} Z" class="cp-fo-shape" />
                            @elseif ($note['shape'] === 'sol')
                                <ellipse cx="{{ $x }}" cy="{{ $y }}" rx="9" ry="7" class="cp-fo-shape" />
                            @elseif ($note['shape'] === 'la')
                                <rect x="{{ $x - 7 }}" y="{{ $y - 7 }}" width="14" height="14" class="cp-fo-shape" />
                            @else
                                <path d="M{{ $x }} {{ $y - 8 }} L{{ $x + 9 }} {{ $y }} L{{ $x }} {{ $y + 8 }} L{{ $x - 9 }} {{ $y }} Z" class="cp-fo-diamond" />
                            @endif
                        @endforeach
                        <g>
                            <rect x="60" y="116" width="340" height="236" class="cp-fo-frame" />
                            <circle cx="340" cy="160" r="22" class="cp-fo-sun" />
                            <path d="M90 156 q18 -14 36 0 q14 -10 28 0 q-30 8 -64 0 Z M250 146 q14 -10 28 0 q10 -8 20 0 q-24 6 -48 0 Z" class="cp-fo-cloud" />
                            <path d="M180 150 q6 -6 12 0 q6 -6 12 0 M296 196 q5 -5 10 0 q5 -5 10 0" class="cp-fo-gull" />
                            <path d="M228 286 V170" class="cp-fo-mast" />
                            <path d="M232 176 C 300 206, 314 252, 300 280 L232 280 Z" fill="url(#fo-hatch)" class="cp-fo-sail" />
                            <path d="M224 186 C 176 222, 170 258, 180 280 L224 280 Z" class="cp-fo-sail cp-fo-sail-plain" />
                            <path d="M228 170 L252 178 L228 186" class="cp-fo-flag" />
                            <path d="M150 284 H310 L292 310 H170 Z" class="cp-fo-hull" />
                            <rect x="61" y="300" width="338" height="51" fill="url(#fo-hatch)" class="cp-fo-sea" />
                            <path d="M61 300 q14 -10 28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0" class="cp-fo-wave" />
                            <path d="M61 324 q14 -10 28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0 t28 0" class="cp-fo-wave" />
                        </g>
                        <path d="M60 372 H190 M270 372 H400" class="cp-fo-orn-rule" />
                        <path d="M230 362 L240 372 L230 382 L220 372 Z" class="cp-fo-diamond" />
                    </svg>
                    @foreach ([[1, 1], [1, 2], [2, 1], [2, 2]] as [$row, $col])
                        <span class="cp-fo-tack cp-fo-tack-{{ $row }}{{ $col }}"></span>
                    @endforeach
                </div>
                <figcaption class="cp-fo-caption">{{ $fo['caption'] }}</figcaption>
            </figure>
        </div>

        {{-- The shape key: the four verses (the units, from the course's own file) and their shapes. --}}
        <div class="cp-fo-key">
            <p class="cp-fo-key-lead"><em>{{ $fo['key'][0] }}</em> {{ $fo['key'][1] }} <a href="{{ $fo['key_source'][1] }}" class="cp-fo-src" rel="noopener">{{ $fo['key_source'][0] }}</a></p>
            @if ($outline !== null)
                <ol class="cp-fo-key-list">
                    @foreach ($outline['units'] as $unit)
                        @php($sh = $verseShapes[$loop->index % 4])
                        <li>
                            <i class="cp-fo-sh @if ($sh === 'mi') cp-fo-is-red @endif" data-sh="{{ $sh }}" aria-hidden="true"></i>
                            <span>
                                <span class="cp-fo-sc cp-fo-red">{{ $fo['syllables'][$sh] }} <span class="cp-fo-star" aria-hidden="true">✶</span> {{ str_replace(':n', $roman[$loop->index] ?? (string) $loop->iteration, $page['verse_n']) }}</span>
                                <b>{{ $unit['title'] }}</b>
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
        <div class="cp-fo-drone" aria-hidden="true">
            <span class="cp-fo-drone-bar"></span>
            <span class="cp-fo-drone-says"><span>{{ $fo['drone'][0] }}</span><span>{{ $fo['drone'][1] }}</span></span>
        </div>
    </section>

    {{-- Now Playing: the shared course deck (.cp-dk), Drunken Sailor's third line as shape notes over its words. --}}
    <section class="s-wrap cp-dk-demosec cp-fo-demosec" aria-labelledby="demo-h">
        <div class="cp-dk-demo cp-fo-demo" data-fo-demo data-sixth="bright" data-tempo="{{ $line['tempo'] }}" data-home="{{ $line['home'] }}"
            data-bright="{{ $demo['bright'] }}" data-dark="{{ $demo['dark'] }}" data-held-bright="{{ $demo['held_bright'] }}" data-held-dark="{{ $demo['held_dark'] }}" data-after="{{ $demo['after'] }}">
            <p class="cp-dk-demo-bar"><span class="cp-dk-live"><span class="cp-dk-rec" aria-hidden="true"></span>{{ $demo['kick'] }}</span><span><x-site.skin :game="$demo['lesson']" :serious="$fo['demo']['lesson']" /></span></p>
            <div class="cp-dk-demo-in">
                <div>
                    <h2 id="demo-h" class="cp-dk-demo-h cp-fo-demo-h">{{ $demo['title'] }}</h2>
                    <p class="cp-dk-demo-lead">{{ $demo['lead'] }}</p>
                    <p class="cp-dk-btns">
                        <button type="button" class="cp-dk-play cp-dk-play-b" data-fo-play="dark" data-play="{{ $demo['play_dark'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['play_dark'] }}</span></button>
                        <button type="button" class="cp-dk-play" data-fo-play="bright" data-play="{{ $demo['play_bright'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['play_bright'] }}</span></button>
                    </p>
                    <fieldset class="cp-dk-pick cp-fo-pick">
                        <legend>{{ $demo['hold_h'] }}</legend>
                        <button type="button" class="cp-dk-hear" data-fo-hold data-play="{{ $demo['hold'] }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-dk-play-icon" aria-hidden="true"></span><span data-label>{{ $demo['hold'] }}</span></button>
                        <button type="button" class="cp-fo-switch" role="switch" aria-checked="true" data-fo-switch><span class="switch-track" aria-hidden="true"><span class="switch-knob"></span></span><span>{{ $demo['switch'] }}</span></button>
                    </fieldset>
                    <p class="cp-dk-say" role="status" data-fo-say>{{ $demo['ready'] }}</p>
                </div>
                <div>
                    <figure class="cp-fo-roll">
                        <ol class="cp-fo-notes" style="--n: {{ count($line['notes']) }}" aria-hidden="true">
                            @foreach ($line['notes'] as $note)
                                <li data-fo-note="{{ $loop->index }}" data-at="{{ $note['at'] }}" data-len="{{ $note['len'] }}" data-midi="{{ $note['midi'] }}" data-dark="{{ $note['dark'] }}" @if ($note['sixth']) data-fo-sixth @endif>
                                    <span class="cp-fo-col">
                                        <i class="cp-fo-sh cp-fo-v-bright" data-sh="{{ $note['brightShape'] }}" style="--s: {{ $note['semi'] }}"></i>
                                        <i class="cp-fo-sh cp-fo-v-dark" data-sh="{{ $note['darkShape'] }}" style="--s: {{ $note['darkSemi'] }}"></i>
                                    </span>
                                    <span class="cp-fo-word" translate="no">{{ $note['word'] }}</span>
                                </li>
                            @endforeach
                        </ol>
                        <span class="cp-fo-roll-drone" aria-hidden="true"></span>
                        <figcaption class="sr-only">{{ $fo['demo']['alt'] }}</figcaption>
                    </figure>
                    <p class="cp-fo-legend">
                        <span><i class="cp-fo-sh" data-sh="mi" aria-hidden="true"></i>{{ $fo['demo']['legend']['mi'] }}</span>
                        <span><i class="cp-fo-sh" data-sh="fa" aria-hidden="true"></i>{{ $fo['demo']['legend']['fa'] }}</span>
                        <span><i class="cp-fo-legend-drone" aria-hidden="true"></i>{{ $fo['demo']['legend']['drone'] }}</span>
                    </p>
                    <p class="cp-dk-note">{{ $demo['note'] }} {{ $fo['demo']['shapes'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-fo-sec cp-fo-ballad" aria-labelledby="outcomes-h">
        <p class="cp-fo-kick"><span class="cp-fo-star" aria-hidden="true">✶</span> {{ $page['kicks']['ballad'] }} <span class="cp-fo-star" aria-hidden="true">✶</span></p>
        <h2 id="outcomes-h" class="cp-fo-title">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-fo-stanzas">
            @foreach ((array) $page['outcomes'] as $outcome)
                @php($sh = $verseShapes[$loop->index % 4])
                <li>
                    <span class="cp-fo-stanza-n" aria-hidden="true"><i class="cp-fo-sh @if ($sh === 'mi') cp-fo-is-red @endif" data-sh="{{ $sh }}"></i>{{ $roman[$loop->index] ?? $loop->iteration }}.</span>
                    <p>{{ $outcome }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap cp-fo-sec cp-fo-modes" aria-labelledby="modes-h">
        <div>
            <p class="cp-fo-kick"><span class="cp-fo-star" aria-hidden="true">✶</span> {{ $page['kicks']['modes'] }}</p>
            <h2 id="modes-h" class="cp-fo-title">{{ $page['titles']['modes'] }}</h2>
            <p class="cp-fo-lede">{{ $page['modes']['lead'] }}</p>
            <p class="cp-fo-quiet">{{ $fo['modes']['note'] }}</p>
        </div>
        <div class="cp-fo-mode-cards">
            @foreach ($ladders as $kind => $ladder)
                <figure class="cp-fo-card cp-fo-mode">
                    <div class="cp-fo-ladder" aria-hidden="true">
                        @foreach ($ladder['steps'] as $step)
                            <i class="cp-fo-sh @if ($step['changed']) cp-fo-is-red @endif" data-sh="{{ $step['shape'] }}" style="--i: {{ $loop->index }}; --s: {{ $step['semi'] }}"></i>
                        @endforeach
                        <i class="cp-fo-sh cp-fo-ghost" data-sh="{{ $ladder['ghost']['shape'] }}" style="--i: {{ $ladder['ghost']['at'] }}; --s: {{ $ladder['ghost']['semi'] }}"></i>
                    </div>
                    <figcaption>
                        <span class="cp-fo-sc cp-fo-red">{{ $fo['modes'][$kind][0] }} <span class="cp-fo-star" aria-hidden="true">✶</span> {{ $fo['modes'][$kind][1] }}</span>
                        <b>{{ $page['modes'][$kind][0] }}</b>
                        <span>{{ $fo['modes'][$kind][2] }}</span>
                    </figcaption>
                </figure>
            @endforeach
        </div>
    </section>

    <section class="s-ink cp-fo-grace" aria-labelledby="ornaments-h">
        <div class="s-wrap">
            <div class="cp-fo-grace-head">
                <div>
                    <p class="cp-fo-kick"><span class="cp-fo-star" aria-hidden="true">✶</span> {{ $page['kicks']['grace'] }}</p>
                    <h2 id="ornaments-h" class="cp-fo-title">{{ $page['titles']['ornaments'] }}</h2>
                </div>
                <p class="cp-fo-lede">{{ $page['ornaments']['lead'] }} {{ $fo['grace_more'] }}</p>
            </div>
            <div class="cp-fo-orns">
                @foreach (['flick', 'turn'] as $kind)
                    <figure class="cp-fo-orn">
                        {{-- A four-line staff, each step half a line apart: the plain notes square, the ornament's small and red. --}}
                        <svg viewBox="0 0 480 96" aria-hidden="true" class="cp-fo-chant">
                            @foreach ([18, 38, 58, 78] as $y)
                                <path d="M8 {{ $y }} H472" class="cp-fo-chant-line" />
                            @endforeach
                            @if ($kind === 'flick')
                                <rect x="60" y="49" width="22" height="18" class="cp-fo-neume" />
                                <rect x="140" y="49" width="22" height="18" class="cp-fo-neume" />
                                <rect x="222" y="42" width="11" height="10" class="cp-fo-neume cp-fo-grace-n" />
                                <rect x="238" y="49" width="22" height="18" class="cp-fo-neume" />
                                <rect x="320" y="49" width="22" height="18" class="cp-fo-neume" />
                                <rect x="400" y="59" width="22" height="18" class="cp-fo-neume" />
                            @else
                                <rect x="60" y="49" width="22" height="18" class="cp-fo-neume" />
                                <rect x="150" y="42" width="11" height="10" class="cp-fo-neume cp-fo-grace-n" />
                                <rect x="172" y="52" width="11" height="10" class="cp-fo-neume cp-fo-grace-n" />
                                <rect x="194" y="62" width="11" height="10" class="cp-fo-neume cp-fo-grace-n" />
                                <rect x="220" y="49" width="22" height="18" class="cp-fo-neume" />
                                <rect x="320" y="49" width="22" height="18" class="cp-fo-neume" />
                                <rect x="400" y="29" width="22" height="18" class="cp-fo-neume" />
                            @endif
                        </svg>
                        <figcaption>
                            <b>{{ $page['ornaments'][$kind][0] }}</b> <em>{{ $fo['ornaments'][$kind][0] }}</em>
                            <span>{{ $fo['ornaments'][$kind][1] }}</span>
                        </figcaption>
                    </figure>
                @endforeach
            </div>
        </div>
    </section>

    {{-- The course itself, verse by verse, from its own file (SitePages::courseOutline): the lessons numbered through. --}}
    @if ($outline !== null)
        <section class="s-wrap cp-fo-sec cp-fo-verses" aria-labelledby="units-h">
            <p class="cp-fo-kick"><span class="cp-fo-star" aria-hidden="true">✶</span> {{ $page['kicks']['verses'] }}</p>
            <div class="cp-fo-verses-head">
                <h2 id="units-h" class="cp-fo-title">{{ $page['titles']['units'] }}</h2>
                <p class="cp-fo-count">{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
            </div>
            <ol class="cp-fo-sheet">
                @php($first = 1)
                @foreach ($outline['units'] as $unit)
                    @php($sh = $verseShapes[$loop->index % 4])
                    <li class="cp-fo-verse">
                        <div class="cp-fo-verse-top">
                            <p class="cp-fo-sc cp-fo-red"><x-site.skin :game="__('pages.site.course_stage', ['n' => $loop->iteration])" :serious="str_replace(':n', $roman[$loop->index] ?? (string) $loop->iteration, $page['verse_n'])" /> <span class="cp-fo-star" aria-hidden="true">✶</span> {{ $fo['syllables'][$sh] }}</p>
                            <i class="cp-fo-sh @if ($sh === 'mi') cp-fo-is-red @endif" data-sh="{{ $sh }}" aria-hidden="true"></i>
                        </div>
                        <h3>{{ $unit['title'] }}</h3>
                        @isset($page['units_say'][$loop->index])
                            <p class="cp-fo-verse-say">{{ $page['units_say'][$loop->index] }}</p>
                        @endisset
                        <ol class="cp-fo-lessons" start="{{ $first }}">
                            @foreach ($unit['lessons'] as $title)
                                <li><span class="cp-fo-lesson-n" aria-hidden="true">{{ $first + $loop->index }}</span><span>@if (isset($pages[$title]))<a href="{{ $pages[$title] }}">{{ $title }}</a>@else{{ $title }}@endif @if ($loop->parent->first && $loop->first)<span class="s-free">{{ __('pages.site.course_free') }}</span>@endif</span></li>
                            @endforeach
                        </ol>
                    </li>
                    @php($first += count($unit['lessons']))
                @endforeach
            </ol>
        </section>
    @endif

    <section class="s-wrap cp-fo-sec cp-fo-strike" aria-labelledby="try-h">
        <div>
            <p class="cp-fo-kick"><span class="cp-fo-star" aria-hidden="true">✶</span> {{ $page['kicks']['strike'] }}</p>
            <h2 id="try-h" class="cp-fo-title">{{ $page['titles']['try'] }}</h2>
            <p class="cp-fo-lede">{{ $page['try']['lead'] }}</p>
            <div class="cp-fo-call">
                <p class="cp-fo-label">{{ $fo['call'] }}</p>
                <p class="cp-fo-quote">{{ $fo['line'] }}</p>
                <p class="cp-fo-label">{{ $fo['answer'] }}</p>
                <p class="cp-fo-quote cp-fo-echo">{{ $fo['line'] }}</p>
            </div>
            <p class="cp-fo-quiet">{{ $page['try']['after'] }}</p>
        </div>
        <ol class="cp-fo-steps">
            @foreach ((array) $page['try']['steps'] as $step)
                <li>
                    <span class="cp-fo-step-n" aria-hidden="true">{{ $roman[$loop->index] ?? $loop->iteration }}.</span>
                    <p>@if ($loop->first)<b class="cp-fo-label">{{ __('pages.site.course_free') }}</b>@endif{{ $step }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- What the app listens for, beside a real screen of lesson 1 (web/tool/course_shots.py) and how a take is said. --}}
    <section class="s-ink cp-fo-ear" aria-labelledby="inside-h">
        <div class="s-wrap cp-fo-ear-in">
            <div>
                <p class="cp-fo-kick"><span class="cp-fo-star" aria-hidden="true">✶</span> {{ $fo['ear']['kick'] }}</p>
                <h2 id="inside-h" class="cp-fo-title">{{ $page['titles']['inside'] }}</h2>
                <dl class="cp-fo-rows">
                    @foreach ((array) $fo['ear']['rows'] as [$name, $what])
                        <div><dt class="cp-fo-sc">{{ $name }}</dt><dd>{{ $what }}</dd></div>
                    @endforeach
                </dl>
                <p class="cp-fo-quiet">{{ $fo['ear']['judge'] }}</p>
            </div>
            <div class="cp-fo-ear-side">
                @if (isset($page['shots'][0]))
                    <x-site.appshot :id="'course-'.$page['slug'].'--1'" :alt="$page['shots'][0]" />
                @endif
                <div class="cp-fo-said">
                    <p class="cp-fo-label"><x-site.skin :game="$fo['ear']['game']" :serious="$fo['ear']['said']" /></p>
                    <p class="cp-fo-said-q"><span class="s-skin-g">{{ $page['scoring']['game'] }}</span><span class="s-skin-s">{{ $fo['ear']['quote'] }}</span></p>
                    <p class="cp-fo-said-n s-skin-s">{{ $fo['ear']['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-fo-sec cp-fo-wallsec" aria-labelledby="facts-h">
        <p class="cp-fo-kick"><span class="cp-fo-star" aria-hidden="true">✶</span> {{ $page['kicks']['wall'] }}</p>
        <h2 id="facts-h" class="cp-fo-title">{{ $page['titles']['facts'] }}</h2>
        <ul class="cp-fo-clippings">
            @foreach ((array) $page['facts'] as [$text, $source, $url])
                <li class="cp-fo-clip">
                    @isset($fo['clips'][$loop->index])
                        <p class="cp-fo-clip-word" aria-hidden="true">{{ $fo['clips'][$loop->index] }}</p>
                    @endisset
                    <p class="cp-fo-clip-text">{{ $text }}</p>
                    <p class="cp-fo-clip-src">@if ($url)<a href="{{ $url }}" rel="noopener">{{ $source }}</a>@else{{ $source }}@endif</p>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="s-wrap cp-fo-sec cp-fo-singer" aria-labelledby="singer-h">
        <p class="cp-fo-kick"><span class="cp-fo-star" aria-hidden="true">✶</span> {{ $page['kicks']['singer'] }}</p>
        <h2 id="singer-h" class="cp-fo-title">{{ $page['titles']['singer'] }}</h2>
        <div class="cp-fo-singer-grid">
            <div>
                <h3 class="cp-fo-label">{{ $page['titles']['who'] }}</h3>
                <ul class="cp-fo-list">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="18" />{{ $item }}</li>@endforeach</ul>
                <h3 class="cp-fo-label cp-fo-gap">{{ $page['titles']['need'] }}</h3>
                <ul class="cp-fo-list">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="18" />{{ $item }}</li>@endforeach</ul>
            </div>
            <div>
                <h3 class="cp-fo-label">{{ $page['titles']['safety'] }}</h3>
                <ul class="cp-fo-list">@foreach ((array) $page['safety'] as $item)<li><x-site.icon name="info" size="18" />{{ $item }}</li>@endforeach</ul>
            </div>
            <div class="cp-fo-card cp-fo-tunes">
                <h3 class="cp-fo-tunes-h">{{ $fo['tunes_h'] }}</h3>
                <p class="cp-fo-tunes-sub">{{ $fo['tunes_sub'][0] }} <span class="cp-fo-star" aria-hidden="true">✶</span> {{ $fo['tunes_sub'][1] }}</p>
                <ol>
                    @foreach ((array) $fo['tunes'] as [$when, $song, $credit])
                        <li><span class="cp-fo-when">{{ $when }}</span><span><b translate="no">{{ $song }}</b><span>{{ $credit }}</span></span></li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-fo-sec cp-fo-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-fo-kick"><span class="cp-fo-star" aria-hidden="true">✶</span> {{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-fo-title">{{ $fo['faq_h'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-fo-close" aria-labelledby="join-h">
        <div class="s-wrap cp-fo-close-in">
            <p class="cp-fo-close-shapes" aria-hidden="true">
                @foreach ($verseShapes as $sh)
                    <i class="cp-fo-sh @if ($sh === 'mi') cp-fo-is-red @endif" data-sh="{{ $sh }}"></i>
                @endforeach
            </p>
            <p class="cp-fo-kick">{{ $page['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-fo-title cp-fo-join">{{ $fo['join'][0] }} <em>{{ $fo['join'][1] }}</em></h2>
            <p class="cp-fo-lede">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            @if ($open)
                <p class="cp-join-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <p class="cp-join-btns">
                @if ($open)
                    <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                @else
                    <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                @endif
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </p>
            <div class="cp-fo-related">
                <h3 class="cp-fo-kick">{{ $page['titles']['related'] }}</h3>
                @include('site.courses._related')
            </div>
        </div>
    </section>
    <script type="module" src="/js/public/folk-and-early-music.js"></script>
</main>
