{{-- The public site's top bar (Hillel's hero mockup; landing v2, docs/landing-v2-plan.md): the mark and name home,
     Courses and Tools (Hillel, 2026-11-07: "make the tools and courses pages more reachable from the main page"), For
     choirs and Pricing (How it works is the landing's next section and in the footer; Help is in the footer and the
     phone menu), Game mode on or off, the light and dark switch, and Open the app; on phones the links and the switch
     are in the menu. `appUrl` is the
     app's address (the landing's carries ?ref=); not `app`, which views already have. `home` is where the logo leads (a
     tool page's tags it, ?ref=tool-<tool>), and `themeToggle` false leaves out light and dark where the page has the
     tools' theme menu instead (Hillel, 2026-11-08: "when using a tool make sure we show the header"). --}}
@props(['appUrl' => \App\Support\Http\Hosts::app('/'), 'home' => '/', 'themeToggle' => true, 'hat' => request()->is(ltrim(\App\Support\Pages\Carols::PATH, '/')) && \App\Support\Pages\Carols::countdown() !== null])
{{-- Off the nexus there's no landing or pricing page (docs/branding-matrix.md): only Help. --}}
@php($nav = config('app.is_nexus', true) ? ['courses' => '/courses', 'tools' => \App\Support\Site\SitePages::toolsHome(), 'for_choirs' => '/for/choir-directors', 'pricing' => '/pricing'] : ['help' => '/help'])
<header class="s-wrap s-top">
    {{-- The mark's Santa hat (Icons::hat): on when `hat` (by default the carols page, 1 November to Christmas Day),
         else only on the visitor's own Christmas Eve and Day (js/public/season.js). Before the mark, so the mark stays the wordmark's neighbour
         (tests/flows/logo_match.py measures it there). --}}
    <a href="{{ $home }}" class="s-brand">{{ \App\Support\Site\Icons::hat($hat) }}{{ \App\Support\Site\Icons::mark(30) }}<span class="s-word">{{ __('meta.brand') }}</span></a>
    <nav class="s-nav" aria-label="{{ __('meta.brand') }}">
        @foreach ($nav as $key => $path)
            <a href="{{ $path }}" @if (request()->is(ltrim($path, '/').'*')) aria-current="page" @endif>{{ __('pages.nav.'.$key) }}</a>
        @endforeach
    </nav>
    {{-- Game mode on or off (Hillel: "two skins for one app"): the same choice as the app's. --}}
    <x-site.game-toggle size="pill" />
    @if ($themeToggle)
        <button type="button" class="s-iconbtn" data-theme-toggle aria-label="{{ __('pages.nav.theme') }}"><span class="s-light-only"><x-site.icon name="moon" size="18" /></span><span class="s-dark-only"><x-site.icon name="sun" size="18" /></span></button>
    @endif
    <a href="{{ $appUrl }}" class="btn-secondary">{{ __('pages.nav.open_app') }}</a>
    <details class="s-menu">
        <summary class="s-iconbtn" aria-label="{{ __('pages.nav.menu') }}"><x-site.icon name="menu" size="18" /></summary>
        <nav class="s-menu-panel" aria-label="{{ __('pages.nav.menu') }}">
            @foreach ([...$nav, 'help' => '/help'] as $key => $path)
                <a href="{{ $path }}">{{ __('pages.nav.'.$key) }}</a>
            @endforeach
            <x-site.game-toggle size="row" />
            @if ($themeToggle)
                <button type="button" data-theme-toggle><span class="s-light-only"><x-site.icon name="moon" size="18" /></span><span class="s-dark-only"><x-site.icon name="sun" size="18" /></span>{{ __('pages.nav.theme') }}</button>
            @endif
        </nav>
    </details>
</header>
