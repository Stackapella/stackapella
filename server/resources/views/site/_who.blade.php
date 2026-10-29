{{-- "Who it's for": each "for" page, as a glass chip on the landing or (`$whoCards`, Hillel's Features
     design) a card with its one line on /features; coaches marked Coming soon while coaching is. --}}
<section class="s-wrap s-sec" aria-labelledby="who-h">
    <p class="s-kick"><x-site.skin :game="__('pages.landing.game.who_kick')" :serious="__('pages.site.who')" /></p>
    <h2 id="who-h" class="s-title">{{ __('pages.site.who_lead') }}</h2>
    @if ($whoCards ?? false)
        <ul class="s-who">
            @foreach ($who as $page)
                <li>
                    <a href="{{ $page['url'] }}">
                        <span class="s-who-ic s-v{{ $loop->index % 4 + 1 }}" aria-hidden="true"><x-site.icon :name="$page['icon']" size="18" /></span>
                        <b>{{ $page['name'] }}</b>
                        <span>{{ $page['summary'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @else
    <ul class="s-chips">
        @foreach ($who as $page)
            <li>
                <a href="{{ $page['url'] }}">
                    <span class="s-who-ic s-v{{ $loop->index % 4 + 1 }}" aria-hidden="true"><x-site.icon :name="$page['icon']" size="18" /></span>
                    {{ $page['name'] }}
                    @php($tag = __('pages.landing.game.who_tags.'.basename($page['url'])))
                    @if ($page['url'] === '/for/coaches' && \App\Support\Site\SitePages::soon('coaching'))<x-site.soon />@elseif (in_array($tag, ['solo', 'coop'], true))<span class="s-tag s-skin-g">{{ __('pages.landing.game.'.$tag) }}</span>@endif
                </a>
            </li>
        @endforeach
    </ul>
    @endif
</section>
