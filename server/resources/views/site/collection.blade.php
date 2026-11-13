{{-- A song collection, /songs/<collection> (SongCollectionPageController gives $slug, $songs and $canonical;
     App\Support\Pages\SongCollections): the trail, the heading with its last words in the italic serif, the lead and a
     short intro, then the songbook (site._songbook: each song a card with its first line to play and its parts to their
     pages, and the "I sing" filter), the About panel and the free tools. The carols page's frame, its hero in the collection's own palette (s-tone-<slug>). Words: site.collections.<slug>. --}}
@php($words = 'site.collections.'.$slug)
@php($title = ($season ?? null) !== null ? __($words.'.season_title.'.$season) : __($words.'.title'))
@php($description = ($season ?? null) !== null ? __($words.'.season_description.'.$season) : __($words.'.description'))
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($title)])

@section('arcade')
@endsection

@section('head')
    <meta name="description" content="{{ $description }}">
    <meta property="og:site_name" content="{{ __('meta.brand') }}">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:type" content="website">
    {{-- Its own link card (tool/link_cards.py, the page's theme), the landing's until it's drawn. --}}
    <meta property="og:image" content="{{ \App\Support\Site\SitePages::image($canonical) }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $title }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ $canonical }}">
    <x-site.json-ld :data="\App\Support\Site\StructuredData::collection(__($words.'.list'), __($words.'.crumb'), $canonical, $songs)" />
@endsection

@section('content')
    <x-site.header />
    <main>
        {{-- Its hero in its own palette and motif (s-tone-<slug>), the same as its band on /songs and its link card. --}}
        <section class="s-xhero s-tone s-tone-{{ $slug }}" aria-labelledby="page-h">
            {{-- The Jewish songs' lamp sits in its next festival's card instead (Hillel, 2026-11-12: "show in top right corner of
                 the next festival box"), so it's never drawn twice. --}}
            @if ($slug !== \App\Support\Pages\JewishSongs::SLUG)
                @include('site._tone-motif', ['key' => $slug])
            @endif
            <div @class(['s-wrap s-xhero-in s-xcoll-in', 's-xcoll-has-try' => ($try ?? null) !== null])>
                <div class="s-xhero-text">
                    <x-site.crumbs :trail="[[__('site.song_part.songs'), '/songs'], [__($words.'.crumb')]]" />
                    <h1 id="page-h">{{ __($words.'.headline') }} <em class="s-xserif">{{ __($words.'.headline_em') }}</em></h1>
                    {{-- One short lead (Hillel's Jewish Songs redesign); the intro is its own section after the songs. --}}
                    <p class="s-lead">{{ __($words.'.lead') }}</p>
                    <div class="s-actions s-actions-row">
                        <a href="#songbook" class="btn-primary btn-lg">{{ __($words.'.pick') }} <span aria-hidden="true">↓</span></a>
                        @include('site._share', ['surface' => 'page-collection', 'url' => $canonical, 'title' => $title, 'name' => __($words.'.crumb'), 'lg' => true, 'label' => trans()->has($words.'.share') ? __($words.'.share') : __('site.share.choir')])
                    </div>
                </div>
                {{-- Beside the heading from 900 px (the width design): the collection's own song, the one on its link card,
                     its opening in four parts, each one switched on or off as it plays (x-site.mixer), that card's part lit. --}}
                @if (($try ?? null) !== null)
                    <div class="s-sh-try s-xcoll-try">
                        <x-site.mixer :song="$try" id="try-mixer"><span class="s-sh-try-k">{{ __('pages.songs.try') }}</span><x-slot:foot>{{ __('pages.songs.try_note') }}</x-slot:foot></x-site.mixer>
                    </div>
                @endif
                {{-- The Jewish songs page's countdown to its next festival (App\Support\Pages\JewishSongs::festival), the shared
                     festival card: the festival large, the days beside it with its first sundown, then its songs to learn in time. --}}
                @if (($festivalCard ?? null) !== null)
                    @include('site._festival-card', ['card' => $festivalCard, 'id' => 'jcount'])
                @endif
            </div>
        </section>

        @include('site._songbook', ['songs' => $songs, 'heading' => __($words.'.songbook'), 'sub' => __($words.'.songbook_sub'), 'subPart' => __($words.'.songbook_part'), 'places' => null, 'chips' => $chips ?? null, 'note' => trans()->has($words.'.songbook_note') ? __($words.'.songbook_note') : null])

        {{-- Where the songs and their parts come from (Hillel's redesign: the hero's long intro moved here), its heading at
             left from 900 px. --}}
        <section class="s-wrap s-sec2 s-xfrom" aria-labelledby="from-h">
            <h2 id="from-h">{{ __($words.'.from') }} <em class="s-xserif">{{ __($words.'.from_em') }}</em></h2>
            <div>
                @foreach ((array) \Illuminate\Support\Facades\Lang::get($words.'.intro') as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                {{-- More to sing: the collections nearest this one that are pages now (SongCollections::SEE_ALSO). --}}
                @php($seeAlso = app(\App\Support\Pages\SongCollections::class)->seeAlso($slug))
                @if ($seeAlso !== [])
                    <p class="s-xsee">{{ __('site.see_also') }} @foreach ($seeAlso as $other)<a href="{{ $other['url'] }}" class="s-lk">{{ $other['name'] }}</a>@if (! $loop->last), @endif @endforeach</p>
                @endif
                {{-- A For choirs lead-in (Hillel, 2026-11-12: For choirs left the header for the pages a director reads). --}}
                <p><a href="/for/choir-directors?ref=for-choirs-collection" class="s-lk">{{ __('pages.songs.for_choirs') }}&#8288;<x-site.icon name="arrow" size="16" /></a></p>
            </div>
        </section>

        @include('site.tools._about', ['tie' => __('site.carols.tie'), 'tryLink' => __($words.'.try_link'), 'try' => \App\Support\Http\Hosts::app('/?ref=songs'), 'links' => ['app' => \App\Support\Http\Hosts::app('/')]])
        @include('site.tools._directory', ['directory' => \App\Support\Site\SitePages::directory()])
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    <script type="module" src="/js/public/mixer.js"></script>
    <script type="module" src="/js/public/carols.js"></script>
    @if ($slug === \App\Support\Pages\JewishSongs::SLUG)
        <script type="module" src="/js/public/hanukkiah.js"></script>
    @endif
@endsection
