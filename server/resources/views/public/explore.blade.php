{{-- Explore (/explore, behind the gallery): the newest harmonies people chose to share, or the
     featured ones. --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

@section('head')
    <meta name="description" content="{{ $meta['description'] }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    <meta property="og:image" content="{{ \App\Support\Site\SitePages::image($meta['url']) }}">
    <link rel="canonical" href="{{ $meta['url'] }}">
@endsection

@section('content')
    <x-site.header />
    <main>
        @include('site._hero', ['headline' => $meta['title'], 'lead' => $meta['description']])
        <section class="s-wrap s-sec" style="padding-top: 24px" aria-label="{{ $meta['title'] }}">
            <nav class="s-seg2 s-tabs" aria-label="{{ __('pages.gallery_page.tabs') }}">
                <a href="/explore" @if ($tab === 'new') aria-current="page" @endif>{{ __('pages.gallery_page.new') }}</a>
                <a href="/explore?tab=featured" @if ($tab === 'featured') aria-current="page" @endif>{{ __('pages.gallery_page.featured_tab') }}</a>
            </nav>
            @if ($harmonies === [])
                <p class="s-cur">{{ __('pages.gallery_page.none') }}</p>
            @else
                <ul class="s-cards">
                    @foreach ($harmonies as $harmony)
                        <li>@include('public._harmony-card')</li>
                    @endforeach
                </ul>
            @endif
        </section>
    </main>
    <x-site.footer />
@endsection
