{{-- Who it's for (/for; Hillel, 2026-11-24: "add a new whos it for page under products which covers all 3 for pages"),
     in Hillel's 2026-11-26 design: the headline left, the lead and a jump to each audience right; then each audience a
     full-width band, a real picture in its colour beside its words (who it suits, three things you get, what it costs, a
     button in its colour); everyone else as a two-column table; and the first lesson in a dark closing panel under the
     four bars. On a phone the bands stack, picture first. Words: site.for.index (marketing's), landing.paths. --}}
@extends('public.layout')

@section('head')
    @include('pages._head')
@endsection

@php([$plain, $accent] = \App\Support\Site\PathPages::split((string) __('site.for.index.hero.headline')))

@section('content')
    <x-site.header :app-url="$links['app']" />
    <main class="s-aud s-v1">
        <section class="s-wrap s-hub-head" aria-labelledby="page-h">
            <div>
                <h1 id="page-h" class="s-aud-kick">{{ __('site.for.index.hero.eyebrow') }}</h1>
                <p class="s-aud-h1">{{ $plain }} <em>{{ $accent }}</em></p>
            </div>
            <div>
                <p class="s-lede">{{ __('site.for.index.hero.lead') }}</p>
                {{-- A jump to each audience's band, below. --}}
                <ul class="s-hub-chips">
                    @foreach ($paths as $path)
                        <li class="s-v{{ $path['n'] }}"><a href="#{{ $path['card'] }}"><i class="s-aud-dot" aria-hidden="true"></i>{{ $path['name'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        </section>

        {{-- The cards' words through Teams: the directors' cost line says what starting a group takes, as the questions do. --}}
        @php($cards = (array) \App\Support\Site\Teams::words('site.for.index.cards'))
        @foreach ($paths as $path)
            @php($card = (array) $cards[$path['card']])
            <section class="s-wrap s-hub-sec" id="{{ $path['card'] }}" aria-labelledby="{{ $path['card'] }}-h">
            <div class="s-hub-band s-v{{ $path['n'] }}">
                <div class="s-hub-pic" aria-hidden="true">
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
                </div>
                <div class="s-hub-say">
                    <p class="s-kick"><i class="s-aud-dot" aria-hidden="true"></i>{{ $path['name'] }}</p>
                    <h2 id="{{ $path['card'] }}-h">{{ __('landing.paths.'.$path['key'].'.who') }}</h2>
                    <p class="s-hub-who">{{ $card['who'] }}</p>
                    {{-- How it works, in three steps true to the app. --}}
                    <p class="s-hub-how">{{ __('pages.nav.how') }}</p>
                    <ol class="s-hub-steps">
                        @foreach ((array) \Illuminate\Support\Facades\Lang::get('site.for.index.steps.'.$path['card']) as $step)
                            <li><b aria-hidden="true">{{ $loop->iteration }}</b><span>{{ $step }}</span></li>
                        @endforeach
                    </ol>
                    <ul class="s-aud-gets">
                        @foreach ((array) $card['gets'] as $get)
                            <li><x-site.icon name="check" size="16" />{{ $get }}</li>
                        @endforeach
                    </ul>
                    <p class="s-aud-cost">{{ $card['cost'] }}</p>
                    <a href="{{ $path['url'] }}" class="s-hub-btn">{{ $card['action'] }} <x-site.icon name="arrow" size="16" /></a>
                </div>
            </div>
            {{-- Real songs to play here, in the site's own player (one plays at a time), each to its page. --}}
            @if (($songs[$path['card']] ?? []) !== [])
                <div class="s-hub-songs">
                    <p class="s-kick">{{ __('site.for.index.songs.label') }}</p>
                    <div class="s-hub-players">
                        @foreach ($songs[$path['card']] as $song)
                            <div>
                                <x-site.mixer :song="$song" id="for-{{ $path['card'] }}-{{ $loop->iteration }}" />
                                <a href="/songs/{{ $song['song'] }}" class="s-arrow">{{ $song['title'] }} <x-site.icon name="arrow" size="16" /></a>
                            </div>
                        @endforeach
                        @if ($path['card'] === 'choir-directors')
                            <p class="s-hub-parts">{{ __('site.for.index.songs.directors') }} <a href="/songs" class="s-arrow">{{ __('site.for_directors.nogroup.browse') }} <x-site.icon name="arrow" size="16" /></a></p>
                        @endif
                    </div>
                </div>
            @endif
            </section>
        @endforeach

        {{-- Everyone else with a page (or a place) of their own: who, then what's there for them, each row a link. --}}
        @php($also = (array) \Illuminate\Support\Facades\Lang::get('site.for.index.also'))
        <section class="s-wrap s-sec" aria-labelledby="also-h">
            <h2 id="also-h" class="s-hub-h2">{{ $also['heading'] }}</h2>
            <ul class="s-hub-also">
                {{-- A collection page only while it's a page (enough open songs); worship teams have their own page otherwise. --}}
                @php($collections = collect(app(\App\Support\Pages\SongCollections::class)->open())->pluck('path')->all())
                @foreach ((array) $also['items'] as [$href, $line])
                    @php($href = str_starts_with($href, '/songs/') && ! in_array($href, $collections, true) ? '/for/worship-teams' : $href)
                    @php([$who, $what] = str_contains($line, ': ') ? explode(': ', $line, 2) : [$line, ''])
                    <li><a href="{{ $href }}"><b>{{ $who }}</b><span>{{ $what }}</span><x-site.icon name="arrow" size="16" /></a></li>
                @endforeach
            </ul>
        </section>

        {{-- A few questions, short and true to what's live (the home's own answers where they fit). --}}
        <section class="s-wrap s-sec" aria-labelledby="faq-h">
            <div class="s-two">
                <h2 id="faq-h" class="s-hub-h2">{{ __('pages.landing.faq_title') }}</h2>
                <div class="s-faq s-faq-round">
                    @foreach ($faq as [$question, $answer])
                        <details>
                            <summary>{{ $question }}<span class="s-faq-btn" aria-hidden="true"><span class="s-faq-plus"><x-site.icon name="plus" size="16" /></span><span class="s-faq-minus"><x-site.icon name="minus" size="16" /></span></span></summary>
                            <p>{{ $answer }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
        <x-site.json-ld :data="[['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => array_map(static fn (array $qa): array => ['@type' => 'Question', 'name' => $qa[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]], $faq)]]" />

        <section class="s-wrap s-sec" aria-labelledby="end-h">
            <div class="s-hub-end">
                {{-- The four bars as the logo has them: one left edge (design rules). --}}
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                <h2 id="end-h">{{ __('site.for.index.unsure.line') }}</h2>
                <a href="{{ $links['start'] }}" class="s-hub-white" data-funnel-start>{{ __('site.for.index.unsure.action') }} <x-site.icon name="arrow" size="18" /></a>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    @if (collect($songs)->flatten(1)->isNotEmpty())
        <script type="module" src="/js/public/mixer.js"></script>
    @endif
@endsection
