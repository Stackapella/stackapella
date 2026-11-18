{{-- Counterpoint's canon (site/courses/counterpoint): Row, Row, Row Your Boat as a round for three, in
     the page's early notation: square notes on a red four-line staff a voice, a voice a colour, each
     voice entering two bars after the last (App\Support\Pages\CounterpointPage::canon, which places
     them). A thin bar after a note holds it. The tune is the one source for the sound too:
     js/public/counterpoint.js reads data-tune ([start, length, semitones above do], in eighths of a
     6/8 bar) and data-eighth (seconds), and lights each note (its data-at and data-len, in seconds
     from the round's start) and moves the playhead from the audio clock as it plays. --}}
<svg viewBox="0 0 1072 344" class="cp-cp2-canon-svg" aria-hidden="true" data-tune="{{ json_encode(\App\Support\Pages\CounterpointPage::TUNE) }}" data-eighth="{{ \App\Support\Pages\CounterpointPage::EIGHTH }}" data-x0="{{ \App\Support\Pages\CounterpointPage::X0 }}" data-u="{{ \App\Support\Pages\CounterpointPage::U }}">
    @foreach (\App\Support\Pages\CounterpointPage::canon() as $line)
        <g class="cp-cp2-line cp-cp2-v{{ $line['n'] }}" data-line="{{ $line['n'] }}">
            @foreach ($line['lines'] as $y)
                <path d="M46 {{ $y }} H1066" class="cp-cp2-staff" />
            @endforeach
            <text x="6" y="{{ $line['label'] }}" class="cp-cp2-vn">{{ $love['voices'][$line['n'] - 1] }}</text>
            @foreach ($line['notes'] as $note)
                @if ($note['hold'] !== null)
                    <path d="M{{ $note['x'] + 6 }} {{ $note['y'] }} H{{ $note['hold'] }}" class="cp-cp2-hold" />
                @endif
                <rect x="{{ $note['x'] - 5 }}" y="{{ $note['y'] - 5 }}" width="10" height="10" rx="1" class="cp-cp2-dot" data-at="{{ $note['at'] }}" data-len="{{ $note['len'] }}" />
            @endforeach
        </g>
    @endforeach
    <path d="M46 8 V340" class="cp-cp2-head" data-cp-head />
</svg>
