{{-- A song's virtual choir (/choirs/<song>, behind `virtual_choir`): everyone's voices mixed
     together (the mix only, never a single voice), each part and how many sing it, who chose to be
     credited, and Add your voice into the app. --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

@section('head')
    <meta name="description" content="{{ $meta['description'] }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    <meta property="og:type" content="music.song">
    <meta property="og:image" content="{{ $meta['image'] }}">
    @if ($meta['audio'])
        <meta property="og:audio" content="{{ $meta['audio'] }}">
    @endif
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ $meta['url'] }}">
@endsection

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main>
        <section class="s-wrap s-hero s-page s-doc-head" aria-labelledby="page-h">
            <p class="s-eyebrow">{{ __('pages.together.choir_eyebrow') }}</p>
            <h1 id="page-h" translate="no">{{ $meta['title'] }}</h1>
            <p class="s-lead">{{ $meta['description'] }}</p>
        </section>
        <section class="s-wrap s-sec s-doc" style="padding-top: 24px" aria-label="{{ $meta['title'] }}">
            @if ($choir['mixUrl'])
                <div class="s-step s-v1 s-listen">
                    <h2>{{ __('pages.together.listen') }}</h2>
                    @if ($choir['mixedAt'])
                        <p>{{ __('pages.together.mixed', ['when' => \Illuminate\Support\Carbon::parse($choir['mixedAt'])->isoFormat('D MMMM YYYY')]) }}</p>
                    @endif
                    <audio controls preload="none" src="{{ $choir['mixUrl'] }}"></audio>
                </div>
            @endif
            <h2 class="s-h2" style="font-size: 26px">{{ __('pages.together.parts') }}</h2>
            <ul class="s-parts s-idea-parts">
                @foreach ($choir['parts'] as $part)
                    <li class="s-v{{ min($loop->iteration, 4) }}">
                        <span class="s-disc" aria-hidden="true">{{ mb_substr($part['name'], 0, 1) }}</span>
                        <b>{{ $part['name'] }}</b>
                        <span>{{ trans_choice('pages.together.voices', (int) $part['voices'], ['count' => (int) $part['voices']]) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="s-step s-v2 s-try">
                <p>{{ __('pages.together.join_note') }}</p>
                <p class="s-share-btns">
                    <a href="{{ $links['sing'] }}" class="btn-primary btn-lg">{{ __('pages.together.join') }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="{{ $links['song'] }}" class="btn-secondary btn-lg">{{ __('pages.together.song') }}</a>
                </p>
            </div>
            @if ($choir['credits'] !== [])
                <h2 class="s-h2" style="font-size: 26px; margin-top: 40px">{{ __('pages.together.sung_by') }}</h2>
                @include('public._credits', ['credits' => $choir['credits'], 'parts' => $choir['parts']])
                @if ((int) $choir['voices'] > count($choir['credits']))
                    <p class="s-cur">{{ __('pages.together.more') }}</p>
                @endif
            @endif
        </section>
    </main>
    <x-site.footer />
@endsection
