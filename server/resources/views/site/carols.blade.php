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
    <meta property="og:image" content="{{ \App\Support\Http\Hosts::public('/icons/og-landing.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ $canonical }}">
    <x-site.json-ld :data="\App\Support\Site\StructuredData::carols(__('site.carols.list'), $canonical, $carols)" />
@endsection

{{-- Every part any carol has, in score order, for the "I sing" filter. --}}
@php($singParts = collect($carols)->flatMap(fn (array $carol): array => $carol['parts'])->unique('key')->mapWithKeys(fn (array $part): array => [$part['key'] => $part['name']])->all())

@section('content')
    <x-site.header />
    <main @class(['s-xmas' => $festive])>
        <section class="s-xhero" aria-labelledby="page-h">
            @if ($festive)
                {{-- A string of still lights along the top: CSS marks, never images. --}}
                <div class="s-xlights" aria-hidden="true">@for ($i = 0; $i < 24; $i++)<i></i>@endfor</div>
                {{-- Snow falling across the hero, behind the words (Hillel: "add nice animated falling snow in the green
                     header", "make it look like snow"): each flake its own place, size, speed and drift from a fixed
                     sequence, far ones small, faint and slow, near ones big, bright and a little faster; paused off
                     screen (js/public/carols.js), none under reduced motion. --}}
                <div class="s-xsnow" aria-hidden="true" data-snow>@for ($i = 0; $i < 60; $i++)@php($depth = $i % 3)@php($r = fmod($i * 0.618034, 1))<i style="--x: {{ round(fmod($i * 37.3, 100), 1) }}%; --s: {{ [3, 5, 8][$depth] + ($i % 2) }}px; --o: {{ [0.55, 0.8, 0.95][$depth] }}; --d: {{ round([18, 14, 11][$depth] + $r * 3, 1) }}s; --w: -{{ round($r * 18, 1) }}s; --sway: {{ [6, 11, 16][$depth] + ($i % 4) }}px"></i>@endfor</div>
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
                        <a href="#choirs-h" class="btn-secondary btn-lg">{{ __('site.carols.choirs_link') }}</a>
                    </div>
                </div>
                @if ($countdown !== null)
                    {{-- Up to Christmas Day; js/public/carols.js counts again in the visitor's own day and hides it after. On a
                         phone it's one line. --}}
                    <div class="s-xcountwrap" data-countdown>
                        <aside class="s-xcount" aria-label="{{ __('site.carols.count_label') }}">
                            <p class="s-xcount-k" data-count-label data-count="{{ __('site.carols.count_label') }}" data-christmas="{{ __('site.carols.christmas_label') }}">{{ $countdown['christmas'] ? __('site.carols.christmas_label') : __('site.carols.count_label') }}</p>
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
                                    <a href="#carol-{{ $tonight['slug'] }}"><span><small>{{ __('site.carols.tonight') }}</small><b translate="no">{{ $tonight['title'] }}</b></span><x-site.icon name="arrow" size="18" /></a>
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
                    <li class="s-v{{ $loop->iteration }}"><span class="s-xstep-n" aria-hidden="true">{{ $loop->iteration }}</span><span><b>{{ $step }}</b> {{ $line }}</span></li>
                @endforeach
            </ol>
        </section>

        <section id="songbook" class="s-wrap s-xbook" aria-labelledby="book-h" data-songbook>
            <div class="s-xbook-head">
                <div>
                    <h2 id="book-h">{{ __('site.carols.songbook') }}</h2>
                    <p class="s-xbook-sub" data-book-sub data-any="{{ __('site.carols.songbook_sub') }}" data-part="{{ __('site.carols.songbook_part') }}">{{ __('site.carols.songbook_sub') }}</p>
                </div>
                {{-- Plain buttons (a menu on a phone), remembered only while the page is open. --}}
                <div class="s-xfilter">
                    <span class="s-xfilter-k" id="sing-k">{{ __('site.carols.i_sing') }}</span>
                    <div class="s-xfilter-seg" role="group" aria-labelledby="sing-k">
                        <button type="button" data-sing="" aria-pressed="true">{{ __('site.carols.any_part') }}</button>
                        @foreach ($singParts as $key => $name)
                            <button type="button" data-sing="{{ $key }}" aria-pressed="false" class="s-v{{ $loop->iteration }}"><i aria-hidden="true"></i>{{ $name }}</button>
                        @endforeach
                    </div>
                    <select class="s-xfilter-menu" aria-labelledby="sing-k" data-sing-menu>
                        <option value="">{{ __('site.carols.any_part') }}</option>
                        @foreach ($singParts as $key => $name)
                            <option value="{{ $key }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="s-xcards">
                @foreach ($carols as $carol)
                    <article id="carol-{{ $carol['slug'] }}" class="s-xcard s-xrib{{ $loop->index % 3 + 1 }}" aria-labelledby="carol-{{ $carol['slug'] }}-h" @if ($carol['line'] !== null) data-player @endif>
                        <div class="s-xcard-top">
                            <span class="s-xno s-xserif">{{ __('site.carols.number', ['n' => sprintf('%02d', $loop->iteration)]) }}</span>
                            @if (trans()->has('site.carols.places.'.$carol['slug']))
                                <span class="s-xplace">{{ __('site.carols.places.'.$carol['slug']) }}</span>
                            @endif
                            {{-- Its first line in every part, in the guide voices (mixer.js), one carol at a time. --}}
                            @if ($carol['line'] !== null)
                                <script type="application/json" data-song>@json($carol['line'])</script>
                                <button type="button" class="s-play" data-play aria-label="{{ __('site.carols.play', ['song' => $carol['title']]) }}" data-label-play="{{ __('site.carols.play', ['song' => $carol['title']]) }}" data-label-stop="{{ __('site.carols.stop', ['song' => $carol['title']]) }}">
                                    <span class="s-i-play"><x-site.icon name="play" size="16" /></span><span class="s-i-stop"><x-site.icon name="stop" size="16" /></span>
                                </button>
                            @endif
                        </div>
                        <h3 id="carol-{{ $carol['slug'] }}-h" translate="no">{{ $carol['title'] }}</h3>
                        {{-- Its key, how much of it and its level, from the song file; its story is on its own page. --}}
                        @if ($carol['meta'] !== [])
                            <p class="s-xmeta">{{ implode(' · ', $carol['meta']) }}</p>
                        @endif
                        <ul class="s-xparts" aria-label="{{ __('site.carols.parts') }}">
                            @foreach ($carol['parts'] as $part)
                                <li>
                                    <a href="{{ $part['url'] }}" class="s-xpart s-v{{ $loop->iteration }}" data-part-key="{{ $part['key'] }}">
                                        <i aria-hidden="true"></i>
                                        <span class="s-xpart-name"><b>{{ $part['name'] }}</b>@if ($part['melody'])<span class="s-xmel">{{ __('site.song_part.melody_tag') }}</span>@endif</span>
                                        <span class="s-xrange" translate="no">{{ __('site.song_part.other_range', ['low' => $part['lowName'], 'high' => $part['highName']]) }}</span>
                                        <x-site.icon name="arrow" size="16" />
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                        <a href="{{ $carol['url'] }}" class="s-lk s-xmore">{{ __('site.carols.song_page', ['song' => $carol['title']]) }} <x-site.icon name="arrow" size="16" /></a>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="s-wrap s-xchoirs" aria-labelledby="choirs-h">
            <div class="s-xband">
                <div>
                    <h2 id="choirs-h">{{ __('site.carols.choirs_title') }} <em class="s-xserif">{{ __('site.carols.choirs_title_em') }}</em></h2>
                    <p>{{ __('site.carols.choirs_body') }}</p>
                </div>
                <div class="s-xband-go">
                    <a href="{{ \App\Support\Http\Hosts::app('/groups?start=1&ref=carols') }}" class="btn-secondary btn-lg">{{ __('site.carols.choirs_start') }} <x-site.icon name="arrow" size="18" /></a>
                    <a href="/for/choir-directors" class="s-lk">{{ __('site.carols.choirs_more') }}</a>
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
