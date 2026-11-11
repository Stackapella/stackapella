{{-- /songs, the songbook (Hillel's "Songbook page rebuild", 2026-11-11, on his sampler; App\Support\Pages\SongsHub):
     the promise, a search and every collection as a chip, with a song to try beside it, each part switchable; the
     collection in season as a full-width band in its page's look; the other collections as a grid of panels in their
     palettes, each with four songs to play and the way to all of them; then every song A to Z by letter, to filter and
     search in place, every song a plain link (for people and for search). Kept out of the site's navigation (Hillel). --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

@section('head')
    <meta name="description" content="{{ $meta['description'] }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    <meta property="og:type" content="website">
    <meta property="og:image" content="{{ \App\Support\Site\SitePages::image($meta['url']) }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ $meta['url'] }}">
@endsection

{{-- A band's words: the carols' own, else its collection's. --}}
@php($w = fn (string $key): string => $key === 'christmas' ? 'site.carols' : 'site.collections.'.$key)
@php($season = collect($bands)->firstWhere('season', true))
@php($panels = array_values(array_filter($bands, static fn (array $band): bool => ! $band['season'])))
@php($letters = collect($index)->groupBy('letter'))
@php($others = count(array_filter($index, static fn (array $entry): bool => $entry['in'] === ['other'])))

@section('content')
    <x-site.header />
    <main>
        <section class="s-wrap s-sh-hero" aria-labelledby="page-h">
            <div>
                <p class="s-eyebrow"><x-site.skin :game="__('pages.landing.game.songs_kick')" :serious="__('pages.songs.eyebrow_count', ['count' => $count])" /></p>
                <h1 id="page-h">{{ __('pages.songs.headline') }} <em class="s-xserif">{{ __('pages.songs.headline_em') }}</em></h1>
                <p class="s-lede">{{ __('pages.songs.lead') }}</p>
                {{-- Search: the A to Z narrows as you type (js/public/songbook.js); without the script, ?q= does it on the server. --}}
                <form class="s-sh-search" action="/songs#every-song" method="get" role="search" data-songbook-search>
                    <x-site.icon name="search" size="18" />
                    <input type="search" name="q" value="{{ $q }}" placeholder="{{ __('pages.songs.search', ['count' => $count]) }}" data-short="{{ __('pages.songs.search_short', ['count' => $count]) }}" aria-label="{{ __('pages.songs.search_label') }}" autocomplete="off">
                </form>
                <ul class="s-sh-chips" aria-label="{{ __('pages.songs.jump') }}">
                    @foreach ($bands as $band)
                        <li><a href="{{ $band['path'] }}" class="s-sh-chip2 s-tone-{{ $band['key'] }}"><i aria-hidden="true"></i>{{ __($w($band['key']).'.crumb') }}<span>{{ $band['count'] }}</span></a></li>
                    @endforeach
                </ul>
                @if ($rounds !== null)
                    <p class="s-sh-start"><a href="{{ $rounds }}" class="s-lk">{{ __('pages.songs.start_round') }}&#8288;<x-site.icon name="arrow" size="16" /></a></p>
                @endif
            </div>
            {{-- A song to try: its first line in four parts, each switched on or off as it plays (x-site.mixer). --}}
            @if ($try !== null)
                <div class="s-sh-try">
                    <x-site.mixer :song="$try" id="try-mixer"><span class="s-sh-try-k">{{ __('pages.songs.try') }}</span><x-slot:foot>{{ __('pages.songs.try_note') }}</x-slot:foot></x-site.mixer>
                </div>
            @endif
        </section>

        @if ($season !== null)
            @php($words = $w($season['key']))
            <section id="band-{{ $season['key'] }}" class="s-sh-band s-tone s-tone-{{ $season['key'] }} s-sh-season" aria-labelledby="band-{{ $season['key'] }}-h">
                @include('site._xmas-sky')
                <div class="s-sh-band-in">
                    <div class="s-sh-words">
                        <p class="s-sh-kick">
                            <span>{{ __($words.'.crumb') }}</span>
                            <span class="s-sh-chip"><span>{{ __('pages.songs.season') }}</span>@if ($until !== null)<span>{{ __('pages.songs.season_until', ['date' => $until]) }}</span>@endif</span>
                        </p>
                        <h2 id="band-{{ $season['key'] }}-h">{{ __($words.'.headline') }} <em class="s-xserif">{{ __($words.'.headline_em') }}</em></h2>
                        <p class="s-sh-line">{{ __($words.'.lead') }}</p>
                    </div>
                    @include('public._songs-samples', ['songs' => $season['samples']])
                    <a href="{{ $season['path'] }}" class="btn-primary s-sh-all">{{ __($words.'.all', ['count' => $season['count']]) }} <x-site.icon name="arrow" size="18" /></a>
                </div>
            </section>
        @endif

        {{-- The other collections as a grid of panels, each a preview of its page. --}}
        @if ($panels !== [])
            <section class="s-wrap s-sec s-sh-grid-sec" aria-labelledby="collections-h">
                <div class="s-sh-grid-head">
                    <div>
                        <p class="s-kick">{{ __('pages.songs.collections_kick') }}</p>
                        <h2 id="collections-h">{{ __('pages.songs.collections') }} <em class="s-xserif">{{ __('pages.songs.collections_em') }}</em></h2>
                    </div>
                    <p class="s-sh-grid-note">{{ __('pages.songs.collections_note') }}</p>
                </div>
                <div class="s-sh-grid">
                    @foreach ($panels as $band)
                        @php($words = $w($band['key']))
                        <section id="band-{{ $band['key'] }}" class="s-sh-panel s-tone s-tone-{{ $band['key'] }}" aria-labelledby="band-{{ $band['key'] }}-h">
                            @include('site._tone-motif', ['key' => $band['key']])
                            <div class="s-sh-panel-in">
                                <div class="s-sh-panel-top">
                                    <h3 id="band-{{ $band['key'] }}-h">{{ __($words.'.band') }} <em class="s-xserif">{{ __($words.'.band_em') }}</em></h3>
                                    <a href="{{ $band['path'] }}" class="s-sh-panel-all" aria-label="{{ __($words.'.all', ['count' => $band['count']]) }}">{{ __('pages.songs.all_short', ['count' => $band['count']]) }}&#8288;<x-site.icon name="arrow" size="16" /></a>
                                </div>
                                <p class="s-sh-line">{{ __($words.'.band_line') }}</p>
                                @include('public._songs-samples', ['songs' => $band['samples']])
                            </div>
                        </section>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Every song by letter, each a plain link; the chips and the search narrow it in place (js/public/songbook.js). --}}
        <section id="every-song" class="s-wrap s-sec s-sh-index" aria-labelledby="every-song-h" data-songbook-index>
            <div class="s-sh-index-head">
                <div>
                    <p class="s-kick">{{ __('pages.songs.index_kick') }}</p>
                    <h2 id="every-song-h">{{ __('pages.songs.index') }}</h2>
                </div>
                <label class="s-sh-search s-sh-search-sm">
                    <x-site.icon name="search" size="16" />
                    <input type="search" value="{{ $q }}" placeholder="{{ __('pages.songs.index_search') }}" aria-label="{{ __('pages.songs.search_label') }}" autocomplete="off" data-index-search>
                </label>
            </div>
            <div class="s-sh-filters" role="group" aria-label="{{ __('pages.songs.jump') }}">
                <button type="button" class="s-sh-filter" data-filter="" aria-pressed="true">{{ __('pages.songs.all_songs') }}<span>{{ $count }}</span></button>
                @foreach ($bands as $band)
                    <button type="button" class="s-sh-filter s-tone-{{ $band['key'] }}" data-filter="{{ $band['key'] }}" aria-pressed="false"><i aria-hidden="true"></i>{{ __($w($band['key']).'.crumb') }}<span>{{ $band['count'] }}</span></button>
                @endforeach
                @if ($others > 0)
                    <button type="button" class="s-sh-filter s-tone-other" data-filter="other" aria-pressed="false"><i aria-hidden="true"></i>{{ __('pages.songs.other') }}<span>{{ $others }}</span></button>
                @endif
                <p class="s-sh-shown" role="status" data-shown data-one="{{ trans_choice('pages.songs.shown', 1, ['count' => 1]) }}" data-many="{{ trans_choice('pages.songs.shown', 2, ['count' => ':count']) }}">{{ trans_choice('pages.songs.shown', $count, ['count' => $count]) }}</p>
            </div>
            <div class="s-sh-letters">
                @foreach ($letters as $letter => $entries)
                    <div class="s-sh-letter" data-letter>
                        <h3 class="s-xserif" aria-hidden="true">{{ $letter }}</h3>
                        <ul>
                            @foreach ($entries as $entry)
                                <li data-in="{{ implode(' ', $entry['in']) }}" data-title="{{ mb_strtolower($entry['title']) }}" @if ($q !== '' && ! str_contains(mb_strtolower($entry['title']), mb_strtolower($q))) hidden @endif><a href="{{ $entry['url'] }}"><i class="s-tone-{{ $entry['dot'] }}" aria-hidden="true"></i><span translate="no">{{ $entry['title'] }}</span></a></li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
            <p class="s-sh-none" data-none hidden>{{ __('pages.songs.none') }}</p>
        </section>

        <section class="s-ink s-sh-end" aria-labelledby="end-h">
            <div class="s-wrap s-sh-end-in">
                <div>
                    <h2 id="end-h">{{ __('pages.songs.end_title') }} <em class="s-xserif">{{ __('pages.songs.end_em') }}</em></h2>
                    <p>{{ __('pages.songs.end_body') }}</p>
                </div>
                <a href="{{ \App\Support\Http\Hosts::app('/?ref=songs') }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    <script type="module" src="/js/public/mixer.js"></script>
    <script type="module" src="/js/public/carols.js"></script>
    <script type="module" src="/js/public/songbook.js"></script>
@endsection
