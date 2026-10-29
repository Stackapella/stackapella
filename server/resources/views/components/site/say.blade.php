{{-- A line that may differ by skin: a string, or ['game' => ..., 'serious' => ...] (lang/en/site.php),
     both in the page with the other skin's hidden (components/site/skin). --}}
@props(['text'])
@if (is_array($text))<x-site.skin :game="(string) ($text['game'] ?? '')" :serious="(string) ($text['serious'] ?? '')" />@else{{ $text }}@endif
