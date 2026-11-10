{{-- One plain line to the Christmas carols page, in the carol season (November to Twelfth Night) while a carol is
     open (App\Support\Pages\Carols::linked), from the pages search already sends choir singers to. `line`: the page's
     own words (site.carols.links). --}}
@if (\App\Support\Pages\Carols::linked())
    <p class="s-carols-link"><a href="{{ \App\Support\Pages\Carols::PATH }}" class="s-lk">{{ $line }} <x-site.icon name="arrow" size="16" /></a></p>
@endif
