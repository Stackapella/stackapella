{{-- The season's sky over a green band (the carols page's hero, /songs's carols band): lights and snow, CSS marks only. --}}
{{-- A string of still lights along the top: CSS marks, never images. --}}
<div class="s-xlights" aria-hidden="true">@for ($i = 0; $i < 24; $i++)<i></i>@endfor</div>
{{-- Snow falling across the hero, behind the words (Hillel: "add nice animated falling snow in the green
     header", "make it look like snow"): each flake its own place, size, speed and drift from a fixed
     sequence, far ones small, faint and slow, near ones big, bright and a little faster; paused off
     screen (js/public/carols.js), none under reduced motion. --}}
<div class="s-xsnow" aria-hidden="true" data-snow>@for ($i = 0; $i < 60; $i++)@php($depth = $i % 3)@php($r = fmod($i * 0.618034, 1))<i style="--x: {{ round(fmod($i * 37.3, 100), 1) }}%; --s: {{ [3, 5, 8][$depth] + ($i % 2) }}px; --o: {{ [0.55, 0.8, 0.95][$depth] }}; --d: {{ round([18, 14, 11][$depth] + $r * 3, 1) }}s; --w: -{{ round($r * 18, 1) }}s; --sway: {{ [6, 11, 16][$depth] + ($i % 4) }}px"></i>@endfor</div>
