{{-- Feature tiles: each its drawing of the app (components/site/pic), name and one line, with the
     quiet label while it's coming. `$wide` lists the slugs that take two columns on a wide screen;
     `$bento` lays them out as the features page's bento instead (Hillel's Features design). --}}
<ul @class(['s-tiles', 's-bento7' => $bento ?? false])>
    @foreach ($tiles as $tile)
        <li @class(['s-tile-wide' => in_array($tile['slug'], $wide ?? [], true)])>
            <a href="{{ $tile['url'] }}" class="s-tile">
                <x-site.pic :name="$tile['pic']" :hero="$draw['hero']" :pics="$draw['pics']" />
                <b>{{ $tile['name'] }} <x-site.icon name="arrow" size="16" /></b>
                @if ($tile['soon'])
                    <x-site.soon />
                @endif
                <span>{{ $tile['summary'] }}</span>
                {{-- Choir groups need Plus (pricing's With others), said where the tile says it works. --}}
                @if ($tile['slug'] === 'choirs' && ! $tile['soon'])
                    <span class="s-tile-plus"><i>{{ __('pages.site.with_plus') }}</i>@unless (\App\Support\Pages\Landing::plans()['checkoutOpen']) {{ __('pages.site.plus_not_open') }}@endunless</span>
                @endif
            </a>
        </li>
    @endforeach
</ul>
