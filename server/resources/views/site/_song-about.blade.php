{{-- A song's About, on its own page and its part pages (Hillel's part page redesign, 2026-11-10): two columns from
     900 px, its name (and `credits`, where the page doesn't already show them) at left, its story (`text`, paragraphs
     split on a blank line) and the ways on at right: the song's own page (`songPage`, [address, words], from a part page)
     and the page that collects it (`home`, SongCollections::home). A rule above it unless `ruled` is false (the song page,
     where the verse band already sets it apart). --}}
<section class="s-wrap s-sec2" aria-labelledby="about-song-h">
    <div @class(['s-spart-about', 's-spart-about-ruled' => $ruled ?? true])>
        <div>
            <h2 id="about-song-h">{{ $heading }}</h2>
            @foreach ($credits as $credit)
                <p class="s-spart-credit">{{ $credit }}</p>
            @endforeach
        </div>
        <div>
            @foreach (preg_split('/\n\s*\n/', (string) $text) ?: [] as $paragraph)
                <p>{{ $paragraph }}</p>
            @endforeach
            @if ($songPage !== null || $home !== null)
                <p class="s-spart-links">
                    @if ($songPage !== null)
                        <a href="{{ $songPage[0] }}" class="s-lk">{{ $songPage[1] }}</a>
                    @endif
                    @if ($home !== null)
                        <a href="{{ $home['path'] }}" class="s-lk">{{ $home['more'] }} <x-site.icon name="arrow" size="16" /></a>
                    @endif
                </p>
            @endif
        </div>
    </div>
</section>
