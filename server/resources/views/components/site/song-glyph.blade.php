{{-- A song's small glyph: a bar per part, placed where its range sits on the song's scale (low to
     the start, high to the end), so the picture says something true about the song. --}}
@props(['ranges'])
<span class="s-stackglyph s-rangeglyph" aria-hidden="true">@foreach ($ranges['parts'] as $part)<b class="s-v{{ ($part['color'] - 1) % 4 + 1 }}" style="margin-inline-start: {{ $part['glyphStart'] }}%; width: {{ $part['glyphWidth'] }}%"></b>@endforeach</span>
