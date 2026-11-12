{{-- The public site's top bar (Hillel's hero mockup; landing v2, docs/landing-v2-plan.md): the mark and name home,
     Songs, Courses, Tools and Pricing (Hillel, 2026-11-12: "change the header options to songs courses tools pricing";
     For choirs is led to from the pages a choir director reads, each with ?ref=for-choirs-<place>; How it works is the landing's next section and in the footer; Help is in the footer and the
     phone menu), Game mode on or off, the light and dark switch, and Open the app; on phones the links and the switch
     are in the menu. `appUrl` is the
     app's address (the landing's carries ?ref=); not `app`, which views already have. `home` is where the logo leads (a
     tool page's tags it, ?ref=tool-<tool>), and `themeToggle` false leaves out light and dark where the page has the
     tools' theme menu instead (Hillel, 2026-11-08: "when using a tool make sure we show the header"). --}}
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
    {{-- Game mode on or off (Hillel: "two skins for one app"): the same choice as the app's. --}}
    <x-site.game-toggle size="pill" />
    @if ($themeToggle)
        <button type="button" class="s-iconbtn" data-theme-toggle aria-label="{{ __('pages.nav.theme') }}"><span class="s-light-only"><x-site.icon name="moon" size="18" /></span><span class="s-dark-only"><x-site.icon name="sun" size="18" /></span></button>
    @endif
    {{-- The language menu (LanguageMenu; Hillel, 2026-11-12: "Header switcher"): only while more than English is open,
         between light and dark and Open the app (Hillel, 2026-11-12: "move the lang selector to between the light/dark
         toggle and open the app buttons"; on a phone, and on tool pages, light and dark is in the menu or the tool bar). Its
         links name their language (hreflang), so a page in a language never rewrites them into its own. --}}
    @php($languages = \App\Support\Languages\LanguageMenu::for(request()))
    @if ($languages !== null)
        @php($here = collect($languages['choices'])->firstWhere('current', true))
        <details class="s-lang" data-lang-menu>
            <summary class="s-langbtn" aria-label="{{ __('pages.nav.language', ['language' => $here['name'] ?? '']) }}"><x-site.icon name="globe" size="18" /><span aria-hidden="true">{{ strtoupper($languages['current']) }}</span></summary>
            <ul class="s-lang-panel" role="list">
                @foreach ($languages['choices'] as $choice)
                    <li><a href="{{ $choice['url'] }}" hreflang="{{ $choice['code'] }}" lang="{{ $choice['code'] }}" data-lang-choice="{{ $choice['code'] }}" @if ($choice['current']) aria-current="true" @endif>{{ $choice['name'] }}</a></li>
                @endforeach
            </ul>
        </details>
        <script type="module" src="/js/public/lang-menu.js"></script>
    @endif
    {{-- The bar's one primary (Hillel's open source design, 2026-11-12: the dark pill, not a grey one beside Game mode). On a
         page in another language, the app opens in it too (?lang, which the app reads). --}}
    <a href="{{ app()->getLocale() === 'en' ? $appUrl : $appUrl.(str_contains($appUrl, '?') ? '&' : '?').'lang='.app()->getLocale() }}" class="btn-primary">{{ __('pages.nav.open_app') }}</a>
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
