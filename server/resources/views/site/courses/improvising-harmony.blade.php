{{-- Improvising a harmony (/courses/improvising-harmony), its own page after Hillel's "tunebook" design (2026-11-18,
     ~/Code/course-designs/improvising-harmony): the shaped note heads of the old shape-note songbooks (four-shape: fa a
     triangle, sol an oval, la a square, mi a diamond), ruled staff lines and an italic serif (Libre Caslon Text), beside
     the course's glowing note lights. The hero leads into one dark player: Michael, Row the Boat Ashore's first line on
     eight solfège rows, each with its shape, the tune from its template and lesson 1's part above lit over it; it plays
     the tune alone, the two together, or the tune for you to sing the lit part (js/public/improvising-harmony.js, on
     voices.js and the audio clock), and its four steps move the same lane from every note lit to lights off. Then the
     outcomes numbered by shape, the one rule (a third up, a fourth over so) as two small lanes, every lesson in one
     numbered index linked to its page, Try this now with lesson 1's long-note step, what the app listens for beside a
     take plainly marked as a sample, the three checked facts as a timeline, who it's for beside the songs, the
     questions and the close. Real notes and lessons come from App\Support\Site\ImprovisingHarmonyPage. Words:
     lang/en/site.php courses.pages.improvising-harmony (`ih` for the design's own). --}}
@section('lesson-pages-inline', '1')
@php($ih = (array) $page['ih'])
@php($demo = (array) $page['demo'])
@php($lane = \App\Support\Site\ImprovisingHarmonyPage::lane())
@php($strip = \App\Support\Site\ImprovisingHarmonyPage::strip())
@php($index = \App\Support\Site\ImprovisingHarmonyPage::index())
{{-- The four shapes, in the order the page numbers by them (fa, sol, la, mi). --}}
@php($marks = ['fa', 'sol', 'la', 'mi'])
@php($h1 = \App\Support\Site\ImprovisingHarmonyPage::em((string) $ih['headline']))
@php($ruleH = \App\Support\Site\ImprovisingHarmonyPage::em((string) $ih['rule_h']))
@php($indexH = \App\Support\Site\ImprovisingHarmonyPage::em((string) $ih['index_h']))
@php($tryH = \App\Support\Site\ImprovisingHarmonyPage::em((string) $ih['try_h']))
@php($hearsH = \App\Support\Site\ImprovisingHarmonyPage::em((string) $ih['hears_h']))
@php($factsH = \App\Support\Site\ImprovisingHarmonyPage::em((string) $ih['facts_h']))
@php($joinH = \App\Support\Site\ImprovisingHarmonyPage::em((string) $ih['join_h']))
@php($scienceH = \App\Support\Site\ImprovisingHarmonyPage::em((string) $ih['science']['title']))
@php($oneH = \App\Support\Site\ImprovisingHarmonyPage::em((string) $ih['one']['title']))
@php($one = \App\Support\Site\ImprovisingHarmonyPage::lessonOne())
{{-- Every fact on the page, the course's checked lesson facts beside the page's own, numbered into one Sources list. --}}
@php($facts = \App\Support\Site\ImprovisingHarmonyPage::facts((array) $page['facts']))
@php($sources = \App\Support\Site\ImprovisingHarmonyPage::sources($facts))
{{-- The sample take: lesson 1's part, every note sung on it but one, pulled onto the tune's own note. --}}
@php($pulled = 7)
<main class="cp cp-imp">
    <section class="cp-imp-hero" aria-labelledby="page-h">
        <div class="s-wrap">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <div class="cp-imp-top">
                <div>
                    <p class="cp-imp-chip"><b>{{ __('pages.site.course_chip_free') }}</b>@if ($outline !== null)<span>{{ __('pages.site.course_size', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</span>@endif</p>
                    <h1 id="page-h" class="cp-imp-h1">{{ $h1[0] }}<em>{{ $h1[1] }}</em>{{ $h1[2] }}</h1>
                </div>
                <div class="cp-imp-top-r">
                    <p class="cp-imp-lead">{{ $page['hero_lead'] }}</p>
                    <p class="cp-imp-btns">
                        @if ($open)
                            <a href="{{ $courseStart }}" class="btn-primary btn-lg">{{ __('pages.site.course_try') }} <x-site.icon name="arrow" size="18" /></a>
                        @else
                            <a href="{{ $links['start'] }}" class="btn-primary btn-lg">{{ __('pages.site.course_start') }} <x-site.icon name="arrow" size="18" /></a>
                        @endif
                        <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                    </p>
                    <ul class="cp-imp-facts">
                        @foreach ((array) $page['hero_facts'] as $fact)
                            <li>{{ $fact }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>

            {{-- The player: the lane on its eight rows, then its plays beside the four lights steps. --}}
            <div class="cp-imp-player" data-im-demo data-stage="0" data-tempo="{{ $lane['tempo'] }}" data-span="{{ $lane['span'] }}" data-chords="{{ json_encode($lane['chords']) }}"
                data-say-tune="{{ $demo['say_tune'] }}" data-say-both="{{ $demo['say_both'] }}" data-say-you="{{ $demo['say_you'] }}" data-after="{{ $demo['after'] }}">
                <p class="cp-imp-bar"><span class="cp-imp-live"><i aria-hidden="true"></i>{{ $ih['player']['kick'] }}</span><span translate="no">{{ $ih['player']['song'] }}</span></p>
                <figure class="cp-imp-fig">
                    <div class="cp-imp-board" aria-hidden="true">
                        <ol class="cp-imp-rows">
                            @foreach ($lane['rows'] as $row)
                                <li><i class="cp-imp-shape cp-imp-{{ $row['shape'] }}"></i><span>{{ $ih['solfa'][$row['name']] ?? '' }}</span></li>
                            @endforeach
                        </ol>
                        <div class="cp-imp-lane">
                            @foreach ($lane['choices'] as $note)
                                <span class="cp-imp-note cp-imp-alt" style="--row: {{ $note['row'] }}; left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"></span>
                            @endforeach
                            @foreach ($lane['tune'] as $note)
                                <span class="cp-imp-note cp-imp-tune" data-im-tune data-at="{{ $note['at'] }}" data-len="{{ $note['len'] }}" data-midi="{{ $note['midi'] }}" style="--row: {{ $note['row'] }}; left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"></span>
                            @endforeach
                            @foreach ($lane['part'] as $note)
                                <span class="cp-imp-note cp-imp-part cp-imp-{{ $note['fade'] }}" data-im-part data-at="{{ $note['at'] }}" data-len="{{ $note['len'] }}" data-midi="{{ $note['midi'] }}" style="--row: {{ $note['row'] }}; left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"></span>
                            @endforeach
                            <span class="cp-imp-ph" data-im-ph></span>
                        </div>
                        <p class="cp-imp-words">
                            @foreach ($lane['tune'] as $note)
                                <span class="cp-imp-w{{ $loop->index % 2 }}" data-im-word data-at="{{ $note['at'] }}" data-len="{{ $note['len'] }}" style="left: {{ $note['left'] }}%">{{ $note['word'] }}</span>
                            @endforeach
                        </p>
                    </div>
                    <figcaption class="sr-only">{{ $ih['player']['alt'] }}</figcaption>
                </figure>
                <div class="cp-imp-controls">
                    <div>
                        <p class="cp-imp-plays">
                            @foreach (['tune' => $demo['tune'], 'both' => $ih['player']['both'], 'you' => $demo['you']] as $mode => $words)
                                <button type="button" class="cp-imp-play" data-im-play="{{ $mode }}" data-play="{{ $words }}" data-stop="{{ $demo['stop'] }}" aria-pressed="false"><span class="cp-imp-play-icon" aria-hidden="true"></span><span data-label>{{ $words }}</span></button>
                            @endforeach
                        </p>
                        <p class="cp-imp-say" role="status" data-im-say>{{ $demo['ready'] }}</p>
                        <p class="cp-imp-note-p">{{ $ih['player']['note'] }}</p>
                    </div>
                    <div>
                        <h2 id="lights-h" class="cp-imp-kick cp-imp-kick-lit">{{ $page['titles']['lights'] }}</h2>
                        <div class="cp-imp-steps" role="group" aria-labelledby="lights-h">
                            @foreach ((array) $page['lights'] as [$step, $what])
                                <button type="button" data-im-stage="{{ $loop->index }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">{{ $step }}</button>
                            @endforeach
                        </div>
                        @foreach ((array) $page['lights'] as [$step, $what])
                            <p class="cp-imp-stage-say" data-im-stage-say="{{ $loop->index }}" @if (! $loop->first) hidden @endif>{{ $what }}</p>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-imp-sec" aria-labelledby="outcomes-h">
        <h2 id="outcomes-h" class="cp-imp-kick cp-imp-ruled">{{ $page['titles']['outcomes'] }}</h2>
        <ol class="cp-imp-outcomes">
            @foreach ((array) $page['outcomes'] as $outcome)
                <li>
                    <p class="cp-imp-mark"><i class="cp-imp-shape cp-imp-{{ $marks[$loop->index % 4] }}" aria-hidden="true"></i><span>{{ $ih['marks'][$loop->index % 4] }}</span></p>
                    <p>{{ $outcome }}</p>
                </li>
            @endforeach
        </ol>
        <p class="cp-imp-small">{{ $ih['shapes_note'] }}</p>
    </section>

    <section class="s-wrap cp-imp-sec cp-imp-rule" aria-labelledby="rule-h">
        <div>
            <p class="cp-imp-kick">{{ $ih['rule_kick'] }}</p>
            <h2 id="rule-h" class="cp-imp-h2">{{ $ruleH[0] }}<em>{{ $ruleH[1] }}</em>{{ $ruleH[2] }}</h2>
            <p class="cp-imp-lede">{{ $page['rule']['lead'] }}</p>
            <p class="cp-imp-p">{{ $page['rule']['body'] }}</p>
        </div>
        <div class="cp-imp-figs">
            {{-- A third up: over mi, so (fa between). A fourth up over so, to do: ti, the third, would rub. --}}
            <figure class="cp-imp-card">
                <div class="cp-imp-mini" aria-hidden="true">
                    @foreach (['ti', 'so', 'fa', 'mi'] as $name)
                        <span class="cp-imp-mini-row" style="--row: {{ $loop->index }}">{{ $ih['solfa'][$name] }}</span>
                    @endforeach
                    <span class="cp-imp-mini-note cp-imp-mini-lit" style="--row: 1"></span>
                    <span class="cp-imp-mini-note cp-imp-mini-day" style="--row: 3"></span>
                    <em class="cp-imp-tag" style="--row: 2">{{ $ih['third_tag'] }}</em>
                </div>
                <figcaption><b>{{ $page['rule']['third'][0] }}</b> {{ $page['rule']['third'][1] }}</figcaption>
            </figure>
            <figure class="cp-imp-card">
                <div class="cp-imp-mini" aria-hidden="true">
                    @foreach (['do', 'ti', 'la', 'so'] as $name)
                        <span class="cp-imp-mini-row" style="--row: {{ $loop->index }}">{{ $ih['solfa'][$name] }}</span>
                    @endforeach
                    <span class="cp-imp-mini-note cp-imp-mini-lit" style="--row: 0"></span>
                    <span class="cp-imp-mini-note cp-imp-mini-rub" style="--row: 1"></span>
                    <span class="cp-imp-mini-note cp-imp-mini-day" style="--row: 3"></span>
                    <em class="cp-imp-tag" style="--row: 2">{{ $ih['fourth_tag'] }}</em>
                </div>
                <figcaption><b>{{ $page['rule']['fourth'][0] }}</b> {{ $page['rule']['fourth'][1] }}</figcaption>
            </figure>
        </div>
    </section>

    {{-- Why it works: each rule in plain words, over the course's own checked fact that backs it. --}}
    <section class="s-wrap cp-imp-sec cp-imp-why" aria-labelledby="why-h">
        <p class="cp-imp-kick">{{ $ih['science']['kick'] }}</p>
        <h2 id="why-h" class="cp-imp-h2 cp-imp-h2-s">{{ $scienceH[0] }}<em>{{ $scienceH[1] }}</em>{{ $scienceH[2] }}</h2>
        <ol class="cp-imp-why-list">
            @foreach ((array) $ih['science']['cards'] as $key => [$title, $plain])
                @continue(! isset($facts[$key]))
                <li>
                    <p class="cp-imp-mark"><i class="cp-imp-shape cp-imp-{{ $marks[$loop->index % 4] }}" aria-hidden="true"></i><span>{{ $ih['marks'][$loop->index % 4] }}</span></p>
                    <h3>{{ $title }}</h3>
                    <p class="cp-imp-why-plain">{{ $plain }}</p>
                    <p class="cp-imp-why-fact">{{ $facts[$key][0] }}@include('site.courses._cites', ['keys' => [$key]])</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- Every lesson, numbered through the course and linked to the lesson page it's on (a unit's challenge is on its
         last lesson's page), each unit with a sketch of its parts. It stands in for the shared list below the page. --}}
    @if ($index !== [])
        <section class="cp-imp-index" aria-labelledby="units-h">
            <div class="s-wrap">
                <div class="cp-imp-index-head">
                    <h2 id="units-h" class="cp-imp-h2">{{ $indexH[0] }}<em>{{ $indexH[1] }}</em>{{ $indexH[2] }}</h2>
                    @if ($outline !== null)
                        <p class="cp-imp-index-note">{{ __('site.courses.pages.improvising-harmony.ih.index_note', ['units' => count($outline['units']), 'lessons' => $outline['lessons']]) }}</p>
                    @endif
                </div>
                <ol class="cp-imp-units">
                    @foreach ($index as $unit)
                        @php($stage = __('pages.site.course_stage', ['n' => $loop->iteration]))
                        @php($unitName = __('pages.site.course_unit', ['n' => $loop->iteration]))
                        <li class="cp-imp-unit" data-unit="{{ $loop->iteration }}">
                            <div class="cp-imp-unit-head">
                                <div>
                                    <p class="cp-imp-kick"><x-site.skin :game="$stage" :serious="$unitName" /></p>
                                    <h3>{{ $unit['title'] }}</h3>
                                </div>
                                <span class="cp-imp-sketch" aria-hidden="true"><i></i><i></i><i></i></span>
                            </div>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-imp-unit-say">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            <ol class="cp-imp-lessons">
                                @foreach ($unit['lessons'] as $lesson)
                                    <li>
                                        <span class="cp-imp-num" aria-hidden="true">{{ $lesson['n'] }}</span>
                                        <span class="cp-imp-lesson">
                                            @if ($lesson['url'] !== null)
                                                <a href="{{ $lesson['url'] }}">{{ $lesson['title'] }}</a>
                                            @else
                                                <b>{{ $lesson['title'] }}</b>
                                            @endif
                                            @if ($lesson['sub'] !== null)
                                                <span class="cp-imp-sub">{{ $lesson['sub'] }}</span>
                                            @endif
                                        </span>
                                        @if ($loop->parent->first && $loop->first)
                                            <span class="s-free">{{ __('pages.site.course_free') }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    <section class="s-wrap cp-imp-sec" aria-labelledby="try-h">
        <div class="cp-imp-try">
            <figure class="cp-imp-strip">
                <div class="cp-imp-strip-lane" aria-hidden="true">
                    @foreach ($strip['tune'] as $note)
                        <span class="cp-imp-strip-note" style="--row: {{ $note['row'] }}; left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"></span>
                    @endforeach
                    @foreach ($strip['part'] as $note)
                        <span class="cp-imp-strip-note cp-imp-strip-held" style="--row: {{ $note['row'] }}; left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"><b>{{ $note['word'] }}</b></span>
                    @endforeach
                </div>
                <figcaption class="sr-only">{{ $ih['strip_alt'] }}</figcaption>
            </figure>
            <div class="cp-imp-try-in">
                <div>
                    <h2 id="try-h" class="cp-imp-h2">{{ $tryH[0] }}<em>{{ $tryH[1] }}</em>{{ $tryH[2] }}</h2>
                    <p class="cp-imp-try-lead">{{ $page['try']['lead'] }}</p>
                    <p class="cp-imp-try-after">{{ $page['try']['after'] }}</p>
                </div>
                <ol class="cp-imp-try-steps">
                    @foreach ((array) $page['try']['steps'] as $step)
                        <li><i class="cp-imp-shape cp-imp-{{ $marks[$loop->index % 4] }}" aria-hidden="true"></i><span>{{ $step }}</span></li>
                    @endforeach
                </ol>
            </div>
        </div>
    </section>

    {{-- Lesson 1 in its own words, from the course file: its why and cards, then its three sung steps. --}}
    <section class="s-wrap cp-imp-sec cp-imp-one" aria-labelledby="one-h">
        <div>
            <p class="cp-imp-kick">{{ $ih['one']['kick'] }}</p>
            <h2 id="one-h" class="cp-imp-h2 cp-imp-h2-s">{{ $oneH[0] }}<em>{{ $oneH[1] }}</em>{{ $oneH[2] }}</h2>
            <p class="cp-imp-p">{{ $ih['one']['lead'] }}</p>
            @if ($one['why'] !== '')
                <p class="cp-imp-one-why">{{ $one['why'] }}</p>
            @endif
            <ul class="cp-imp-one-cards">
                @foreach ($one['cards'] as [$title, $detail])
                    <li><b>{{ $title }}</b><span>{{ $detail }}</span></li>
                @endforeach
            </ul>
        </div>
        <ol class="cp-imp-one-steps">
            @foreach ($one['steps'] as $step)
                <li><i class="cp-imp-shape cp-imp-{{ $marks[$loop->index % 4] }}" aria-hidden="true"></i><span class="cp-imp-num" aria-hidden="true">{{ $loop->iteration }}</span><p>{{ $step }}</p></li>
            @endforeach
        </ol>
    </section>

    {{-- What the app listens for, beside a take plainly marked as a sample: lesson 1's part, one note pulled onto the
         tune (the course's own example measure, "76% on pitch"), never a singer's score. --}}
    <section class="cp-imp-dark" aria-labelledby="hears-h">
        <div class="s-wrap cp-imp-hears">
            <div>
                <h2 id="hears-h" class="cp-imp-h2">{{ $hearsH[0] }}<em>{{ $hearsH[1] }}</em>{{ $hearsH[2] }}</h2>
                <p class="cp-imp-lede">{{ $page['measures'] }}</p>
                <p class="cp-imp-p"><x-site.say :text="$page['scoring']" /></p>
            </div>
            <figure class="cp-imp-take">
                <p class="cp-imp-take-head"><b>{{ $ih['sample']['kick'] }}</b><span>{{ $ih['sample']['what'] }}</span></p>
                <div class="cp-imp-take-lane" aria-hidden="true">
                    @foreach ($lane['part'] as $note)
                        <span @class(['cp-imp-take-note', 'cp-imp-take-miss' => $loop->index === $pulled]) style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%"></span>
                    @endforeach
                    @foreach ($lane['tune'] as $note)
                        <span @class(['cp-imp-take-tune', 'cp-imp-take-pulled' => $loop->index === $pulled]) style="left: {{ $note['left'] }}%; width: {{ $note['width'] }}%">@if ($loop->index === $pulled)<b>{{ $ih['sample']['pulled'] }}</b>@endif</span>
                    @endforeach
                </div>
                <div class="cp-imp-take-foot">
                    <p class="cp-imp-score"><b>{{ $ih['sample']['score'] }}</b><span>{{ $ih['sample']['on_pitch'] }}</span></p>
                    <ul class="cp-imp-legend">
                        @foreach ((array) $ih['sample']['key'] as $key)
                            <li><i class="cp-imp-key-{{ $loop->iteration }}" aria-hidden="true"></i>{{ $key }}</li>
                        @endforeach
                    </ul>
                </div>
                <figcaption class="cp-imp-sample-note">{{ $ih['sample']['note'] }}</figcaption>
            </figure>
        </div>
    </section>

    <section class="s-wrap cp-imp-sec" aria-labelledby="facts-h">
        <p class="cp-imp-kick">{{ $page['titles']['facts'] }}</p>
        <h2 id="facts-h" class="cp-imp-h2 cp-imp-h2-s">{{ $factsH[0] }}<em>{{ $factsH[1] }}</em>{{ $factsH[2] }}</h2>
        <ol class="cp-imp-time">
            @foreach (\App\Support\Site\ImprovisingHarmonyPage::ARCHIVE as $key)
                @continue(! isset($facts[$key]))
                <li>
                    <i class="cp-imp-shape cp-imp-{{ $marks[$loop->index % 4] }}" aria-hidden="true"></i>
                    <p class="cp-imp-era">{{ $ih['eras'][$key] ?? '' }}</p>
                    <p>{{ $facts[$key][0] }}@include('site.courses._cites', ['keys' => [$key]])</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="s-wrap cp-imp-sec cp-imp-about" aria-label="{{ $page['titles']['who'] }}">
        <div class="cp-imp-lists">
            @foreach (['who' => 'sol', 'need' => 'la', 'safety' => 'mi'] as $list => $shape)
                <div>
                    <h2 class="cp-imp-kick cp-imp-ruled">{{ $page['titles'][$list] }}</h2>
                    <ul class="cp-imp-list">
                        @foreach ((array) $page[$list] as $line)
                            <li><i class="cp-imp-shape cp-imp-{{ $shape }}" aria-hidden="true"></i>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
        @if (($page['songs'] ?? []) !== [])
            <div class="cp-imp-songs">
                <h2 class="cp-imp-kick">{{ __('pages.site.course_songs') }}</h2>
                <ol>
                    @foreach ((array) $page['songs'] as [$song, $credit])
                        <li><span class="cp-imp-num" aria-hidden="true">{{ $loop->iteration }}</span><span><b translate="no">{{ $song }}</b><span>{{ $credit }}</span></span></li>
                    @endforeach
                </ol>
            </div>
        @endif
    </section>

    <section class="s-wrap cp-imp-sec cp-imp-faq" aria-labelledby="faq-h">
        <h2 id="faq-h" class="cp-imp-h2">{{ $page['titles']['faq'] }}</h2>
        @include('site.courses._faq')
    </section>

    {{-- The one Sources list every numbered note points at. --}}
    <section class="s-wrap cp-imp-sources" aria-labelledby="sources-h">
        <h2 id="sources-h" class="cp-imp-kick cp-imp-ruled">{{ __('pages.site.sources') }}</h2>
        <ol>
            @foreach ($sources as [$label, $url])
                <li id="src-{{ $loop->iteration }}">@if ($url !== '')<a href="{{ $url }}" rel="noopener">{{ $label }}</a>@else{{ $label }}@endif</li>
            @endforeach
        </ol>
    </section>

    <section class="cp-imp-dark cp-imp-join" aria-labelledby="join-h">
        <div class="s-wrap">
            <span class="cp-imp-join-mark" aria-hidden="true"><i></i><i></i><i></i></span>
            <h2 id="join-h" class="cp-imp-h2">{{ $joinH[0] }}<em>{{ $joinH[1] }}</em>{{ $joinH[2] }}</h2>
            @include('site.courses._join')
            <h3 class="cp-imp-kick">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
    </section>
    <script type="module" src="/js/public/improvising-harmony.js"></script>
</main>
