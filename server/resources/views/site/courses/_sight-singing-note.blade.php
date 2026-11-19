{{-- One note of the Sight-singing page's Find do (site/courses/sight-singing): a button drawn as its note in every key (the
     key on show is the demo's data-key), Guido's yellow C line under it when it's middle C, and its name under it. --}}
<button type="button" class="cp-ss-note" data-ss-note="{{ $i }}" data-degree="{{ $note['degree'] }}" data-semis="{{ $note['semis'] }}">
    <svg viewBox="-34 20 68 140" aria-hidden="true" focusable="false">
        @foreach ($note['keys'] as $key => $at)
            <g class="cp-ss-for" data-for="{{ $key }}">
                @if ($at['ledger'])
                    <rect x="-17" y="{{ $at['y'] - 1.2 }}" width="34" height="2.4" class="cp-ss-c-line" />
                @endif
                <circle cx="0" cy="{{ $at['y'] }}" r="17" class="cp-ss-ring" />
                <rect x="{{ $at['stem']['x'] }}" y="{{ $at['stem']['y'] }}" width="1.9" height="{{ $at['stem']['h'] }}" class="cp-ss-stem" />
                <use href="#ss-noteheadBlack" transform="translate({{ $headX }} {{ $at['y'] }}) scale(1.6)" class="cp-ss-head" />
            </g>
        @endforeach
    </svg>
    <span class="sr-only">{{ strtr($demo['note_n'], [':n' => (string) ($i + 1)]) }}</span>
    <span class="cp-ss-name">{{ $names[$note['degree']] }}</span>
</button>
