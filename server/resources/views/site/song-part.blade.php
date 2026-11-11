{{-- A song's part page, /songs/<song>/<part> (docs/seo-growth-plan.md, item 1; SongPartPagesController gives
     $songPart, App\Support\Pages\SongParts): the part's name as the heading, its range and key, the frontend's player
     for a free song (the part alone, with the others, or sung with each note lit; js/public/song-part-page.js mounts
     it) or the app for a Plus one, then how to learn it, the song, the About panel and the free tools; every part as a chip to switch to.
     Words: site.song_part. --}}
@php($s = $songPart['song'])
@php($p = $songPart['part'])
@php($melody = $p['key'] === ($s['melodyPart'] ?? null))
@php($fill = ['song' => $s['title'], 'part' => mb_strtolower($p['name']), 'key' => $s['key'], 'low' => $p['lowName'], 'high' => $p['highName']])
@php($heading = __($melody ? 'site.song_part.headline_melody' : 'site.song_part.headline', $fill))
{{-- In the page's title and description a round is named without its "(round)"; the description says round. --}}
@php($metaFill = [...$fill, 'song' => $songPart['round'] ? \App\Support\Pages\SongParts::searchTitle($s['title']) : $s['title']])
@php($metaTitle = __($songPart['round'] ? 'site.song_part.title_round' : ($melody ? 'site.song_part.title_melody' : 'site.song_part.title'), $metaFill))
{{-- SATB named where it's true (docs/search-content-plan.md, item 4): choirs search it. --}}
@php($description = __($songPart['round'] ? 'site.song_part.description_round' : ($songPart['satb'] ? 'site.song_part.description_satb' : 'site.song_part.description'), $metaFill))
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
    <meta property="og:image" content="{{ $image }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ $songPart['canonical'] }}">
    <x-site.json-ld :data="\App\Support\Site\StructuredData::songPart($songPart, $heading)" />
@endsection

@section('content')
    <x-site.header />
    <main>
        {{-- The top, two columns from 900 px (Hillel's redesign, 2026-11-10: "it makes better use of space"): the trail,
             the heading and the lead at left; at right the part's own line, its facts in one ruled row and every part
             as a chip to switch to, this one filled. Then the player as one full-width panel, with the way into the app
             at its foot. A phone stacks it all in the same order. --}}
        <section class="s-wrap s-head s-spart" aria-labelledby="page-h">
            <div class="s-spart-hero">
                <div>
                    <nav class="s-crumb" aria-label="{{ __('site.song_part.crumb') }}"><a href="/songs">{{ __('site.song_part.songs') }}</a><span aria-hidden="true">/</span>@if ($songPart['home'] !== null)<a href="{{ $songPart['home']['path'] }}">{{ $songPart['home']['name'] }}</a><span aria-hidden="true">/</span>@endif<a href="{{ $s['pageUrl'] }}" translate="no">{{ $s['title'] }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $p['name'] }}</span></nav>
                    <h1 id="page-h">{{ $heading }}</h1>
                    <p class="s-lead">{{ __($melody ? 'site.song_part.lead_melody' : 'site.song_part.lead', $fill) }}</p>
                </div>
                <div class="s-spart-side">
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
                    <h2 id="switch-h" class="s-kick s-spart-switch">{{ __('site.song_part.switch') }}</h2>
                    <ul class="s-spart-chips" aria-labelledby="switch-h">
                        @foreach ($songPart['parts'] as $other)
                            <li>
                                @if ($other['current'])
                                    <span class="s-spart-chip s-v{{ $loop->iteration }}" aria-current="page"><b>{{ $other['name'] }}</b><span translate="no">{{ __('site.song_part.other_range', ['low' => $other['lowName'], 'high' => $other['highName']]) }}</span>@if ($other['melody'])<span class="s-spart-tag">{{ __('site.song_part.melody_tag') }}</span>@endif</span>
                                @else
                                    <a href="{{ $other['url'] }}" class="s-spart-chip s-v{{ $loop->iteration }}"><b>{{ $other['name'] }}</b><span translate="no">{{ __('site.song_part.other_range', ['low' => $other['lowName'], 'high' => $other['highName']]) }}</span>@if ($other['melody'])<span class="s-spart-tag">{{ __('site.song_part.melody_tag') }}</span>@endif</a>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>

            <div class="s-spart-panel">
                @if ($s['free'])
                    <div class="s-spart-player" data-part-player></div>
                    <script type="application/json" data-part-player-data>@json(\App\Support\Pages\SongPartPage::player($songPart))</script>
                @endif
                {{-- One primary: the player's Play when it's here, else Learn this part. --}}
                <div class="s-spart-foot">
                    <p>{{ __($s['free'] ? 'site.song_part.foot' : 'site.song_part.plus_note') }}</p>
                    {{-- The primary and Share as one row, so the foot never gains one (game mode stacks the foot's items). --}}
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:12px;min-width:0;max-width:100%">
                        <a href="{{ $songPart['startUrl'] }}" @class(['btn-secondary' => $s['free'], 'btn-primary' => ! $s['free']]) style="flex:1 1 auto">{{ __('site.song_part.start') }} <x-site.icon name="arrow" size="18" /></a>
                        @include('site._share', ['surface' => 'page-part', 'url' => $songPart['canonical'], 'title' => $s['title'], 'text' => __('site.share.text_part', ['part' => mb_strtolower($p['name']), 'song' => $s['title']])])
                    </div>
                </div>
            </div>
        </section>

        {{-- How to learn it: the player's three ways as three numbered steps, in columns from 900 px. --}}
        <section class="s-wrap s-sec2 s-spart-how" aria-labelledby="how-h">
            <h2 id="how-h">{{ __('site.song_part.how', $fill) }}</h2>
            <ol class="s-spart-steps">
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.song_part.steps') as $step)
                    <li><span class="s-spart-step-k">{{ __('site.song_part.step', ['n' => sprintf('%02d', $loop->iteration), 'name' => __('site.song_part.player.'.['hear', 'withOthers', 'sing'][$loop->index])]) }}</span><p>{{ $step }}</p></li>
                @endforeach
            </ol>
        </section>

        {{-- About the song, two columns from 900 px: its name and credits at left, its story and the ways on at right. --}}
        @if (($s['about'] ?? null) !== null || $s['description'] !== '')
            @include('site._song-about', ['heading' => __('site.song_part.about', $fill), 'credits' => $s['credits'], 'text' => $s['about'] ?? $s['description'], 'songPage' => [$s['pageUrl'], __('site.song_part.song_page', $fill)], 'home' => $songPart['home']])
        @endif

        @include('site.tools._about', ['tie' => __('site.song_part.tie'), 'tryLink' => __('site.song_part.try_link'), 'try' => $songPart['startUrl'], 'links' => ['app' => \App\Support\Http\Hosts::app('/')]])
        @include('site.tools._directory', ['directory' => \App\Support\Site\SitePages::directory()])
    </main>
    <x-site.footer />
    @if ($s['free'])
        <script type="module" src="/js/public/song-part-page.js"></script>
    @endif
@endsection
