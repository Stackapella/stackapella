{{-- Musical theatre (/courses/musical-theatre), its own page after Hillel's design (2026-11-18,
     ~/Code/course-designs/musical-theatre): a Victorian Savoy programme, headings in Bodoni Moda. The hero is a tilted
     playbill listing the course's units as acts, each with its song, an "Admission free" stamp and footlights. Then the
     featured demo, Two notes, fourteen syllables (js/public/musical-theatre.js), in a gilt proscenium between red
     curtains: the Major-General's first fourteen syllables as lamps on two rows, C and B flat, each lit as it's sung, the
     notes and syllables the course's own (MusicalTheatrePage::patter, unit 2's Two notes, many words) where the Chappell
     vocal score has them; Play, a tempo slider and smeared or crisp, on voices.js and the audio clock. No mic. Then the
     programme notes (the story), the overture (what you'll be able to do, No. 1 to 4), the running order (every unit an
     act, every lesson linked to its page), the science by seat (stalls to gallery, the course's own card your seat),
     backstage with the call-board, the company as a cast list (each part as the sources give it), before you begin with
     the musical numbers, the interval (questions), the curtain call and the sources. Every claim is from the research
     pack (docs/courses/musical-theatre-love-letter.md), cited. Calm light and dark from the page's tokens; game mode
     restyles the same pieces. No drawn people. Words: lang/en/site.php courses.pages.musical-theatre (love). --}}
@php($love = $page['love'])
@php($sources = $love['sources'])
@php($demo = $love['demo'])
@php($bill = $love['playbill'])
@php($patter = \App\Support\Site\MusicalTheatrePage::patter())
@php($acts = $outline !== null ? \App\Support\Site\MusicalTheatrePage::acts($outline) : [])
@php($tryUrl = $open ? $courseStart : $links['start'])
@php($tryLabel = $open ? __('pages.site.course_try') : __('pages.site.course_start'))
@php($counts = [':lessons' => (string) ($outline['lessons'] ?? ''), ':units' => (string) count($outline['units'] ?? [])])
{{-- The running order lists every lesson, linked: the shared list under the page isn't needed. --}}
@section('lesson-pages-inline', '1')
<main class="cp cp-mt">
    <section class="cp-mt-hero" aria-labelledby="page-h">
        <div class="s-wrap">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/courses">{{ __('pages.site.courses') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $page['name'] }}</span></nav>
            <div class="cp-mt-hero-grid">
                <div class="cp-mt-hero-copy">
                    <p class="cp-mt-kick">{{ $outline !== null ? strtr($love['eyebrow'], $counts) : $page['eyebrow'] }}</p>
                    <h1 id="page-h" class="cp-mt-h1">{{ \App\Support\Site\MusicalTheatrePage::marked($love['heads']['hero']) }}</h1>
                    <p class="cp-mt-lead">{{ $page['hero_lead'] }}</p>
                    <p class="cp-mt-btns">
                        <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                        @if ($outline !== null)
                            <a href="#units-h" class="btn-secondary btn-lg">{{ $page['see_lessons'] }}</a>
                        @endif
                    </p>
                    @unless ($open)
                        <p class="cp-mt-quiet">{{ __('pages.site.course_soon') }}</p>
                    @endunless
                    <ul class="cp-mt-facts">
                        @foreach ((array) $love['chips'] as $chip)
                            @if ($outline !== null || ! str_contains($chip, ':lessons'))
                                <li>{{ strtr($chip, $counts) }}</li>
                            @endif
                        @endforeach
                    </ul>
                </div>
                {{-- The playbill: the units as acts, each with its song. --}}
                <div class="cp-mt-bill-wrap">
                    <div class="cp-mt-bill">
                        <p class="cp-mt-bill-kick">{{ $bill['kick'] }}</p>
                        <p class="cp-mt-bill-presents">{{ $bill['presents'] }}</p>
                        <p class="cp-mt-bill-name">{{ $bill['name'] }}</p>
                        @if ($acts !== [])
                            <p class="cp-mt-bill-acts">{{ strtr($bill['acts'], $counts) }}</p>
                            <p class="cp-mt-orn" aria-hidden="true"><span>◆ ◆ ◆</span></p>
                            <ol class="cp-mt-bill-list">
                                @foreach ($acts as $act)
                                    <li><b>{{ str_replace(':n', $act['numeral'], $bill['act']) }}</b><span>{{ $act['title'] }}</span><i>{{ $bill['songs'][$loop->index] ?? '' }}</i></li>
                                @endforeach
                            </ol>
                        @endif
                        <p class="cp-mt-orn" aria-hidden="true"><span>◆</span></p>
                        <p class="cp-mt-bill-credit">{{ $bill['credit'] }}</p>
                        <p class="cp-mt-stamp">{{ $bill['stamp'] }}</p>
                        <p class="cp-mt-bill-lights" aria-hidden="true">@foreach (range(1, 9) as $i)<i></i>@endforeach</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-mt-demosec" aria-labelledby="demo-h">
        <div class="cp-mt-demohead">
            <p class="cp-mt-kick">{{ $page['kicks']['demo'] }}</p>
            <h2 id="demo-h" class="cp-mt-title">{{ \App\Support\Site\MusicalTheatrePage::marked($love['heads']['demo']) }}</h2>
        </div>
        <div class="cp-mt-frame" data-mt-demo data-style="legato" data-notes="{{ json_encode(array_column($patter, 1)) }}"
            data-ready="{{ $demo['ready'] }}" data-legato="{{ $demo['playing_legato'] }}" data-crisp="{{ $demo['playing_crisp'] }}" data-after="{{ $demo['after'] }}" data-rate="{{ $demo['rate'] }}">
            <div class="cp-mt-valance" aria-hidden="true"></div>
            <div class="cp-mt-boards">
                <span class="cp-mt-curtain" aria-hidden="true"></span>
                <div class="cp-mt-stage">
                    <p class="cp-mt-stagebar"><span>{{ $demo['key'] }}</span><span>{{ $demo['show'] }}</span></p>
                    <div class="cp-mt-lamps" role="img" aria-label="{{ $demo['lamps'] }}">
                        @foreach (array_chunk($patter, 7, true) as $half)
                            <div class="cp-mt-half">
                                <span class="cp-mt-pitch">{{ $love['stage']['high'] }}</span>
                                <span class="cp-mt-pitch cp-mt-pitch-lo">{{ $love['stage']['low'] }}</span>
                                @foreach ($half as $i => [$syllable, $midi, $high])
                                    <span class="cp-mt-col" data-mt-note="{{ $i }}" @if ($high) data-high @endif><i class="cp-mt-bulb"></i><b>{{ $syllable }}</b></span>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <p class="sr-only">{{ implode(' ', array_column($patter, 0)) }}</p>
                    <button type="button" class="cp-mt-play" data-mt-play aria-pressed="false"><span class="cp-mt-play-icon" aria-hidden="true"></span><span class="cp-mt-go"><x-site.skin :game="__('pages.site.demo_press_start')" :serious="$demo['play']" /></span><span class="cp-mt-stop">{{ $demo['stop'] }}</span></button>
                </div>
                <span class="cp-mt-curtain cp-mt-curtain-r" aria-hidden="true"></span>
            </div>
            <p class="cp-mt-foot" aria-hidden="true">@foreach (range(1, 12) as $i)<i></i>@endforeach</p>
            <div class="cp-mt-deck">
                <p class="cp-mt-deck-lead">{{ $demo['lead'] }}@include('site.courses._cites', ['keys' => ['score']])</p>
                <div class="cp-mt-controls">
                    <label class="cp-mt-tempo">
                        <span class="cp-mt-tempo-top"><span>{{ $demo['tempo'] }}</span><output data-mt-rate-out>{{ str_replace(':n', '3', $demo['rate']) }}</output></span>
                        <input type="range" min="2" max="7" step="0.5" value="3" data-mt-rate>
                        <span class="cp-mt-ends" aria-hidden="true"><span>{{ $demo['slow'] }}</span><span>{{ $demo['fast'] }}</span></span>
                    </label>
                    <fieldset class="cp-mt-style">
                        <legend>{{ $demo['style'] }}</legend>
                        <span class="cp-mt-chips">
                            @foreach (['legato', 'crisp'] as $style)
                                <button type="button" class="cp-mt-chip" data-mt-style="{{ $style }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}"><span class="cp-mt-tick" aria-hidden="true"></span>{{ $demo[$style] }}</button>
                            @endforeach
                        </span>
                    </fieldset>
                    <p class="cp-mt-say" role="status" data-mt-say>{{ $demo['ready'] }}</p>
                    <p class="cp-mt-demo-note">{{ $demo['note'] }}</p>
                </div>
            </div>
        </div>
    </section>

    <section class="s-wrap cp-mt-story" aria-labelledby="story-h">
        <div class="cp-mt-side">
            <p class="cp-mt-kick">{{ $page['kicks']['story'] }}</p>
            <h2 id="story-h" class="cp-mt-title">{{ \App\Support\Site\MusicalTheatrePage::marked($love['heads']['story']) }}</h2>
        </div>
        <div class="cp-mt-years-wrap">
            @php($years = (array) $love['story'])
            @foreach ([array_slice($years, 0, 2), array_slice($years, 2)] as $part)
                @if (! $loop->first)
                    <details class="cp-mt-more" data-mt-wide><summary>{{ $love['more']['story'] }}<span class="cp-mt-sign" aria-hidden="true"></span></summary>
                @endif
                <ol class="cp-mt-years">
                    @foreach ($part as [$when, $title, $text, $cites])
                        <li>
                            <p class="cp-mt-year">{{ $when }}</p>
                            <div>
                                <h3>{{ $title }}</h3>
                                <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
                @if (! $loop->first)
                    </details>
                @endif
            @endforeach
        </div>
    </section>

    <section class="cp-mt-overture" aria-labelledby="outcomes-h">
        <div class="s-wrap">
            <p class="cp-mt-kick">{{ $page['kicks']['aims'] }}</p>
            <h2 id="outcomes-h" class="cp-mt-title">{{ \App\Support\Site\MusicalTheatrePage::marked($love['heads']['aims']) }}</h2>
            <ol class="cp-mt-aims">
                @foreach ((array) $page['outcomes'] as $outcome)
                    <li><p class="cp-mt-aim-n">{{ str_replace(':n', (string) $loop->iteration, $love['number']) }}</p><p>{{ $outcome }}</p></li>
                @endforeach
            </ol>
        </div>
    </section>

    @if ($acts !== [])
        <section class="cp-mt-order" aria-labelledby="units-h">
            <div class="s-wrap">
                <div class="cp-mt-orderhead">
                    <div>
                        <p class="cp-mt-kick">{{ $page['kicks']['units'] }}</p>
                        <h2 id="units-h" class="cp-mt-title">{{ \App\Support\Site\MusicalTheatrePage::marked($love['heads']['units']) }}</h2>
                    </div>
                    <p class="cp-mt-count">{{ __('pages.site.course_size', ['units' => count($acts), 'lessons' => $outline['lessons']]) }}</p>
                </div>
                <ol class="cp-mt-acts">
                    @foreach ($acts as $act)
                        <li class="cp-mt-act" data-unit="{{ $act['n'] }}" style="--unit: {{ $act['n'] }}">
                            <p class="cp-mt-numeral" aria-hidden="true">{{ $act['numeral'] }}</p>
                            <p class="cp-mt-act-name"><x-site.skin :game="__('pages.site.course_stage', ['n' => $act['n']])" :serious="$love['act_names'][$loop->index] ?? str_replace(':n', $act['numeral'], $bill['act'])" /></p>
                            <h3>{{ $act['title'] }}</h3>
                            @isset($page['units_say'][$loop->index])
                                <p class="cp-mt-act-say">{{ $page['units_say'][$loop->index] }}</p>
                            @endisset
                            <ol class="cp-mt-lessons">
                                @foreach ($act['lessons'] as $lesson)
                                    <li @class(['cp-mt-song' => $lesson['song']])>
                                        @if ($lesson['url'] !== null)
                                            <a href="{{ $lesson['url'] }}">{{ $lesson['name'] }}</a>
                                        @else
                                            <span>{{ $lesson['name'] }}</span>
                                        @endif
                                        @if ($loop->parent->first && $loop->first)
                                            <span class="cp-mt-start">{{ __('pages.site.course_free') }}</span>
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

    <section class="s-wrap cp-mt-science" aria-labelledby="science-h">
        <div class="cp-mt-scihead">
            <div>
                <p class="cp-mt-kick">{{ $page['kicks']['science'] }}</p>
                <h2 id="science-h" class="cp-mt-title">{{ \App\Support\Site\MusicalTheatrePage::marked($love['heads']['science']) }}</h2>
            </div>
            <p class="cp-mt-scilead">{{ $love['science_lead'] }}</p>
        </div>
        @php($cards = (array) $love['science'])
        @foreach ([array_slice($cards, 0, 2, true), array_slice($cards, 2, null, true)] as $part)
            @if (! $loop->first)
                <details class="cp-mt-more" data-mt-wide><summary>{{ $love['more']['science'] }}<span class="cp-mt-sign" aria-hidden="true"></span></summary>
            @endif
            <ul class="cp-mt-cards">
                @foreach ($part as $i => [$title, $text, $cites])
                    <li class="cp-mt-card">
                        <p class="cp-mt-tier">{{ $love['tiers'][$i] ?? '' }}</p>
                        <h3>{{ $title }}</h3>
                        <p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
                    </li>
                @endforeach
                @if (! $loop->first)
                    <li class="cp-mt-card cp-mt-card-ours">
                        <p class="cp-mt-tier">{{ $love['seat'] }}</p>
                        <h3>{{ $love['science_course'][0] }}</h3>
                        <p>{{ $love['science_course'][1] }}</p>
                    </li>
                @endif
            </ul>
            @if (! $loop->first)
                </details>
            @endif
        @endforeach
    </section>

    <section class="cp-mt-band" aria-labelledby="margin-h">
        <div class="s-wrap cp-mt-band-grid">
            <div>
                <p class="cp-mt-kick">{{ $page['kicks']['margin'] }}</p>
                <h2 id="margin-h" class="cp-mt-title">{{ \App\Support\Site\MusicalTheatrePage::marked($love['heads']['margin']) }}</h2>
                <div class="cp-mt-listens">
                    <h3 class="cp-mt-kick">{{ $page['titles']['inside'] }}</h3>
                    <p>{{ $page['measures'] }}</p>
                </div>
            </div>
            <div>
                <p class="cp-mt-board-kick">{{ $love['callboard'] }}</p>
                @php($notes = (array) $love['margin'])
                <ul class="cp-mt-board">
                    @foreach (array_slice($notes, 0, 2) as [$text, $cites])
                        <li><p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p></li>
                    @endforeach
                </ul>
                <details class="cp-mt-more" data-mt-wide><summary>{{ $love['more']['margin'] }}<span class="cp-mt-sign" aria-hidden="true"></span></summary>
                    <ul class="cp-mt-board">
                        @foreach (array_slice($notes, 2) as [$text, $cites])
                            <li><p>{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p></li>
                        @endforeach
                    </ul>
                </details>
            </div>
        </div>
    </section>

    <section class="cp-mt-companysec" aria-labelledby="company-h">
        <div class="s-wrap cp-mt-narrow">
            <p class="cp-mt-kick">{{ $page['kicks']['company'] }}</p>
            <h2 id="company-h" class="cp-mt-title">{{ $page['titles']['company'] }}</h2>
            <p class="cp-mt-orn" aria-hidden="true"><span>◆ ◆ ◆</span></p>
            @php($cast = (array) $love['company'])
            @foreach ([array_slice($cast, 0, 3), array_slice($cast, 3)] as $part)
                @if (! $loop->first)
                    <details class="cp-mt-more" data-mt-wide><summary>{{ $love['more']['company'] }}<span class="cp-mt-sign" aria-hidden="true"></span></summary>
                @endif
                <ol class="cp-mt-cast">
                    @foreach ($part as [$name, $role, $years, $what, $cites])
                        <li>
                            <p class="cp-mt-cast-top"><b>{{ $name }}</b><span class="cp-mt-leader" aria-hidden="true"></span><i>{{ $role }}</i></p>
                            <p class="cp-mt-cast-more"><span class="cp-mt-cast-years">{{ $years }}</span><span>{{ $what }}@include('site.courses._cites', ['keys' => $cites])</span></p>
                        </li>
                    @endforeach
                </ol>
                @if (! $loop->first)
                    </details>
                @endif
            @endforeach
        </div>
    </section>

    <section class="s-wrap cp-mt-begin" aria-labelledby="begin-h">
        <p class="cp-mt-kick">{{ $page['kicks']['begin'] }}</p>
        <h2 id="begin-h" class="cp-mt-title">{{ \App\Support\Site\MusicalTheatrePage::marked($love['heads']['who']) }}</h2>
        <div class="cp-mt-begin-grid">
            <div>
                <h3 class="cp-mt-colhead">{{ $love['audience'] }}</h3>
                <ul class="cp-mt-ticks">@foreach ((array) $page['who'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul>
            </div>
            <div>
                <h3 class="cp-mt-colhead">{{ $page['titles']['safety'] }}</h3>
                <ul class="cp-mt-ticks cp-mt-warn">@foreach ((array) $page['safety'] as $item)<li><i aria-hidden="true">!</i><span>{{ $item }}</span></li>@endforeach</ul>
            </div>
            <div>
                <h3 class="cp-mt-colhead">{{ $page['titles']['need'] }}</h3>
                <ul class="cp-mt-ticks">@foreach ((array) $page['need'] as $item)<li><x-site.icon name="check" size="16" /><span>{{ $item }}</span></li>@endforeach</ul>
            </div>
        </div>
        <div class="cp-mt-numbers">
            <h3 class="cp-mt-colhead">{{ $love['numbers'] }}</h3>
            <ul>
                @foreach ((array) $love['numbers_list'] as [$song, $show])
                    <li><b translate="no">{{ $song }}</b><span>{{ $show }}</span></li>
                @endforeach
            </ul>
            <p>{{ $love['numbers_note'] }}</p>
        </div>
    </section>

    <section class="s-wrap cp-mt-fine" aria-labelledby="faq-h">
        <div>
            <p class="cp-mt-kick">{{ $page['kicks']['questions'] }}</p>
            <h2 id="faq-h" class="cp-mt-title">{{ $page['titles']['faq'] }}</h2>
        </div>
        @include('site.courses._faq')
    </section>

    <section class="cp-mt-close" aria-labelledby="join-h">
        <div class="cp-mt-valance" aria-hidden="true"></div>
        <span class="cp-mt-drape" aria-hidden="true"></span>
        <span class="cp-mt-drape cp-mt-drape-r" aria-hidden="true"></span>
        <div class="s-wrap cp-mt-close-in">
            <p class="cp-mt-kick">{{ $page['kicks']['close'] }}</p>
            <h2 id="join-h" class="cp-mt-title">{{ \App\Support\Site\MusicalTheatrePage::marked($love['heads']['join']) }}</h2>
            <p class="cp-mt-close-body">{{ $open ? $page['close'] : __('pages.site.course_soon') }}</p>
            <p class="cp-mt-btns">
                <a href="{{ $tryUrl }}" class="btn-primary btn-lg">{{ $tryLabel }} <x-site.icon name="arrow" size="18" /></a>
                <a href="/courses" class="btn-secondary btn-lg">{{ __('pages.site.course_all') }}</a>
            </p>
            @if ($open)
                <p class="cp-mt-close-note">{{ __('pages.site.course_join_note') }}</p>
            @endif
            <h3 class="cp-mt-kick cp-mt-related-h">{{ $page['titles']['related'] }}</h3>
            @include('site.courses._related')
        </div>
        <p class="cp-mt-foot" aria-hidden="true">@foreach (range(1, 10) as $i)<i></i>@endforeach</p>
    </section>

    <section class="s-wrap cp-mt-sources" aria-labelledby="notes-h">
        <details data-mt-sources>
            <summary><h2 id="notes-h" class="cp-mt-kick">{{ __('pages.site.sources') }} <span>({{ count($sources) }})</span></h2><span class="cp-mt-sign" aria-hidden="true"></span></summary>
            <ol>
                @foreach ($sources as [$label, $url])
                    <li id="src-{{ $loop->iteration }}"><a href="{{ $url }}" rel="noopener">{{ $label }}</a></li>
                @endforeach
            </ol>
        </details>
    </section>
    <script type="module" src="/js/public/musical-theatre.js"></script>
</main>
