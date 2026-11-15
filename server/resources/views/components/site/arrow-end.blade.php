{{-- A link's words and its arrow, the last word and the arrow on one line however the line wraps (Icons::after). --}}
@props(['text', 'size' => 16, 'spaced' => false])
{{ \App\Support\Site\Icons::after((string) $text, 'arrow', (int) $size, (bool) $spaced) }}
