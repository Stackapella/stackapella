{{-- A song's featured picture (App\Support\Pages\SongPictures; Hillel, 2026-11-18: "pick an image from each song to use as
     the featured image"): in a frame of a fixed shape, so the page never moves as it loads, lazy, its 480 and 960 px
     sizes for `sizes`, with its alt. Cropped to the frame, centred on its `focus`, unless it's far from the frame's shape
     (a strip of music, a tall cup): then whole, on a mat. Its credit only where its licence asks for one; the song's page
     credits every picture in full. A song without one shows its opening words set large in its collection's palette (`tone`).
     `class` is the frame's, `sizes` the picture's (a phone asks for 240 px, so it takes the 480 file at 2x and the 960 only
     at 3x: the cards and panels stayed over their weight budget fetching 960s on every phone, 2026-11-20). With `background`, it's the quiet ground under a title (the
     song cards, Hillel, 2026-11-18: "more of a background element"): always cropped, and nothing at all without a picture,
     the title's palette standing in. --}}
@if ($picture !== null)
    <figure @class([$class, 's-fpic', 's-fpic-bg' => $background ?? false, 's-fpic-whole' => $picture['whole'] && ! ($background ?? false)]) style="--focus: {{ $picture['focus'] }}">
        <img src="{{ $picture['src'] }}" @if ($picture['srcset'] !== '') srcset="{{ $picture['srcset'] }}" sizes="{{ $sizes }}" @endif width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" alt="{{ $picture['alt'] }}" loading="lazy" decoding="async">
        {{-- Game mode's pixel art of it, in its place (Hillel, 2026-11-18: "for game mode can we apply a pixel art effect"): a
             few KB, drawn large in crisp blocks; never fetched in calm, where it isn't shown. --}}
        @if (($background ?? false) && ($picture['pixel'] ?? null) !== null)
            <img class="s-fpic-px" src="{{ $picture['pixel'] }}" width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" alt="" loading="lazy" decoding="async">
        @endif
        @if ($picture['credit'] !== null)
            {{-- The credit as its licence asks, the licence's name linked once (SongPictures: creditBefore). --}}
            <figcaption class="s-fpic-credit">{{ $picture['creditBefore'] }}@if ($picture['licenceUrl'] !== null)
                <a href="{{ $picture['licenceUrl'] }}" rel="license">{{ $picture['licenceName'] }}</a>@else{{ $picture['licenceName'] }}@endif</figcaption>
        @endif
    </figure>
@elseif (! ($background ?? false))
    {{-- No place or thing among its pictures: its opening words set large, as on a book's cover, in its collection's
         palette (`tone`), one of three grounds by its place (`variant`, the card's index) so neighbours differ (its title when it has no words); never
         its music, never a drawn person. --}}
    @php($opening = \App\Support\Pages\SongPictures::opening($song['line'] ?? null))
    @php($ground = \App\Support\Pages\SongPictures::ground($variant ?? null, (string) ($song['slug'] ?? $song['title'])))
    <div @class([$class, 's-fpic', 's-fpic-words', $ground, 's-tone', 's-tone-'.($tone ?? 'song')]) aria-hidden="true">
        <span class="s-xserif" translate="no">{{ $opening !== '' ? '“'.$opening.'”' : $song['title'] }}</span>
    </div>
@endif
