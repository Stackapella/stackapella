{{-- /songs as a sampler (Hillel, 2026-11-11: "change the /songs page so it samples and then leads to the page for each
     section, the section style should match the page it leads to"; App\Support\Pages\SongsHub, and the design review):
     the songbook's promise with every collection as a swatch to jump to; the collection in season as a full-width band
     in its page's look; each other collection as a panel in its own palette (its page's hero and link card share it),
     with a few songs to play and the way to all of them; then every song A to Z, so none is more than a click away.
     Kept out of the site's navigation (Hillel); here for search and shared links. --}}
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

@section('content')
    <x-site.header />
    <main>
        <section class="s-wrap s-sh-hero" aria-labelledby="page-h">
            <div>
                <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span><x-site.skin :game="__('pages.landing.game.songs_kick')" :serious="__('pages.songs.eyebrow')" /></p>
                <h1 id="page-h">{{ __('pages.songs.headline') }}</h1>
                <p class="s-lede">{{ __('pages.songs.lead') }}</p>
                <p class="s-countline">{{ __('pages.songs.count', ['count' => $count]) }}</p>
                @if ($rounds !== null)
                    <p class="s-sh-start"><a href="{{ $rounds }}" class="s-lk">{{ __('pages.songs.start_round') }}&#8288;<x-site.icon name="arrow" size="16" /></a></p>
                @endif
            </div>
            {{-- Every band as a swatch in its palette, with its count: what's below, one tap away. --}}
            <nav class="s-sh-swatches" aria-label="{{ __('pages.songs.jump') }}">
                <ul>
                    @foreach ($bands as $band)
                        <li><a href="#band-{{ $band['key'] }}" class="s-sh-swatch s-tone-{{ $band['key'] }}"><i aria-hidden="true"></i><b>{{ __($w($band['key']).'.crumb') }}</b><span>{{ $band['count'] }}</span></a></li>
                    @endforeach
                    <li><a href="#every-song" class="s-sh-swatch s-sh-swatch-all"><i aria-hidden="true"></i><b>{{ __('pages.songs.index') }}</b><span>{{ $count }}</span></a></li>
                </ul>
            </nav>
        </section>

        @foreach ($bands as $band)
            @php($words = $w($band['key']))
            <section id="band-{{ $band['key'] }}" @class(['s-sh-band', 's-tone', 's-tone-'.$band['key'], 's-sh-season' => $band['season'], 's-sh-flip' => ! $band['season'] && $loop->index % 2 === 1]) aria-labelledby="band-{{ $band['key'] }}-h">
                @if ($band['season'] && $band['key'] === 'christmas')
                    @include('site._xmas-sky')
                @else
                    @include('site._tone-motif', ['key' => $band['key']])
                @endif
                <div class="s-sh-band-in">
                    <div class="s-sh-words">
                        {{-- The season's band says so; a panel's headline names it (a kicker over it only repeated it). --}}
                        @if ($band['season'])
                            <p class="s-sh-kick">
                                <span>{{ __($words.'.crumb') }}</span>
                                <span class="s-sh-chip"><span>{{ __('pages.songs.season') }}</span>@if ($until !== null)<span>{{ __('pages.songs.season_until', ['date' => $until]) }}</span>@endif</span>
                            </p>
                        @endif
                        <h2 id="band-{{ $band['key'] }}-h">{{ __($words.'.headline') }} <em class="s-xserif">{{ __($words.'.headline_em') }}</em></h2>
                        <p class="s-sh-line">{{ __($words.'.lead') }}</p>
                    </div>
                    <ul class="s-sh-songs">
                        @foreach ($band['samples'] as $song)
                            <li class="s-sh-song" @if ($song['line'] !== null) data-player @endif>
                                <a href="{{ $song['url'] }}"><b translate="no">{{ $song['title'] }}</b>@if ($song['meta'] !== [])<span>{{ $song['meta'][0] }}</span>@endif</a>
                                @if ($song['line'] !== null)
                                    <script type="application/json" data-song>@json($song['line'])</script>
                                    <button type="button" class="s-play" data-play aria-label="{{ __('pages.songs.play', ['title' => $song['title']]) }}" data-label-play="{{ __('pages.songs.play', ['title' => $song['title']]) }}" data-label-stop="{{ __('pages.songs.stop', ['title' => $song['title']]) }}">
                                        <span class="s-i-play"><x-site.icon name="play" size="16" /></span><span class="s-i-stop"><x-site.icon name="stop" size="16" /></span>
                                    </button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    {{-- Its one way on, after the songs on a phone and under the words beside them from 900 px. --}}
                    <a href="{{ $band['path'] }}" class="btn-primary s-sh-all">{{ __($words.'.all', ['count' => $band['count']]) }} <x-site.icon name="arrow" size="18" /></a>
                </div>
            </section>
        @endforeach

        {{-- Every song, each to its own page: nothing more than one click from here (for people and for search). --}}
        <section id="every-song" class="s-wrap s-sec s-sh-index" aria-labelledby="every-song-h">
            <p class="s-kick">{{ __('pages.songs.index_kick') }}</p>
            <h2 id="every-song-h">{{ __('pages.songs.index') }}</h2>
            <p class="s-sh-index-lead">{{ __('pages.songs.index_lead') }}</p>
            <ul class="s-sh-az">
                @foreach ($index as $entry)
                    <li><a href="{{ $entry['url'] }}" translate="no">{{ $entry['title'] }}</a></li>
                @endforeach
            </ul>
        </section>

        <section class="s-ink" aria-labelledby="end-h">
            <div class="s-wrap s-final">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w2])<b class="s-v{{ $n }}" style="width: {{ $w2 }}%"></b>@endforeach</span>
                <h2 id="end-h">{{ __('pages.songs.end_title') }}</h2>
                <p>{{ __('pages.songs.end_body') }}</p>
                <p class="s-final-btns"><a href="{{ \App\Support\Http\Hosts::app('/?ref=songs') }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a></p>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    <script type="module" src="/js/public/mixer.js"></script>
    <script type="module" src="/js/public/carols.js"></script>
@endsection
