{{-- The public site's top bar (Hillel's hero mockup; landing v2, docs/landing-v2-plan.md): the mark and name home,
     Songs, Courses, Tools and Pricing (Hillel, 2026-11-12: "change the header options to songs courses tools pricing";
     For choirs is led to from the pages a choir director reads, each with ?ref=for-choirs-<place>; How it works is the landing's next section and in the footer; Help is in the footer and the
     phone menu), Game mode on or off, Language and appearance (one pill: x-site.look-menu), and Open the app; on phones
     the links are in the menu and Game mode in Language and appearance's. `appUrl` is the
     app's address (the landing's carries ?ref=); not `app`, which views already have. `home` is where the logo leads (a
     tool page's tags it, ?ref=tool-<tool>), and `themeToggle` false leaves light and dark out of Language and appearance
     (Hillel, 2026-11-08: "when using a tool make sure we show the header"). --}}
@props(['appUrl' => \App\Support\Http\Hosts::app('/'), 'home' => '/', 'themeToggle' => true, 'hat' => request()->is(ltrim(\App\Support\Pages\Carols::PATH, '/')) && \App\Support\Pages\Carols::countdown() !== null])
{{-- Off the nexus there's no landing or pricing page (docs/branding-matrix.md): only Help. --}}
@php($nav = config('app.is_nexus', true) ? ['songs' => '/songs', 'courses' => '/courses', 'tools' => \App\Support\Site\SitePages::toolsHome(), 'pricing' => '/pricing'] : ['help' => '/help'])
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
    {{-- Game mode on or off (Hillel: "two skins for one app"): the same choice as the app's. From 900 px; on a phone it's
         in Language and appearance's menu. --}}
    <x-site.game-toggle size="pill" />
    {{-- Language and appearance, one pill (Hillel, 2026-11-16: "combine the two buttons"), between Game mode and Open the
         app at every width: the languages while more than English is open, Light, Dark or Auto, and Game mode on a phone.
         Its links name their language (hreflang), so a page in a language never rewrites them into its own. --}}
    <x-site.look-menu :appearance="$themeToggle" />
    {{-- The bar's one primary (Hillel's open source design, 2026-11-12: the dark pill, not a grey one beside Game mode). On a
         page in another language, the app opens in it too (?lang, which the app reads). --}}
    <a href="{{ app()->getLocale() === 'en' ? $appUrl : $appUrl.(str_contains($appUrl, '?') ? '&' : '?').'lang='.app()->getLocale() }}" class="btn-primary">{{ __('pages.nav.open_app') }}</a>
    <details class="s-menu">
        <summary class="s-iconbtn" aria-label="{{ __('pages.nav.menu') }}"><x-site.icon name="menu" size="18" /></summary>
        <nav class="s-menu-panel" aria-label="{{ __('pages.nav.menu') }}">
            @foreach ([...$nav, 'help' => '/help'] as $key => $path)
                <a href="{{ $path }}">{{ __('pages.nav.'.$key) }}</a>
            @endforeach
        </nav>
    </details>
</header>
