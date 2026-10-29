{{-- A public profile at /@handle (GalleryPagesController::profile, behind the gallery): who they
     are, their counts, and the harmonies they chose to share. --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

@section('head')
    <meta name="description" content="{{ $meta['description'] }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    <meta property="og:type" content="profile">
    @if ($profile['avatarUrl'])
        <meta property="og:image" content="{{ $profile['avatarUrl'] }}">
    @endif
    <link rel="canonical" href="{{ $meta['url'] }}">
@endsection

@section('content')
    <x-site.header />
    <main>
        <section class="s-wrap s-hero s-page s-doc-head" aria-labelledby="page-h">
            <p class="s-eyebrow">{{ __('pages.gallery_page.eyebrow') }}</p>
            <div class="s-coach-top s-coach-hero">
                @include('public._avatar', ['coach' => $profile, 'size' => 80])
                <div>
                    <h1 id="page-h" translate="no">{{ $profile['name'] }}</h1>
                    <p class="s-song-kind" translate="no">{{ '@'.$profile['handle'] }}</p>
                </div>
            </div>
            @if ($profile['bio'])
                <p class="s-lead" translate="no">{{ $profile['bio'] }}</p>
            @endif
            <p class="s-song-kind">{{ trans_choice('pages.gallery_page.harmonies', (int) $profile['harmonies'], ['count' => (int) $profile['harmonies']]) }} · {{ trans_choice('pages.gallery_page.followers', (int) $profile['followers'], ['count' => (int) $profile['followers']]) }} · {{ __('pages.gallery_page.following', ['count' => (int) $profile['following']]) }}</p>
            <p class="s-share-btns"><a href="{{ \App\Support\Http\Hosts::app('/?'.http_build_query(['ref' => 'p-'.$profile['handle']])) }}" class="btn-primary btn-lg">{{ __('pages.gallery_page.open_app') }}</a></p>
        </section>
        <section class="s-wrap s-sec" style="padding-top: 28px" aria-label="{{ $profile['name'] }}">
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
