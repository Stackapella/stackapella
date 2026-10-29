{{-- The public pages' frame (the frontend's; contract/api.md "Public pages"): no session, no
     cookies, the app's styles and fonts, scripts only from 'self' (never inline). A page sets
     `title`, its <head> extras in @section('head'), its body in @section('content'), and its
     script in @section('scripts'). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ __('meta.dir') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    {{-- Kept out of search until the launch (SITE_INDEXING); a page can say so for itself (@section('robots')). --}}
    @hasSection('robots')
        @yield('robots')
    @elseif (! config('ops.indexing'))
        <meta name="robots" content="noindex">
    @endif
    <title>{{ $title }}</title>
    <link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    {{-- Figtree for reading; Bungee (headlines) and Press Start 2P (small HUD labels) for the site's
         arcade look (Hillel, 2026-10-29: "love the 90's redesign"). --}}
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Bungee&family=Figtree:wght@300..800&family=Press+Start+2P&display=swap">
    <meta name="theme-color" content="#17120f">
    {{-- The theme the person chose anywhere (mode, colour and accent), before the page paints: shared
         with the app's host by a cookie on this domain (hosts.pass_domain; host-only without it). --}}
    @if (config('hosts.pass_domain'))
        <meta name="theme-cookie-domain" content="{{ config('hosts.pass_domain') }}">
    @endif
    <script src="/js/theme-boot.js"></script>
    <link rel="stylesheet" href="/css/app.css">
    @yield('head')
</head>
{{-- The arcade look on every public page; a page where people read or decide (help, legal, a guide)
     says @section('calm') and keeps the colours without the HUD type, glow or scanlines. --}}
<body @class(['min-h-dvh bg-ink font-sans text-cream antialiased s-arcade', 's-calm' => $__env->hasSection('calm')])>
    @yield('content')
    @yield('scripts')
</body>
</html>
