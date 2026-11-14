{{-- A hub's eyebrow (the design direction's "Hub hero"; Hillel, 2026-11-14): the four voice dots, then the page and its
     count, the number bold ("SONGBOOK · 110 SONGS"), muted, never pink in calm, on one line. `words` holds `:count`;
     `game` is game's own wording where it has one (the same count after it). --}}
@props(['words', 'count', 'game' => null])
@php($split = static function (string $text) use ($count): array {
    $mark = "\u{E000}";
    $line = str_replace(':count', $mark, $text);
    [$before, $after] = str_contains($line, $mark) ? explode($mark, $line, 2) : [$line, ''];
    return [$before, (string) $count, $after];
})
@php([$b, $n, $a] = $split($words))
<p {{ $attributes->class(['s-eyebrow', 's-hub-eyebrow']) }}><span class="s-hub-dots" aria-hidden="true"><b class="s-v1"></b><b class="s-v2"></b><b class="s-v3"></b><b class="s-v4"></b></span>@if ($game !== null)@php([$gb, $gn, $ga] = $split($game))<span class="s-skin-g">{{ $gb }}<b class="s-n">{{ $gn }}</b>{{ $ga }}</span><span class="s-skin-s">{{ $b }}<b class="s-n">{{ $n }}</b>{{ $a }}</span>@else<span>{{ $b }}<b class="s-n">{{ $n }}</b>{{ $a }}</span>@endif</p>
