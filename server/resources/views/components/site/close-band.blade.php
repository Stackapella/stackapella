{{-- The five marketing pages' one closing band (docs/marketing-pages-cohesion-plan.md, piece 6; the design direction's
     "Closing band" and "Explore tile"; Hillel, 2026-11-14): one centred block (the rule for a page's closing call to
     action) under a warm stage light: the page's line with its key phrase in colour, Start singing to lesson 1, a caption
     true of what it says, then "Explore Stackapella", the other four pages, each a tile with its bubble, its name and its
     fact, its number bold (App\Support\Site\Explore). Game reads it as a CONTINUE? screen: PRESS START over the line,
     the label as CONTINUE? (x-site.skin), each tile's number as its big readout. `page` is one of Explore::PAGES; `href`
     is the page's way into lesson 1; `funnel` marks the landing's start for its funnel count. The landing passes its
     voice bars (and its own game kick) in the slot. --}}
@props(['page', 'href', 'funnel' => false])
@php($words = (array) __('pages.site.close.'.$page))
<section {{ $attributes->class(['s-ink', 's-close']) }} aria-labelledby="close-h">
    <div class="s-wrap s-close-in">
        @if ($slot->isEmpty())
            <p class="s-close-kick s-skin-g" aria-hidden="true">{{ __('pages.nav.game_wake') }}</p>
        @else
            {{ $slot }}
        @endif
        <h2 id="close-h"><span>{{ $words['title'] }}</span> <em class="s-hl-em">{{ $words['em'] }}</em></h2>
        <div class="s-close-act">
            <a href="{{ $href }}" class="btn-primary btn-lg" @if ($funnel) data-funnel-start @endif>{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a>
            <p>{{ $words['line'] }}</p>
        </div>
        <nav class="s-explore" aria-labelledby="explore-h">
            <p class="s-explore-h" id="explore-h"><x-site.skin heading :game="__('pages.site.explore.game')" :serious="__('pages.site.explore.title')" /></p>
            <ul>
                @foreach (\App\Support\Site\Explore::ways($page) as $way)
                    <li>
                        <a href="{{ $way['url'] }}" class="s-explore-way s-hub-{{ $way['key'] }}">
                            <span class="s-explore-ic bubble" aria-hidden="true"><x-site.icon :name="$way['icon']" size="16" /></span>
                            <b class="s-explore-name">{{ $way['name'] }}</b>
                            <span class="s-explore-go" aria-hidden="true"><x-site.icon name="arrow" size="16" /></span>
                            <span class="s-explore-hud" aria-hidden="true">{{ $way['readout'] }}</span>
                            <span class="s-explore-fact">{{ $way['before'] }}@if ($way['n'] !== null)<b class="s-n">{{ $way['n'] }}</b>@endif{{ $way['after'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>
    </div>
</section>
