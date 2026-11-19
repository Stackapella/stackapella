{{-- The Barbershop page's overtone ladder (site/courses/barbershop), in an arched shop window with its
     gold sign: one deep note's overtones, evenly spaced as they are (overtone n sits at n times the
     note), the barbershop seventh lit on rungs 4 to 7 with its part names, rung 1 the root, and on
     rung 8 the star of the fifth voice, which lights while the chord rings (Hear it ring). --}}
<figure class="cp-bb2-ladder" data-bb-ladder>
    <div class="cp-bb2-window">
        <p class="cp-bb2-window-sign">{{ $love['ladder']['title'] }}</p>
        <p class="cp-bb2-window-sub">{{ $love['ladder']['sub'] }}</p>
        <svg viewBox="0 0 420 340" aria-hidden="true">
            @foreach (range(1, 8) as $n)
                @php($y = 324 - ($n - 1) * 42)
                <g class="cp-bb2-rung{{ isset($parts[$n]) ? ' cp-bb2-rung-'.$parts[$n] : '' }}">
                    <text x="22" y="{{ $y + 9 }}" class="cp-bb2-rung-n">{{ $n }}</text>
                    @isset($parts[$n])
                        <path d="M60 {{ $y }} H270" class="cp-bb2-rung-line" />
                        <circle cx="270" cy="{{ $y }}" r="11" class="cp-bb2-note" />
                        <path d="M288 {{ $y - 9 }} a12 12 0 0 1 0 18" class="cp-bb2-wave" />
                        <text x="300" y="{{ $y + 5 }}" class="cp-bb2-rung-part">{{ $names[$parts[$n]] }}</text>
                    @else
                        <path d="M60 {{ $y }} H270" class="cp-bb2-rung-off" />
                    @endisset
                    @if ($n === 1)
                        <text x="290" y="{{ $y + 5 }}" class="cp-bb2-rung-root">{{ $love['ladder']['root'] }}</text>
                    @endif
                </g>
            @endforeach
            <g class="cp-bb2-spark"><circle cx="270" cy="30" r="24" /><path d="M270 6 L275 25 L294 30 L275 35 L270 54 L265 35 L246 30 L265 25 Z" /></g>
            <text x="300" y="35" class="cp-bb2-fifth">{{ $love['ladder']['fifth'] }}</text>
        </svg>
    </div>
    <figcaption>{{ $love['ladder_note'] }}</figcaption>
</figure>
