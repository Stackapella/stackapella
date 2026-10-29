{{-- "Stackapella has moved" (docs/app-subdomain-plan.md, "Old installs"): served on the old address
     for an app page or an old install, with `$appUrl` (the same page on the app's new address).
     js/public/moving.js finds what this device kept here, brings it to the new address, and goes
     there; its words come from the app's locale files. Without scripts, the new address is the link. --}}
@extends('public.layout', ['title' => 'Stackapella'])

@section('content')
    <main class="mx-auto flex min-h-dvh max-w-xl flex-col justify-center gap-6 px-6 py-16">
        <a href="{{ $appUrl }}" class="flex items-center gap-2 text-lg font-bold tracking-tight text-cream" translate="no">
            <svg width="22" height="22" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="4" rx="2" fill="#f2a06b"/><rect x="3" y="10" width="14" height="4" rx="2" fill="#4ccfb0"/><rect x="3" y="16" width="16" height="4" rx="2" fill="#b9a3f5"/></svg>
            <span class="text-[17px]">stackapella</span>
        </a>
        <section class="card space-y-4 p-6 md:p-8" data-moving data-app-url="{{ $appUrl }}" aria-live="polite">
            <noscript><p><a href="{{ $appUrl }}" class="link">{{ $appUrl }}</a></p></noscript>
        </section>
    </main>
@endsection

@section('scripts')
    <script type="module" src="/js/public/moving.js"></script>
@endsection
