{{-- A free tool for another site's iframe (docs/seo-growth-plan.md, item 2; ToolEmbedController gives $embed): the bare
     tool, compact, its look from the link (?theme=, ?skin=), and a small credit linking to Stackapella with the framing
     site's host in its ref. No brand row, no heading, no footer; no session and no cookies. The tool mounts from
     js/public/tools/embed-page.js, which hands this page's data to the frontend's embedFrame (embed.js); scripts come
     only from our own files. Kept out of search: the tool's own page (/tools/<tool>) is the one to find. data-gs and
     s-arcade are the tool page's arcade screen (public.layout), so ?skin=game gives the frame game mode's whole look, as
     /tools/<tool> has it; in the calm skin they show nothing. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ __('meta.dir') }}" data-embed data-gs>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ $embed['name'] }}</title>
    <link rel="canonical" href="{{ $embed['pageUrl'] }}">
    <link rel="preload" as="font" type="font/woff2" href="/fonts/figtree-latin.woff2" crossorigin>
    {{-- Game mode's own sheet, only in game mode, right after site.css (docs/css-split-plan.md, step 3; public/layout's). --}}
    <meta name="game-css" content="{{ \App\Support\Http\StaticVersions::url((string) config('app.web_path'), '/css/site-game.css') }}">
    {{-- Game mode's sheet starts downloading before theme-boot.js arrives (App\Support\Site\GamePreload). --}}
    {{ \App\Support\Site\GamePreload::tag() }}
    <script src="{{ \App\Support\Http\StaticVersions::url((string) config('app.web_path'), '/js/theme-boot.js') }}"></script>
    <link rel="stylesheet" href="{{ \App\Support\Http\StaticVersions::url((string) config('app.web_path'), '/css/site.css') }}">
    <script src="{{ \App\Support\Http\StaticVersions::url((string) config('app.web_path'), '/js/game-css.js') }}"></script>
</head>
<body class="s-embed s-arcade">
    <main>
        <div data-embed-stage aria-label="{{ $embed['name'] }}"></div>
    </main>
    <script type="application/json" data-embed-data>@json(\App\Support\Tools\EmbedBoot::of($embed))</script>
    <script type="module" src="{{ \App\Support\Http\StaticVersions::url((string) config('app.web_path'), '/js/public/tools/embed-page.js') }}"></script>
</body>
</html>
