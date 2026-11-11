{{-- Every official song at /songs (SongPagesController::index), as a songbook in the redesign's
     sections (docs/marketing-redesign-plan.md): the head with how many there are, the song of the
     month on its own, then the free songs and the Plus ones, each card with its parts' ranges and,
     where its line is passed (`lines`), a Play that sings it in the app's guide voices (mixer.js;
     Hillel: every card gets a play preview). Kept out of the site's navigation (Hillel); here for
     search and shared links. --}}
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

@php($all = collect($songs))

@section('content')
    <x-site.header />
    <main>
        <section class="s-wrap s-featurehead" aria-labelledby="page-h">
            <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span><x-site.skin :game="__('pages.landing.game.songs_kick')" :serious="__('pages.songs.eyebrow')" /></p>
            <h1 id="page-h">{{ __('pages.songs.headline') }}</h1>
            <p class="s-lede">{{ __('pages.songs.lead') }}</p>
            <p class="s-countline">{{ __('pages.songs.count', ['count' => $all->count()]) }}</p>
            {{-- The groups below, one tap away (about 50 songs make a long page). --}}
            <nav class="s-songs-jump" aria-label="{{ __('pages.songs.jump') }}">
                @foreach ($groups as $group)
                    <a href="#songs-{{ $group['key'] }}-h" class="s-lk">{{ __('pages.songs.groups.'.$group['key']) }}</a>
                @endforeach
            </nav>
        </section>

        {{-- The songs in groups (SongCollections::grouped), each with the page that collects it: the carols page, the hymns. --}}
        @foreach ($groups as $group)
            <section class="s-wrap s-sec" aria-labelledby="songs-{{ $group['key'] }}-h">
                <div class="s-headrow">
                    <h2 id="songs-{{ $group['key'] }}-h" class="s-kick">{{ __('pages.songs.groups.'.$group['key']) }} <span class="s-songs-n">{{ count($group['songs']) }}</span></h2>
                    @if ($group['link'] !== null)
                        <a href="{{ $group['link'] }}" class="s-arrow">{{ $group['key'] === 'christmas' ? __('site.carols.songs_link') : __('site.collections.'.$group['key'].'.songs_link') }} <x-site.icon name="arrow" size="16" /></a>
                    @endif
                </div>
                <ul class="s-songs s-songs-2">
                    @foreach ($group['songs'] as $song)
                        <li class="s-neon{{ $loop->index % 3 + 1 }}" @if (($lines[$song['slug']] ?? null) !== null) data-player @endif>
                            <a href="/songs/{{ $song['slug'] }}" class="s-song">
                                <span class="s-row">
                                    <x-site.song-glyph :ranges="$song['ranges']" />
                                </span>
                                <span><span class="s-t" translate="no">{{ $song['title'] }}</span><span class="s-k">{{ __('pages.songs.parts', ['count' => count($song['parts'])]) }}@if ($song['genre'] !== []) · {{ ucfirst($song['genre'][0]) }}@endif</span></span>
                            </a>
                            @if (($lines[$song['slug']] ?? null) !== null)
                                <script type="application/json" data-song>@json($lines[$song['slug']])</script>
                                <button type="button" class="s-play s-songplay" data-play aria-label="{{ __('pages.songs.play', ['title' => $song['title']]) }}" data-label-play="{{ __('pages.songs.play', ['title' => $song['title']]) }}" data-label-stop="{{ __('pages.songs.stop', ['title' => $song['title']]) }}">
                                    <span class="s-i-play"><x-site.icon name="play" size="18" /></span><span class="s-i-stop"><x-site.icon name="stop" size="18" /></span>
                                </button>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach

        <section class="s-ink" aria-labelledby="end-h">
            <div class="s-wrap s-final">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
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
@endsection
