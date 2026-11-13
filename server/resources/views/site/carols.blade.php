{{-- The Christmas carols page, /songs/christmas-carols, in Hillel's redesign (2026-11-10, shots-review/review-2026-11-10/
     carols-redesign, v2). CarolsPageController gives $carols (App\Support\Pages\Carols), $festive, $countdown, $tonight
     and $totals. The hero: the trail, the heading with its last words in the italic serif, the lead, Pick a carol and For
     choir directors, and, up to Christmas Day, a card counting the days with the songbook's totals and tonight's carol.
     Then the songbook: a card for each open carol (its number, where and when it comes from, its first line to play, its
     fact, its parts as tiles to their pages, and its song page), with an "I sing" filter that lights one part on every
     card (js/public/carols.js); the band for choir directors with how to send each section its part; the About panel
     and the free tools. While the carols are pushed ($festive: 1 November to 26 December) the hero, the band and the
     footer wear the season's green and red with a string of lights; the rest of the year the page is plain. A carol
     joins by itself when it opens. Words: site.carols. --}}
@php($title = __('site.carols.title'))
@php($description = __('site.carols.description'))
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
    <x-site.json-ld :data="\App\Support\Site\StructuredData::carols(__('site.carols.list'), $canonical, $carols)" />
@endsection


@section('content')
    <x-site.header />
    <main @class(['s-xmas' => $festive])>
        <section class="s-xhero" aria-labelledby="page-h">
            @if ($festive)
                @include('site._xmas-sky')
            @endif
            <div class="s-wrap s-xhero-in">
                <div class="s-xhero-text">
                    <x-site.crumbs :trail="[[__('site.song_part.songs'), '/songs'], [__('site.carols.crumb')]]" />
                    <h1 id="page-h">{{ __('site.carols.headline') }} <em class="s-xserif">{{ __('site.carols.headline_em') }}</em></h1>
                    <p class="s-lead">{{ __('site.carols.lead') }}</p>
                    @if ($weekly)
                        <p class="s-xweekly">{{ __('site.carols.weekly') }}</p>
                    @endif
                    <div class="s-actions s-actions-row">
                        <a href="#songbook" class="btn-primary btn-lg">{{ __('site.carols.pick') }} <span aria-hidden="true">↓</span></a>
                        @include('site._share', ['surface' => 'page-carols', 'url' => $canonical, 'title' => $title, 'text' => __('site.share.text_carols'), 'lg' => true])
                        <a href="#choirs-h" class="btn-secondary btn-lg"><x-site.skin :game="__('site.carols.game.co_op_link')" :serious="__('site.carols.choirs_link')" /></a>
                    </div>
                </div>
                @if ($countdown !== null)
                    {{-- Up to Christmas Day; js/public/carols.js counts again in the visitor's own day and hides it after. On a
                         phone it's one line. --}}
                    <div class="s-xcountwrap" data-countdown>
                        <aside class="s-xcount" aria-label="{{ __('site.carols.count_label') }}">
                            <p class="s-xcount-k"><span class="s-skin-g" data-count-label data-count="{{ __('site.carols.game.time_left') }}" data-christmas="{{ __('site.carols.christmas_label') }}">{{ $countdown['christmas'] ? __('site.carols.christmas_label') : __('site.carols.game.time_left') }}</span><span class="s-skin-s" data-count-label data-count="{{ __('site.carols.count_label') }}" data-christmas="{{ __('site.carols.christmas_label') }}">{{ $countdown['christmas'] ? __('site.carols.christmas_label') : __('site.carols.count_label') }}</span></p>
                            <p class="s-xcount-n">
                                <span class="s-xserif" data-days>{{ $countdown['christmas'] ? 25 : $countdown['days'] }}</span>
                                <span data-days-unit data-one="{{ trans_choice('site.carols.days', 1) }}" data-many="{{ trans_choice('site.carols.days', 2) }}" data-christmas="{{ __('site.carols.christmas') }}">{{ $countdown['christmas'] ? __('site.carols.christmas') : trans_choice('site.carols.days', $countdown['days']) }}</span>
                            </p>
                            <p class="s-xcount-totals">
                                <span><b>{{ $totals['carols'] }}</b> {{ trans_choice('site.carols.totals_carols', $totals['carols']) }}</span>
                                @if ($totals['each'] !== null)
                                    <span>{{ trans_choice('site.carols.totals_each', $totals['each'], ['count' => $totals['each']]) }}</span>
                                @endif
                            </p>
                            @if ($tonight !== null)
                                {{-- Tonight's carol plays its first line here: the page's one inviting play. --}}
                                <div class="s-xtonight" @if ($tonight['line'] !== null) data-player @endif>
                                    @if ($tonight['line'] !== null)
                                        <script type="application/json" data-song>@json($tonight['line'])</script>
                                        <button type="button" class="s-play" data-play aria-label="{{ __('site.carols.play', ['song' => $tonight['title']]) }}" data-label-play="{{ __('site.carols.play', ['song' => $tonight['title']]) }}" data-label-stop="{{ __('site.carols.stop', ['song' => $tonight['title']]) }}">
                                            <span class="s-i-play"><x-site.icon name="play" size="16" /></span><span class="s-i-stop"><x-site.icon name="stop" size="16" /></span>
                                        </button>
                                    @endif
                                    <a href="#carol-{{ $tonight['slug'] }}"><span><small><x-site.skin :game="__('site.carols.game.tonight')" :serious="__('site.carols.tonight')" /></small><b translate="no">{{ $tonight['title'] }}</b></span><x-site.icon name="arrow" size="18" /></a>
                                </div>
                            @endif
                        </aside>
                        <p class="s-xcount-line">
                            <span data-line-days data-one="{{ trans_choice('site.carols.count_line', 1, ['count' => 1]) }}" data-many="{{ trans_choice('site.carols.count_line', 2, ['count' => ':count']) }}" data-christmas="{{ __('site.carols.christmas') }}">{{ $countdown['christmas'] ? __('site.carols.christmas') : trans_choice('site.carols.count_line', $countdown['days'], ['count' => $countdown['days']]) }}</span>
                            @if ($tonight !== null)
                                · <a href="#carol-{{ $tonight['slug'] }}" class="s-lk">{{ __('site.carols.tonight_line', ['song' => $tonight['title']]) }} <x-site.icon name="arrow" size="16" /></a>
                            @endif
                        </p>
                    </div>
                @endif
            </div>
        </section>

        {{-- How a carol is learnt here, in three steps. --}}
        <section class="s-wrap s-xsteps" aria-label="{{ __('site.carols.steps_label') }}">
            <ol>
                @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.carols.steps') as [$step, $line])
                    <li class="s-v{{ $loop->iteration }}"><span class="s-xstep-n" aria-hidden="true"><x-site.skin :game="__('site.carols.game.level', ['n' => $loop->iteration])" :serious="$loop->iteration" /></span><span><b>{{ $step }}</b> {{ $line }}</span></li>
                @endforeach
            </ol>
        </section>

        @include('site._songbook', ['songs' => $carols, 'heading' => __('site.carols.songbook'), 'sub' => __('site.carols.songbook_sub'), 'subPart' => __('site.carols.songbook_part'), 'places' => 'site.carols.places'])

        <section class="s-wrap s-xchoirs" aria-labelledby="choirs-h">
            <div class="s-xband">
                <div>
                    {{-- Game mode's kicker (Hillel's 90s design): the band as two players' co-op. --}}
                    <p class="s-xband-k s-skin-g">{{ __('site.carols.game.co_op') }}</p>
                    <h2 id="choirs-h">{{ __('site.carols.choirs_title') }} <em class="s-xserif">{{ __('site.carols.choirs_title_em') }}</em></h2>
                    <p>{{ \App\Support\Site\Teams::text('site.carols.choirs_body') }}</p>
                </div>
                <div class="s-xband-go">
                    <a href="{{ \App\Support\Http\Hosts::app('/groups?start=1&ref=carols') }}" class="btn-secondary btn-lg"><x-site.skin :game="__('site.carols.game.co_op_start')" :serious="__('site.carols.choirs_start')" /> <x-site.icon name="arrow" size="18" /></a>
                    <a href="/for/choir-directors?ref=for-choirs-carols" class="s-lk">{{ __('site.carols.choirs_more') }}</a>
                </div>
            </div>
        </section>

        @include('site.tools._about', ['tie' => __('site.carols.tie'), 'tryLink' => __('site.carols.try_link'), 'try' => \App\Support\Http\Hosts::app('/?ref=carols'), 'links' => ['app' => \App\Support\Http\Hosts::app('/')]])
        @include('site.tools._directory', ['directory' => \App\Support\Site\SitePages::directory()])
    </main>
    @if ($festive)
        <div class="s-xfoot"><x-site.footer :festive="true" /></div>
    @else
        <x-site.footer />
    @endif
@endsection

@section('scripts')
    <script type="module" src="/js/public/mixer.js"></script>
    <script type="module" src="/js/public/carols.js"></script>
@endsection
