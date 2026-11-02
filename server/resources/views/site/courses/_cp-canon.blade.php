{{-- Counterpoint's canon (site/courses/counterpoint): Row, Row, Row Your Boat as a round for three,
     drawn as points (one a note) joined by threads, a voice a colour, each voice entering two bars
     after the last, 12px a step of the scale. The tune is the one source for the sound too:
     js/public/counterpoint.js reads data-tune ([start, length, semitones above do], in eighths of a
     6/8 bar) and data-eighth (seconds), and lights each point (its data-at and data-len, in seconds
     from the round's start) and moves the playhead from the audio clock as it plays. --}}
@php($tune = [[0, 3, 0], [3, 3, 0], [6, 2, 0], [8, 1, 2], [9, 3, 4], [12, 2, 4], [14, 1, 2], [15, 2, 4], [17, 1, 5], [18, 6, 7], [24, 1, 12], [25, 1, 12], [26, 1, 12], [27, 1, 7], [28, 1, 7], [29, 1, 7], [30, 1, 4], [31, 1, 4], [32, 1, 4], [33, 1, 0], [34, 1, 0], [35, 1, 0], [36, 2, 7], [38, 1, 5], [39, 2, 4], [41, 1, 2], [42, 6, 0]])
@php($eighth = 0.18)
{{-- Height on the thread by step of the scale (do, re, mi, fa, so, and high do), not by semitone. --}}
@php($step = [0 => 0, 2 => 1, 4 => 2, 5 => 3, 7 => 4, 12 => 7])
@php($u = 14)
<svg viewBox="0 0 1072 364" class="cp-cp2-canon-svg" aria-hidden="true" data-tune="{{ json_encode($tune) }}" data-eighth="{{ $eighth }}" data-x0="46" data-u="{{ $u }}">
    @foreach (range(0, 12) as $bar)
        <path d="M{{ 46 + $bar * 6 * $u }} 12 V352" class="cp-cp2-bar" />
    @endforeach
    @foreach ([0, 1, 2] as $v)
        @php($base = 110 + $v * 118)
        @php($from = 46 + $v * 12 * $u)
        <g class="cp-cp2-line cp-cp2-v{{ $v + 1 }}" data-line="{{ $v + 1 }}">
            <text x="6" y="{{ $base - 30 }}" class="cp-cp2-vn">{{ $love['voices'][$v] }}</text>
            <path d="M{{ $from }} {{ $base + 14 }} H{{ $from + 48 * $u }}" class="cp-cp2-staff" />
            <polyline points="{{ implode(' ', array_map(fn (array $n): string => ($from + $n[0] * $u).','.($base - $step[$n[2]] * 12), $tune)) }}" class="cp-cp2-thread" />
            @foreach ($tune as [$at, $length, $semis])
                <circle cx="{{ $from + $at * $u }}" cy="{{ $base - $step[$semis] * 12 }}" r="{{ $length >= 3 ? 4.6 : 3.9 }}" class="cp-cp2-dot" data-at="{{ round(($v * 12 + $at) * $eighth, 3) }}" data-len="{{ round($length * $eighth, 3) }}" />
            @endforeach
        </g>
    @endforeach
    <path d="M46 8 V356" class="cp-cp2-head" data-cp-head />
</svg>
