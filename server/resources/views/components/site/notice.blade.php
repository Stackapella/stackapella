{{-- A short page with one thing to say (an error, the gate before launch, an unsubscribe): the
     site's header and footer, a headline, a sentence or two and the way on (the slot). It reads
     nothing from the database, so it works in maintenance mode too. --}}
@props(['headline', 'body' => null, 'eyebrow' => null])
<x-site.header />
<main>
    <section class="s-wrap s-hero s-page s-notice" aria-labelledby="page-h">
        <p class="s-eyebrow"><span class="s-dots" aria-hidden="true">@foreach ([1, 2, 3, 4] as $n)<b class="s-v{{ $n }}"></b>@endforeach</span>{{ $eyebrow ?? __('meta.brand') }}</p>
        <h1 id="page-h">{{ $headline }}</h1>
        @if ($body !== null)
            <p class="s-lead">{{ $body }}</p>
        @endif
        <div class="s-notice-actions">{{ $slot }}</div>
    </section>
</main>
<x-site.footer />
