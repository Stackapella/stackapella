{{-- A song collection, /songs/<collection> (SongCollectionPageController gives $slug, $songs and $canonical;
     App\Support\Pages\SongCollections): the trail, the heading with its last words in the italic serif, the lead and a
     short intro, then the songbook (site._songbook: each song a card with its first line to play and its parts to their
     pages, and the "I sing" filter), the About panel and the free tools. The carols page's calm frame, without the
     season. Words: site.collections.<slug>. --}}
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
    <meta property="og:image" content="{{ \App\Support\Http\Hosts::public('/icons/og-landing.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ $canonical }}">
    <x-site.json-ld :data="\App\Support\Site\StructuredData::collection(__($words.'.list'), __($words.'.crumb'), $canonical, $songs)" />
@endsection

@section('content')
    <x-site.header />
    <main>
        <section class="s-xhero" aria-labelledby="page-h">
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
                    </div>
                </div>
            </div>
        </section>

        @include('site._songbook', ['songs' => $songs, 'heading' => __($words.'.songbook'), 'sub' => __($words.'.songbook_sub'), 'subPart' => __($words.'.songbook_part'), 'places' => null])

        @include('site.tools._about', ['tie' => __('site.carols.tie'), 'tryLink' => __($words.'.try_link'), 'try' => \App\Support\Http\Hosts::app('/?ref=songs'), 'links' => ['app' => \App\Support\Http\Hosts::app('/')]])
        @include('site.tools._directory', ['directory' => \App\Support\Site\SitePages::directory()])
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    <script type="module" src="/js/public/mixer.js"></script>
    <script type="module" src="/js/public/carols.js"></script>
@endsection
