{{-- The public pages' frame (the frontend's; contract/api.md "Public pages"): no session, no
     cookies, the app's styles and fonts, scripts only from 'self' (never inline). A page sets
     `title`, its <head> extras in @section('head'), its body in @section('content'), and its
     script in @section('scripts'). --}}
<!DOCTYPE html>
{{-- A page on the app's arcade screen (data-gs: the same layer and tokens as the app's game mode; it shows only in
     the game skin): the landing, How it works, Pricing and Help (Hillel, 2026-10-31), the songs (/songs, each song's
     page, its parts, the carols) and the comparisons (game's arcade pass, 2026-11-10), About, the "for" pages, the
     guides and What's new (the next pass, 2026-11-10), and any page that opts in with @section('arcade'). A calm page
     among them (a help article, a guide) keeps its reading type under the arcade header. The legal pages stay off it
     (docs/decisions.md). A page in another language (/es/guides/...) is matched by its English path, so it takes the
     same screen (it didn't: game mode's Spanish pages had the arcade header but not its screen). --}}
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ __('meta.dir') }}" @if (\Illuminate\Support\Str::is(['/', 'how-it-works', 'pricing', 'help', 'help/*', 'features', 'features/*', 'courses', 'courses/*', 'songs', 'songs/*', 'compare', 'compare/*', 'about', 'for/*', 'guides', 'guides/*', 'whats-new'], ltrim(\App\Support\Languages\SiteLanguages::englishPath(request()->getPathInfo(), app()->getLocale()), '/') ?: '/') || $__env->hasSection('arcade')) data-gs @endif>
<head>
    <meta charset="utf-8">
    {{-- Every public page's Open Graph locale (the Spanish parity pass, 2026-11-13: only the landing, courses and tools had
         one); the other languages' are added beside its hreflang links (SiteLanguage). --}}
    <meta property="og:locale" content="{{ \App\Support\Languages\SiteLanguages::ogLocale(app()->getLocale()) }}">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    {{-- Kept out of search until the launch (SITE_INDEXING); a page can say so for itself (@section('robots')). --}}
    @hasSection('robots')
        @yield('robots')
    @elseif (! config('ops.indexing'))
        <meta name="robots" content="noindex">
    @endif
    <title>{{ $title }}</title>
    <link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
    {{-- Figtree for reading; Bungee (headlines) and Stackapella Pixel (small HUD labels) for the site's arcade look (Hillel,
         2026-10-29: "love the 90's redesign"), all from our own address (css/input.css). Figtree's Latin half comes with
         the page; the others load as a page uses them. --}}
    <link rel="preload" as="font" type="font/woff2" href="/fonts/figtree-latin.woff2" crossorigin>
    <meta name="theme-color" content="#17120f">
    {{-- The theme the person chose anywhere (mode, colour and accent), before the page paints: shared
         with the app's host by a cookie on this domain (hosts.pass_domain; host-only without it). --}}
    @if (config('hosts.pass_domain'))
        <meta name="theme-cookie-domain" content="{{ config('hosts.pass_domain') }}">
    @endif
    {{-- Each file asked for with its content's hash (?v=): nginx keeps this build's exact address a year, in the browser
         and at the edge, as its bytes can't change under it; a new deploy's file has a new address
         (App\Support\Http\StaticVersions, which also writes nginx's map). --}}
    @php($web = (string) config('app.web_path'))
    {{-- The page's family's sheets (docs/css-split-plan.md, step 4): site.css and site-game.css less the rules no page of
         its family can use (the landing, the songs, the tools, the reference pages, the courses, the rest), in their own
         order, /site-css/<family>.css; the whole site.css and site-game.css when the page shows a class its family's
         manifest lacks (App\Support\Site\SiteCss). Read from what the page renders, as the course sheet is.
         Game mode's own sheet (step 3): the game skin's rules, fetched only in game mode (theme-boot.js preloads it) and
         linked right after the page's sheet by game-css.js, so serious mode never loads them and game mode never paints
         without them. --}}
    @php($siteCss = \App\Support\Site\SiteCss::urls($web, '/'.ltrim(request()->path(), '/'), $__env->yieldContent('content').$__env->yieldContent('head')))
    <meta name="css-family" content="{{ \App\Support\Site\SiteCss::family('/'.ltrim(request()->path(), '/')) }}">
    <meta name="game-css" content="{{ $siteCss[1] }}">
    <script src="{{ \App\Support\Http\StaticVersions::url($web, '/js/theme-boot.js') }}"></script>
    <link rel="stylesheet" href="{{ $siteCss[0] }}">
    <script src="{{ \App\Support\Http\StaticVersions::url($web, '/js/game-css.js') }}"></script>
    {{-- The course pages' own rules (.cp-*), only where the page shows one (a course page, or a course demo on a "for",
         guide or feature page) and only the courses it shows: /course-css/<prefixes>.css, courses.css less the other
         courses' rules, in its order (App\Support\Site\CourseCss, docs/css-split-plan.md); the whole css/courses.css if
         that ever fails. Read from what the page renders, so no view has to ask. --}}
    @php($courseCss = \App\Support\Site\CourseCss::url($web, $__env->yieldContent('content').$__env->yieldContent('head')))
    @if ($courseCss !== null)
        <link rel="stylesheet" href="{{ $courseCss }}">
    @endif
    @yield('head')
</head>
{{-- The arcade look on every public page; a page where people read or decide (help, legal, a guide)
     says @section('calm') and keeps the colours without the HUD type, glow or scanlines. --}}
{{-- Help's index is where people pick a topic, so it takes the arcade look; its articles, where people read, stay calm. --}}
<body @class(['min-h-dvh bg-ink font-sans text-cream antialiased s-arcade', 's-calm' => $__env->hasSection('calm') && ! request()->is('help')])>
    {{-- Someone who came by an organization's outreach link (a known o- tag; Landing::links): one quiet
         line to the partner offer, above everything else. Nobody else sees it. --}}
    @if (is_string($links['partner'] ?? null) && ! request()->is('for/organizations'))
        <p class="s-partnerbar"><span>{{ __('pages.site.partner_line') }}</span> <a href="{{ $links['partner'] }}" class="s-lk">{{ __('pages.site.partner_link') }}</a></p>
    @endif
    @yield('content')
    {{-- The play controls' words while they load (js/public/play-state.js), once for the page. --}}
    <template id="s-play-words" data-loading="{{ __('pages.site.play_loading') }}" data-slow="{{ __('pages.site.play_slow') }}" data-failed="{{ __('pages.site.play_failed') }}"></template>
    @yield('scripts')
</body>
</html>
