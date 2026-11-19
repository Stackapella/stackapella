{{-- The songs in the public domain, and the songs that will be (/songs/public-domain; PublicDomainPageController gives
     $page from App\Support\Pages\PublicDomain, and $canonical). It celebrates the songs and the people who wrote them
     (Hillel, 2026-11-19: "we should be more celebrating their legacy than mourning their passing"): the heading with
     Public Domain Day beside it, the filter bar, our public domain songs, then each 1 January as a release of songs (the
     years ahead, then free now, then free for generations by century, then the songs passed down without a name), each
     song first and its writer after it, and the questions people ask. Every word from lang/en/site.php `public_domain`;
     the roll's words are the support class's. The roll's lists are written without indents: a few hundred people, and
     the page's weight is its own. --}}
@php($w = 'site.public_domain.')
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title(__('site.public_domain.title'))])

@section('head')
    <meta name="description" content="{{ __($w.'description', ['year' => $page['year'] + 1]) }}">
    <meta property="og:site_name" content="{{ __('meta.brand') }}">
    <meta property="og:title" content="{{ __($w.'title') }}">
    <meta property="og:description" content="{{ __($w.'description', ['year' => $page['year'] + 1]) }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ \App\Support\Site\SitePages::image($canonical) }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ __($w.'title') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ $canonical }}">
    <x-site.json-ld :data="\App\Support\Site\StructuredData::publicDomain($page, $canonical)" />
@endsection

@section('content')
    <x-site.header />
    <main class="s-pd">
        <section class="s-wrap s-pd-hero" aria-labelledby="page-h">
            <div class="s-pd-hero-text">
                <x-site.crumbs :trail="[[__('site.song_part.songs'), '/songs'], [__($w.'crumb')]]" />
                <p class="s-eyebrow s-pd-eyebrow"><x-site.skin :game="__($w.'game_eyebrow')" :serious="__($w.'eyebrow')" /></p>
                <h1 id="page-h" class="s-hub-h1 s-pd-h1"><span><x-site.skin heading :game="__($w.'game_headline')" :serious="__($w.'headline')" /></span> <em class="s-hl-em"><x-site.skin heading :game="__($w.'game_headline_em')" :serious="__($w.'headline_em')" /></em></h1>
                <p class="s-pd-lead">{{ __($w.'lead') }}</p>
                <dl class="s-pd-facts" aria-label="{{ __($w.'facts_label') }}">
                    <div><dt>{{ trans_choice($w.'facts.songs', $page['counts']['songs']) }}</dt><dd>{{ $page['counts']['songs'] }}</dd></div>
                    <div><dt>{{ trans_choice($w.'facts.works', $page['counts']['works'], ['last' => $page['counts']['worksBy']]) }}</dt><dd>{{ $page['counts']['works'] }}</dd></div>
                    <div><dt>{{ trans_choice($w.'facts.people', $page['counts']['people']) }}</dt><dd>{{ $page['counts']['people'] }}</dd></div>
                </dl>
            </div>

            {{-- Public Domain Day: the songs newly free this coming 1 January, each song first and its writer after it (Hillel:
                 "I recognize the song names more than the authors"), and the way to that year on the roll. --}}
            @if ($page['next'] !== null)
                @php($next = $page['next'])
                <aside class="s-pd-next" aria-labelledby="next-h">
                    <p class="s-pd-next-k"><x-site.skin :game="__($w.'next.game_kick')" :serious="__($w.'next.kick', ['year' => $next['year']])" /></p>
                    <h2 id="next-h" class="s-pd-next-date"><span class="s-pd-next-y">{{ $next['year'] }}</span><span class="s-pd-next-d">{{ __($w.'next.newly') }}</span></h2>
                    <p class="s-pd-next-line">{{ $next['nextLine'] }}</p>
                    <ul class="s-pd-next-list">
                        @foreach ($next['newly'] as $item)
                            <li>
                                <span class="s-pd-next-songs">
                                    @foreach ($item['songs'] as $song)
                                        @if (! $loop->first)<span class="s-pd-next-sep" aria-hidden="true">·</span>@endif
                                        @if ($song['url'] !== null)<a href="{{ $song['url'] }}" translate="no">{{ $song['title'] }}</a>@else<span>{{ $song['title'] }}</span>@endif
                                    @endforeach
                                </span>
                                <a href="#{{ $item['key'] }}" class="s-pd-next-by">{{ $item['name'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                    <p class="s-pd-next-more"><a href="#y{{ $next['year'] }}" class="s-lk"><x-site.arrow-end :text="__($w.'next.more', ['year' => $next['year']])" size="16" /></a></p>
                </aside>
            @endif
        </section>

        {{-- The filters (the five pages' kit): one chip pressed at a time and a search; both narrow the roll in place
             (js/public/public-domain.js), a year left with no one hidden. --}}
        <div class="s-fbar s-pd-bar" id="pd-bar" data-pd-filter>
            <div class="s-wrap s-fbar-in">
                <div class="s-fbar-chips" role="group" aria-label="{{ __($w.'filter_label') }}">
                    <button type="button" class="s-fchip" data-filter="all" aria-pressed="true">{{ __($w.'chips.all') }} <span>{{ $page['counts']['people'] }}</span></button>
                    <button type="button" class="s-fchip" data-filter="coming" aria-pressed="false">{{ __($w.'chips.coming') }} <span>{{ $page['counts']['coming'] }}</span></button>
                    <button type="button" class="s-fchip" data-filter="joined" aria-pressed="false">{{ __($w.'chips.joined') }} <span>{{ $page['counts']['joined'] }}</span></button>
                    <button type="button" class="s-fchip" data-filter="site" aria-pressed="false">{{ __($w.'chips.site') }} <span>{{ $page['counts']['library'] }}</span></button>
                </div>
                <label class="s-fbar-search">
                    <span class="sr-only">{{ __($w.'search_label') }}</span>
                    <x-site.icon name="search" size="16" />
                    <input type="search" data-pd-search placeholder="{{ __($w.'search') }}" autocomplete="off" enterkeyhint="search">
                </label>
            </div>
            {{-- The Year row: every year on the roll, to jump to; a compact menu on a phone, where the row would be tall. --}}
            <div class="s-wrap s-pd-years">
                <span class="s-pd-years-k" aria-hidden="true">{{ __($w.'year_label') }}</span>
                <nav class="s-pd-years-row" aria-label="{{ __($w.'year_select') }}">
                    @foreach ($page['years'] as $year)
                        <a href="{{ $year['href'] }}" data-pd-jump="{{ $year['part'] }}">{{ $year['label'] }}</a>
                    @endforeach
                </nav>
                <label class="s-pd-years-pick">
                    <span class="sr-only">{{ __($w.'year_select') }}</span>
                    <select data-pd-pick>
                        <option value="">{{ __($w.'year_select') }}</option>
                        @foreach ($page['years'] as $year)
                            <option value="{{ $year['href'] }}" data-pd-jump="{{ $year['part'] }}">{{ $year['label'] }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <p class="sr-only" aria-live="polite" data-pd-shown data-words="{{ __($w.'shown') }}"></p>
        </div>

        <div id="roll" class="s-pd-roll">
            {{-- Public domain songs you can sing now: our songs by title, each with who wrote it, its first printing and
                 whether a longer term still holds it somewhere (what most people searching for public domain songs want). --}}
            @if ($page['songs'] !== [])
                <section class="s-wrap s-pd-part s-pd-songs" id="songs" aria-labelledby="songs-h" data-pd-part="songs" data-pd-year>
                    <header class="s-pd-part-head">
                        <h2 id="songs-h"><x-site.skin heading :game="__($w.'game_songs_h')" :serious="__($w.'songs_h')" /></h2>
                        <p>{{ __($w.'songs_sub') }}</p>
                        @include('site._public-domain-legend')
                    </header>
                    <ol class="s-pd-songlist" data-pd-songlist>
@foreach ($page['songs'] as $song)
<li data-pd-item data-pd-state="joined" data-pd-site="1" data-pd-text="{{ $song['search'] }}"><a href="{{ $song['url'] }}" class="s-pd-song" translate="no">{{ $song['title'] }}</a><span class="s-pd-by">{{ $song['by'] }}@if ($song['printedText'] !== '') · {{ $song['printedText'] }}@endif</span><span class="s-pd-dot s-pd-dot-now s-pd-free">{{ __($w.'legend_now') }}</span>
@if ($song['longerText'] !== '')
<span class="s-pd-dot s-pd-dot-later s-pd-free">{{ $song['longerText'] }}</span>
@endif
</li>
@endforeach
                    </ol>
                    {{-- The list shows its first songs until asked for the rest (js/public/public-domain.js; every song stays in
                         the page, and a search reaches them all); without the script, all of them show. --}}
                    <p class="s-pd-songs-more" data-pd-songs-more hidden><button type="button" class="btn-secondary">{{ trans_choice($w.'songs_all', count($page['songs']), ['count' => count($page['songs'])]) }}</button></p>
                </section>
            @endif
            @foreach ([['coming', $page['coming']], ['joined', $page['joined']]] as [$part, $groups])
                @if ($groups !== [])
                    <section class="s-wrap s-pd-part" aria-labelledby="{{ $part }}-h" data-pd-part="{{ $part }}">
                        <header class="s-pd-part-head">
                            <h2 id="{{ $part }}-h"><x-site.skin heading :game="__($w.'game_'.$part.'_h')" :serious="__($w.$part.'_h', ['year' => $page['year'] + 1])" /></h2>
                            <p>{{ __($w.$part.'_sub') }}</p>
                            @include('site._public-domain-legend')
                        </header>
                        @foreach ($groups as $group)
                            <section class="s-pd-year" id="y{{ $group['year'] }}" aria-labelledby="yh{{ $group['year'] }}" data-pd-year>
                                <div class="s-pd-year-head">
                                    <h3 id="yh{{ $group['year'] }}" class="s-pd-yh">{{ $group['date'] }}</h3>
                                    <p class="s-pd-yline">{{ $group['line'] }}</p>
                                </div>
                                <div class="s-pd-year-body">
@if ($group['people'] !== [])
<ol class="s-pd-people">
@foreach ($group['people'] as $person)
@include('site._public-domain-person', ['person' => $person])
@endforeach
</ol>
@endif
                                </div>
                            </section>
                        @endforeach
                    </section>
                @endif
            @endforeach

            @if ($page['long'] !== [])
                <section class="s-wrap s-pd-part s-pd-part-long" id="long" aria-labelledby="long-h" data-pd-part="long">
                    <header class="s-pd-part-head">
                        <h2 id="long-h"><x-site.skin heading :game="__($w.'game_long_h')" :serious="__($w.'long_h')" /></h2>
                        <p>{{ __($w.'long_sub') }}</p>
                        @include('site._public-domain-legend')
                    </header>
                    @foreach ($page['long'] as $century)
                        <section class="s-pd-year s-pd-century" aria-labelledby="c{{ $century['century'] }}" data-pd-year>
                            <div class="s-pd-year-head">
                                <h3 id="c{{ $century['century'] }}" class="s-pd-yh">{{ $century['label'] }}</h3>
                            </div>
                            <div class="s-pd-year-body">
<ol class="s-pd-people s-pd-people-2">
@foreach ($century['people'] as $person)
@include('site._public-domain-person', ['person' => $person])
@endforeach
</ol>
                            </div>
                        </section>
                    @endforeach
                </section>
            @endif

            @if ($page['unsigned'] !== [])
                <section class="s-wrap s-pd-part s-pd-unsigned" aria-labelledby="unsigned-h" data-pd-part="unsigned" data-pd-year>
                    <header class="s-pd-part-head">
                        <h2 id="unsigned-h"><x-site.skin heading :game="__($w.'game_unsigned_h')" :serious="__($w.'unsigned_h')" /></h2>
                        <p>{{ __($w.'unsigned_sub') }}</p>
                    </header>
                    <ul class="s-pd-unsigned-list">
@foreach ($page['unsigned'] as $song)
<li data-pd-item data-pd-state="long" data-pd-site="1" data-pd-text="{{ $song['search'] }}"><a href="{{ $song['url'] }}" translate="no">{{ $song['title'] }}</a>
@if ($song['printedText'] !== '')
<span>{{ $song['printedText'] }}</span>
@endif
</li>
@endforeach
                    </ul>
                </section>
            @endif

            <div class="s-wrap s-pd-none" data-pd-none hidden>
                <p data-words="{{ __($w.'none') }}"></p>
                <button type="button" class="btn-secondary" data-pd-reset>{{ __($w.'reset') }}</button>
            </div>
        </div>

        {{-- The questions people search with, answered plainly (FAQPage data has the same words): when a song enters the
             public domain, with the terms we check summarised from the table; and whether it's free everywhere, with how
             this site uses it and where the dates come from; then the one plain line that it isn't legal advice. --}}
        <section class="s-wrap s-pd-about" aria-label="{{ __($w.'faq_when_h') }}">
            <div class="s-pd-about-grid">
                <div>
                    <h2 class="s-pd-about-h">{{ __($w.'faq_when_h') }}</h2>
                    <p>{{ __($w.'what') }}</p>
                    <p>{{ __($w.'faq_when', ['before' => $page['usBefore']]) }}</p>
                    <table class="s-pd-terms">
                        <caption>{{ __($w.'terms_label') }}</caption>
                        <thead><tr><th scope="col">{{ __($w.'terms_term') }}</th><th scope="col">{{ __($w.'terms_places') }}</th></tr></thead>
                        <tbody>
                            @foreach ($page['terms'] as $row)
                                <tr><th scope="row">{{ $row['term'] }}</th><td>{{ $row['places'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div>
                    <h2 class="s-pd-about-h">{{ __($w.'faq_where_h') }}</h2>
                    <p>{{ __($w.'faq_where') }}</p>
                    <p>{{ $page['enforced'] ? __($w.'here_on') : __($w.'here_off') }}</p>
                    <p>{{ __($w.'low') }}</p>
                    <h3>{{ __($w.'sources_h') }}</h3>
                    <p>{{ __($w.'sources') }}</p>
                </div>
            </div>
            <p class="s-pd-legal">{{ __($w.'legal') }}</p>
        </section>

        {{-- On a phone the bar scrolls away with the page; this brings it back (shown once it's out of view). --}}
        <a href="#pd-bar" class="s-pd-tobar" data-pd-tobar hidden><x-site.icon name="search" size="16" /><span>{{ __($w.'to_bar') }}</span></a>

        <section class="s-wrap s-pd-close" aria-labelledby="close-h">
            <h2 id="close-h">{{ __($w.'close_h') }}</h2>
            <p>{{ __($w.'close') }}</p>
            <p><a href="/songs" class="btn-primary btn-lg">{{ __($w.'close_cta') }} <x-site.icon name="arrow" size="18" /></a></p>
        </section>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    <script type="module" src="/js/public/public-domain.js"></script>
@endsection
