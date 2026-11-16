{{-- A song page's actions (public/song.blade.php): Learn the part you pick in the hero's player (the melody's until
     then; js/public/song-page.js follows the pick), Share beside it, and Check my range for anyone unsure, a quiet link so
     Learn and Share keep one row (share_buttons). Under the player's rows when the page has one, else under the lead. --}}
<div class="s-actions s-actions-row s-sg-actions">
    @if ($first !== null)
        <a href="{{ $learn[$first['key']] }}" class="btn-primary btn-lg" data-learn data-label="{{ __('pages.song.learn_part', ['part' => ':part']) }}"><span data-learn-label>{{ __('pages.song.learn_part', ['part' => mb_strtolower($first['name'])]) }}</span> <x-site.icon name="arrow" size="18" /></a>
    @else
        <a href="{{ $song['startUrl'] }}" class="btn-primary btn-lg">{{ __('pages.song.start') }} <x-site.icon name="arrow" size="18" /></a>
    @endif
    {{-- Share stands as tall as Learn beside it (Hillel's design, 2026-11-16). --}}
    @include('site._share', ['surface' => 'page-song', 'url' => $meta['url'], 'title' => $song['title'], 'lg' => true])
</div>
<p class="s-sg-note">{{ __('pages.song.start_note') }} {{ __('pages.song.unsure') }} <a href="/tools/vocal-range-test" class="s-lk">{{ __('pages.song.check_range') }}</a></p>
