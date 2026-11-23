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
{{-- Behind own_choir_teaser (docs/ai-voices-build-plan.md, phase 2, finding out cheaply): one quiet button, Coming soon
     beside it; a tap says what it will be, leading with the angle (this real arrangement, from 30 seconds of singing) and
     naming AI once, and counts choir_teaser once (js/public/choir-teaser.js). Nothing is recorded or kept. --}}
{{-- In Hanukkah's window (JewishSongs::seasonKey), a Hanukkah song's page offers the choir guide (2026-11-23). --}}
@if (in_array('hanukkah', $song['genre'] ?? [], true) && \App\Support\Pages\JewishSongs::seasonKey() === 'hanukkah')
    <p class="s-sg-note">{{ __('pages.song.hanukkah_guide') }} <a href="/guides/hanukkah-songs-for-choir" class="s-lk">{{ __('pages.song.hanukkah_guide_link') }}</a></p>
@endif
@if (($choirLive ?? null) !== null)
    {{-- Live (own_choir): one quiet link into the app with this song, and the free first song said once. --}}
    <p class="s-sg-note"><a href="{{ $choirLive }}" class="s-lk" data-choir-live>{{ __('pages.song.choir.button') }}</a>@if ($choirFree ?? false) {{ __('pages.song.choir.free') }}@endif @if (in_array('jewish', $song['genre'] ?? [], true)){{ __('pages.song.choir.own_words') }}@endif</p>
@elseif ($choirTeaser ?? false)
    <p class="s-sg-note" data-choir-teaser><button type="button" class="s-lk" aria-expanded="false" aria-controls="choir-soon">{{ __('pages.song.choir.button') }}</button> <x-site.soon /></p>
    <p class="s-sg-note" id="choir-soon" hidden>{{ __('pages.song.choir.soon') }}</p>
@endif
