{{-- A song's part page, /songs/<song>/<part> (docs/seo-growth-plan.md, item 1; SongPartPagesController gives
     $songPart, App\Support\Pages\SongParts): the part's name as the heading, its range and key, the frontend's player
     for a free song (the part alone, with the others, or sung with each note lit; js/public/song-part-page.js mounts
     it) or the app for a Plus one, then how to learn it, the song, the other parts, the About panel and the free tools.
     Words: site.song_part. --}}
@php($s = $songPart['song'])
@php($p = $songPart['part'])
@php($melody = $p['key'] === ($s['melodyPart'] ?? null))
@php($fill = ['song' => $s['title'], 'part' => mb_strtolower($p['name']), 'key' => $s['key'], 'low' => $p['lowName'], 'high' => $p['highName']])
@php($heading = __($melody ? 'site.song_part.headline_melody' : 'site.song_part.headline', $fill))
@php($metaTitle = __($melody ? 'site.song_part.title_melody' : 'site.song_part.title', $fill))
{{-- SATB named where it's true (docs/search-content-plan.md, item 4): choirs search it. --}}
@php($description = __($songPart['satb'] ? 'site.song_part.description_satb' : 'site.song_part.description', $fill))
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($metaTitle)])

@section('arcade')
@endsection

@section('head')
    <meta name="description" content="{{ $description }}">
    <meta property="og:site_name" content="{{ __('meta.brand') }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ $songPart['canonical'] }}">
    <meta property="og:type" content="music.song">
    <meta property="og:image" content="{{ \App\Support\Http\Hosts::public('/icons/og-landing.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ $songPart['canonical'] }}">
    <x-site.json-ld :data="\App\Support\Site\StructuredData::songPart($songPart, $heading)" />
@endsection

@section('content')
    <x-site.header />
    <main>
        <section class="s-wrap s-head s-spart" aria-labelledby="page-h">
            <nav class="s-crumb" aria-label="{{ __('site.song_part.crumb') }}"><a href="/songs">{{ __('site.song_part.songs') }}</a><span aria-hidden="true">/</span><a href="{{ $s['pageUrl'] }}" translate="no">{{ $s['title'] }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $p['name'] }}</span></nav>
            <h1 id="page-h">{{ $heading }}</h1>
            <p class="s-lead">{{ __($melody ? 'site.song_part.lead_melody' : 'site.song_part.lead', $fill) }}</p>
            @if (\Illuminate\Support\Facades\Lang::has('song_parts.'.$s['slug'].'.'.$p['key']))
                <p class="s-spart-note">{{ __('song_parts.'.$s['slug'].'.'.$p['key']) }}</p>
            @endif
            <dl class="s-spart-facts">
                <div><dt>{{ __('site.song_part.range') }}</dt><dd translate="no">{{ __('site.song_part.range_value', $fill) }}</dd></div>
                <div><dt>{{ __('site.song_part.key') }}</dt><dd translate="no">{{ $s['key'] }}</dd></div>
                <div><dt>{{ __('site.song_part.voice') }}</dt><dd>
                    @if ($songPart['guide']['realVoice'])
                        @if ($songPart['guide']['credit'] !== null && $songPart['guide']['creditHandle'] !== null)
                            <a href="{{ '/@'.$songPart['guide']['creditHandle'] }}" class="s-lk">{{ __('site.song_part.sung_by', ['name' => $songPart['guide']['credit']]) }}</a>
                        @elseif ($songPart['guide']['credit'] !== null)
                            {{ __('site.song_part.sung_by', ['name' => $songPart['guide']['credit']]) }}
                        @else
                            {{ __('site.song_part.sung_by_anon') }}
                        @endif
                    @else
                        {{ __('site.song_part.synth') }}
                    @endif
                </dd></div>
            </dl>

            @if ($s['free'])
                <div class="s-spart-player" data-part-player></div>
                <script type="application/json" data-part-player-data>@json(\App\Support\Pages\SongPartPage::player($songPart))</script>
            @else
                <p class="s-spart-plus">{{ __('site.song_part.plus_note') }}</p>
            @endif
            {{-- One primary: the player's Play when it's here, else Learn this part. --}}
            <p class="s-actions"><a href="{{ $songPart['startUrl'] }}" @class(['btn-lg', 'btn-secondary' => $s['free'], 'btn-primary' => ! $s['free']])>{{ __('site.song_part.start') }} <x-site.icon name="arrow" size="18" /></a></p>
        </section>

        <section class="s-wrap s-sec2 s-tool-guide" aria-labelledby="how-h">
            <h2 id="how-h">{{ __('site.song_part.how', $fill) }}</h2>
            <ol class="s-tool-how">
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.song_part.steps') as $step)
                    <li>{{ $step }}</li>
                @endforeach
            </ol>
        </section>

        @if (($s['about'] ?? null) !== null || $s['description'] !== '')
            <section class="s-wrap s-sec2 s-tool-guide" aria-labelledby="about-song-h">
                <h2 id="about-song-h">{{ __('site.song_part.about', $fill) }}</h2>
                @foreach (preg_split('/\n\s*\n/', (string) ($s['about'] ?? $s['description'])) ?: [] as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                @foreach ($s['credits'] as $credit)
                    <p class="help">{{ $credit }}</p>
                @endforeach
                <p><a href="{{ $s['pageUrl'] }}" class="s-lk">{{ __('site.song_part.song_page', $fill) }}</a></p>
            </section>
        @endif

        <section class="s-wrap s-sec2" aria-labelledby="others-h">
            <h2 id="others-h" class="s-kick">{{ __('site.song_part.others') }}</h2>
            <ul class="s-spart-parts">
                @foreach ($songPart['parts'] as $other)
                    @continue($other['current'])
                    <li><a href="{{ $other['url'] }}"><b>{{ $other['name'] }}</b>@if ($other['melody'])<span class="s-spart-tag">{{ __('site.song_part.melody_tag') }}</span>@endif<span translate="no">{{ __('site.song_part.other_range', ['low' => $other['lowName'], 'high' => $other['highName']]) }}</span></a></li>
                @endforeach
            </ul>
            @if (\App\Support\Pages\Carols::has($s['slug']))
                @include('site._carols-link', ['line' => __('site.carols.links.part')])
            @endif
        </section>

        @include('site.tools._about', ['tie' => __('site.song_part.tie'), 'tryLink' => __('site.song_part.try_link'), 'try' => $songPart['startUrl'], 'links' => ['app' => \App\Support\Http\Hosts::app('/')]])
        @include('site.tools._directory', ['directory' => \App\Support\Site\SitePages::directory()])
    </main>
    <x-site.footer />
    @if ($s['free'])
        <script type="module" src="/js/public/song-part-page.js"></script>
    @endif
@endsection
