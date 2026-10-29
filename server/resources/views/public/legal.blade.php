{{-- A legal page at /legal/<page> (DocumentPagesController::legal): its words at a reading width
     beside a contents list of its headings (a fold on a phone, held at the side on a wider screen),
     with its version, and plainly marked while it's still a draft with the lawyer. Words:
     resources/legal/en; the frame: lang/en/pages.php `legal`. --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

{{-- Calm: people read here (the arcade-versus-calm rule). --}}
@section('calm', true)

@section('head')
    {{-- The description search shows: the document's first paragraph, cut to length. --}}
    @php($first = collect($document['blocks'])->firstWhere('type', 'paragraph'))
    <meta name="description" content="{{ \Illuminate\Support\Str::limit(str_replace('**', '', (string) ($first['text'] ?? $document['title'])), 155) }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    <meta property="og:type" content="article">
    <meta property="og:image" content="{{ \App\Support\Site\SitePages::image($meta['url']) }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <link rel="canonical" href="{{ $meta['url'] }}">
@endsection

@section('content')
    <x-site.header />
    <main>
        <section class="s-wrap s-hero s-page s-doc-head" aria-labelledby="page-h">
            <p class="s-eyebrow">{{ __('pages.legal.eyebrow') }}</p>
            <h1 id="page-h">{{ $document['title'] }}</h1>
            <p class="s-lead">{{ __('pages.legal.version', ['version' => $document['version']]) }}@unless ($document['reviewed']) · {{ __('pages.legal.draft') }}@endunless</p>
        </section>
        @php($headings = collect($document['blocks'])->filter(fn (array $b): bool => $b['type'] === 'heading' && ($b['level'] ?? 2) <= 2)->map(fn (array $b): string => str_replace('**', '', (string) $b['text']))->values())
        <section class="s-wrap s-sec s-doc s-doc-toc" aria-label="{{ $document['title'] }}">
            @if ($headings->count() > 2)
                <nav class="s-toc" aria-label="{{ __('pages.legal.contents') }}">
                    <details class="s-toc-fold">
                        <summary>{{ __('pages.legal.contents') }}</summary>
                        <ol>
                            @foreach ($headings as $heading)
                                <li><a href="#{{ \Illuminate\Support\Str::slug($heading) }}">{{ $heading }}</a></li>
                            @endforeach
                        </ol>
                    </details>
                    <ol class="s-toc-side">
                        @foreach ($headings as $heading)
                            <li><a href="#{{ \Illuminate\Support\Str::slug($heading) }}">{{ $heading }}</a></li>
                        @endforeach
                    </ol>
                </nav>
            @endif
            <x-site.prose :blocks="$document['blocks']" />
        </section>
    </main>
    <x-site.footer />
@endsection
