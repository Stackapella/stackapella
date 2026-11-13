{{-- A songbook (the carols page and the song collections, SongCollections): each song as a card, its number, an optional
     place-and-year chip (`places`: a words key whose children are by song; or `chips`, a tag by song, the Jewish songs' festivals), its first line to play (mixer.js), its line
     from the song file and its parts as rows to their pages; with an "I sing" filter that rings one part on every card
     (js/public/carols.js). `songs` are Carols::card()s; `heading`, `sub` and `subPart` (":part") are the page's words. --}}
@php($singParts = collect($songs)->flatMap(fn (array $song): array => $song['parts'])->unique('key')->mapWithKeys(fn (array $part): array => [$part['key'] => $part['name']])->all())
        <section id="songbook" class="s-wrap s-xbook" aria-labelledby="book-h" data-songbook>
            <div class="s-xbook-head">
                <div>
                    <h2 id="book-h"><x-site.skin :game="__('site.carols.game.stage_select')" :serious="$heading" /></h2>
                    <p class="s-xbook-sub" data-book-sub data-any="{{ $sub }}" data-part="{{ $subPart }}">{{ $sub }}</p>
                    {{-- A page's own note on its cards (a round's parts all sing the tune, an octave apart). --}}
                    @if (($note ?? null) !== null)
                        <p class="s-xbook-note">{{ $note }}</p>
                    @endif
                </div>
                {{-- Plain buttons (a menu on a phone), remembered only while the page is open. --}}
                <div class="s-xfilter">
                    <span class="s-xfilter-k" id="sing-k"><x-site.skin :game="__('site.carols.game.one_p')" :serious="__('site.carols.i_sing')" /></span>
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

            {{-- What a part row does, said once (a singer: "it was hard to figure out how to change to listen to one part or
                 another"): each row is a link to that part's own page, where it plays alone. --}}
            <p class="s-xparts-hint">{{ __('site.carols.parts_hint') }}</p>
            {{-- At most three across; when three would leave one card alone on its last row (7, 10, 13, 16), the last four sit two and two. --}}
            @php($scale = \App\Support\Pages\Carols::SCALE)
            @php($at = static fn (int $midi): float => round(($midi - $scale[0]) / ($scale[1] - $scale[0]) * 100, 2))
            <div @class(['s-xcards', 's-xcards-tail' => count($songs) >= 4 && count($songs) % 3 === 1]) style="--mid: {{ $at(\App\Support\Pages\Carols::MIDDLE_C) }}%">
                @foreach ($songs as $carol)
                    <article id="carol-{{ $carol['slug'] }}" class="s-xcard s-xrib{{ $loop->index % 3 + 1 }}" aria-labelledby="carol-{{ $carol['slug'] }}-h" @if ($carol['line'] !== null) data-player @endif>
                        <div class="s-xcard-top">
                            <span class="s-xno s-xserif"><x-site.skin :game="__('site.carols.game.stage', ['n' => sprintf('%02d', $loop->iteration)])" :serious="__('site.carols.number', ['n' => sprintf('%02d', $loop->iteration)])" /></span>
                            @if (($chips ?? null) !== null && isset($chips[$carol['slug']]))
                                <span class="s-xplace">{{ $chips[$carol['slug']] }}</span>
                            @elseif ($places !== null && trans()->has($places.'.'.$carol['slug']))
                                <span class="s-xplace">{{ __($places.'.'.$carol['slug']) }}</span>
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
                                        {{-- Its range on the songbook's one scale, E2 to G5 (Carols::SCALE), middle C marked; the words beside it say the same. --}}
                                        <span class="s-xbar" aria-hidden="true"><i style="--lo: {{ $at($part['low']) }}%; --hi: {{ $at($part['high']) }}%"></i></span>
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
            <div class="s-xbook-foot">
                <p>{{ __('site.carols.scale', ['low' => \App\Support\Pages\Carols::signs(\App\Support\Pages\SongPage::note($scale[0])), 'high' => \App\Support\Pages\Carols::signs(\App\Support\Pages\SongPage::note($scale[1]))]) }}</p>
                <p>{{ __('site.carols.more') }} <a href="{{ \App\Support\Http\Hosts::app('/contact') }}" class="s-lk">{{ __('site.carols.write') }}</a></p>
            </div>
        </section>
