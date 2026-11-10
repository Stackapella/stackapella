{{-- One plain line to the page that collects a song (App\Support\Pages\SongCollections::home: its hymns, spirituals,
     sea shanties or rounds page, or the carols page in the carol season), on the song's page and its part pages: `home`,
     or nothing when no page collects it. Styled as the carols page's own line (input.css .s-carols-link). --}}
@if ($home !== null)
    <p class="s-carols-link"><a href="{{ $home['path'] }}" class="s-lk">{{ $home['more'] }} <x-site.icon name="arrow" size="16" /></a></p>
@endif
