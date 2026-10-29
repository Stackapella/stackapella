{{-- On the way: every planned feature (config/site.php) as a chip linking to its page: its name while
     it's coming, its open line ("Explore the gallery") once its flag opens it; and What's new. No
     "notify me" (that would be email, which needs Hillel's OK). The section goes when all are open. --}}
@if (collect($way)->contains('soon', true))
    <section class="s-wrap s-sec" aria-labelledby="way-h">
        <div class="s-headrow">
            <div>
                <p class="s-kick"><x-site.skin :game="__('pages.landing.game.way_kick')" :serious="__('pages.landing.way_title')" /></p>
                <h2 id="way-h" class="s-title" style="font-size: clamp(28px, 6vw, 40px)">{{ __('pages.landing.way_lead') }}</h2>
            </div>
            <a href="/whats-new" class="s-arrow">{{ __('pages.landing.way_all') }} <x-site.icon name="arrow" size="16" /></a>
        </div>
        <ul class="s-chips">
            @foreach ($way as $item)
                <li><a href="{{ $item['href'] }}" @class(['s-chip-open' => ! $item['soon']])><span class="s-who-ic" aria-hidden="true"><x-site.icon :name="$item['icon']" size="16" /></span>{{ __('pages.landing.way.'.$item['key'].'.'.($item['soon'] ? 0 : 2)) }}</a></li>
            @endforeach
        </ul>
    </section>
@endif
