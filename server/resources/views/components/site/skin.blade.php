{{-- Words that differ by skin (Hillel: "two skins for one app"): the game skin's and the serious
     skin's, both in the page, the other skin's hidden by <html data-skin> (input.css). The facts
     never differ; only the labels and the game features' words.
     `heading` (inside an h1 to h3): the serious words are the heading's only text, for search engines (the SEO audit:
     "Stage selectThe songbook"), kept for screen readers in the game skin too; the game skin's words are drawn by CSS
     from data-skin-text, so they're never in the text. --}}
@props(['game', 'serious', 'heading' => false])
@if ($heading)
<span class="s-skin-g" data-skin-text="{{ $game }}" aria-hidden="true"></span><span class="s-skin-s s-skin-sr">{{ $serious }}</span>
@else
<span class="s-skin-g">{{ $game }}</span><span class="s-skin-s">{{ $serious }}</span>
@endif
