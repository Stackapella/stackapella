{{-- One bar of the Sight-singing page's story (site/courses/sight-singing), its age's own way of writing where a note goes:
     Guido's four lines a third apart, the C line yellow and the F line red, each lettered; the singing schools' four
     syllables; the shape-note books' scale, fa sol la fa sol la mi fa, each syllable its own head; Curwen's letter sol-fa;
     Kodály's rhythm names over sol-fa letters; numbers beside syllables. The words are the page's `figures`. --}}
@if ($figure === 0)
    <div class="cp-ss-fig cp-ss-fig-guido" aria-hidden="true">
        <span class="cp-ss-gl cp-ss-gl-c"><b>{{ $figures['guido'][0] }}</b></span>
        <span class="cp-ss-gl"></span>
        <span class="cp-ss-gl cp-ss-gl-f"><b>{{ $figures['guido'][1] }}</b></span>
        <span class="cp-ss-gl"></span>
    </div>
@elseif ($figure === 1)
    <p class="cp-ss-fig cp-ss-fig-fasola" aria-hidden="true">@foreach ([0, 1, 2, 0, 1, 2, 3] as $n)<span>{{ $figures['fasola'][$n] }}</span>@endforeach</p>
@elseif ($figure === 2)
    <div class="cp-ss-fig cp-ss-fig-shapes" aria-hidden="true">
        <svg viewBox="0 -4 268 76" focusable="false">
            @foreach ([12, 22, 32, 42, 52] as $y)
                <rect x="0" y="{{ $y - 0.5 }}" width="262" height="1" class="cp-ss-faint" />
            @endforeach
            <use href="#ss-gClef" transform="translate(2 42)" class="cp-ss-ink" />
            @foreach (\App\Support\Pages\SightSingingPage::shapeScale() as $shape)
                @if ($shape['ledger'])
                    <rect x="{{ $shape['x'] - 10 }}" y="{{ $shape['y'] - 0.5 }}" width="20" height="1" class="cp-ss-ink" />
                @endif
                @if ($shape['shape'] === 'triangle')
                    <polygon points="{{ $shape['x'] - 6 }},{{ $shape['y'] + 5 }} {{ $shape['x'] + 6 }},{{ $shape['y'] + 5 }} {{ $shape['x'] + 6 }},{{ $shape['y'] - 5 }}" class="cp-ss-ink" />
                @elseif ($shape['shape'] === 'oval')
                    <ellipse cx="{{ $shape['x'] }}" cy="{{ $shape['y'] }}" rx="6.5" ry="4.6" transform="rotate(-20 {{ $shape['x'] }} {{ $shape['y'] }})" class="cp-ss-ink" />
                @elseif ($shape['shape'] === 'rect')
                    <rect x="{{ $shape['x'] - 6 }}" y="{{ $shape['y'] - 4.5 }}" width="12" height="9" class="cp-ss-ink" />
                @else
                    <polygon points="{{ $shape['x'] }},{{ $shape['y'] - 6 }} {{ $shape['x'] + 6.5 }},{{ $shape['y'] }} {{ $shape['x'] }},{{ $shape['y'] + 6 }} {{ $shape['x'] - 6.5 }},{{ $shape['y'] }}" class="cp-ss-ink" />
                @endif
            @endforeach
            <rect x="261" y="11.5" width="1.2" height="41" class="cp-ss-ink" />
        </svg>
    </div>
@elseif ($figure === 3)
    <p class="cp-ss-fig cp-ss-fig-curwen" aria-hidden="true">{{ $figures['curwen'] }}</p>
@elseif ($figure === 4)
    <p class="cp-ss-fig cp-ss-fig-kodaly" aria-hidden="true">@foreach ((array) $figures['kodaly'] as [$rhythm, $sol])<span><small>{{ $rhythm }}</small><i>{{ $sol }}</i></span>@endforeach</p>
@else
    <p class="cp-ss-fig cp-ss-fig-numbers" aria-hidden="true">@foreach ((array) $figures['numbers'] as $number)<span>{{ $number }}</span>@endforeach<span class="cp-ss-fig-rule"></span>@foreach ((array) $figures['syllables'] as $syllable)<i>{{ $syllable }}</i>@endforeach</p>
@endif
