{{-- A collection's motif along the top edge of its panel or hero (its `key`; the same shapes as its link card,
     server/tool/link_cards.py THEMES): still, faint, never the left edge (the design review, 2026-11-11). The carols'
     are the lights (site._xmas-sky). Marks only, no drawn people. --}}
@if ($key === 'hymns')
    <svg class="s-tone-motif" viewBox="0 0 1200 120" preserveAspectRatio="xMaxYMin slice" aria-hidden="true" focusable="false">@foreach ([860, 950, 1040, 1130] as $x)<path d="M{{ $x }} 120 V58 Q{{ $x }} 26 {{ $x + 30 }} 14 Q{{ $x + 60 }} 26 {{ $x + 60 }} 58 V120" fill="none" stroke="currentColor" stroke-width="3"/>@endforeach</svg>
@elseif ($key === 'spirituals')
    <svg class="s-tone-motif" viewBox="0 0 1200 120" preserveAspectRatio="xMaxYMin slice" aria-hidden="true" focusable="false">@foreach ([0, 1, 2] as $k)<path d="M0 {{ 40 + $k * 18 }} C 200 {{ 22 + $k * 18 }}, 400 {{ 58 + $k * 18 }}, 600 {{ 40 + $k * 18 }} S 1000 {{ 22 + $k * 18 }}, 1200 {{ 40 + $k * 18 }}" fill="none" stroke="currentColor" stroke-width="2.5"/>@endforeach @foreach ([[880, 18], [960, 34], [1050, 12], [1130, 30]] as [$cx, $cy])<circle cx="{{ $cx }}" cy="{{ $cy }}" r="3" fill="currentColor"/>@endforeach</svg>
@elseif ($key === 'sea-shanties')
    <svg class="s-tone-motif" viewBox="0 0 1200 120" preserveAspectRatio="xMaxYMin slice" aria-hidden="true" focusable="false"><path d="M0 14 H1200" stroke="currentColor" stroke-width="5" stroke-dasharray="14 8"/>@foreach ([0, 1] as $k)<path d="M0 {{ 52 + $k * 22 }} @for ($j = 0; $j < 8; $j++)Q {{ 75 + $j * 150 }} {{ 36 + $k * 22 }} {{ 150 + $j * 150 }} {{ 52 + $k * 22 }} T {{ 300 + $j * 150 }} {{ 52 + $k * 22 }} @endfor" fill="none" stroke="currentColor" stroke-width="2.5"/>@endforeach</svg>
@elseif ($key === 'rounds')
    <svg class="s-tone-motif" viewBox="0 0 1200 120" preserveAspectRatio="xMaxYMin slice" aria-hidden="true" focusable="false">@foreach ([1, 2, 3, 4] as $k)<circle cx="{{ 1040 + $k * 22 }}" cy="{{ 10 + $k * 8 }}" r="{{ 70 - $k * 4 }}" fill="none" stroke="var(--color-part-{{ $k }})" stroke-width="7" stroke-dasharray="{{ round((70 - $k * 4) * 6.283 * (0.95 - $k * 0.18)) }} 2000"/>@endforeach</svg>
@elseif ($key === 'jewish-songs')
    {{-- Nine small flames along the top, the middle one raised: lights, as a menorah's are, never the lamp itself drawn. --}}
    <svg class="s-tone-motif" viewBox="0 0 1200 120" preserveAspectRatio="xMaxYMin slice" aria-hidden="true" focusable="false">@foreach (range(0, 8) as $k)@php($x = 900 + $k * 34)@php($y = $k === 4 ? 18 : 34)<path d="M{{ $x }} {{ $y + 26 }} C {{ $x - 8 }} {{ $y + 18 }}, {{ $x - 5 }} {{ $y + 8 }}, {{ $x }} {{ $y }} C {{ $x + 5 }} {{ $y + 8 }}, {{ $x + 8 }} {{ $y + 18 }}, {{ $x }} {{ $y + 26 }} Z" fill="currentColor"/>@endforeach</svg>
@elseif ($key === 'christmas')
    <div class="s-xlights" aria-hidden="true">@for ($i = 0; $i < 24; $i++)<i></i>@endfor</div>
@endif
