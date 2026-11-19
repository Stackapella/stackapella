{{-- One of Counterpoint's years (site/courses/counterpoint's history): the year, a line for every voice
     the music asks for (Tallis's forty are the full-width spread above it instead), what happened and its
     notes. Takes $beat ([when, what happened, its sources, players]), $love and $cp. --}}
@php([$when, $text, $cites, $players] = $beat)
<li @class(['cp-cp2-year', 'cp-cp2-year-forty' => $players === 40])>
    <p class="cp-cp2-when">{{ $when }}</p>
    <div class="cp-cp2-year-in">
        <p class="cp-cp2-band-row">
            @if ($players !== 40)
                <svg viewBox="0 0 240 {{ $players * 6 + 4 }}" class="cp-cp2-band" aria-hidden="true">
                    @foreach ($cp::band($players) as $voice)
                        <g class="{{ $voice['voice'] }}"><path d="M{{ $voice['x'] }} {{ $voice['y'] }} H240" class="cp-cp2-thread" /><rect x="{{ $voice['x'] - 2.5 }}" y="{{ $voice['y'] - 2.5 }}" width="5" height="5" class="cp-cp2-dot" /></g>
                    @endforeach
                </svg>
            @endif
            <span class="cp-cp2-players"><x-site.skin :game="str_replace(':n', (string) $players, $love['history']['players'])" :serious="str_replace(':n', (string) $players, $love['history']['voices_n'])" /></span>
        </p>
        <p class="cp-cp2-what">{{ $text }}@include('site.courses._cites', ['keys' => $cites])</p>
        @if ($players === 40)
            <p class="cp-cp2-small">{{ $love['history']['forty'] }}</p>
        @endif
    </div>
</li>
