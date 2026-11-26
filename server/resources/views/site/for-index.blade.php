{{-- Who it's for (/for; Hillel, 2026-11-24: "add a new whos it for page under products which covers all 3 for pages"):
     the page above For learners, For singers and For choir directors, in the audience pages' 2026-11-24 design. The home's
     three ways in, each a card in its part's colour (no stripe: its eyebrow and a dot carry it) with a real picture, its
     goal and line in the home's own words and a link to its page; then the other audiences with a page of their own, and
     the first lesson under the four bars. The footer's one link to the audiences. Words: site.for.index, landing.paths. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@php([$plain, $accent] = \App\Support\Site\PathPages::split((string) __('site.for.index.hero.headline')))

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main class="s-aud s-v1">
        <section class="s-wrap s-aud-head s-aud-hubhead" aria-labelledby="page-h">
            <div>
                <h1 id="page-h" class="s-aud-kick">{{ __('site.for.index.hero.eyebrow') }}</h1>
                <p class="s-aud-h1">{{ $plain }} <em>{{ $accent }}</em></p>
                <p class="s-lede">{{ __('site.for.index.hero.lead') }}</p>
            </div>
        </section>

        <section class="s-wrap s-aud-hub" aria-label="{{ __('pages.nav.who_for') }}">
            <ul>
                @foreach ($paths as $path)
                    <li class="s-v{{ $path['n'] }}">
                        <a href="{{ $path['url'] }}">
                            <span class="s-aud-hubpic" aria-hidden="true">
                                @if ($path['shot'] !== null)
                                    <x-site.appshot :id="$path['shot']['id']" :alt="''" />
                                @else
                                    {{-- The director's view, as a picture: each part and how far its singers have got. --}}
                                    <span class="s-aud-board">
                                        @foreach (['soprano' => 'started', 'alto' => 'none', 'tenor' => 'recorded', 'bass' => null] as $part => $status)
                                            <span class="s-aud-part s-v{{ $loop->iteration }}@if ($status === null) s-aud-wait @endif"><i class="s-aud-dot"></i>{{ __('pages.landing.hero.parts.'.$part) }}<small>{{ $status === null ? __('pages.site.choir_demo.nobody') : __('pages.site.choir_demo.status.'.$status) }}</small></span>
                                        @endforeach
                                    </span>
                                @endif
                            </span>
                            <small class="s-kick"><i class="s-aud-dot" aria-hidden="true"></i>{{ $path['name'] }}</small>
                            <b>{{ __('landing.paths.'.$path['key'].'.who') }}</b>
                            @php($card = (array) \Illuminate\Support\Facades\Lang::get('site.for.index.cards.'.$path['card']))
                            <span>{{ $card['who'] }}</span>
                            <ul class="s-aud-gets">
                                @foreach ((array) $card['gets'] as $get)
                                    <li><x-site.icon name="check" size="16" />{{ $get }}</li>
                                @endforeach
                            </ul>
                            <small class="s-aud-cost">{{ $card['cost'] }}</small>
                            <em>{{ $card['action'] }} <x-site.icon name="arrow" size="16" /></em>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Everyone else with a page (or a place) of their own, each in a line. --}}
        @php($also = (array) \Illuminate\Support\Facades\Lang::get('site.for.index.also'))
        <section class="s-wrap s-sec" aria-labelledby="also-h">
            <h2 id="also-h" class="s-kick">{{ $also['heading'] }}</h2>
            <ul class="s-aud-also-list">
                {{-- A collection page only while it's a page (enough open songs); worship teams have their own page otherwise. --}}
                @php($collections = collect(app(\App\Support\Pages\SongCollections::class)->open())->pluck('path')->all())
                @foreach ((array) $also['items'] as [$href, $line])
                    @php($href = str_starts_with($href, '/songs/') && ! in_array($href, $collections, true) ? '/for/worship-teams' : $href)
                    <li><a href="{{ $href }}">{{ $line }}<x-site.icon name="arrow" size="16" /></a></li>
                @endforeach
            </ul>
        </section>

        <section class="s-wrap s-aud-end" aria-labelledby="end-h">
            {{-- The four bars as the logo has them: one left edge (design rules). --}}
            <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
            <h2 id="end-h">{{ __('site.for.index.unsure.line') }}</h2>
            <p class="s-final-btns"><a href="{{ $links['start'] }}" class="btn-primary btn-lg" data-funnel-start>{{ __('site.for.index.unsure.action') }} <x-site.icon name="arrow" size="18" /></a></p>
        </section>
    </main>
    <x-site.footer />
@endsection
