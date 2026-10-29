{{-- A guide at /guides/<slug> (DocumentPagesController::guide; the design round's direction): its
     words in a reading column, its numbered steps as a path in the parts' colours, the guide's own
     song to play where the steps begin (a free song only), and beside it the song to try and the
     next guides. Words: resources/guides/en; the frame: lang/en/pages.php `guides`. --}}
@extends('public.layout', ['title' => \App\Support\Site\SitePages::title($meta['title'])])

{{-- Calm: people read here (the arcade-versus-calm rule). --}}
@section('calm', true)

@section('head')
    <meta name="description" content="{{ $meta['description'] }}">
    <meta property="og:title" content="{{ $meta['title'] }}">
    <meta property="og:description" content="{{ $meta['description'] }}">
    <meta property="og:url" content="{{ $meta['url'] }}">
    <meta property="og:type" content="article">
    <meta property="og:image" content="{{ \App\Support\Site\SitePages::image($meta['url']) }}">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="{{ $meta['url'] }}">
@endsection

@section('content')
    <x-site.header />
    <main>
        <section class="s-wrap s-head" aria-labelledby="page-h">
            <nav class="s-crumb" aria-label="{{ __('pages.site.crumb') }}"><a href="/guides">{{ __('pages.guides.eyebrow') }}</a><span aria-hidden="true">/</span><span aria-current="page">{{ $guide['title'] }}</span></nav>
            <h1 id="page-h">{{ $guide['title'] }}</h1>
            <p class="s-lede">{{ $guide['description'] }}</p>
        </section>

        <div class="s-wrap s-greading">
            <article class="s-gread">
                <div class="s-prose">
                    @foreach ($guide['blocks'] as $block)
                        {{-- The song's opening line plays where the steps begin, so you can try each one. --}}
                        @if ($line !== null && $block['type'] === 'heading' && $block['step'] === 1)
                            <div class="s-gmixer s-narrow"><x-site.mixer :song="$line" id="guide-mixer" /></div>
                        @endif
                        @if ($block['type'] === 'heading' && $block['step'] !== null)
                            <h2 class="s-gstep"><span class="s-disc s-v{{ ($block['step'] - 1) % 4 + 1 }}" aria-hidden="true">{{ $block['step'] }}</span><span>{{ \App\Support\Site\Prose::inline((string) $block['text']) }}</span></h2>
                        @elseif ($block['type'] === 'heading')
                            @if (($block['level'] ?? 2) <= 2)
                                <h2>{{ \App\Support\Site\Prose::inline((string) $block['text']) }}</h2>
                            @else
                                <h3>{{ \App\Support\Site\Prose::inline((string) $block['text']) }}</h3>
                            @endif
                        @elseif ($block['type'] === 'list')
                            <ul>
                                @foreach ($block['items'] ?? [] as $item)
                                    <li>{{ \App\Support\Site\Prose::inline((string) $item) }}</li>
                                @endforeach
                            </ul>
                        @else
                            <p>{{ \App\Support\Site\Prose::inline((string) $block['text']) }}</p>
                        @endif
                    @endforeach
                </div>
            </article>

            <aside class="s-gside" aria-label="{{ __('pages.guides.try') }}">
                @if ($guide['song'] !== null)
                    <div class="s-gcard">
                        <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                        <p class="s-gcard-k">{{ __('pages.guides.try') }}</p>
                        <h2 translate="no">{{ $guide['song']['title'] }}</h2>
                        <p>{{ collect($guide['song']['parts'])->pluck('name')->join(', ') }}</p>
                        <a href="{{ $guide['song']['startUrl'] }}" class="btn-primary">{{ __('pages.guides.start') }} <x-site.icon name="arrow" size="16" /></a>
                        <a href="{{ $guide['song']['pageUrl'] }}" class="link-quiet">{{ __('pages.guides.song') }}</a>
                    </div>
                @endif
                @if ($guide['next'] !== [])
                    <nav class="s-gnext" aria-labelledby="next-h">
                        <h2 id="next-h">{{ __('pages.guides.next') }}</h2>
                        <ul>
                            @foreach ($guide['next'] as $other)
                                <li><a href="/guides/{{ $other['slug'] }}">{{ $other['title'] }}</a></li>
                            @endforeach
                        </ul>
                        <a href="/guides" class="btn-secondary btn-sm">{{ __('pages.guides.all') }}</a>
                    </nav>
                @endif
            </aside>
        </div>

        <section class="s-wrap" aria-labelledby="end-h">
            <div class="s-end">
                <span class="s-stackbig" aria-hidden="true">@foreach ([[1, 100], [2, 74], [3, 88], [4, 62]] as [$n, $w])<b class="s-v{{ $n }}" style="width: {{ $w }}%"></b>@endforeach</span>
                <div>
                    <h2 id="end-h">{{ __('pages.site.final_title') }}</h2>
                    <p>{{ __('pages.site.final_body') }}</p>
                </div>
                <a href="{{ \App\Support\Http\Hosts::app('/learn/1-comfortable') }}" class="btn-primary btn-lg">{{ __('pages.site.start') }} <x-site.icon name="arrow" size="18" /></a>
            </div>
        </section>
    </main>
    <x-site.footer />
@endsection

@section('scripts')
    @if ($line !== null)
        <script type="module" src="/js/public/mixer.js"></script>
    @endif
@endsection
