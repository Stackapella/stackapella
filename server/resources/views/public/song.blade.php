{{-- A song's page at /songs/<slug> (SongPagesController::show; Hillel's design, Danny Boy v2, "Responsive wrapping
     fixes", and his "the definitive page for each song", docs/seo-growth-plan.md; the hero's player, Hillel 2026-11-13,
     "it should be easy to hear the song on the song page", option C of the song page review): the hero in the palette of
     the page that collects it, with the whole song to play, each part's row heard alone and picked ("I sing this"), and
     Learn naming the part you pick under the rows; every verse, one sung line a line; "How each part goes", each part's
     range on one scale, its one true tag and where it goes in the words; then its story (SongStories) with its contents,
     timeline, questions and numbered sources, else its About; what to sing next; the page's last word. --}}
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
    <x-site.json-ld :data="\App\Support\Site\StructuredData::song($song, $story)" />
@endsection

@section('content')
    <x-site.header />
    {{-- The part Learn names until one is picked: the melody's (Hillel's "Melody first"). --}}
    @php($first = collect($cards)->firstWhere('melody', true) ?? ($cards[0] ?? null))
    {{-- Each part's row in the player says its tag and range ("Melody · B3 to E5"), so the choice is made where you hear. --}}
    {{-- A round's parts all sing the tune, entering in turn: each one's tag says so, never Melody and Harmony. --}}
    @php($tagOf = \App\Support\Pages\SongParts::round($song['genre']) ? static fn (array $card): string => 'tune' : static fn (array $card): string => $card['tag'])
    @php($rowLine = static fn (array $card): string => __('pages.song.row_line', ['tag' => __('pages.song.tag.'.$tagOf($card)), 'range' => __('pages.song.range', ['low' => \App\Support\Pages\Carols::signs($card['low']), 'high' => \App\Support\Pages\Carols::signs($card['high'])])]))
    @if ($line !== null)
        @php($byKey = collect($cards)->keyBy('key'))
        @php($line['parts'] = array_map(static fn (array $part): array => $byKey->has($part['key']) ? [...$part, 'line' => $rowLine($byKey->get($part['key']))] : $part, $line['parts']))
    @endif
    <main data-sg-page>
        {{-- Learn's part for each pick (js/public/song-page.js follows the player's "I sing this"). --}}
        @if ($first !== null)
            <script type="application/json" data-learn-parts>@json(collect($cards)->mapWithKeys(fn (array $card): array => [$card['key'] => ['name' => mb_strtolower($card['name']), 'href' => $learn[$card['key']]]]))</script>
        @endif
        {{-- The top, in its collection's palette: where you are, the song, its lead and facts as chips; beside it on a wide
             screen (under it on a phone), the whole song to play, a row a part, and under the rows Learn the part you
             pick (the tune's until you pick), Share, and Check my range. --}}
        <section class="s-xhero s-tone s-tone-{{ $tone }} s-sg-hero" aria-labelledby="page-h">
            @include('site._tone-motif', ['key' => $tone])
            <div @class(['s-wrap s-xhero-in s-xcoll-in', 's-xcoll-has-try' => $line !== null || $song['previewUrl'] !== null])>
                <div class="s-xhero-text">
                    <x-site.crumbs :trail="[[__('pages.song.eyebrow'), '/songs'], ...($home !== null ? [[$home['name'], $home['path']]] : []), [$song['title'], null, true]]" :ld="true" />
                    <h1 id="page-h" translate="no">{{ $song['title'] }}</h1>
                    <p class="s-lead">{{ $story !== null && $story['lead'] !== '' ? $story['lead'] : $song['description'] }}</p>
                    <ul class="s-sg-chips">
                        <li class="s-sg-chip-free">{{ __('pages.song.free') }}</li>
                        <li>{{ __('pages.songs.parts', ['count' => count($song['parts'])]) }}</li>
                        @if (\Illuminate\Support\Facades\Lang::has('pages.song.difficulty.'.$song['difficulty']))
                            <li>{{ __('pages.song.difficulty.'.$song['difficulty']) }}</li>
                        @endif
                        {{-- The kinds of song it is live in its story's At a glance; a song without a story keeps them here. --}}
                        @if ($story === null)
                            @foreach ($song['genre'] as $genre)
                                <li>{{ \App\Support\Languages\SongWords::genre($genre) }}</li>
                            @endforeach
                        @endif
                    </ul>
                    @if ($line === null)
                        @include('public._song-actions')
                    @endif
                </div>
                @if ($line !== null)
                    <div class="s-sh-try s-xcoll-try s-sg-try">
                        <x-site.mixer :song="$line" id="song-mixer" :picked="false">
                            <x-slot:foot>@include('public._song-actions')</x-slot:foot>
                        </x-site.mixer>
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

        {{-- Every verse, one sung line a line, stanzas apart; a long line wraps under itself (a hanging indent). --}}
        @if ($lines !== [])
            <section class="s-band s-sg-words" aria-labelledby="verse-h">
                <div class="s-wrap s-sg-words-in">
                    <div>
                        <h2 id="verse-h" class="s-h2">{{ __('pages.song.words_heading') }}</h2>
                        @if ($song['credits'] !== [])
                            <p class="s-song-credits">@foreach ($song['credits'] as $credit)<span>{{ $credit }}</span>@endforeach</p>
                        @endif
                    </div>
                    @include('public._song-words', ['lines' => $lines, 'labels' => $labels ?? [], 'intro' => $wordsIntro ?? '', 'sungLanguage' => $sungLanguage ?? null, 'toggle' => \App\Support\Pages\SongPage::meaningToggle($lines)])
                </div>
            </section>
        @endif

        {{-- How each part goes: a row a part, its tag, its range on the page's one scale and its guide voice, then where it
             goes in the words (kept whole and unfolded: it's the page's facts) and its own page to practise it. Nothing to
             pick here: the hero's player picks. --}}
        @if ($cards !== [])
            <section id="parts" class="s-wrap s-sec2 s-sg-parts" aria-labelledby="parts-h">
                <div class="s-sg-head">
                    <h2 id="parts-h" class="s-h2">{{ __('pages.song.how') }}</h2>
                    <p class="s-sub">{{ __('pages.song.how_lead') }}</p>
                </div>
                <ul class="s-sg-how">
                    @foreach ($cards as $card)
                        <li id="{{ $card['key'] }}" class="s-sg-how-row s-v{{ $card['color'] }}" data-part="{{ $card['key'] }}">
                            <div class="s-sg-how-facts">
                                <span class="s-disc" aria-hidden="true">{{ mb_substr($card['name'], 0, 1) }}</span>
                                <h3>{{ $card['name'] }}</h3>
                                <span class="s-sg-tag">{{ __('pages.song.tag.'.$tagOf($card)) }}</span>
                                <p class="s-sg-range" translate="no">{{ __('pages.song.range', ['low' => \App\Support\Pages\Carols::signs($card['low']), 'high' => \App\Support\Pages\Carols::signs($card['high'])]) }}</p>
                                <span class="s-sg-bar" aria-hidden="true"><i style="--lo: {{ $card['from'] }}%; --hi: {{ $card['to'] }}%"></i></span>
                                {{-- Its guide voice: a real singer's, credited as its part page credits it, or the app's own with the ask to sing it. --}}
                                @php($guide = $guides[$card['key']] ?? null)
                                <p class="s-sg-voice">
                                    @if ($guide !== null && $guide['realVoice'])
                                        <b>{{ __('pages.song.voice_real') }}</b>
                                        @if ($guide['credit'] !== null && $guide['creditHandle'] !== null)
                                            <a href="{{ '/@'.$guide['creditHandle'] }}" class="s-lk">{{ __('site.song_part.sung_by', ['name' => $guide['credit']]) }}</a>
                                        @elseif ($guide['credit'] !== null)
                                            <span>{{ __('site.song_part.sung_by', ['name' => $guide['credit']]) }}</span>
                                        @endif
                                    @else
                                        <span>{{ __('pages.song.voice_generated') }}</span>
                                        @if ($lendVoice)
                                            <a href="{{ $learn[$card['key']] }}" class="s-lk" data-be-voice="{{ $card['key'] }}">{{ __('pages.song.be_voice', ['part' => mb_strtolower($card['name'])]) }}</a>
                                        @endif
                                    @endif
                                </p>
                            </div>
                            <div class="s-sg-how-text">
                                @if (\Illuminate\Support\Facades\Lang::has('song_parts.'.$song['slug'].'.'.$card['key']))
                                    <p class="s-sg-part-note">{{ __('song_parts.'.$song['slug'].'.'.$card['key']) }}</p>
                                @endif
                                @if ($card['url'] !== null)
                                    <a href="{{ $card['url'] }}" class="s-lk s-sg-practise" data-part-link="{{ $card['key'] }}">{{ __('pages.song.practise', ['part' => mb_strtolower($card['name'])]) }} <x-site.icon name="arrow" size="16" /></a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if ($lendVoice && collect($guides)->contains('realVoice', false))
                    <p class="s-sg-voices-note">{{ __('pages.song.be_voice_note') }} <a href="/features/real-voices" class="s-lk">{{ __('pages.landing.guide.more') }}</a></p>
                @endif
            </section>
        @endif

        @if ($story !== null)
            @include('public._song-story', ['story' => $story, 'song' => $song, 'quoteN' => $quoteN, 'genres' => array_map(static fn (string $genre): string => \App\Support\Languages\SongWords::genre($genre), $song['genre'])])
        @elseif ($song['about'] !== null)
            {{-- Its About, as its part pages tell it (site._song-about), until it has a story. --}}
            @include('site._song-about', ['heading' => __('site.song_part.about', ['song' => $song['title']]), 'credits' => $lines === [] ? $song['credits'] : [], 'text' => $song['about'], 'songPage' => null, 'home' => null, 'ruled' => false])
        @endif

        {{-- What to sing next: the story's own picks, each with why, else three more at its level; then its collection. --}}
        @if (($story['next'] ?? []) !== [] || $more !== [])
            <section class="s-wrap s-sec2" aria-labelledby="more-h">
                <h2 id="more-h" class="s-h2">{{ __('pages.song.more') }}</h2>
                @if (($story['next'] ?? []) !== [])
                    <ul class="s-sg-next">
                        @foreach ($story['next'] as $next)
                            <li><a href="/songs/{{ $next['slug'] }}"><b translate="no">{{ $next['title'] }}</b><span>{{ \Illuminate\Support\Str::ucfirst($next['reason']) }}</span></a></li>
                        @endforeach
                    </ul>
                @else
                    <ul class="s-songs s-more-songs">
                        @foreach ($more as $other)
                            <li class="s-neon{{ $loop->index % 3 + 1 }}">
                                <a href="/songs/{{ $other['slug'] }}" class="s-song">
                                    <span class="s-row">
                                        <x-site.song-glyph :ranges="$other['ranges']" />
                                        <span class="s-badge s-badge-free">{{ __('pages.song.free') }}</span>
                                    </span>
                                    <span><span class="s-t" translate="no">{{ $other['title'] }}</span><span class="s-k">{{ __('pages.songs.parts', ['count' => count($other['parts'])]) }}@if ($other['genre'] !== []) · {{ \App\Support\Languages\SongWords::genre($other['genre'][0]) }}@endif</span></span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @include('site._collection-link', ['home' => $home])
                <p class="s-more"><a href="/songs" class="btn-secondary">{{ __('pages.songs.headline') }}</a></p>
            </section>
        @endif

        {{-- The page's own last word. --}}
        <section class="s-wrap" aria-labelledby="end-h">
            <div class="s-end">
                <span class="s-stackbig" aria-hidden="true"><b class="s-v1" style="width: 100%"></b><b class="s-v2" style="width: 74%"></b><b class="s-v3" style="width: 88%"></b><b class="s-v4" style="width: 62%"></b></span>
                <div>
                    <h2 id="end-h">{{ __('pages.song.end_title', ['title' => $song['title']]) }}</h2>
                    <p>{{ __('pages.song.end_free') }}</p>
                </div>
                {{-- The hero's Learn again, following the pick (js/public/song-page.js). --}}
                @if ($first !== null)
                    <a href="{{ $learn[$first['key']] }}" class="btn-primary btn-lg" data-learn data-label="{{ __('pages.song.learn_part', ['part' => ':part']) }}"><span data-learn-label>{{ __('pages.song.learn_part', ['part' => mb_strtolower($first['name'])]) }}</span> <x-site.icon name="arrow" size="18" /></a>
                @else
                    <a href="{{ $song['startUrl'] }}" class="btn-primary btn-lg">{{ __('pages.song.start') }} <x-site.icon name="arrow" size="18" /></a>
                @endif
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    {{-- The app's first screen, fetched while the visitor reads (first-session review, item 3): timing only. --}}
    @include('site._app-prefetch', ['prefetch' => \App\Support\Site\AppPrefetch::song()])
    @if ($line !== null)
        <script type="module" src="/js/public/mixer.js"></script>
    @endif
    <script type="module" src="/js/public/song-page.js"></script>
@endsection
