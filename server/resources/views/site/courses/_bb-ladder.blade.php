{{-- The Barbershop page's overtone ladder (site/courses/barbershop): one deep note's overtones, evenly
     spaced as they are (overtone n sits at n times the note), the barbershop seventh lit on rungs 4 to
     7 with its part names, and the spark of the fifth voice on rung 8. Drawn twice: whole beside the
     words on a big screen ('big'), and rungs 4 to 8 under the title on a phone ('mini'). --}}
<figure class="cp-bb2-ladder cp-bb2-ladder-{{ $size }}" data-bb-ladder>
    <svg viewBox="{{ $box }}" aria-hidden="true">
        @foreach (range(1, 8) as $n)
            @php($y = 560 - $n * 64)
            <g class="cp-bb2-rung{{ isset($parts[$n]) ? ' cp-bb2-rung-'.$parts[$n] : '' }}">
                <text x="30" y="{{ $y + 8 }}" class="cp-bb2-rung-n">{{ $n }}</text>
                @isset($parts[$n])
                    <path d="M64 {{ $y }} H380" class="cp-bb2-rung-line" />
                    <path d="M540 {{ $y }} H2400" class="cp-bb2-rung-off" />
                    <circle cx="380" cy="{{ $y }}" r="14" class="cp-bb2-note" />
                    <path d="M402 {{ $y - 12 }} a16 16 0 0 1 0 24 M412 {{ $y - 20 }} a26 26 0 0 1 0 40" class="cp-bb2-wave" />
                    <text x="436" y="{{ $y + 5 }}" class="cp-bb2-rung-part">{{ $names[$parts[$n]] }}</text>
                @else
                    <path d="M64 {{ $y }} H2400" class="cp-bb2-rung-off" />
                @endisset
            </g>
        @endforeach
        <g class="cp-bb2-spark"><circle cx="380" cy="48" r="40" /><path d="M380 -14 L389 39 L442 48 L389 57 L380 110 L371 57 L318 48 L371 39 Z" /></g>
        <path d="M64 496 V48" class="cp-bb2-rail" />
    </svg>
    @if ($size === 'big')
        <figcaption>{{ $love['ladder_note'] }}</figcaption>
    @endif
</figure>
