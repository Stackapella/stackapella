{{--
    The server's own pages (the admin's sign-in and second factor, error pages). The public Blade
    pages have their own layout under views/public/, owned by the frontend agent. Styles are the
    app's Tailwind build; scripts only from 'self' (SecurityHeaders), never inline.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ __('meta.dir') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · {{ __('meta.brand') }}</title>
    <link rel="icon" href="/icons/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Figtree:wght@300..800&display=swap">
    <link rel="stylesheet" href="/css/app.css">
    @stack('head')
</head>
<body class="min-h-dvh bg-ink font-sans text-cream antialiased">
    <main class="mx-auto flex min-h-dvh w-full max-w-md flex-col justify-center px-5 py-10">
        <p class="mb-6 text-sm font-semibold uppercase tracking-wider text-muted">{{ __('meta.brand') }}</p>
        @yield('content')
    </main>
    @stack('scripts')
</body>
</html>
