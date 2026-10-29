{{-- The public site's top bar (Hillel's hero mockup): the mark and name home, How it works (its own
     page, as Pricing and Help are), Pricing and Help, Game mode on or off, the light and dark switch, and Open the app; on phones the links and the switch are in the menu. `appUrl` is the
     app's address (the landing's carries ?ref=); not `app`, which views already have. --}}
@props(['appUrl' => \App\Support\Http\Hosts::app('/')])
{{-- Off the nexus there's no landing or pricing page (docs/branding-matrix.md): only Help. --}}
@php($nav = config('app.is_nexus', true) ? ['how' => '/how-it-works', 'pricing' => '/pricing', 'help' => '/help'] : ['help' => '/help'])
<header class="s-wrap s-top">
    <a href="/" class="s-brand">{{ \App\Support\Site\Icons::mark(30) }}<span class="s-word">{{ __('meta.brand') }}</span></a>
    <nav class="s-nav" aria-label="{{ __('meta.brand') }}">
        @foreach ($nav as $key => $path)
            <a href="{{ $path }}" @if (request()->is(ltrim($path, '/').'*')) aria-current="page" @endif>{{ __('pages.nav.'.$key) }}</a>
        @endforeach
    </nav>
    {{-- Game mode on or off (Hillel: "two skins for one app"): the same choice as the app's. --}}
    <x-site.game-toggle size="pill" />
    <button type="button" class="s-iconbtn" data-theme-toggle aria-label="{{ __('pages.nav.theme') }}"><span class="s-light-only"><x-site.icon name="moon" size="18" /></span><span class="s-dark-only"><x-site.icon name="sun" size="18" /></span></button>
    <a href="{{ $appUrl }}" class="btn-secondary">{{ __('pages.nav.open_app') }}</a>
    <details class="s-menu">
        <summary class="s-iconbtn" aria-label="{{ __('pages.nav.menu') }}"><x-site.icon name="menu" size="18" /></summary>
        <nav class="s-menu-panel" aria-label="{{ __('pages.nav.menu') }}">
            @foreach ($nav as $key => $path)
                <a href="{{ $path }}">{{ __('pages.nav.'.$key) }}</a>
            @endforeach
            <x-site.game-toggle size="row" />
            <button type="button" data-theme-toggle><span class="s-light-only"><x-site.icon name="moon" size="18" /></span><span class="s-dark-only"><x-site.icon name="sun" size="18" /></span>{{ __('pages.nav.theme') }}</button>
        </nav>
    </details>
</header>
