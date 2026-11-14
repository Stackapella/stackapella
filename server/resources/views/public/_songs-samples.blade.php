{{-- A band's or panel's sample songs on /songs (SongsHub samples): each to its own page, with its key and its kind
     ("C major · SATB", "Round" or how many parts), and its first line to play (js/public/mixer.js). A phone shows three. Play and
     Stop come from the page's icon sprite (songs.blade.php: Icons::sprite), one copy for every card. --}}
<ul class="s-sh-songs">
    @foreach ($songs as $song)
        <li class="s-sh-song" @if ($song['line'] !== null) data-player @endif>
            <a href="{{ $song['url'] }}"><b translate="no">{{ $song['title'] }}</b><span>{{ implode(' · ', array_filter([$song['meta'][0] ?? null, $song['kind'] === 'parts' ? trans_choice('pages.songs.kind.parts', count($song['parts']), ['count' => count($song['parts'])]) : __('pages.songs.kind.'.$song['kind'])])) }}</span></a>
            @if ($song['line'] !== null)
                <script type="application/json" data-song>@json($song['line'])</script>
                <button type="button" class="s-play" data-play aria-label="{{ __('pages.songs.play', ['title' => $song['title']]) }}" data-label-play="{{ __('pages.songs.play', ['title' => $song['title']]) }}" data-label-stop="{{ __('pages.songs.stop', ['title' => $song['title']]) }}">
                    <span class="s-i-play">{{ \App\Support\Site\Icons::use('play', 16) }}</span><span class="s-i-stop">{{ \App\Support\Site\Icons::use('stop', 16) }}</span>
                </button>
            @endif
        </li>
    @endforeach
</ul>
