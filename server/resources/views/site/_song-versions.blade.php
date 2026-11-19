{{-- A song's versions (Hillel, 2026-11-18, "One song, US/UK switch"; App\Support\Templates\SongVersions::forPage): one
     small row of links, "Version · US UK", the one shown chosen, and the tune it sings under it. The page opened on the
     version the visitor's country sings (the UK's in the UK); a link picks another, and js/public/song-version.js keeps
     that pick on the device for the song's page and its part pages. One address per song: the version is a query.
     $versions: forPage()'s list; $song: the song's slug (the page's, never a version's). --}}
@if (($versions ?? []) !== [])
    @php($shown = collect($versions)->firstWhere('current', true))
    <nav class="s-ver" aria-labelledby="ver-h" data-song-versions data-version-song="{{ $song }}" data-version="{{ $shown['name'] ?? '' }}">
        <span class="s-ver-h" id="ver-h">{{ __('pages.song.version.heading') }}</span>
        <span class="s-ver-seg">
            @foreach ($versions as $version)
                <a href="{{ $version['url'] }}" data-version-pick="{{ $version['name'] }}" aria-label="{{ __('pages.song.version.label', ['label' => $version['label']]) }}" @if ($version['current']) aria-current="true" @endif translate="no">{{ $version['label'] }}</a>
            @endforeach
        </span>
        @if (($shown['tune'] ?? null) !== null)
            <p class="s-ver-note">{{ __('pages.song.version.note.'.(\Illuminate\Support\Facades\Lang::has('pages.song.version.note.'.$shown['name']) ? $shown['name'] : 'other'), ['tune' => $shown['tune']]) }}</p>
        @endif
    </nav>
@endif
