{{-- The song's whole mix, every part together (Hillel: "they only need to visit to sing along"), once the server gives
     it ($song['mix']: url, type, and credits when it names them): a plain player that loads nothing until pressed, its
     height fixed so nothing moves, each real voice credited else the synthesized voices said, and beside it the way in:
     pick your part. With the hero's actions, never a row of its own. --}}
<div class="s-sg-mix">
    <div class="s-sg-mix-play">
        <audio controls preload="none" src="{{ $mix['url'] }}" @if (is_string($mix['type'] ?? null)) type="{{ $mix['type'] }}" @endif aria-label="{{ __('pages.song.mix_label', ['count' => $count]) }}"></audio>
        <p class="s-sg-mix-credit">{{ $credit }}</p>
    </div>
    <a href="#find" class="btn-secondary btn-lg s-sg-mix-cta">{{ __('pages.song.mix_cta') }}</a>
</div>
