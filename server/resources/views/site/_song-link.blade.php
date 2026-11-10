{{-- One plain line to a song's page in the library (/songs/<slug>), only while the song is open (its part pages are:
     live, reviewed, public domain, App\Support\Pages\SongParts), so a song still waiting for its Monday is never
     linked. `slug`: the song; `line`: the page's own words. Drawn as the carols line is. --}}
@if (\App\Support\Pages\SongParts::open() && app(\App\Support\Pages\SongParts::class)->songs()->contains('slug', $slug))
    <p class="s-carols-link"><a href="/songs/{{ $slug }}" class="s-lk">{{ $line }} <x-site.icon name="arrow" size="16" /></a></p>
@endif
