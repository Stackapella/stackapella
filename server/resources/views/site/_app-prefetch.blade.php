{{-- The app's first screen, fetched while the visitor reads (App\Support\Site\AppPrefetch; first-session review, item 3):
     timing only, nothing shown. $prefetch is AppPrefetch::landing() or ::song(). --}}
@if ($prefetch !== [])
<script type="application/json" data-app-prefetch>@json($prefetch)</script>
<script type="module" src="/js/public/app-prefetch.js"></script>
@endif
