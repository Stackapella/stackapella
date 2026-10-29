{{-- A song's page at /songs/<slug> (SongPagesController::show, docs/go-to-market-plan.md "Search";
     the design round's direction A): what it is and Learn this song into the app (startUrl), with a
     free song's opening line to play beside it (a Plus song: its preview clip, never a part or the
     notes); the parts as a range chart; the first verse in a band with its credits; more songs; and
     the page's own last word. --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

@section('head')
    {{-- How it looks when shared: the song's own card (SongCards) once the server has made it,
         the landing's until then; both 1200 by 630. --}}
    @php($image = $meta['image'] ?? \App\Support\Http\Hosts::public('/icons/og-landing.png'))
    @php($imageAlt = $meta['imageAlt'] ?? __('site.meta.app.default.image_alt'))
    <meta name="description" content="{{ $meta['description'] }}">
    <meta property="og:site_name" content="{{ __('meta.brand') }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    <meta property="og:type" content="music.song">
    <meta property="og:image" content="{{ $image }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="{{ $imageAlt }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $meta['title'] }}">
    <meta name="twitter:description" content="{{ $meta['description'] }}">
    <meta name="twitter:image" content="{{ $image }}">
    <meta name="twitter:image:alt" content="{{ $imageAlt }}">
    <link rel="canonical" href="{{ $meta['url'] }}">
    <x-site.json-ld :data="\App\Support\Site\StructuredData::song($song)" />
@endsection

@section('content')
    <x-site.header />
    <main>
        {{-- The top: where you are, the song, what it is, Learn this song; beside it on a wide
             screen, a free song's opening line to play (a Plus song's clip instead). --}}
        <section class="s-wrap s-head" aria-labelledby="page-h">
            <div @class(['s-head-grid', 's-head-solo' => $line === null && $song['previewUrl'] === null])>
                <div>
                    <nav class="s-crumb" aria-label="{{ __('pages.song.crumb') }}"><a href="/songs">{{ __('pages.song.eyebrow') }}</a><span aria-hidden="true">/</span><span aria-current="page" translate="no">{{ $song['title'] }}</span></nav>
                    <h1 id="page-h" translate="no">{{ $song['title'] }}</h1>
                    <p class="s-lead">{{ $song['description'] }}</p>
                    <p class="s-meta">
                        @if ($song['freeThisMonth'])
                            <span class="s-badge-free">{{ __('pages.song.free_month') }}</span>
                        @elseif ($song['plus'])
                            <span class="s-badge-plus">{{ __('pages.song.plus') }}</span>
                        @else
                            <span class="s-badge-free">{{ __('pages.song.free') }}</span>
                        @endif
                        @if (\Illuminate\Support\Facades\Lang::has('pages.song.difficulty.'.$song['difficulty']))
                            <span>{{ __('pages.song.difficulty.'.$song['difficulty']) }}</span>
                        @endif
                        @foreach ($song['genre'] as $genre)
                            <span>{{ ucfirst($genre) }}</span>
                        @endforeach
                        <span>{{ __('pages.songs.parts', ['count' => count($song['parts'])]) }}</span>
                    </p>
                    <div class="s-actions">
                        <a href="{{ $song['startUrl'] }}" class="btn-primary btn-lg">{{ __('pages.song.start') }} <x-site.icon name="arrow" size="18" /></a>
                        <p>{{ $song['plus'] ? __('pages.song.plus_note') : __('pages.song.start_note') }}</p>
                    </div>
                </div>
                @if ($line !== null)
                    <div class="s-narrow">
                        <x-site.mixer :song="$line" id="song-mixer" />
                    </div>
                @elseif ($song['previewUrl'] !== null)
                    <div class="s-step s-v1 s-listen">
                        <h2>{{ __('pages.song.listen') }}</h2>
                        <p>{{ __('pages.song.listen_note') }}</p>
                        <audio controls preload="none" src="{{ $song['previewUrl'] }}" type="{{ $song['previewType'] }}"></audio>
                    </div>
                @endif
            </div>
        </section>

        {{-- The parts as a range chart: each part's lowest and highest note on one scale. --}}
        @if ($song['ranges']['parts'] !== [])
            <section class="s-wrap s-sec2" aria-labelledby="parts-h">
                <div class="s-two2">
                    <div>
                        <h2 id="parts-h" class="s-h2">{{ __('pages.song.parts') }}</h2>
                        <p class="s-sub">{{ __('pages.song.parts_lead') }}</p>
                    </div>
                    <div>
                        <div class="s-range" role="list">
                            <div class="s-range-scale" aria-hidden="true"><span></span><div>@foreach ($song['ranges']['marks'] as $mark)<span style="inset-inline-start: {{ $mark['at'] }}%">{{ $mark['name'] }}</span>@endforeach</div></div>
                            @foreach ($song['ranges']['parts'] as $part)
                                <div class="s-range-row s-v{{ $part['color'] }}" role="listitem">
                                    <span class="sr-only">{{ __('pages.song.range_row', ['part' => $part['name'], 'low' => $part['low'], 'high' => $part['high']]) }}</span>
                                    <span class="s-range-nm" aria-hidden="true"><span class="s-disc">{{ mb_substr($part['name'], 0, 1) }}</span>{{ $part['name'] }}</span>
                                    <span class="s-range-track" aria-hidden="true"><span class="s-range-bar" style="inset-inline-start: min({{ $part['from'] }}%, calc(100% - 76px)); width: max({{ max(0, $part['to'] - $part['from']) }}%, 76px)"><span>{{ $part['low'] }}</span><span>{{ $part['high'] }}</span></span></span>
                                </div>
                            @endforeach
                        </div>
                        <p class="s-cur">{{ __('pages.song.parts_hint') }}</p>
                    </div>
                </div>
            </section>
        @endif

        {{-- The first verse, large, in a band, with its credits. --}}
        @if ($song['words'] !== [])
            <section class="s-band" aria-labelledby="verse-h">
                <div class="s-wrap s-two2">
                    <div>
                        <h2 id="verse-h" class="s-h2">{{ __('pages.song.verse') }}</h2>
                        @if ($song['credits'] !== [])
                            <p class="s-song-credits">@foreach ($song['credits'] as $credit)<span>{{ $credit }}</span>@endforeach</p>
                        @endif
                    </div>
                    <p class="s-verse-big">@foreach ($song['words'] as $words)<span>{{ $words }}</span>@endforeach</p>
                </div>
            </section>
        @endif

        @if ($more !== [])
            <section class="s-wrap s-sec2" aria-labelledby="more-h">
                <h2 id="more-h" class="s-h2">{{ __('pages.song.more') }}</h2>
                <ul class="s-songs s-more-songs">
                    @foreach ($more as $other)
                        <li>
                            <a href="/songs/{{ $other['slug'] }}" class="s-song">
                                <span class="s-row">
                                    <x-site.song-glyph :ranges="$other['ranges']" />
                                    <span class="s-badge {{ $other['plus'] ? 's-badge-plus' : 's-badge-free' }}">{{ $other['plus'] ? __('pages.song.plus') : ($other['freeThisMonth'] ? __('pages.song.free_month') : __('pages.song.free')) }}</span>
                                </span>
                                <span><span class="s-t" translate="no">{{ $other['title'] }}</span><span class="s-k">{{ __('pages.songs.parts', ['count' => count($other['parts'])]) }}@if ($other['genre'] !== []) · {{ ucfirst($other['genre'][0]) }}@endif</span></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="s-more"><a href="/songs" class="btn-secondary">{{ __('pages.songs.headline') }}</a></p>
            </section>
        @endif

        {{-- The page's own last word. --}}
        <section class="s-wrap" aria-labelledby="end-h">
            <div class="s-end">
                <span class="s-stackbig" aria-hidden="true"><b class="s-v1" style="width: 100%"></b><b class="s-v2" style="width: 74%"></b><b class="s-v3" style="width: 88%"></b><b class="s-v4" style="width: 62%"></b></span>
                <div>
                    <h2 id="end-h">{{ __('pages.song.end_title', ['title' => $song['title']]) }}</h2>
                    <p>{{ $song['plus'] ? __('pages.song.end_plus') : __('pages.song.end_free') }}</p>
                </div>
                <a href="{{ $song['startUrl'] }}" class="btn-primary btn-lg">{{ __('pages.song.start') }} <x-site.icon name="arrow" size="18" /></a>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    @if ($line !== null)
        <script type="module" src="/js/public/mixer.js"></script>
    @endif
@endsection
