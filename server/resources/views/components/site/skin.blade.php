{{-- Words that differ by skin (Hillel: "two skins for one app"): the game skin's and the serious
     skin's, both in the page, the other skin's hidden by <html data-skin> (input.css). The facts
     never differ; only the labels and the game features' words. --}}
@props(['game', 'serious'])
<span class="s-skin-g">{{ $game }}</span><span class="s-skin-s">{{ $serious }}</span>
