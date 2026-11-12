{{-- A song collection, /songs/<collection> (SongCollectionPageController gives $slug, $songs and $canonical;
     App\Support\Pages\SongCollections): the trail, the heading with its last words in the italic serif, the lead and a
     short intro, then the songbook (site._songbook: each song a card with its first line to play and its parts to their
     pages, and the "I sing" filter), the About panel and the free tools. The carols page's frame, its hero in the collection's own palette (s-tone-<slug>). Words: site.collections.<slug>. --}}
@php($words = 'site.collections.'.$slug)
@php($title = __($words.'.title'))
@php($description = __($words.'.description'))
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
            @include('site._tone-motif', ['key' => $slug])
            <div class="s-wrap s-xhero-in s-xcoll-in">
                <div class="s-xhero-text">
                    <x-site.crumbs :trail="[[__('site.song_part.songs'), '/songs'], [__($words.'.crumb')]]" />
                    <h1 id="page-h">{{ __($words.'.headline') }} <em class="s-xserif">{{ __($words.'.headline_em') }}</em></h1>
                    <p class="s-lead">{{ __($words.'.lead') }}</p>
                    @foreach ((array) \Illuminate\Support\Facades\Lang::get($words.'.intro') as $paragraph)
                        <p class="s-xcoll-intro">{{ $paragraph }}</p>
                    @endforeach
                    <div class="s-actions s-actions-row">
                        <a href="#songbook" class="btn-primary btn-lg">{{ __($words.'.pick') }} <span aria-hidden="true">↓</span></a>
                        @include('site._share', ['surface' => 'page-collection', 'url' => $canonical, 'title' => $title, 'name' => __($words.'.crumb'), 'lg' => true])
                    </div>
                </div>
                {{-- The Jewish songs page's countdown to its next festival (App\Support\Pages\JewishSongs::festival). --}}
                @if (($festival ?? null) !== null)
                    <aside class="s-jcount" aria-labelledby="jcount-k">
                        <p id="jcount-k" class="s-jcount-k">{{ $festival['on'] ? __($words.'.count_on_label') : __($words.'.count_label') }}</p>
                        <p class="s-jcount-n s-xserif">{{ __($words.'.festivals.'.$festival['key']) }}</p>
                        <p class="s-jcount-line">{{ $festival['on'] ? __($words.'.count_until', ['date' => \App\Support\Languages\Dates::weekdayDayMonth($festival['until'])]) : ($festival['days'] === 0 ? __($words.'.count_tonight') : trans_choice($words.'.count_in', $festival['days'], ['count' => $festival['days']])) }}</p>
                        @unless ($festival['on'])
                            <p class="s-jcount-date">{{ __($words.'.count_from', ['date' => \App\Support\Languages\Dates::weekdayDayMonth($festival['from'])]) }}</p>
                        @endunless
                    </aside>
                @endif
            </div>
        </section>

        @include('site._songbook', ['songs' => $songs, 'heading' => __($words.'.songbook'), 'sub' => __($words.'.songbook_sub'), 'subPart' => __($words.'.songbook_part'), 'places' => null, 'chips' => $chips ?? null])

        @include('site.tools._about', ['tie' => __('site.carols.tie'), 'tryLink' => __($words.'.try_link'), 'try' => \App\Support\Http\Hosts::app('/?ref=songs'), 'links' => ['app' => \App\Support\Http\Hosts::app('/')]])
        @include('site.tools._directory', ['directory' => \App\Support\Site\SitePages::directory()])
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    <script type="module" src="/js/public/mixer.js"></script>
    <script type="module" src="/js/public/carols.js"></script>
@endsection
