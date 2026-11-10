{{-- The look's choices for a public page (the tools' brand row; docs/tools-plan.md, "Tool page frame"): Game mode,
     light or dark, and the four palettes, in the app's own words. The markup is here; js/public/theme-menu.js
     (mountThemeMenus, the frontend's) opens and closes it and calls theme-boot's choose(). A palette's swatch is its
     light and dark pages with its accent, named as the app names the pair. --}}
@props(['id' => 'theme-menu'])
@php($word = static fn (string $key): string => \App\Support\Site\AppWords::get($key))
@php($swatches = ['warm' => ['#faf5ee', '#17120f', '#b2357a'], 'stage' => ['#f0f0f0', '#000000', '#ff79da'], 'midnight' => ['#f1f5fb', '#0d1220', '#6cc4ff'], 'graphite' => ['#f3f3f1', '#151617', '#c99bff']])
<div class="s-tmenu">
    <button type="button" class="s-iconbtn" data-theme-menu-button aria-expanded="false" aria-controls="{{ $id }}" aria-label="{{ $word('appearance.theme') }}"><span class="s-light-only"><x-site.icon name="moon" size="18" /></span><span class="s-dark-only"><x-site.icon name="sun" size="18" /></span></button>
    <div id="{{ $id }}" class="s-tmenu-panel" data-theme-menu hidden role="group" aria-label="{{ $word('appearance.theme') }}">
        <x-site.game-toggle size="row" />
        <p class="s-tmenu-h">{{ $word('appearance.mode.title') }}</p>
        <div class="s-seg2">
            <button type="button" data-theme-mode="light" aria-pressed="false">{{ $word('appearance.mode.light') }}</button>
            <button type="button" data-theme-mode="dark" aria-pressed="false">{{ $word('appearance.mode.dark') }}</button>
        </div>
        <p class="s-tmenu-h">{{ $word('appearance.colour.titleBoth') }}</p>
        <div class="s-tmenu-swatches">
            @foreach ($swatches as $family => [$light, $dark, $accent])
                @php($name = str_replace(['{light}', '{dark}'], [$word('appearance.colour.'.$family.'.light'), $word('appearance.colour.'.$family.'.dark')], $word('appearance.colour.pair')))
                <button type="button" class="s-tmenu-sw" data-theme-colour="{{ $family }}" aria-pressed="false" aria-label="{{ $name }}"><span class="s-tmenu-chip" aria-hidden="true" style="--sw-l: {{ $light }}; --sw-d: {{ $dark }}; --sw-a: {{ $accent }}"></span><span class="s-tmenu-name" aria-hidden="true">{{ $name }}</span></button>
            @endforeach
        </div>
    </div>
</div>
