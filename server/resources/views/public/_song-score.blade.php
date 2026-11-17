{{-- A song page's player and score, one card (Hillel's design, 2026-11-16; docs/song-sheet-music-plan.md): Play, what is
     heard and the time; the score on its sheet, a round letter at each staff to hear that part alone; the bars shown and
     the way to the full score; then the downloads, free with an account. The notes are engraved by js/public/score.js
     from the song the player reads (js/public/mixer.js plays it and lights each note as it sounds); until then, and
     without the script, the sheet keeps its staves' room. `$line` is the player's song, `$score` SongScore::of(). --}}
@php($count = count($line['parts']))
{{-- A round is written on one staff, its letters all beside it (js/engrave.js). --}}
@php($staves = $score['voicing'] === 'round' ? 1 : $count)
<div class="s-score" data-score data-slug="{{ $line['song'] }}">
    <script type="application/json" data-song>@json($line)</script>
    {{-- Every note, for the engraver: in the page, so the score reads nothing more (SongScore::notes). --}}
    <script type="application/json" data-score-song>@json($score['song'])</script>
    <div class="s-score-head">
        <button type="button" class="s-play" data-play aria-label="{{ __('pages.landing.hero.play') }}" data-label-play="{{ __('pages.landing.hero.play') }}" data-label-stop="{{ __('pages.landing.hero.stop') }}">
            <span class="s-i-play"><x-site.icon name="play" size="22" /></span><span class="s-i-stop"><x-site.icon name="stop" size="22" /></span>
        </button>
        <div class="s-score-now">
            <p><b id="song-mixer-title" data-score-hearing data-all="{{ trans_choice('pages.song.score.preview_all', $count, ['count' => $count]) }}" data-one="{{ __('pages.song.score.preview_one', ['part' => ':part']) }}">{{ trans_choice('pages.song.score.preview_all', $count, ['count' => $count]) }}</b><span class="s-score-time" data-time>0:00 / {{ $score['length'] }}</span></p>
            <span class="s-score-bar" aria-hidden="true"><i></i></span>
            <span class="s-mix-loading" data-loading-line hidden>{{ __('pages.landing.hero.loading') }}</span>
        </div>
    </div>
    <div class="s-score-sheet" translate="no">
        <div class="s-score-view" data-score-view @if ($staves === 1 && $count > 1) data-round @endif style="--staves: {{ $staves }}">
            @foreach ($line['parts'] as $i => $part)
                {{-- The staff's letter: hear this part alone, again for all of them (the mixer's own hear-alone). --}}
                <span @class(['s-score-voice', 's-is-you' => $part['key'] === $line['you']]) data-voice="{{ $part['key'] }}" data-name="{{ mb_strtolower($part['name']) }}" style="--i: {{ $staves === 1 ? 0 : $i }}">
                    <button type="button" class="bubble" data-hear-alone aria-pressed="false" aria-label="{{ __('pages.landing.hero.hear_only', ['part' => $part['name']]) }}" data-label="{{ __('pages.landing.hero.hear_only', ['part' => $part['name']]) }}" data-label-back="{{ __('pages.landing.hero.hear_all_back') }}">{{ mb_substr($part['name'], 0, 1) }}</button>
                </span>
            @endforeach
            <div class="s-score-systems" data-score-systems aria-hidden="true"></div>
        </div>
        <div class="s-score-foot">
            <span class="s-score-pager">
                <button type="button" data-score-prev aria-label="{{ __('pages.song.score.prev') }}" disabled><x-site.icon name="arrow" size="14" /></button>
                <span data-score-bars data-many="{{ __('pages.song.score.bars', ['from' => ':from', 'to' => ':to', 'count' => ':count']) }}" data-one="{{ __('pages.song.score.bar', ['from' => ':from', 'count' => ':count']) }}" aria-live="polite"></span>
                <button type="button" data-score-next aria-label="{{ __('pages.song.score.next') }}" disabled><x-site.icon name="arrow" size="14" /></button>
            </span>
            <button type="button" class="s-score-full-open" data-score-full aria-expanded="false" aria-controls="song-full-score">{{ __('pages.song.score.full') }} <x-site.icon name="arrow" size="13" /></button>
        </div>
    </div>
    <p class="s-score-dl"><span>{{ __('pages.song.score.download') }} <em>· {{ __('pages.song.score.download_free') }}</em></span>
        <span class="s-score-files">
            @foreach ($score['downloads'] as $file => $url)
                <a href="{{ $url }}" rel="nofollow" data-score-file="{{ $file }}">{{ __('pages.song.score.files.'.$file) }}</a>
            @endforeach
        </span>
    </p>
    {{-- What the score holds, in words: for a screen reader, and for anyone without the script. --}}
    <p class="sr-only">{{ $scoreAlt }}</p>
</div>
